<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config.php';
require_once __DIR__ . '/../../app/inc/user_session.php';

$pdo = pdo();
user_session_user_id();

$typeFilter = isset($_GET['type']) && trim((string)$_GET['type']) !== '' ? trim((string)$_GET['type']) : null;

$sql = "
  SELECT id, event_type, title, body, ref_type, ref_id, published_at, created_at
  FROM app_alert_events
";
$params = [];
if ($typeFilter !== null) {
  $sql .= " WHERE event_type = :etype";
  $params[':etype'] = $typeFilter;
}
$sql .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
$base = APP_BASE . '/user';
$q = $typeFilter !== null ? ['type' => $typeFilter] : [];
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>GILIME - Alerts</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .nav{margin-bottom:20px;}
    .nav a{margin-right:16px;}
    .filters{margin-bottom:12px;}
    .filters a{margin-right:8px;}
    .card{background:#fff;border:1px solid #eee;border-radius:8px;padding:16px;}
    .muted{color:#666;font-size:13px;}
    table{border-collapse:collapse;width:100%;}
    th,td{border-bottom:1px solid #eee;padding:8px 12px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;}
  </style>
</head>
<body>
  <nav class="nav">
    <a href="<?= $base ?>/home.php">Home</a>
    <a href="<?= $base ?>/routes.php">Routes</a>
    <a href="<?= $base ?>/alerts.php">Alerts</a>
  </nav>

  <h1>Alerts</h1>
  <div class="filters">
    <a href="<?= $base ?>/alerts.php">전체</a>
    <a href="<?= $base ?>/alerts.php?type=strike">strike</a>
    <a href="<?= $base ?>/alerts.php?type=event">event</a>
    <a href="<?= $base ?>/alerts.php?type=update">update</a>
  </div>

  <div class="card">
    <?php if ($events === []): ?>
      <p class="muted">알림이 없습니다.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>유형</th><th>제목</th><th>본문</th><th>발행일</th></tr></thead>
        <tbody>
          <?php foreach ($events as $e): ?>
            <tr>
              <td><?= h($e['event_type'] ?? '') ?></td>
              <td><?= h($e['title'] ?? '') ?></td>
              <td><?= h(mb_substr((string)($e['body'] ?? ''), 0, 80)) ?><?= mb_strlen((string)($e['body'] ?? '')) > 80 ? '…' : '' ?></td>
              <td><?= h($e['published_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
