<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';

$pdo = pdo();
$userId = user_session_user_id();
$base = APP_BASE . '/user';

$subscribedRoutes = [];
$st = $pdo->prepare("
  SELECT target_id
  FROM app_subscriptions
  WHERE user_id = :uid
    AND target_type = 'route'
    AND is_active = 1
  ORDER BY updated_at DESC
  LIMIT 50
");
$st->execute([':uid' => $userId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $tid = (string)$row['target_id'];
  $parts = explode('_', $tid, 2);
  $docId = isset($parts[0]) ? (int)$parts[0] : 0;
  $routeLabel = (string)($parts[1] ?? '');
  if ($docId > 0 && $routeLabel !== '') {
    $subscribedRoutes[] = [
      'doc_id' => $docId,
      'route_label' => $routeLabel,
    ];
  }
}

$selectedDocId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
$selectedRouteLabel = isset($_GET['route_label']) ? trim((string)$_GET['route_label']) : '';

if (isset($_GET['route_key']) && (string)$_GET['route_key'] !== '') {
  $parts = explode('|', (string)$_GET['route_key'], 2);
  $redirDoc = isset($parts[0]) ? (int)$parts[0] : 0;
  $redirRl = (string)($parts[1] ?? '');
  if ($redirDoc > 0 && $redirRl !== '') {
    header('Location: ' . $base . '/journey.php?doc_id=' . $redirDoc . '&route_label=' . urlencode($redirRl));
    exit;
  }
}

if (($selectedDocId <= 0 || $selectedRouteLabel === '') && $subscribedRoutes !== []) {
  $selectedDocId = (int)$subscribedRoutes[0]['doc_id'];
  $selectedRouteLabel = (string)$subscribedRoutes[0]['route_label'];
}

$isAllowedRoute = false;
foreach ($subscribedRoutes as $sr) {
  if ((int)$sr['doc_id'] === $selectedDocId && (string)$sr['route_label'] === $selectedRouteLabel) {
    $isAllowedRoute = true;
    break;
  }
}

$routeStops = [];
if ($isAllowedRoute) {
  $rs = $pdo->prepare("
    SELECT stop_order, stop_id, stop_name
    FROM shuttle_route_stop
    WHERE source_doc_id = :doc
      AND route_label = :rl
      AND is_active = 1
    ORDER BY stop_order ASC
  ");
  $rs->execute([':doc' => $selectedDocId, ':rl' => $selectedRouteLabel]);
  $routeStops = $rs->fetchAll(PDO::FETCH_ASSOC);
}

function normalizeCompareKeyJourney(string $s): string {
  $v = trim((string)preg_replace('/\s+/', ' ', $s));
  return mb_strtolower($v, 'UTF-8');
}

// v1.7-18: 공공데이터 기준 노선 참고 정보 (route_label exact/prefix)
$publicRouteCandidates = [];
$publicRouteResolved = null;
$publicRouteStopCount = 0;
$publicNameOverlapCount = 0;
if ($isAllowedRoute) {
  try {
    $exactStmt = $pdo->prepare("
      SELECT route_id, route_name, route_type, start_stop_name, end_stop_name
      FROM seoul_bus_route_master
      WHERE route_name = :exact
      ORDER BY route_id DESC
      LIMIT 5
    ");
    $exactStmt->execute([':exact' => $selectedRouteLabel]);
    $exactRows = $exactStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($exactRows as $r) {
      $r['match_type'] = 'exact';
      $publicRouteCandidates[] = $r;
    }

    if ($publicRouteCandidates === []) {
      $prefixStmt = $pdo->prepare("
        SELECT route_id, route_name, route_type, start_stop_name, end_stop_name
        FROM seoul_bus_route_master
        WHERE route_name LIKE CONCAT(:prefix, '%')
        ORDER BY route_id DESC
        LIMIT 5
      ");
      $prefixStmt->execute([':prefix' => $selectedRouteLabel]);
      $prefixRows = $prefixStmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($prefixRows as $r) {
        $r['match_type'] = 'prefix';
        $publicRouteCandidates[] = $r;
      }
    }

    if ($publicRouteCandidates !== []) {
      $publicRouteResolved = $publicRouteCandidates[0];
      $stopCntStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM seoul_bus_route_stop_master
        WHERE route_id = :rid
      ");
      $stopCntStmt->execute([':rid' => (int)$publicRouteResolved['route_id']]);
      $publicRouteStopCount = (int)($stopCntStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

      if ($routeStops !== []) {
        $publicStopsStmt = $pdo->prepare("
          SELECT stop_name
          FROM seoul_bus_route_stop_master
          WHERE route_id = :rid
          ORDER BY seq_in_route ASC
          LIMIT 500
        ");
        $publicStopsStmt->execute([':rid' => (int)$publicRouteResolved['route_id']]);
        $publicStops = $publicStopsStmt->fetchAll(PDO::FETCH_ASSOC);

        $shuttleNames = [];
        foreach ($routeStops as $s) {
          $k = normalizeCompareKeyJourney((string)($s['stop_name'] ?? ''));
          if ($k !== '') $shuttleNames[$k] = true;
        }
        $publicNames = [];
        foreach ($publicStops as $s) {
          $k = normalizeCompareKeyJourney((string)($s['stop_name'] ?? ''));
          if ($k !== '') $publicNames[$k] = true;
        }
        $publicNameOverlapCount = count(array_intersect_key($shuttleNames, $publicNames));
      }
    }
  } catch (Throwable $ignore) {
    $publicRouteCandidates = [];
    $publicRouteResolved = null;
    $publicRouteStopCount = 0;
    $publicNameOverlapCount = 0;
  }
}

$latestAlert = null;
try {
  $hasRouteLabelColumn = (bool) array_filter($pdo->query("SHOW COLUMNS FROM app_alert_events LIKE 'route_label'")->fetchAll());
  if ($hasRouteLabelColumn && $isAllowedRoute) {
    $a = $pdo->prepare("
      SELECT id, event_type, title, published_at
      FROM app_alert_events
      WHERE published_at IS NOT NULL
        AND ref_type = 'route'
        AND ref_id = :doc
        AND route_label = :rl
      ORDER BY published_at DESC, id DESC
      LIMIT 1
    ");
    $a->execute([':doc' => $selectedDocId, ':rl' => $selectedRouteLabel]);
    $latestAlert = $a->fetch(PDO::FETCH_ASSOC) ?: null;
  }
} catch (Throwable $ignore) {
  $latestAlert = null;
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>GILIME - 경로 안내</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <nav class="nav g-topnav mb-3">
    <a class="nav-link" href="<?= $base ?>/home.php">홈</a>
    <a class="nav-link" href="<?= $base ?>/issues.php">이슈</a>
    <a class="nav-link" href="<?= $base ?>/route_finder.php">길찾기</a>
    <a class="nav-link active" href="<?= $base ?>/my_routes.php">마이노선</a>
  </nav>

  <div class="g-page-head mb-3">
    <h1>경로 안내</h1>
    <p class="helper mb-0">구독 중인 노선의 셔틀 정류장 순서를 빠르게 확인합니다.</p>
  </div>

  <div class="card g-card mb-3">
    <div class="card-body">
      <?php if ($subscribedRoutes === []): ?>
        <p class="text-muted-g small mb-0">구독 중인 노선이 없습니다. 먼저 노선 페이지에서 구독을 설정해 주세요.</p>
      <?php else: ?>
        <form method="get" class="g-form-inline">
          <label class="small text-muted-g">노선 선택</label>
          <select class="form-select form-select-sm w-auto" name="route_key">
            <?php foreach ($subscribedRoutes as $sr):
              $key = (int)$sr['doc_id'] . '|' . (string)$sr['route_label'];
              $isSelected = ((int)$sr['doc_id'] === $selectedDocId && (string)$sr['route_label'] === $selectedRouteLabel);
            ?>
            <option value="<?= h($key) ?>" <?= $isSelected ? 'selected' : '' ?>>
              doc <?= (int)$sr['doc_id'] ?> / <?= h((string)$sr['route_label']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-gilaime-primary btn-sm" type="submit">적용</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isAllowedRoute): ?>
  <div class="card g-card mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">선택 노선 정보</h2>
      <p class="mb-1">문서 ID: <strong><?= (int)$selectedDocId ?></strong></p>
      <p class="mb-1">노선 라벨: <strong><?= h($selectedRouteLabel) ?></strong></p>
      <p class="mb-1">
        공공데이터 매칭:
        <?php if ($publicRouteResolved !== null): ?>
          <strong>route_id=<?= (int)$publicRouteResolved['route_id'] ?></strong>
          (<?= h((string)$publicRouteResolved['route_name']) ?>, <?= h((string)$publicRouteResolved['match_type']) ?>)
        <?php else: ?>
          <span class="text-muted-g small">매칭 없음</span>
        <?php endif; ?>
      </p>
      <?php if ($publicRouteResolved !== null): ?>
      <p class="mb-1">
        정류장 비교: 셔틀 <?= count($routeStops) ?>건 / 공공 <?= (int)$publicRouteStopCount ?>건 / 정류장명 교집합 <?= (int)$publicNameOverlapCount ?>건
      </p>
      <?php endif; ?>
      <p class="text-muted-g small mb-0">
        <?php if ($latestAlert !== null): ?>
          최신 관련 알림: <?= h((string)$latestAlert['title']) ?> (<?= h((string)$latestAlert['published_at']) ?>)
        <?php else: ?>
          최신 관련 알림이 없거나, 연결 정보가 없습니다.
        <?php endif; ?>
      </p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isAllowedRoute && $publicRouteCandidates !== []): ?>
  <div class="card g-card mb-3">
    <div class="card-body">
      <h2 class="h5 mb-2">공공데이터 노선 후보 (참고)</h2>
      <div class="table-responsive">
      <table class="table table-hover align-middle g-table g-table-dense mb-0">
        <thead><tr><th>route_id</th><th>route_name</th><th>매칭</th><th>기점</th><th>종점</th></tr></thead>
        <tbody>
          <?php foreach ($publicRouteCandidates as $pr): ?>
          <tr>
            <td><?= (int)$pr['route_id'] ?></td>
            <td><?= h((string)$pr['route_name']) ?></td>
            <td><?= h((string)$pr['match_type']) ?></td>
            <td><?= h((string)($pr['start_stop_name'] ?? '')) ?></td>
            <td><?= h((string)($pr['end_stop_name'] ?? '')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="card g-card">
    <div class="card-body">
      <h2 class="h5 mb-2">셔틀 정류장 순서</h2>
      <?php if (!$isAllowedRoute): ?>
        <p class="text-muted-g small mb-0">선택한 노선을 확인할 수 없습니다.</p>
      <?php elseif ($routeStops === []): ?>
        <p class="text-muted-g small mb-0">활성 정류장 데이터가 없습니다. 관리자에게 승격(PROMOTE) 완료 여부를 확인해 주세요.</p>
      <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle g-table g-table-dense mb-0">
          <thead><tr><th class="g-nowrap">순서</th><th class="g-nowrap">정류장 ID</th><th>정류장명</th><th>간단 안내</th></tr></thead>
          <tbody>
            <?php
            $total = count($routeStops);
            foreach ($routeStops as $idx => $stop):
              $order = (int)($stop['stop_order'] ?? ($idx + 1));
              $hint = '중간 경유 정류장';
              if ($idx === 0) $hint = '출발 정류장';
              if ($idx === $total - 1) $hint = '도착 정류장';
            ?>
            <tr>
              <td class="g-nowrap"><?= $order ?></td>
              <td class="g-nowrap"><?= h((string)($stop['stop_id'] ?? '')) ?></td>
              <td><?= h((string)($stop['stop_name'] ?? '')) ?></td>
              <td><?= h($hint) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script src="<?= APP_BASE ?>/public/assets/js/gilaime_ui.js"></script>
  </main>
</body>
</html>
