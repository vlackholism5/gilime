<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';

// 1) Approvals 최근 20
$approvals = $pdo->query("
  SELECT a.id, a.alert_event_id, a.actor_user_id, a.action, a.note, a.created_at,
         e.event_type, e.title, e.route_label
  FROM app_alert_approvals a
  LEFT JOIN app_alert_events e ON e.id = a.alert_event_id
  ORDER BY a.created_at DESC, a.id DESC
  LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// 2) Events 최근 50 + draft/published 카운트
$draftCnt = (int)$pdo->query("SELECT COUNT(*) AS c FROM app_alert_events WHERE published_at IS NULL")->fetch(PDO::FETCH_ASSOC)['c'];
$publishedCnt = (int)$pdo->query("SELECT COUNT(*) AS c FROM app_alert_events WHERE published_at IS NOT NULL")->fetch(PDO::FETCH_ASSOC)['c'];
$events = $pdo->query("
  SELECT id, event_type, title, ref_type, ref_id, route_label, published_at, created_at
  FROM app_alert_events
  ORDER BY created_at DESC, id DESC
  LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// 3) Deliveries 상태별 카운트 + 최근 20
$statusCounts = $pdo->query("
  SELECT status, COUNT(*) AS cnt FROM app_alert_deliveries GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);
$recentDeliveries = $pdo->query("
  SELECT id, alert_event_id, user_id, channel, status, sent_at, delivered_at, last_error, created_at
  FROM app_alert_deliveries
  ORDER BY created_at DESC, id DESC
  LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function inboundFilePath(string $rel): string {
  return __DIR__ . '/../../' . ltrim($rel, '/');
}

function dataReady(string $rel): bool {
  return is_file(inboundFilePath($rel));
}

function tableCountSafe(PDO $pdo, string $table): ?int {
  try {
    $existsStmt = $pdo->prepare("
      SELECT COUNT(*) AS c
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t
    ");
    $existsStmt->execute([':t' => $table]);
    $exists = (int)($existsStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    if ($exists === 0) return null;
    $cnt = $pdo->query("SELECT COUNT(*) AS c FROM {$table}")->fetch(PDO::FETCH_ASSOC);
    return (int)($cnt['c'] ?? 0);
  } catch (Throwable $ignore) {
    return null;
  }
}

$stopMasterRel = 'data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv';
$routeMasterRel = 'data/inbound/seoul/bus/route_master/서울시 노선마스터 정보.csv';
$routeStopMasterRel = 'data/inbound/seoul/bus/route_stop_master/서울시 노선 정류장마스터 정보.csv';
$stopMasterReady = dataReady($stopMasterRel);
$routeMasterReady = dataReady($routeMasterRel);
$routeStopReady = dataReady($routeStopMasterRel);
$stopMasterCount = tableCountSafe($pdo, 'seoul_bus_stop_master');
$routeMasterCount = tableCountSafe($pdo, 'seoul_bus_route_master');
$routeStopCount = tableCountSafe($pdo, 'seoul_bus_route_stop_master');
$routeStopStopIdNullCount = null;
if ($routeStopCount !== null && $routeStopCount > 0) {
  try {
    $row = $pdo->query("SELECT COUNT(*) AS c FROM seoul_bus_route_stop_master WHERE stop_id IS NULL")->fetch(PDO::FETCH_ASSOC);
    $routeStopStopIdNullCount = (int)($row['c'] ?? 0);
  } catch (Throwable $e) {
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - 운영 요약</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '운영 요약', 'url' => null],
  ], false); ?>
  <div class="g-page-header-row">
    <div class="g-page-head">
      <h2 class="h3">운영 요약</h2>
      <p class="helper mb-0">승인/이벤트/배달 상태를 한 화면에서 확인합니다.</p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/alert_ops.php">알림 운영</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/alert_event_audit.php">알림 감사</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/index.php">관리자 홈</a>
    </div>
  </div>

  <section class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5">1. 승인 이력 (최근 20)</h3>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table g-table-dense mb-0">
      <thead>
        <tr>
          <th>id</th><th>event_id</th><th>actor</th><th>action</th><th>note</th><th>event_type</th><th>route</th><th>created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($approvals as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><a href="<?= $base ?>/alert_ops.php?event_id=<?= (int)$r['alert_event_id'] ?>"><?= (int)$r['alert_event_id'] ?></a></td>
            <td><?= h((string)$r['actor_user_id']) ?></td>
            <td><?= h((string)$r['action']) ?></td>
            <td><?= h((string)($r['note'] ?? '')) ?></td>
            <td><?= h((string)($r['event_type'] ?? '')) ?></td>
            <td><?= h((string)($r['route_label'] ?? '')) ?></td>
            <td><?= h((string)$r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$approvals): ?>
          <tr><td colspan="8" class="text-muted-g small">데이터가 없습니다</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    </div>
  </section>

  <section class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5">2. 이벤트 (최근 50) — 초안: <?= $draftCnt ?>, 발행: <?= $publishedCnt ?></h3>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table g-table-dense mb-0">
      <thead>
        <tr>
          <th>id</th><th>event_type</th><th>title</th><th>route</th><th>status</th><th>created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td><a href="<?= $base ?>/alert_ops.php?event_id=<?= (int)$e['id'] ?>"><?= (int)$e['id'] ?></a></td>
            <td><?= h((string)($e['event_type'] ?? '')) ?></td>
            <td><?= h((string)($e['title'] ?? '')) ?></td>
            <td><?= h((string)($e['route_label'] ?? '')) ?></td>
            <td><?= !empty($e['published_at']) ? '발행' : '초안' ?></td>
            <td><?= h((string)$e['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$events): ?>
          <tr><td colspan="6" class="text-muted-g small">데이터가 없습니다</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    <p class="text-muted-g small mt-2 mb-0">이벤트 상세: <a href="<?= $base ?>/alert_ops.php">알림 운영</a>, <a href="<?= $base ?>/alert_event_audit.php">알림 감사</a></p>
    </div>
  </section>

  <section class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5">3. 배달(Deliveries) (상태별 카운트 + 최근 20)</h3>
    <p><strong>상태별:</strong>
      <?php
        $parts = [];
        foreach ($statusCounts as $row) {
          $parts[] = $row['status'] . '=' . (int)$row['cnt'];
        }
        echo $parts ? implode(', ', $parts) : '(none)';
      ?>
    </p>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table g-table-dense mb-0">
      <thead>
        <tr>
          <th>id</th><th>event_id</th><th>user_id</th><th>channel</th><th>status</th><th>sent_at</th><th>delivered_at</th><th>last_error</th><th>created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentDeliveries as $d): ?>
          <tr>
            <td><?= (int)$d['id'] ?></td>
            <td><a href="<?= $base ?>/alert_event_audit.php?event_id=<?= (int)$d['alert_event_id'] ?>"><?= (int)$d['alert_event_id'] ?></a></td>
            <td><?= (int)$d['user_id'] ?></td>
            <td><?= h((string)$d['channel']) ?></td>
            <td><?= h((string)$d['status']) ?></td>
            <td><?= h((string)($d['sent_at'] ?? '')) ?></td>
            <td><?= h((string)($d['delivered_at'] ?? '')) ?></td>
            <td><?= h((string)($d['last_error'] ?? '')) ?></td>
            <td><?= h((string)$d['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentDeliveries): ?>
          <tr><td colspan="9" class="text-muted-g small">데이터가 없습니다</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    </div>
  </section>

  <section class="card g-card">
    <div class="card-body">
    <h3 class="h5">4. Outbound Stub 실행 안내</h3>
    <p>대기(pending) → 발송됨(sent) 처리 스텁: 터미널에서 아래 명령 실행.</p>
    <p><code class="small g-nowrap">php scripts/run_delivery_outbound_stub.php --limit=200</code></p>
    <p class="text-muted-g small mb-0">실제 이메일/SMS/푸시 연동 없음. pending만 sent로 전환하는 운영 스텁.</p>
    </div>
  </section>

  <section class="card g-card mt-4">
    <div class="card-body">
    <h3 class="h5">5. 서울시 노선 데이터 반영 준비 상태 (v1.7-18)</h3>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table g-table-dense mb-0">
      <thead>
        <tr><th>항목</th><th>상태</th><th>값/경로</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>inbound 정류장 마스터 CSV</td>
          <td><?= $stopMasterReady ? '준비됨' : '없음' ?></td>
          <td><code class="small"><?= h($stopMasterRel) ?></code></td>
        </tr>
        <tr>
          <td>inbound 노선 마스터 CSV</td>
          <td><?= $routeMasterReady ? '준비됨' : '없음' ?></td>
          <td><code class="small"><?= h($routeMasterRel) ?></code></td>
        </tr>
        <tr>
          <td>inbound 노선-정류장 마스터 CSV</td>
          <td><?= $routeStopReady ? '준비됨' : '없음' ?></td>
          <td><code class="small"><?= h($routeStopMasterRel) ?></code></td>
        </tr>
        <tr>
          <td>DB 적재 건수 (seoul_bus_stop_master)</td>
          <td><?= $stopMasterCount === null ? '스키마 미적용/확인불가' : '확인됨' ?></td>
          <td><?= $stopMasterCount === null ? '—' : (int)$stopMasterCount . '건' ?></td>
        </tr>
        <tr>
          <td>DB 적재 건수 (seoul_bus_route_master)</td>
          <td><?= $routeMasterCount === null ? '스키마 미적용/확인불가' : '확인됨' ?></td>
          <td><?= $routeMasterCount === null ? '—' : (int)$routeMasterCount . '건' ?></td>
        </tr>
        <tr>
          <td>DB 적재 건수 (seoul_bus_route_stop_master)</td>
          <td><?= $routeStopCount === null ? '스키마 미적용/확인불가' : '확인됨' ?></td>
          <td><?= $routeStopCount === null ? '—' : (int)$routeStopCount . '건' ?></td>
        </tr>
        <?php if ($routeStopStopIdNullCount !== null): ?>
        <tr>
          <td>route_stop stop_id NULL 건수</td>
          <td><?= $routeStopStopIdNullCount > 0 && $routeStopCount > 0 && ($routeStopStopIdNullCount / $routeStopCount) > 0.05 ? '경고(&gt;5%)' : '정상' ?></td>
          <td><?= (int)$routeStopStopIdNullCount ?>건 (<?= $routeStopCount > 0 ? round(100 * $routeStopStopIdNullCount / $routeStopCount, 1) : 0 ?>%)</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    <p class="mt-3 mb-1"><strong>실행 순서(터미널):</strong></p>
    <p class="mb-1"><code class="small g-nowrap">php scripts/php/import_seoul_bus_stop_master_full.php</code></p>
    <p class="mb-1"><code class="small g-nowrap">php scripts/php/import_seoul_bus_route_master_full.php</code></p>
    <p class="mb-1"><code class="small g-nowrap">php scripts/php/import_seoul_bus_route_stop_master_full.php</code></p>
    <p class="text-muted-g small mb-0">검증 SQL: <code class="small">sql/releases/v1.7/validation/validation_18_seoul_route_public_data.sql</code></p>
    </div>
  </section>
  </main>
</body>
</html>
