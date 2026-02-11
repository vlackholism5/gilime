<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';

// v1.6-07: event_id (alias), user_id, channel, status, since
$filterEventId = null;
if (isset($_GET['event_id']) && (string)$_GET['event_id'] !== '') {
  $filterEventId = (int)$_GET['event_id'];
} elseif (isset($_GET['alert_event_id']) && (string)$_GET['alert_event_id'] !== '') {
  $filterEventId = (int)$_GET['alert_event_id'];
}
$filterUserId = isset($_GET['user_id']) && (string)$_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$filterChannel = isset($_GET['channel']) && trim((string)$_GET['channel']) !== '' ? trim((string)$_GET['channel']) : null;
$filterStatus = isset($_GET['status']) && trim((string)$_GET['status']) !== '' ? trim((string)$_GET['status']) : null;
$filterSince = isset($_GET['since']) && trim((string)$_GET['since']) !== '' ? trim((string)$_GET['since']) : null;

$where = [];
$params = [];
if ($filterEventId !== null && $filterEventId > 0) {
  $where[] = " alert_event_id = :eid";
  $params[':eid'] = $filterEventId;
}
if ($filterUserId !== null && $filterUserId > 0) {
  $where[] = " user_id = :uid";
  $params[':uid'] = $filterUserId;
}
if ($filterChannel !== null) {
  $where[] = " channel = :channel";
  $params[':channel'] = $filterChannel;
}
if ($filterStatus !== null) {
  $where[] = " status = :status";
  $params[':status'] = $filterStatus;
}
if ($filterSince !== null) {
  $where[] = " created_at >= :since";
  $params[':since'] = $filterSince;
}
$whereClause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

// 요약: COUNT(*), MIN(created_at), MAX(created_at)
$summarySql = "SELECT COUNT(*) AS delivery_cnt, MIN(created_at) AS min_created, MAX(created_at) AS max_created FROM app_alert_deliveries" . $whereClause;
$summaryStmt = $params === [] ? $pdo->query($summarySql) : $pdo->prepare($summarySql);
if ($params !== []) {
  $summaryStmt->execute($params);
}
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$sql = "SELECT id, alert_event_id, user_id, channel, status, sent_at, created_at FROM app_alert_deliveries" . $whereClause;
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
  <?php
  $cnt = (int)($summary['delivery_cnt'] ?? 0);
  $minC = $summary['min_created'] ?? null;
  $maxC = $summary['max_created'] ?? null;
  ?>
  <p class="muted">요약: delivery_cnt=<strong><?= $cnt ?></strong><?= $minC !== null && $minC !== '' ? ', min_created=' . h($minC) : '' ?><?= $maxC !== null && $maxC !== '' ? ', max_created=' . h($maxC) : '' ?>.</p>

  <form method="get" action="<?= h($base) ?>/alert_event_audit.php" style="margin-bottom:12px;">
    <label>event_id</label>
    <input type="number" name="event_id" value="<?= $filterEventId !== null ? (int)$filterEventId : '' ?>" min="1" placeholder="alert_event_id" />
    <label>user_id</label>
    <input type="number" name="user_id" value="<?= $filterUserId !== null ? (int)$filterUserId : '' ?>" min="1" />
    <label>channel</label>
    <input type="text" name="channel" value="<?= h($filterChannel ?? '') ?>" size="8" placeholder="web" />
    <label>status</label>
    <input type="text" name="status" value="<?= h($filterStatus ?? '') ?>" size="8" placeholder="shown" />
    <label>since (created_at &gt;=)</label>
    <input type="datetime-local" name="since" value="<?= h($filterSince ?? '') ?>" />
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
          <td><a href="<?= h($base) ?>/alert_event_audit.php?user_id=<?= (int)$d['user_id'] ?>"><?= (int)$d['user_id'] ?></a></td>
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
