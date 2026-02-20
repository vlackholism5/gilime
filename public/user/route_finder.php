<?php
declare(strict_types=1);
/**
 * v1.8 길찾기 — 출발/도착 입력, 경로 결과 (U-JNY-01 ~ U-JNY-04)
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/route/route_finder.php';
require_once __DIR__ . '/../../app/inc/api/g1_station_lines.php';

user_session_user_id(); // lazy init
$base = APP_BASE . '/user';
$pdo = pdo();

$step = isset($_GET['step']) ? trim((string)$_GET['step']) : '';
$from = isset($_REQUEST['from']) ? trim((string)$_REQUEST['from']) : '';
$to = isset($_REQUEST['to']) ? trim((string)$_REQUEST['to']) : '';
$from = $from !== '' ? mb_substr($from, 0, 60) : '';
$to = $to !== '' ? mb_substr($to, 0, 60) : '';
$nearbyQuery = isset($_GET['nearby_q']) ? trim((string)$_GET['nearby_q']) : '';
$issueId = isset($_REQUEST['issue_id']) ? (int)$_REQUEST['issue_id'] : 0;
$includeShuttle = isset($_REQUEST['include_shuttle']) && $_REQUEST['include_shuttle'] === '1';
$routeFilter = isset($_GET['route_filter']) ? trim((string)$_GET['route_filter']) : 'all';
if (!in_array($routeFilter, ['all', 'bus', 'shuttle'], true)) {
  $routeFilter = 'all';
}
$routeSort = isset($_REQUEST['route_sort']) ? trim((string)$_REQUEST['route_sort']) : 'best';
if (!in_array($routeSort, ['best', 'time', 'transfer', 'walk', 'arrival'], true)) {
  $routeSort = 'best';
}
$stairAvoid = isset($_REQUEST['stair_avoid']) && $_REQUEST['stair_avoid'] === '1';

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
  $from = $from !== '' ? mb_substr($from, 0, 60) : '';
  $to = $to !== '' ? mb_substr($to, 0, 60) : '';
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
$g1FromLabel = '';
$g1ToLabel = '';
$fromCoords = null;
$toCoords = null;
$routeMapOptions = [];
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
          $st2 = $pdo->prepare("SELECT seq_in_route, stop_id, stop_name FROM seoul_bus_route_stop_master WHERE route_id = :id ORDER BY seq_in_route ASC");
          $st2->execute([':id' => $routeId]);
          $detailStops = $st2->fetchAll(PDO::FETCH_ASSOC);
        }
      } else {
        $st = $pdo->prepare("SELECT route_label, first_bus_time, last_bus_time, headway_min FROM shuttle_temp_route WHERE id = :id LIMIT 1");
        $st->execute([':id' => $routeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $detailRoute = ['route_type' => 'shuttle_temp', 'route_label' => $row['route_label'], 'first_bus_time' => $row['first_bus_time'], 'last_bus_time' => $row['last_bus_time'], 'headway_min' => $row['headway_min']];
          $st2 = $pdo->prepare("SELECT seq_in_route, stop_id, COALESCE(stop_name, raw_stop_name) AS stop_name FROM shuttle_temp_route_stop WHERE temp_route_id = :id ORDER BY seq_in_route ASC");
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
  $resultBaseParams['route_sort'] = $routeSort;
  $resultBaseParams['stair_avoid'] = $stairAvoid ? '1' : '0';
  $fromResolved = route_finder_resolve_stop($pdo, $from);
  $toResolved = route_finder_resolve_stop($pdo, $to);
  if ($fromResolved) {
    $fromCoords = route_finder_stop_coords($pdo, $fromResolved['stop_id']);
  }
  if ($toResolved) {
    $toCoords = route_finder_stop_coords($pdo, $toResolved['stop_id']);
  }
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
  if ($routes !== []) {
    usort($routes, function (array $a, array $b) use ($routeSort, $stairAvoid): int {
      $am = (int)($a['est_min'] ?? 9999);
      $bm = (int)($b['est_min'] ?? 9999);
      $ah = (int)preg_replace('/[^0-9]/', '', (string)($a['headway_min'] ?? '9999'));
      $bh = (int)preg_replace('/[^0-9]/', '', (string)($b['headway_min'] ?? '9999'));
      $aw = $stairAvoid ? ($a['route_type'] === 'shuttle_temp' ? 1 : 0) : 0;
      $bw = $stairAvoid ? ($b['route_type'] === 'shuttle_temp' ? 1 : 0) : 0;
      switch ($routeSort) {
        case 'time':
          return $am <=> $bm;
        case 'arrival':
          return ($am + $ah) <=> ($bm + $bh);
        case 'walk':
          return $aw <=> $bw;
        case 'transfer':
          return (($a['route_type'] === 'shuttle_temp') ? 1 : 0) <=> (($b['route_type'] === 'shuttle_temp') ? 1 : 0);
        case 'best':
        default:
          $aScore = $am + $ah + ($aw * 5);
          $bScore = $bm + $bh + ($bw * 5);
          return $aScore <=> $bScore;
      }
    });
    foreach ($routes as $idx => $r) {
      $routeMapOptions[] = [
        'idx' => $idx,
        'route_type' => (string)($r['route_type'] ?? 'bus'),
        'est_min' => (int)($r['est_min'] ?? 0),
      ];
    }
  }
  $g1Cache = [];
  $g1Results = ['from' => null, 'to' => null];
  foreach (['from' => $from, 'to' => $to] as $key => $name) {
    if ($name === '') {
      continue;
    }
    $cacheKey = mb_strtolower($name);
    if (!isset($g1Cache[$cacheKey])) {
      $g1Cache[$cacheKey] = g1_station_lines_lookup($pdo, 'by-name', 'station_name', $name);
    }
    $g1Results[$key] = $g1Cache[$cacheKey];
  }
  $g1FromLabel = $g1Results['from'] !== null ? format_g1_line_label($g1Results['from']['row']) : '';
  $g1ToLabel = $g1Results['to'] !== null ? format_g1_line_label($g1Results['to']['row']) : '';
} else {
  $g1FromLabel = '';
  $g1ToLabel = '';
}

function format_g1_line_label(?array $row): string {
  if ($row === null) {
    return '노선 미연결';
  }
  $codes = $row['line_codes'] ?? [];
  $source = $row['line_codes_source'] ?? 'none';
  if (!is_array($codes)) {
    return '노선 미연결';
  }
  $codes = array_map('trim', array_filter($codes));
  if ($codes === [] || $source === 'none') {
    return '노선 미연결';
  }
  $lineStr = implode(', ', array_map(fn($c) => $c . '호선', $codes));
  $metaSource = $row['meta']['line_code_source'] ?? '';
  if ($metaSource === 'ambiguous' && count($codes) > 1) {
    return $lineStr . ' 환승';
  }
  return $lineStr;
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
  <main class="container-fluid py-4 g-routefinder-page">
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
          <details class="mt-3 small">
            <summary class="text-muted-g mb-1">경로 나오는 예시 (클릭 시 결과 화면)</summary>
            <p class="text-muted-g mb-1">아래 링크를 클릭하면 해당 구간 경로 결과 화면으로 이동합니다.</p>
            <ul class="mb-0 list-unstyled" style="font-size: 0.9em;">
              <li><a href="<?= $base ?>/route_finder.php?step=result&amp;from=<?= urlencode('정류장ID:232001137') ?>&amp;to=<?= urlencode('정류장ID:232000291') ?>">정류장ID:232001137 → 정류장ID:232000291</a></li>
              <li><a href="<?= $base ?>/route_finder.php?step=result&amp;from=<?= urlencode('정류장ID:232001137') ?>&amp;to=<?= urlencode('정류장ID:232000854') ?>">정류장ID:232001137 → 정류장ID:232000854</a></li>
              <li><a href="<?= $base ?>/route_finder.php?step=result&amp;from=<?= urlencode('정류장ID:232001137') ?>&amp;to=<?= urlencode('정류장ID:232000856') ?>">정류장ID:232001137 → 정류장ID:232000856</a></li>
              <li><a href="<?= $base ?>/route_finder.php?step=result&amp;from=<?= urlencode('정류장ID:232000857') ?>&amp;to=<?= urlencode('개화역광역환승센터') ?>">정류장ID:232000857 → 개화역광역환승센터</a></li>
            </ul>
          </details>
        </form>
      </div>
    </div>

    <div id="g-route-map-wrap" class="g-route-map-wrap mb-4" aria-label="경로 지도">
      <div id="g-route-map" class="g-route-map"></div>
      <div id="g-route-map-loading" class="g-route-map-loading" aria-hidden="true" style="display: none;">경로 불러오는 중…</div>
    </div>

    <?php if ($step === 'result'): ?>
    <div class="g-route-transport-mode mb-3" aria-label="이동 수단 탭">
      <button type="button" class="g-mode-btn active">🚌 대중교통</button>
      <button type="button" class="g-mode-btn" disabled>🚗 자동차</button>
      <button type="button" class="g-mode-btn" disabled>🚶 도보</button>
      <button type="button" class="g-mode-btn" disabled>🚲 자전거</button>
    </div>
    <div class="card g-card g-route-result g-route-result-sheet" id="g-route-result-sheet">
      <div class="card-body">
        <button type="button" class="g-sheet-handle" id="g-sheet-toggle" aria-label="결과 시트 접기/펼치기"></button>
        <h2 class="h5 mb-2">경로 결과</h2>
        <?php if ($from === '' || $to === ''): ?>
          <p class="text-muted-g small mb-0">출발지와 도착지를 입력해 주세요.</p>
        <?php elseif ($searchError): ?>
          <p class="text-muted-g small mb-2"><?= h($searchError) ?></p>
          <p class="text-muted-g small mb-0">정류장명 또는 역명을 정확히 입력해 주세요. (예: 노량진역, 강남역)</p>
        <?php elseif ($routes === []): ?>
          <p class="text-muted-g small mb-2">출발: <?= h(route_finder_stop_display_label($pdo, $from)) ?><?= $g1FromLabel !== '' ? ' (지하철 ' . h($g1FromLabel) . ')' : '' ?> → 도착: <?= h(route_finder_stop_display_label($pdo, $to)) ?><?= $g1ToLabel !== '' ? ' (지하철 ' . h($g1ToLabel) . ')' : '' ?></p>
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
          <div class="g-route-od-summary mb-2">
            <?= h(route_finder_stop_display_label($pdo, $from)) ?> → <?= h(route_finder_stop_display_label($pdo, $to)) ?>
          </div>
          <div class="g-route-meta-row mb-2">
            <span>오늘 출발</span>
            <button type="button" class="g-sort-open-btn" id="g-open-sort-modal">
              <?php
                $sortText = match ($routeSort) {
                  'time' => '최소 시간순',
                  'transfer' => '최소 환승순',
                  'walk' => '최소 도보순',
                  'arrival' => '빠른 도착순',
                  default => '최적 경로순',
                };
                echo h($sortText . ', 옵션');
              ?>
            </button>
          </div>
          <p class="text-muted-g small mb-2"><a href="<?= $base ?>/my_routes.php" class="text-decoration-none">구독 노선은 마이노선에서 확인할 수 있습니다.</a></p>
          <div class="g-search-tab g-search-tab-mobile mb-3">
            <?php
            $qAll = $resultBaseParams; $qAll['route_filter'] = 'all';
            $qBus = $resultBaseParams; $qBus['route_filter'] = 'bus';
            $qShuttle = $resultBaseParams; $qShuttle['route_filter'] = 'shuttle';
            ?>
            <a href="<?= $base ?>/route_finder.php?<?= http_build_query($qAll) ?>" class="g-route-filter-link <?= $routeFilter === 'all' ? 'active' : '' ?>">전체 <?= $busCount + $shuttleCount ?></a>
            <a href="<?= $base ?>/route_finder.php?<?= http_build_query($qBus) ?>" class="g-route-filter-link <?= $routeFilter === 'bus' ? 'active' : '' ?>">버스 <?= $busCount ?></a>
            <?php if ($includeShuttle && $shuttleCount > 0): ?>
              <a href="<?= $base ?>/route_finder.php?<?= http_build_query($qShuttle) ?>" class="g-route-filter-link <?= $routeFilter === 'shuttle' ? 'active' : '' ?>">버스+임시셔틀 <?= $shuttleCount ?></a>
            <?php endif; ?>
          </div>
          <?php foreach ($routes as $idx => $r): ?>
            <div class="g-route-card g-route-card-mobile <?= $idx === 0 ? 'active' : '' ?>"
                 data-route-idx="<?= (int)$idx ?>"
                 data-route-type="<?= h((string)$r['route_type']) ?>">
              <div class="d-flex align-items-center gap-2 mb-1 g-route-card-head">
                <strong class="g-route-time"><?= $r['est_min'] ?>분</strong>
                <?php if ($r['route_type'] === 'shuttle_temp'): ?>
                  <span class="badge bg-secondary">임시 셔틀</span>
                <?php endif; ?>
              </div>
              <p class="text-muted-g small mb-1">
                <?php
                  $routeLabel = $r['route_type'] === 'shuttle_temp' ? $r['route_label'] : $r['route_name'];
                  if ($r['route_type'] !== 'shuttle_temp' && $routeLabel !== '' && preg_match('/^\d+$/', (string)$routeLabel)) {
                    $routeLabel = '노선 ' . $routeLabel;
                  }
                  echo h($routeLabel);
                ?>
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
                <?= $r['stops_summary'] !== '' ? h($r['stops_summary']) : (h(route_finder_stop_display_label($pdo, $r['from_name'] ?? '')) . ' - ' . h(route_finder_stop_display_label($pdo, $r['to_name'] ?? ''))) ?>
              </p>
              <p class="text-muted-g small mb-2">
                <?php if (!empty($r['headway_min'])): ?>배차 <?= h($r['headway_min']) ?><?php endif; ?>
              </p>
              <div class="d-flex gap-2 flex-wrap g-route-card-actions">
                <a href="<?= $base ?>/route_finder.php?step=detail&route_type=<?= h($r['route_type']) ?>&route_id=<?= $r['route_type'] === 'bus' ? $r['route_id'] : $r['temp_route_id'] ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-outline-secondary btn-sm">상세</a>
                <button type="button" class="btn btn-gilaime-primary btn-sm" disabled title="Phase 2 지도 뷰에서 제공 예정">안내시작</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'result' && $routes !== []): ?>
    <div class="g-sort-modal-backdrop" id="g-sort-modal-backdrop" hidden>
      <div class="g-sort-modal" role="dialog" aria-modal="true" aria-labelledby="g-sort-modal-title">
        <div class="g-sort-modal-head">
          <h3 id="g-sort-modal-title">정렬 기준 및 옵션</h3>
          <button type="button" class="g-sort-close-btn" id="g-close-sort-modal" aria-label="닫기">×</button>
        </div>
        <div class="g-sort-modal-body">
          <button type="button" class="g-sort-option <?= $routeSort === 'best' ? 'active' : '' ?>" data-sort-value="best" data-sort-label="최적 경로순">최적 경로순</button>
          <button type="button" class="g-sort-option <?= $routeSort === 'time' ? 'active' : '' ?>" data-sort-value="time" data-sort-label="최소 시간순">최소 시간순</button>
          <button type="button" class="g-sort-option <?= $routeSort === 'transfer' ? 'active' : '' ?>" data-sort-value="transfer" data-sort-label="최소 환승순">최소 환승순</button>
          <button type="button" class="g-sort-option <?= $routeSort === 'walk' ? 'active' : '' ?>" data-sort-value="walk" data-sort-label="최소 도보순">최소 도보순</button>
          <button type="button" class="g-sort-option <?= $routeSort === 'arrival' ? 'active' : '' ?>" data-sort-value="arrival" data-sort-label="빠른 도착순">빠른 도착순</button>
          <label class="g-sort-toggle">
            <span>계단 회피</span>
            <input type="checkbox" id="g-stair-avoid" <?= $stairAvoid ? 'checked' : '' ?> />
          </label>
        </div>
        <div class="g-sort-modal-foot">
          <button type="button" class="btn btn-gilaime-primary w-100" id="g-apply-sort-modal">완료</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($step === 'detail' && $detailRoute): ?>
    <div class="card g-card mb-4">
      <div class="card-body">
        <?php
          $detailRouteLabel = $detailRoute['route_type'] === 'shuttle_temp' ? $detailRoute['route_label'] : $detailRoute['route_name'];
          if ($detailRoute['route_type'] !== 'shuttle_temp' && $detailRouteLabel !== '' && preg_match('/^\d+$/', (string)$detailRouteLabel)) {
            $detailRouteLabel = '노선 ' . $detailRouteLabel;
          }
        ?>
        <h2 class="h5 mb-3"><?= h($detailRouteLabel) ?> 상세</h2>
        <?php if ($detailRoute['route_type'] === 'shuttle_temp'): ?>
          <span class="badge bg-secondary mb-2">임시 셔틀</span>
        <?php endif; ?>
        <ul class="list-unstyled mb-2">
          <?php foreach ($detailStops as $i => $s):
            $sn = isset($s['stop_name']) ? trim((string)$s['stop_name']) : '';
            $sid = isset($s['stop_id']) ? (int)$s['stop_id'] : 0;
            if ($sn !== '' && !preg_match('/^정류장ID:\d+$/u', $sn)) {
              $stopLabel = $sid > 0 ? $sn . ' (정류소번호 ' . $sid . ')' : $sn;
            } else {
              $stopLabel = $sid > 0 ? '정류장 (정류소번호 ' . $sid . ')' : '정류장';
            }
          ?>
            <li class="mb-1"><?= (int)($s['seq_in_route'] ?? 0) ?>. <?= h($stopLabel) ?></li>
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
                $nearbyLabel = (trim((string)($s['stop_name'] ?? '')) !== '') ? $s['stop_name'] . ' (정류소번호 ' . (int)$s['stop_id'] . ')' : '정류장 (정류소번호 ' . (int)$s['stop_id'] . ')';
                $newFrom = ($nearbyQuery === $from) ? $nearbyLabel : $from;
                $newTo = ($nearbyQuery === $to) ? $nearbyLabel : $to;
              ?>
                <a href="<?= $base ?>/route_finder.php?step=result&amp;from=<?= urlencode($newFrom) ?>&amp;to=<?= urlencode($newTo) ?>" class="btn btn-outline-secondary btn-sm"><?= h($nearbyLabel) ?></a>
              <?php endforeach; ?>
            </div>
            <p class="text-muted-g small mb-0">정류장을 클릭하면 해당 구간 경로 결과 화면으로 이동합니다.</p>
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
  <script>
    window.GILAIME_ROUTE_MAP = {
      step: <?= json_encode($step) ?>,
      fromCoords: <?= json_encode($fromCoords) ?>,
      toCoords: <?= json_encode($toCoords) ?>,
      routeOptions: <?= json_encode($routeMapOptions, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
  <script src="<?= APP_BASE ?>/public/assets/js/route_autocomplete.js"></script>
  <script src="<?= APP_BASE ?>/public/assets/js/route_finder_map.js"></script>
  <script src="<?= APP_BASE ?>/public/assets/js/route_finder_ui.js"></script>
</body>
</html>
