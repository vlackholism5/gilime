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
  <title>Admin - Ops Summary</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    section{margin-bottom:24px;background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
    h3{margin:0 0 12px 0;font-size:16px;}
    table{border-collapse:collapse;width:100%;}
    th,td{border-bottom:1px solid #eee;padding:8px 10px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;}
    .muted{color:#666;font-size:12px;}
    code{background:#f0f0f0;padding:2px 6px;border-radius:4px;}
  </style>
</head>
<body>
  <div class="top">
    <h2>Ops Summary</h2>
    <div>
      <a href="<?= $base ?>/alert_ops.php">Alert Ops</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/alert_event_audit.php">Alert Audit</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/index.php">Admin Home</a>
    </div>
  </div>

  <section>
    <h3>1. Approvals (최근 20)</h3>
    <table>
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
          <tr><td colspan="8" class="muted">(none)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h3>2. Events (최근 50) — Draft: <?= $draftCnt ?>, Published: <?= $publishedCnt ?></h3>
    <table>
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
            <td><?= !empty($e['published_at']) ? 'published' : 'draft' ?></td>
            <td><?= h((string)$e['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$events): ?>
          <tr><td colspan="6" class="muted">(none)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="muted">Event 상세: <a href="<?= $base ?>/alert_ops.php">Alert Ops</a>, <a href="<?= $base ?>/alert_event_audit.php">Alert Audit</a></p>
  </section>

  <section>
    <h3>3. Deliveries (상태별 카운트 + 최근 20)</h3>
    <p><strong>상태별:</strong>
      <?php
        $parts = [];
        foreach ($statusCounts as $row) {
          $parts[] = $row['status'] . '=' . (int)$row['cnt'];
        }
        echo $parts ? implode(', ', $parts) : '(none)';
      ?>
    </p>
    <table>
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
          <tr><td colspan="9" class="muted">(none)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h3>4. Outbound Stub 실행 안내</h3>
    <p>pending → sent 처리(스텁): 터미널에서 아래 명령 실행.</p>
    <p><code>php scripts/run_delivery_outbound_stub.php --limit=200</code></p>
    <p class="muted">실제 이메일/SMS/푸시 연동 없음. pending만 sent로 전환하는 운영 스텁.</p>
  </section>
</body>
</html>
