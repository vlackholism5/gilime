<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';

$filterEventId = isset($_GET['alert_event_id']) && (string)$_GET['alert_event_id'] !== '' ? (int)$_GET['alert_event_id'] : null;
$filterUserId = isset($_GET['user_id']) && (string)$_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;

$sql = "SELECT id, alert_event_id, user_id, channel, status, sent_at, created_at FROM app_alert_deliveries WHERE 1=1";
$params = [];
if ($filterEventId !== null && $filterEventId > 0) {
  $sql .= " AND alert_event_id = :eid";
  $params[':eid'] = $filterEventId;
}
if ($filterUserId !== null && $filterUserId > 0) {
  $sql .= " AND user_id = :uid";
  $params[':uid'] = $filterUserId;
}
$sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";
$stmt = $params === [] ? $pdo->query($sql) : $pdo->prepare($sql);
if ($params !== []) {
  $stmt->execute($params);
}
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>Admin - Alert Event Audit</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .muted{color:#666;font-size:12px;}
    table{border-collapse:collapse;width:100%;background:#fff;}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <a href="<?= h($base) ?>/index.php">Docs</a>
      <span class="muted"> / Alert Audit</span>
    </div>
    <a href="<?= h($base) ?>/alert_ops.php">Alert Ops</a>
    <a href="<?= h($base) ?>/logout.php">Logout</a>
  </div>

  <h2>Alert Event Audit (deliveries)</h2>
  <p class="muted">read-only. 최근 200건.</p>

  <form method="get" action="<?= h($base) ?>/alert_event_audit.php" style="margin-bottom:12px;">
    <label>alert_event_id</label>
    <input type="number" name="alert_event_id" value="<?= $filterEventId !== null ? (int)$filterEventId : '' ?>" min="1" />
    <label>user_id</label>
    <input type="number" name="user_id" value="<?= $filterUserId !== null ? (int)$filterUserId : '' ?>" min="1" />
    <button type="submit">Filter</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>id</th><th>alert_event_id</th><th>user_id</th><th>channel</th><th>status</th><th>sent_at</th><th>created_at</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($deliveries as $d): ?>
        <tr>
          <td><?= (int)$d['id'] ?></td>
          <td><a href="<?= h($base) ?>/alert_ops.php?event_id=<?= (int)$d['alert_event_id'] ?>"><?= (int)$d['alert_event_id'] ?></a></td>
          <td><?= (int)$d['user_id'] ?></td>
          <td><?= h($d['channel'] ?? '') ?></td>
          <td><?= h($d['status'] ?? '') ?></td>
          <td><?= h($d['sent_at'] ?? '') ?></td>
          <td><?= h($d['created_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
