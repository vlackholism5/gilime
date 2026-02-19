<?php
declare(strict_types=1);
/**
 * v1.8 길찾기 — 출발/도착 입력, 경로 결과 (U-JNY-01 ~ U-JNY-04)
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/route/route_finder.php';

user_session_user_id(); // lazy init
$base = APP_BASE . '/user';
$pdo = pdo();

$step = isset($_GET['step']) ? trim((string)$_GET['step']) : '';
$from = isset($_REQUEST['from']) ? trim((string)$_REQUEST['from']) : '';
$to = isset($_REQUEST['to']) ? trim((string)$_REQUEST['to']) : '';
$nearbyQuery = isset($_GET['nearby_q']) ? trim((string)$_GET['nearby_q']) : '';
$issueId = isset($_REQUEST['issue_id']) ? (int)$_REQUEST['issue_id'] : 0;
$includeShuttle = isset($_REQUEST['include_shuttle']) && $_REQUEST['include_shuttle'] === '1';
$routeFilter = isset($_GET['route_filter']) ? trim((string)$_GET['route_filter']) : 'all';
if (!in_array($routeFilter, ['all', 'bus', 'shuttle'], true)) {
  $routeFilter = 'all';
}

// 이슈 컨텍스트 로드 (issue_id 있을 때)
$issueContext = null;
if ($issueId > 0) {
  try {
    $st = $pdo->prepare("SELECT id, event_type, title, route_label FROM app_alert_events WHERE id = :id AND published_at IS NOT NULL LIMIT 1");
    $st->execute([':id' => $issueId]);
    $issueContext = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  } catch (Throwable $e) {
    $issueContext = null;
  }
  // 이슈에 route_label 있으면 임시 셔틀 포함 기본 체크
  if ($issueContext && !empty($issueContext['route_label']) && !isset($_REQUEST['include_shuttle'])) {
    $includeShuttle = true;
  }
}

// POST 시 검색 후 step=result로 리다이렉트
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
  $from = isset($_POST['from']) ? trim((string)$_POST['from']) : '';
  $to = isset($_POST['to']) ? trim((string)$_POST['to']) : '';
  $includeShuttle = isset($_POST['include_shuttle']) && $_POST['include_shuttle'] === '1';
  $params = http_build_query(array_filter([
    'step' => 'result',
    'from' => $from,
    'to' => $to,
    'include_shuttle' => $includeShuttle ? '1' : '0',
    'issue_id' => $issueId > 0 ? (string)$issueId : null,
  ]));
  header('Location: ' . $base . '/route_finder.php?' . $params);
  exit;
}

$routes = [];
$busCount = 0;
$shuttleCount = 0;
$searchError = null;
$detailRoute = null;
$detailStops = [];
$nearbyStops = [];
$sampleStops = [];
if (in_array($step, ['', 'nearby'], true)) {
  $sampleStops = route_finder_sample_stops($pdo, 25);
}
if ($step === 'nearby') {
  $q = $nearbyQuery !== '' ? $nearbyQuery : ($from !== '' ? $from : $to);
  if ($q !== '') {
    $nearbyStops = route_finder_nearby_stops($pdo, $q, 40);
  }
  $sampleStops = route_finder_sample_stops($pdo, 25);
}
if ($step === 'detail') {
  $from = isset($_GET['from']) ? trim((string)$_GET['from']) : $from;
  $to = isset($_GET['to']) ? trim((string)$_GET['to']) : $to;
  $routeType = isset($_GET['route_type']) ? trim((string)$_GET['route_type']) : '';
  $routeId = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;
  if ($routeId > 0 && in_array($routeType, ['bus', 'shuttle_temp'], true)) {
    try {
      if ($routeType === 'bus') {
        $st = $pdo->prepare("SELECT rm.route_name, rm.first_bus_time, rm.last_bus_time, rm.term_min FROM seoul_bus_route_master rm WHERE rm.route_id = :id LIMIT 1");
        $st->execute([':id' => $routeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $detailRoute = ['route_type' => 'bus', 'route_name' => $row['route_name'], 'first_bus_time' => $row['first_bus_time'], 'last_bus_time' => $row['last_bus_time'], 'headway_min' => $row['term_min'] ? $row['term_min'] . '분' : null];
          $st2 = $pdo->prepare("SELECT seq_in_route, stop_name FROM seoul_bus_route_stop_master WHERE route_id = :id ORDER BY seq_in_route ASC");
          $st2->execute([':id' => $routeId]);
          $detailStops = $st2->fetchAll(PDO::FETCH_ASSOC);
        }
      } else {
        $st = $pdo->prepare("SELECT route_label, first_bus_time, last_bus_time, headway_min FROM shuttle_temp_route WHERE id = :id LIMIT 1");
        $st->execute([':id' => $routeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $detailRoute = ['route_type' => 'shuttle_temp', 'route_label' => $row['route_label'], 'first_bus_time' => $row['first_bus_time'], 'last_bus_time' => $row['last_bus_time'], 'headway_min' => $row['headway_min']];
          $st2 = $pdo->prepare("SELECT seq_in_route, COALESCE(stop_name, raw_stop_name) AS stop_name FROM shuttle_temp_route_stop WHERE temp_route_id = :id ORDER BY seq_in_route ASC");
          $st2->execute([':id' => $routeId]);
          $detailStops = $st2->fetchAll(PDO::FETCH_ASSOC);
        }
      }
    } catch (Throwable $e) {
      $detailRoute = null;
    }
  }
}
if ($step === 'result' && $from !== '' && $to !== '') {
  $resultBaseParams = ['step' => 'result', 'from' => $from, 'to' => $to, 'include_shuttle' => $includeShuttle ? '1' : '0'];
  if ($issueId > 0) {
    $resultBaseParams['issue_id'] = (string)$issueId;
  }
  $fromResolved = route_finder_resolve_stop($pdo, $from);
  $toResolved = route_finder_resolve_stop($pdo, $to);
  if ($fromResolved && $toResolved) {
    $routes = route_finder_search($pdo, $fromResolved['stop_id'], $toResolved['stop_id'], $includeShuttle);
    foreach ($routes as $i => $r) {
      $routeId = $r['route_type'] === 'bus' ? $r['route_id'] : $r['temp_route_id'];
      $routes[$i]['stops_summary'] = route_finder_stops_summary($pdo, $r['route_type'], $routeId, $r['from_seq'], $r['to_seq']);
    }
  } else {
    $searchError = $fromResolved ? '도착지를 정류장으로 찾을 수 없습니다.' : '출발지를 정류장으로 찾을 수 없습니다.';
  }
  foreach ($routes as $r) {
    if ($r['route_type'] === 'bus') $busCount++;
    else $shuttleCount++;
  }
  if ($routeFilter === 'bus') {
    $routes = array_values(array_filter($routes, fn($r) => $r['route_type'] === 'bus'));
  } elseif ($routeFilter === 'shuttle') {
    $routes = array_values(array_filter($routes, fn($r) => $r['route_type'] === 'shuttle_temp'));
  }
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
  <title>GILIME - 경로 찾기</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
    <nav class="nav g-topnav mb-3">
      <a class="nav-link" href="<?= $base ?>/home.php">홈</a>
      <a class="nav-link" href="<?= $base ?>/issues.php">이슈</a>
      <a class="nav-link active" href="<?= $base ?>/route_finder.php">길찾기</a>
      <a class="nav-link" href="<?= $base ?>/my_routes.php">마이노선</a>
    </nav>

    <div class="g-page-head mb-3">
      <h1>길라임</h1>
      <p class="helper mb-0">출발지와 도착지를 입력해 경로를 찾습니다.</p>
    </div>

    <?php if ($issueContext): ?>
    <div class="card g-card g-route-form mb-3 border-primary">
      <div class="card-body py-2">
        <p class="text-muted-g small mb-0">
          <strong>이슈 기반 길찾기:</strong> <?= h($issueContext['title'] ?? '') ?>
          <?php if (!empty($issueContext['route_label'])): ?>
            · 영향 노선: <?= h($issueContext['route_label']) ?>
          <?php endif; ?>
        </p>
        <a href="<?= $base ?>/issue.php?id=<?= (int)$issueContext['id'] ?>" class="btn btn-outline-secondary btn-sm mt-1">이슈 상세</a>
      </div>
    </div>
    <?php endif; ?>

    <div class="card g-card g-route-form mb-4">
      <div class="card-body">
        <h2 class="h5 mb-3">경로 찾기</h2>
        <form method="post" action="<?= $base ?>/route_finder.php">
          <?php if ($issueId > 0): ?><input type="hidden" name="issue_id" value="<?= (int)$issueId ?>" /><?php endif; ?>
          <div class="mb-3 g-autocomplete-wrap">
            <label for="from" class="form-label">출발지</label>
            <input type="text" id="from" name="from" class="form-control form-control-sm"
              placeholder="정류장명, 역명, 주소 검색..."
              value="<?= h($from) ?>" autocomplete="off" />
            <div class="g-autocomplete-dropdown" aria-hidden="true"></div>
          </div>
          <div class="mb-3 g-autocomplete-wrap">
            <label for="to" class="form-label">도착지</label>
            <input type="text" id="to" name="to" class="form-control form-control-sm"
              placeholder="정류장명, 역명, 주소 검색..."
              value="<?= h($to) ?>" autocomplete="off" />
            <div class="g-autocomplete-dropdown" aria-hidden="true"></div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" id="include_shuttle" name="include_shuttle" value="1"
                class="form-check-input" <?= $includeShuttle ? 'checked' : '' ?> />
              <label for="include_shuttle" class="form-check-label">임시 셔틀 포함</label>
            </div>
          </div>
          <button type="submit" name="search" class="btn btn-gilaime-primary">
            경로 찾기
          </button>
          <?php if ($sampleStops !== []): ?>
          <details class="mt-3 small">
            <summary class="text-muted-g mb-1">길찾기 가능한 정류장 예시</summary>
            <p class="text-muted-g mb-1">다음 정류장명을 정확히 입력해 보세요:</p>
            <p class="mb-0" style="font-size: 0.85em;"><?= implode(' · ', array_map(fn($s) => h($s['stop_name']), array_slice($sampleStops, 0, 15))) ?><?= count($sampleStops) > 15 ? ' …' : '' ?></p>
          </details>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if ($step === 'result'): ?>
    <div class="card g-card g-route-result">
      <div class="card-body">
        <h2 class="h5 mb-3">경로 결과</h2>
        <?php if ($from === '' || $to === ''): ?>
          <p class="text-muted-g small mb-0">출발지와 도착지를 입력해 주세요.</p>
        <?php elseif ($searchError): ?>
          <p class="text-muted-g small mb-2"><?= h($searchError) ?></p>
          <p class="text-muted-g small mb-0">정류장명 또는 역명을 정확히 입력해 주세요. (예: 노량진역, 강남역)</p>
        <?php elseif ($routes === []): ?>
          <p class="text-muted-g small mb-2">출발: <?= h($from) ?> → 도착: <?= h($to) ?></p>
          <p class="text-muted-g small mb-2">경로를 찾을 수 없습니다. 출발지-도착지 구간에 운행 중인 경로가 없거나, 일시적으로 조회가 불가합니다.</p>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <a href="<?= $base ?>/route_finder.php?step=nearby&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&nearby_q=<?= urlencode($from ?: $to) ?>" class="btn btn-outline-secondary btn-sm">근처 정류장 보기</a>
            <a href="<?= $base ?>/route_finder.php?step=result&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&include_shuttle=1" class="btn btn-outline-secondary btn-sm">임시 셔틀 추천</a>
            <a href="<?= $base ?>/route_finder.php" class="btn btn-gilaime-primary btn-sm">다시 검색</a>
          </div>
          <?php if ($sampleStops === []): $sampleStops = route_finder_sample_stops($pdo, 25); endif; ?>
          <?php if ($sampleStops !== []): ?>
          <details class="small">
            <summary class="text-muted-g mb-1">길찾기 가능한 정류장 예시</summary>
            <p class="text-muted-g mb-1">다음 정류장명을 정확히 입력해 보세요:</p>
            <p class="mb-0" style="font-size: 0.85em;"><?= implode(' · ', array_map(fn($s) => h($s['stop_name']), array_slice($sampleStops, 0, 15))) ?><?= count($sampleStops) > 15 ? ' …' : '' ?></p>
          </details>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted-g small mb-2">출발: <?= h($from) ?> → 도착: <?= h($to) ?></p>
          <div class="g-search-tab mb-3">
            <?php
            $qAll = $resultBaseParams; $qAll['route_filter'] = 'all';
            $qBus = $resultBaseParams; $qBus['route_filter'] = 'bus';
            $qShuttle = $resultBaseParams; $qShuttle['route_filter'] = 'shuttle';
            ?>
            <a href="<?= $base ?>/route_finder.php?<?= http_build_query($qAll) ?>" class="btn btn-sm <?= $routeFilter === 'all' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">전체 <?= $busCount + $shuttleCount ?></a>
            <a href="<?= $base ?>/route_finder.php?<?= http_build_query($qBus) ?>" class="btn btn-sm <?= $routeFilter === 'bus' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">버스 <?= $busCount ?></a>
            <?php if ($includeShuttle && $shuttleCount > 0): ?>
              <a href="<?= $base ?>/route_finder.php?<?= http_build_query($qShuttle) ?>" class="btn btn-sm <?= $routeFilter === 'shuttle' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">버스+임시셔틀 <?= $shuttleCount ?></a>
            <?php endif; ?>
          </div>
          <?php foreach ($routes as $idx => $r): ?>
            <div class="g-route-card">
              <div class="d-flex align-items-center gap-2 mb-1">
                <strong class="fs-5"><?= $r['est_min'] ?>분</strong>
                <?php if ($r['route_type'] === 'shuttle_temp'): ?>
                  <span class="badge bg-secondary">임시 셔틀</span>
                <?php endif; ?>
              </div>
              <p class="text-muted-g small mb-1">
                <?= $r['route_type'] === 'shuttle_temp' ? h($r['route_label']) : h($r['route_name']) ?>
                <?php if (!empty($r['first_bus_time']) && !empty($r['last_bus_time'])): ?> · <?= h($r['first_bus_time']) ?>~<?= h($r['last_bus_time']) ?><?php endif; ?>
              </p>
              <div class="g-route-timeline" role="presentation">
                <?php if ($r['route_type'] === 'shuttle_temp'): ?>
                  <span class="g-route-segment g-route-segment-shuttle" style="flex: 1;"></span>
                <?php else: ?>
                  <span class="g-route-segment g-route-segment-bus" style="flex: 1;"></span>
                <?php endif; ?>
              </div>
              <p class="text-muted-g small mb-1">
                <?php if ($r['route_type'] === 'shuttle_temp'): ?>
                  <span class="badge bg-secondary me-1">임시 셔틀 구간</span>
                <?php endif; ?>
                <?= $r['stops_summary'] !== '' ? h($r['stops_summary']) : (h($r['from_name'] ?? '') . ' - ' . h($r['to_name'] ?? '')) ?>
              </p>
              <p class="text-muted-g small mb-2">
                <?php if (!empty($r['headway_min'])): ?>배차 <?= h($r['headway_min']) ?><?php endif; ?>
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <a href="<?= $base ?>/route_finder.php?step=detail&route_type=<?= h($r['route_type']) ?>&route_id=<?= $r['route_type'] === 'bus' ? $r['route_id'] : $r['temp_route_id'] ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-outline-secondary btn-sm">상세</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Phase 2 지도 뷰에서 제공 예정">안내시작</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'detail' && $detailRoute): ?>
    <div class="card g-card mb-4">
      <div class="card-body">
        <h2 class="h5 mb-3"><?= h($detailRoute['route_type'] === 'shuttle_temp' ? $detailRoute['route_label'] : $detailRoute['route_name']) ?> 상세</h2>
        <?php if ($detailRoute['route_type'] === 'shuttle_temp'): ?>
          <span class="badge bg-secondary mb-2">임시 셔틀</span>
        <?php endif; ?>
        <ul class="list-unstyled mb-2">
          <?php foreach ($detailStops as $i => $s): ?>
            <li class="mb-1"><?= (int)$s['seq_in_route'] ?>. <?= h($s['stop_name'] ?? '') ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="text-muted-g small mb-0">
          <?php if (!empty($detailRoute['first_bus_time']) && !empty($detailRoute['last_bus_time'])): ?>
            운행 <?= h($detailRoute['first_bus_time']) ?>~<?= h($detailRoute['last_bus_time']) ?>
          <?php endif; ?>
          <?php if (!empty($detailRoute['headway_min'])): ?> · 배차 <?= h($detailRoute['headway_min']) ?><?php endif; ?>
        </p>
        <a href="<?= $base ?>/route_finder.php?step=result&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&include_shuttle=<?= $includeShuttle ? '1' : '0' ?>" class="btn btn-outline-secondary btn-sm mt-2">경로 결과로 돌아가기</a>
      </div>
    </div>
    <?php elseif ($step === 'detail' && !$detailRoute): ?>
    <div class="card g-card">
      <div class="card-body">
        <p class="text-muted-g small mb-0">경로 정보를 찾을 수 없습니다.</p>
        <a href="<?= $base ?>/route_finder.php" class="btn btn-outline-secondary btn-sm mt-2">경로 찾기</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'nearby'): ?>
    <div class="card g-card mb-4">
      <div class="card-body">
        <h2 class="h5 mb-3">근처 정류장</h2>
        <?php if ($nearbyQuery !== ''): ?>
          <p class="text-muted-g small mb-2">"<?= h($nearbyQuery) ?>" 검색 결과</p>
          <?php if ($nearbyStops === []): ?>
            <p class="text-muted-g small mb-0">검색어와 일치하는 정류장이 없습니다.</p>
          <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <?php foreach ($nearbyStops as $s):
                $newFrom = ($nearbyQuery === $from) ? $s['stop_name'] : $from;
                $newTo = ($nearbyQuery === $to) ? $s['stop_name'] : $to;
              ?>
                <a href="<?= $base ?>/route_finder.php?from=<?= urlencode($newFrom) ?>&to=<?= urlencode($newTo) ?>" class="btn btn-outline-secondary btn-sm"><?= h($s['stop_name']) ?></a>
              <?php endforeach; ?>
            </div>
            <p class="text-muted-g small mb-0">정류장을 클릭하면 출발/도착에 반영되어 경로 찾기 폼으로 이동합니다.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted-g small mb-0">출발지 또는 도착지를 입력한 뒤 [근처 정류장 보기]를 클릭하세요.</p>
        <?php endif; ?>
        <a href="<?= $base ?>/route_finder.php" class="btn btn-outline-secondary btn-sm mt-2">경로 찾기</a>
        <?php if ($sampleStops !== []): ?>
        <details class="mt-3 small">
          <summary class="text-muted-g mb-1">길찾기 가능한 정류장 예시</summary>
          <p class="text-muted-g mb-1">다음 정류장명을 정확히 입력해 보세요:</p>
          <p class="mb-0" style="font-size: 0.85em;"><?= implode(' · ', array_map(fn($s) => h($s['stop_name']), $sampleStops)) ?></p>
        </details>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'recommend'): ?>
    <div class="card g-card">
      <div class="card-body">
        <h2 class="h5 mb-3">운행불가 노선 대체 안내</h2>
        <p class="text-muted-g small mb-0">임시 셔틀 추천 UI (Phase 2)</p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'subscribed'): ?>
    <div class="card g-card">
      <div class="card-body">
        <h2 class="h5 mb-3">구독 반영 결과</h2>
        <p class="text-muted-g small mb-0">구독 반영 재탐색 UI (Phase 2)</p>
      </div>
    </div>
    <?php endif; ?>
  </main>
  <script>window.GILAIME_API_BASE = '<?= APP_BASE ?>';</script>
  <script src="<?= APP_BASE ?>/public/assets/js/route_autocomplete.js"></script>
</body>
</html>
