<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';
$userBase = APP_BASE . '/user';

// POST: 새 알림 생성 (ref_type=route 고정)
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $eventType = isset($_POST['event_type']) ? trim((string)$_POST['event_type']) : '';
  $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
  $body = isset($_POST['body']) ? trim((string)$_POST['body']) : '';
  $refId = isset($_POST['ref_id']) ? (int)$_POST['ref_id'] : 0;
  $routeLabel = isset($_POST['route_label']) ? trim((string)$_POST['route_label']) : '';
  $publishedAtRaw = isset($_POST['published_at']) ? trim((string)$_POST['published_at']) : '';
  $publishedAt = $publishedAtRaw !== '' ? $publishedAtRaw : date('Y-m-d H:i:s');
  if (strtotime($publishedAt) === false) {
    $publishedAt = date('Y-m-d H:i:s');
  }
  if ($eventType !== '' && $title !== '' && $refId > 0 && $routeLabel !== '') {
    $title50 = mb_substr($title, 0, 50);
    $hashInput = sprintf('admin_create_%s_%d_%s_%s_%s',
      $eventType, $refId, $routeLabel, $title50, date('YmdHis', strtotime($publishedAt)));
    $contentHash = hash('sha256', $hashInput);
    try {
      $stmt = $pdo->prepare("
        INSERT IGNORE INTO app_alert_events (event_type, title, body, ref_type, ref_id, route_label, content_hash, published_at, created_at)
        VALUES (:etype, :title, :body, 'route', :ref_id, :route_label, :chash, :pub, NOW())
      ");
      $stmt->execute([
        ':etype' => $eventType,
        ':title' => $title,
        ':body' => $body === '' ? null : $body,
        ':ref_id' => $refId,
        ':route_label' => $routeLabel,
        ':chash' => $contentHash,
        ':pub' => $publishedAt,
      ]);
      if ($stmt->rowCount() > 0) {
        $flash = 'created';
      } else {
        $flash = 'duplicate ignored';
      }
    } catch (Throwable $e) {
      $flash = 'failed';
      error_log('OPS alert_create_failed: ' . $e->getMessage());
    }
  } else {
    $flash = 'failed';
  }
  header('Location: ' . $base . '/alert_ops.php?flash=' . urlencode((string)$flash));
  exit;
}
$flash = isset($_GET['flash']) ? trim((string)$_GET['flash']) : null;

// Filters (GET)
$filterType = isset($_GET['event_type']) && trim((string)$_GET['event_type']) !== '' ? trim((string)$_GET['event_type']) : null;
$filterRoute = isset($_GET['route_label']) && trim((string)$_GET['route_label']) !== '' ? trim((string)$_GET['route_label']) : null;
$filterFrom = isset($_GET['published_from']) && trim((string)$_GET['published_from']) !== '' ? trim((string)$_GET['published_from']) : null;
$filterTo = isset($_GET['published_to']) && trim((string)$_GET['published_to']) !== '' ? trim((string)$_GET['published_to']) : null;

$sql = "SELECT id, event_type, title, ref_type, ref_id, route_label, published_at, created_at FROM app_alert_events WHERE 1=1";
$params = [];
if ($filterType !== null) {
  $sql .= " AND event_type = :etype";
  $params[':etype'] = $filterType;
}
if ($filterRoute !== null) {
  $sql .= " AND route_label = :rl";
  $params[':rl'] = $filterRoute;
}
if ($filterFrom !== null) {
  $sql .= " AND published_at >= :pub_from";
  $params[':pub_from'] = $filterFrom;
}
if ($filterTo !== null) {
  $sql .= " AND published_at <= :pub_to";
  $params[':pub_to'] = $filterTo;
}
$sql .= " ORDER BY published_at DESC, id DESC LIMIT 200";
$stmt = $params === [] ? $pdo->query($sql) : $pdo->prepare($sql);
if ($params !== []) {
  $stmt->execute($params);
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>Admin - Alert Ops</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .muted{color:#666;font-size:12px;}
    table{border-collapse:collapse;width:100%;background:#fff;}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;}
    .form-inline{margin-bottom:16px;}
    .form-inline label{margin-right:8px;}
    .form-inline input, .form-inline select{margin-right:12px;}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <a href="<?= h($base) ?>/index.php">Docs</a>
      <span class="muted"> / Alert Ops</span>
    </div>
    <a href="<?= h($base) ?>/alert_event_audit.php">Alert Audit</a>
    <a href="<?= h($base) ?>/logout.php">Logout</a>
  </div>

  <h2>Alert Ops</h2>

  <!-- 새 알림 작성 -->
  <div class="card" style="margin-bottom:20px;">
    <h3>새 알림 작성</h3>
    <form method="post" action="<?= h($base) ?>/alert_ops.php">
      <input type="hidden" name="ref_type" value="route" />
      <div class="form-inline">
        <label>event_type</label>
        <select name="event_type" required>
          <option value="strike">strike</option>
          <option value="event">event</option>
          <option value="update">update</option>
          <option value="e2e_test">e2e_test</option>
        </select>
        <label>title</label>
        <input type="text" name="title" required maxlength="255" size="40" />
        <label>body (optional)</label>
        <input type="text" name="body" maxlength="500" size="30" />
      </div>
      <div class="form-inline">
        <label>ref_id</label>
        <input type="number" name="ref_id" required min="1" value="1" />
        <label>route_label</label>
        <input type="text" name="route_label" required maxlength="64" value="R1" />
        <label>published_at (optional)</label>
        <input type="datetime-local" name="published_at" />
      </div>
      <button type="submit">Create</button>
    </form>
  </div>

  <?php if ($flash !== null): ?>
  <p class="muted"><?= $flash === 'created' ? 'created' : ($flash === 'duplicate ignored' ? 'duplicate ignored' : 'failed') ?></p>
  <?php endif; ?>

  <!-- 필터 -->
  <p class="muted">
    <a href="<?= h($base) ?>/alert_ops.php">전체</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=strike">strike</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=event">event</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=update">update</a>
    | route_label: <form method="get" action="<?= h($base) ?>/alert_ops.php" style="display:inline;">
      <input type="hidden" name="event_type" value="<?= h($filterType ?? '') ?>" />
      <input type="text" name="route_label" value="<?= h($filterRoute ?? '') ?>" placeholder="R1" size="6" />
      <input type="text" name="published_from" value="<?= h($filterFrom ?? '') ?>" placeholder="from" size="12" />
      <input type="text" name="published_to" value="<?= h($filterTo ?? '') ?>" placeholder="to" size="12" />
      <button type="submit">Filter</button>
    </form>
  </p>

  <table>
    <thead>
      <tr>
        <th>id</th><th>event_type</th><th>title</th><th>ref_type</th><th>ref_id</th><th>route_label</th><th>published_at</th><th>created_at</th><th>Links</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($events as $e):
        $refId = (int)($e['ref_id'] ?? 0);
        $rl = trim((string)($e['route_label'] ?? ''));
        $userAlertsUrl = $rl !== '' ? $userBase . '/alerts.php?route_label=' . urlencode($rl) : '';
        $reviewUrl = ($refId > 0 && $rl !== '') ? $base . '/route_review.php?source_doc_id=' . $refId . '&route_label=' . urlencode($rl) . '&quick_mode=1&show_advanced=0' : ($refId > 0 ? $base . '/doc.php?id=' . $refId : '');
      ?>
        <tr>
          <td><?= (int)$e['id'] ?></td>
          <td><?= h($e['event_type'] ?? '') ?></td>
          <td><?= h($e['title'] ?? '') ?></td>
          <td><?= h($e['ref_type'] ?? '') ?></td>
          <td><?= $refId ?></td>
          <td><?= h($rl) ?></td>
          <td><?= h($e['published_at'] ?? '') ?></td>
          <td><?= h($e['created_at'] ?? '') ?></td>
          <td>
            <?php if ($userAlertsUrl !== ''): ?><a href="<?= h($userAlertsUrl) ?>" target="_blank" rel="noopener">User Alerts</a><?php endif; ?>
            <?php if ($reviewUrl !== ''): ?> <a href="<?= h($reviewUrl) ?>">Admin Review</a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted">최근 200건. Pagination은 v1.6-06(확인 필요).</p>
</body>
</html>
