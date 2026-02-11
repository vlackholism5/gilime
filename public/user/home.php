<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config.php';
require_once __DIR__ . '/../../app/inc/user_session.php';

$pdo = pdo();
$userId = user_session_user_id();
$subCount = user_session_subscription_count();

$alerts = [];
try {
  $stmt = $pdo->query("
    SELECT id, event_type, title, published_at, created_at
    FROM app_alert_events
    ORDER BY created_at DESC
    LIMIT 5
  ");
  $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $alerts = [];
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
$base = APP_BASE . '/user';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>GILIME - Home</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    .nav a{margin-right:16px;}
    .card{background:#fff;border:1px solid #eee;border-radius:8px;padding:16px;margin-bottom:16px;}
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
  <h1>GILIME</h1>
  <p class="muted">My subscriptions: <?= (int)$subCount ?></p>
  <div class="card">
    <h2>Latest alerts (5)</h2>
    <?php if ($alerts === []): ?>
      <p class="muted">No alerts.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Type</th><th>Title</th><th>Published</th></tr></thead>
        <tbody>
          <?php foreach ($alerts as $a): ?>
            <tr>
              <td><?= h($a['event_type'] ?? '') ?></td>
              <td><?= h($a['title'] ?? '') ?></td>
              <td><?= h($a['published_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
