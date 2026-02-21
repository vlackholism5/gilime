<?php
declare(strict_types=1);
/**
 * v1.8 U-HOME — 출발/도착 + 이슈 Top3 (길찾기 우선)
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/alert/alert_event_type.php';

$pdo = pdo();
$userId = user_session_user_id();
$base = APP_BASE . '/user';
$homeCenter = ['lat' => 37.4764, 'lng' => 126.8827, 'label' => '금천구 가산동'];

// 이슈 Top3 (app_alert_events = 이슈, published만)
$issues = [];
try {
  $stmt = $pdo->query("
    SELECT id, event_type, title, body, route_label, published_at, created_at
    FROM app_alert_events
    WHERE published_at IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 3
  ");
  $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $issues = [];
}

// 주변 장소(정류장 기반) — 하드코딩 대신 DB 반영
$nearbyPlaces = [];
try {
  $st = $pdo->prepare("
    SELECT stop_id, stop_name, lat, lng
    FROM seoul_bus_stop_master
    WHERE lat IS NOT NULL AND lng IS NOT NULL
    ORDER BY ABS(lat - :lat) + ABS(lng - :lng)
    LIMIT 12
  ");
  $st->execute([':lat' => $homeCenter['lat'], ':lng' => $homeCenter['lng']]);
  $nearbyPlaces = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $nearbyPlaces = [];
}

// 저장 경로(구독 기반)
$savedRoutes = [];
try {
  $st = $pdo->prepare("SELECT target_id, updated_at FROM app_subscriptions WHERE user_id = :uid AND target_type = 'route' AND is_active = 1 ORDER BY updated_at DESC LIMIT 6");
  $st->execute([':uid' => $userId]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $parts = explode('_', (string)$row['target_id'], 2);
    $label = trim((string)($parts[1] ?? $row['target_id']));
    $savedRoutes[] = [
      'label' => $label !== '' ? $label : '저장 경로',
      'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
  }
} catch (Throwable $e) {
  $savedRoutes = [];
}

function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
  $r = 6371.0;
  $dLat = deg2rad($lat2 - $lat1);
  $dLng = deg2rad($lng2 - $lng1);
  $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
  $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
  return $r * $c;
}

// 추천 경로(현재 위치 -> 주변 정류장)
$routeRecommendations = [];
foreach (array_slice($nearbyPlaces, 0, 5) as $p) {
  $toLat = isset($p['lat']) ? (float)$p['lat'] : 0.0;
  $toLng = isset($p['lng']) ? (float)$p['lng'] : 0.0;
  if ($toLat === 0.0 && $toLng === 0.0) continue;
  $km = haversine_km((float)$homeCenter['lat'], (float)$homeCenter['lng'], $toLat, $toLng);
  $eta = max(5, (int)round($km * 7 + 5));
  $routeRecommendations[] = [
    'title' => (string)($p['stop_name'] ?? '추천 경로'),
    'eta_min' => $eta,
    'distance_km' => round($km, 1),
    'to_lat' => $toLat,
    'to_lng' => $toLng,
  ];
}

function issue_status(string $publishedAt): string {
  return strtotime($publishedAt) > strtotime('-7 days') ? 'Active' : 'Ended';
}
function issue_summary(?string $body, int $len = 80): string {
  if ($body === null || $body === '') return '';
  $s = trim(preg_replace('/\s+/u', ' ', $body));
  return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'GILIME - 홈';
$mainClass = 'g-home-map-page';
require_once __DIR__ . '/../../app/inc/user/user_layout_start.php';
?>
    <div class="g-home-map-shell">
      <div id="g-home-map" class="g-home-map-canvas"></div>
      <div id="g-home-map-loading" class="g-route-map-loading" style="z-index: 10;">
        지도를 불러오는 중입니다...
      </div>

      <header class="g-home-map-top">
        <div class="g-home-searchbar-wrap">
          <div role="button" id="g-home-search-trigger" class="g-home-searchbar" aria-label="길찾기 검색 시작">
            장소, 버스, 지하철, 주소 검색
          </div>
          <button type="button" class="g-home-track-cta" id="g-home-track-toggle" aria-pressed="false" aria-label="실시간 위치 추적 끔" title="실시간 위치 추적 끔">
            <svg class="g-icon-svg g-home-cta-icon" viewBox="0 0 24 24" aria-hidden="true">
              <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-locate"></use>
            </svg>
            <span class="visually-hidden">실시간 위치</span>
          </button>
        </div>
        <div class="g-home-filter-chips" id="g-home-filter-chips" aria-label="지도 필터">
          <button type="button" class="active" data-chip="issue" data-chip-type="mode">이슈</button>
          <button type="button" data-chip="temporary_shuttle" data-chip-type="layer">임시셔틀</button>
          <button type="button" data-chip="construction" data-chip-type="layer">통제/공사</button>
          <button type="button" data-chip="congestion" data-chip-type="layer" disabled>혼잡</button>
        </div>
        <div class="g-home-issue-strip">
          <?php if ($issues !== []): ?>
            <a href="<?= $base ?>/issue.php?id=<?= (int)$issues[0]['id'] ?>" class="g-home-issue-banner">
              <strong>이슈 <?= count($issues) ?></strong>
              <span><?= h($issues[0]['title'] ?? '긴급 이슈') ?></span>
            </a>
          <?php else: ?>
            <a href="<?= $base ?>/issues.php" class="g-home-issue-banner muted">
              <strong>이슈 0</strong>
              <span>현재 긴급 이슈 없음</span>
            </a>
          <?php endif; ?>
        </div>
      </header>

      <!-- Search Overlay (Hidden by default) -->
      <div id="g-search-overlay" class="g-search-overlay" aria-hidden="true" style="display: none;">
        <div class="g-search-header">
          <button type="button" class="btn-close" id="g-search-close" aria-label="닫기"></button>
          <div class="g-search-input-wrap g-autocomplete-wrap">
            <input type="search" id="g-search-input" class="form-control" placeholder="장소, 버스, 지하철 검색" autocomplete="off">
            <div class="g-autocomplete-dropdown"></div>
          </div>
          <button type="button" class="btn btn-link text-decoration-none" id="g-search-submit" aria-label="검색">
            <svg class="g-icon-svg" style="width:20px;height:20px;"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-search"></use></svg>
          </button>
        </div>
        
        <!-- Issue Banner (Always visible in Search Overlay) -->
        <div class="g-issue-banner-full">
          <div class="g-issue-content">
            <div class="g-issue-icon-box"><span class="g-issue-emoji">🚧</span></div>
            <div class="g-issue-text">
              <strong>긴급 이슈: 지하철 파업</strong>
              <p>현재 이슈로 인해 대체 경로가 자동 적용됩니다.</p>
            </div>
          </div>
          <div class="g-issue-actions">
            <button type="button" id="g-shuttle-cta" class="btn btn-sm btn-gilaime-primary">임시셔틀 포함 길찾기</button>
            <button type="button" id="g-myroute-cta" class="btn btn-sm btn-outline-secondary">마이노선 추가</button>
          </div>
          <div id="g-issue-msg" class="mt-2 small text-success" style="display:none;"></div>
        </div>

        <!-- Mode: Recent / Search Results -->
        <div id="g-search-content-default">
          <div class="g-recent-searches">
            <div class="d-flex justify-content-between align-items-center px-3 py-2">
              <span class="text-muted small">최근 검색어</span>
              <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" id="g-recent-clear">전체 삭제</button>
            </div>
            <ul id="g-recent-list" class="list-group list-group-flush"></ul>
          </div>
        </div>

        <!-- Mode: Route Input -->
        <div id="g-route-input-panel" style="display: none;">
          <div class="p-3">
            <div class="g-route-finder-form">
              <div class="g-autocomplete-wrap mb-2">
                <input type="text" id="g-route-from" class="form-control" placeholder="출발지 입력" autocomplete="off">
                <div class="g-autocomplete-dropdown"></div>
              </div>
              <div class="g-autocomplete-wrap mb-2">
                <input type="text" id="g-route-to" class="form-control" placeholder="도착지 입력" autocomplete="off">
                <div class="g-autocomplete-dropdown"></div>
              </div>
              <button type="button" id="g-route-submit" class="btn btn-gilaime-primary w-100" disabled>경로 찾기</button>
            </div>
          </div>
        </div>
      </div>

      <div class="g-home-bottom-sheet is-half" id="g-home-bottom-sheet">
        <button type="button" class="g-home-sheet-handle" id="g-home-sheet-toggle" aria-label="시트 접기/펼치기"></button>
        <div class="g-home-main-tabs">
          <button type="button" class="active" data-home-tab="issue">
            <svg class="g-icon-svg g-home-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
              <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-issue"></use>
            </svg>
            <span>이슈</span>
          </button>
          <button type="button" data-home-tab="route">
            <svg class="g-icon-svg g-home-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
              <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-route"></use>
            </svg>
            <span>경로</span>
          </button>
        </div>

        <section class="g-home-panel active" data-home-panel="issue">
          <h2>지금 이슈 TOP <?= count($issues) ?></h2>
          <?php if ($issues === []): ?>
            <p class="g-home-issue-empty">표시할 이슈가 없습니다.</p>
          <?php else: ?>
            <div class="g-home-issue-list">
              <?php foreach ($issues as $i => $it): ?>
                <?php
                  $issueTitle = (string)($it['title'] ?? '이슈 제목');
                  $issueSummary = issue_summary((string)($it['body'] ?? ''), 64) ?: '상세 내용이 아직 없습니다.';
                  $issueType = (string)($it['event_type'] ?? 'issue');
                  $issueTypeLabel = $issueType === 'strike' ? '파업' : ($issueType === 'event' ? '행사' : '업데이트');
                  $issueTypeClass = $issueType === 'strike' ? 'type-strike' : ($issueType === 'event' ? 'type-event' : 'type-update');
                  $thumbCaption = '#' . mb_substr(preg_replace('/\s+/u', '', $issueTitle), 0, 8);
                ?>
                <a class="g-home-issue-card <?= $i === 0 ? 'is-featured' : '' ?> <?= h($issueTypeClass) ?>" href="<?= $base ?>/issue.php?id=<?= (int)$it['id'] ?>">
                  <div class="thumb">
                    <span class="rank"><?= (int)$i + 1 ?></span>
                    <span class="tag"><?= h($issueTypeLabel) ?></span>
                    <span class="thumb-caption"><?= h($thumbCaption) ?></span>
                  </div>
                  <div class="content">
                    <strong><?= h($issueTitle) ?></strong>
                    <small><?= h($issueSummary) ?></small>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="g-home-full-more">
            <a href="<?= $base ?>/issues.php" class="btn btn-outline-secondary btn-sm">더보기</a>
          </div>
          <div class="d-flex gap-2 mt-2">
            <a href="<?= $base ?>/issues.php" class="btn btn-outline-secondary btn-sm">전체 이슈 보기</a>
            <a href="<?= $base ?>/notices.php" class="btn btn-outline-secondary btn-sm">공지/이벤트</a>
            <a href="<?= $base ?>/route_finder.php" id="g-home-start-route" class="btn btn-gilaime-primary btn-sm">길찾기 시작</a>
          </div>
        </section>

        <section class="g-home-panel" data-home-panel="route">
          <!-- Step 1: 입력 (기존 화면) -->
          <div id="g-route-step-input">
            <div class="p-2">
              <h2 class="h5">경로 찾기</h2>
              <div class="g-route-finder-form">
                <div class="g-autocomplete-wrap mb-2">
                  <input type="text" id="from" name="from" class="form-control form-control-sm" placeholder="출발지 입력">
                  <div class="g-autocomplete-dropdown"></div>
                </div>
                <div class="g-autocomplete-wrap mb-2">
                  <input type="text" id="to" name="to" class="form-control form-control-sm" placeholder="도착지 입력">
                  <div class="g-autocomplete-dropdown"></div>
                </div>
                <button type="button" id="find-route-btn" class="btn btn-gilaime-primary btn-sm w-100">경로 찾기</button>
              </div>
            </div>
            <h2>추천 경로</h2>
            <?php if ($routeRecommendations === []): ?>
              <p class="g-home-issue-empty">표시할 경로 추천이 없습니다.</p>
            <?php else: ?>
              <div class="g-home-route-list">
                <?php foreach ($routeRecommendations as $r): ?>
                  <button type="button" class="g-home-route-item"
                    data-map-lat="<?= h((string)$r['to_lat']) ?>"
                    data-map-lng="<?= h((string)$r['to_lng']) ?>"
                    data-map-label="<?= h((string)$r['title']) ?>">
                    <strong><?= h((string)$r['title']) ?></strong>
                    <span><?= (int)$r['eta_min'] ?>분 · <?= h((string)$r['distance_km']) ?>km</span>
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <h3 class="g-home-sub-title">저장된 경로 <?= count($savedRoutes) ?></h3>
            <?php if ($savedRoutes === []): ?>
              <p class="g-home-issue-empty">저장된 경로가 없습니다.</p>
            <?php else: ?>
              <ul class="g-home-saved-route-list">
                <?php foreach ($savedRoutes as $sr): ?>
                  <li>
                    <a href="<?= $base ?>/my_routes.php"><?= h((string)$sr['label']) ?></a>
                    <span><?= h((string)$sr['updated_at']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <!-- Step 2: 분석 중 (UX 시각화) -->
          <div id="g-route-step-analysis" style="display: none; padding: 2rem 1rem; text-align: center;">
            <h3 class="h6 mb-4 text-muted">최적의 이동 경로를 분석하고 있습니다.</h3>
            <div class="d-flex flex-column gap-3 align-items-start mx-auto" style="max-width: 240px;">
              <div class="d-flex align-items-center gap-2 text-muted" id="analysis-step-1">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>기본 경로 조회 중...</span>
              </div>
              <div class="d-flex align-items-center gap-2 text-muted" id="analysis-step-2" style="opacity: 0.5;">
                <svg class="g-icon-svg" style="width:1rem; height:1rem;" viewBox="0 0 24 24">
                  <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-issue"></use>
                </svg>
                <span>이슈 영향 분석 중...</span>
              </div>
              <div class="d-flex align-items-center gap-2 text-muted" id="analysis-step-3" style="opacity: 0.5;">
                <svg class="g-icon-svg" style="width:1rem; height:1rem;" viewBox="0 0 24 24">
                  <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-bus"></use>
                </svg>
                <span>임시셔틀 매칭 중...</span>
              </div>
            </div>
          </div>

          <!-- Step 3: 결과 (MVP) -->
          <div id="g-route-step-result" style="display: none;">
            <div class="p-2 border-bottom d-flex align-items-center">
              <button type="button" class="btn btn-sm btn-link text-decoration-none ps-0" id="g-route-result-back">← 다시 검색</button>
              <span class="ms-auto badge bg-gilaime-primary">임시셔틀 포함</span>
            </div>
            <div class="g-home-route-list mt-2" id="g-route-result-list">
              <!-- JS로 결과 카드가 여기에 렌더링됩니다 -->
            </div>
          </div>
        </section>
      </div>
    </div>

  <script>
    window.GILAIME_HOME_MAP = {
      lat: <?= json_encode((float)$homeCenter['lat']) ?>,
      lng: <?= json_encode((float)$homeCenter['lng']) ?>,
      label: <?= json_encode((string)$homeCenter['label'], JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
<?php
require_once __DIR__ . '/../../app/inc/user/user_layout_end.php';
?>
