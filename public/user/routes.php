<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config.php';
require_once __DIR__ . '/../../app/inc/user_session.php';

$pdo = pdo();
$userId = user_session_user_id();

// POST: subscribe / unsubscribe (MVP2 temporary auth)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
  $docId = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
  $routeLabel = isset($_POST['route_label']) ? trim((string)$_POST['route_label']) : '';
  if ($docId > 0 && $routeLabel !== '' && in_array($action, ['subscribe', 'unsubscribe'], true)) {
    $targetId = $docId . '_' . $routeLabel;
    $isActive = $action === 'subscribe' ? 1 : 0;
    if ($action === 'subscribe') {
      $pdo->prepare("
        INSERT INTO app_subscriptions (user_id, target_type, target_id, alert_type, is_active)
        VALUES (:uid, 'route', :target_id, 'strike,event,update', 1)
        ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
      ")->execute([':uid' => $userId, ':target_id' => $targetId]);
    } else {
      $pdo->prepare("
        UPDATE app_subscriptions SET is_active = 0, updated_at = NOW()
        WHERE user_id = :uid AND target_type = 'route' AND target_id = :target_id
      ")->execute([':uid' => $userId, ':target_id' => $targetId]);
    }
    try {
      error_log(sprintf('OPS subscribe_toggle user_id=%d target_id=%s is_active=%d', $userId, $targetId, $isActive));
    } catch (Throwable $e) {
      // do not break UX
    }
  }
  header('Location: ' . APP_BASE . '/user/routes.php');
  exit;
}

// List: distinct (doc_id, route_label) from shuttle_stop_candidate + latest PARSE_MATCH job
$routes = [];
try {
  $stmt = $pdo->query("
    SELECT c.source_doc_id AS doc_id, c.route_label
    FROM shuttle_stop_candidate c
    INNER JOIN (
      SELECT j.source_doc_id, j.id AS job_id
      FROM shuttle_doc_job_log j
      INNER JOIN (
        SELECT source_doc_id, MAX(id) AS mid
        FROM shuttle_doc_job_log
        WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
        GROUP BY source_doc_id
      ) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
      WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
    ) latest ON c.source_doc_id = latest.source_doc_id AND c.created_job_id = latest.job_id
    GROUP BY c.source_doc_id, c.route_label
    ORDER BY c.source_doc_id, c.route_label
  ");
  $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $routes = [];
}

$subscribed = [];
$st = $pdo->prepare("SELECT target_id FROM app_subscriptions WHERE user_id = :uid AND target_type = 'route' AND is_active = 1");
$st->execute([':uid' => $userId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $subscribed[$row['target_id']] = true;
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
  <title>GILIME - Routes</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    .nav a{margin-right:16px;}
    .card{background:#fff;border:1px solid #eee;border-radius:8px;padding:16px;}
    .muted{color:#666;font-size:13px;}
    table{border-collapse:collapse;width:100%;}
    th,td{border-bottom:1px solid #eee;padding:8px 12px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;}
    button{padding:6px 12px;cursor:pointer;}
  </style>
</head>
<body>
  <nav class="nav">
    <a href="<?= $base ?>/home.php">Home</a>
    <a href="<?= $base ?>/routes.php">Routes</a>
    <a href="<?= $base ?>/alerts.php">Alerts</a>
  </nav>
  <h1>Routes</h1>
  <p class="muted">Subscribe to routes for alerts (read-only list).</p>
  <div class="card">
    <?php if ($routes === []): ?>
      <p class="muted">No routes.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Doc ID</th><th>Route</th><th>Subscribe</th></tr></thead>
        <tbody>
          <?php foreach ($routes as $r):
            $tid = (int)$r['doc_id'] . '_' . $r['route_label'];
            $isSub = isset($subscribed[$tid]);
          ?>
            <tr>
              <td><?= (int)$r['doc_id'] ?></td>
              <td><?= h($r['route_label']) ?><?php if ($isSub): ?> <span class="muted" style="font-size:11px;">(Subscribed)</span><?php endif; ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="doc_id" value="<?= (int)$r['doc_id'] ?>" />
                  <input type="hidden" name="route_label" value="<?= h($r['route_label']) ?>" />
                  <?php if ($isSub): ?>
                    <input type="hidden" name="action" value="unsubscribe" />
                    <button type="submit">Unsubscribe</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="subscribe" />
                    <button type="submit">Subscribe</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
