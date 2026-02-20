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
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>GILIME - 홈</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="g-home-map-page">
    <div class="g-home-map-shell">
      <div id="g-home-map" class="g-home-map-canvas"></div>

      <header class="g-home-map-top">
        <div class="g-home-searchbar-wrap">
          <a href="<?= $base ?>/route_finder.php" class="g-home-searchbar" aria-label="길찾기 검색으로 이동">
            장소, 버스, 지하철, 주소 검색
          </a>
          <button type="button" class="g-home-track-cta" id="g-home-track-toggle" aria-pressed="false" aria-label="실시간 위치 추적 끔" title="실시간 위치 추적 끔">
            <svg class="g-icon-svg g-home-cta-icon" viewBox="0 0 24 24" aria-hidden="true">
              <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-locate"></use>
            </svg>
            <span class="visually-hidden">실시간 위치</span>
          </button>
          <a href="<?= $base ?>/route_finder.php" class="g-home-route-cta" aria-label="길찾기" title="길찾기">
            <svg class="g-icon-svg g-home-cta-icon" viewBox="0 0 24 24" aria-hidden="true">
              <use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-route"></use>
            </svg>
            <span class="visually-hidden">길찾기</span>
          </a>
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
            <a href="<?= $base ?>/route_finder.php" class="btn btn-gilaime-primary btn-sm">길찾기 시작</a>
          </div>
        </section>

        <section class="g-home-panel" data-home-panel="route">
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
        </section>
      </div>

      <nav class="g-bottom-nav g-home-bottom-nav" aria-label="하단 내비게이션">
        <a class="active" href="<?= $base ?>/home.php">
          <span class="g-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-home"></use></svg>
          </span>
          <span class="g-nav-label">홈</span>
        </a>
        <a href="<?= $base ?>/issues.php">
          <span class="g-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-issue"></use></svg>
          </span>
          <span class="g-nav-label">이슈</span>
        </a>
        <a href="<?= $base ?>/route_finder.php">
          <span class="g-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-route"></use></svg>
          </span>
          <span class="g-nav-label">길찾기</span>
        </a>
        <a href="<?= $base ?>/my_routes.php">
          <span class="g-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-star"></use></svg>
          </span>
          <span class="g-nav-label">마이노선</span>
        </a>
      </nav>
    </div>
  </main>
  <script>
    window.GILAIME_HOME_MAP = {
      lat: <?= json_encode((float)$homeCenter['lat']) ?>,
      lng: <?= json_encode((float)$homeCenter['lng']) ?>,
      label: <?= json_encode((string)$homeCenter['label'], JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
  <script src="<?= APP_BASE ?>/public/assets/js/home_map.js"></script>
</body>
</html>
