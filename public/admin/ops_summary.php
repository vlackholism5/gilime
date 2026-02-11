<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
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
  <div class="g-top">
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
    <table class="table table-hover align-middle g-table mb-0">
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
          <tr><td colspan="8" class="text-muted-g small">(none)</td></tr>
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
    <table class="table table-hover align-middle g-table mb-0">
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
          <tr><td colspan="6" class="text-muted-g small">(none)</td></tr>
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
    <table class="table table-hover align-middle g-table mb-0">
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
          <tr><td colspan="9" class="text-muted-g small">(none)</td></tr>
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
    <p><code class="small">php scripts/run_delivery_outbound_stub.php --limit=200</code></p>
    <p class="text-muted-g small mb-0">실제 이메일/SMS/푸시 연동 없음. pending만 sent로 전환하는 운영 스텁.</p>
    </div>
  </section>
  </main>
</body>
</html>
