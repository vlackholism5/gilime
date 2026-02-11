<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config.php';
require_once __DIR__ . '/../../app/inc/user_session.php';
require_once __DIR__ . '/../../app/inc/alert_delivery.php';

$pdo = pdo();
$userId = user_session_user_id();

$typeFilter = isset($_GET['type']) && trim((string)$_GET['type']) !== '' ? trim((string)$_GET['type']) : null;
$routeFilter = isset($_GET['route_label']) && trim((string)$_GET['route_label']) !== '' ? trim((string)$_GET['route_label']) : null;
$subscribedOnly = isset($_GET['subscribed']) && $_GET['subscribed'] === '1';

$hasRouteLabelColumn = false;
try {
  $pdo->query("SELECT 1 FROM app_alert_events LIMIT 0");
  $hasRouteLabelColumn = (bool) array_filter($pdo->query("SHOW COLUMNS FROM app_alert_events LIKE 'route_label'")->fetchAll());
} catch (Throwable $e) {
}
if (!$hasRouteLabelColumn) {
  $routeFilter = null;
  $subscribedOnly = false;
}

$sql = "
  SELECT id, event_type, title, body, ref_type, ref_id" . ($hasRouteLabelColumn ? ", route_label" : "") . ", published_at, created_at
  FROM app_alert_events
";
$params = [];
$where = [];
if ($typeFilter !== null) {
  $where[] = " event_type = :etype";
  $params[':etype'] = $typeFilter;
}
if ($hasRouteLabelColumn && $routeFilter !== null) {
  $where[] = " route_label = :rl";
  $params[':rl'] = $routeFilter;
}
if ($hasRouteLabelColumn && $subscribedOnly) {
  $where[] = " route_label IS NOT NULL AND ref_id IS NOT NULL AND EXISTS (
    SELECT 1 FROM app_subscriptions s
    WHERE s.user_id = :uid AND s.is_active = 1 AND s.target_type = 'route'
      AND s.target_id = CONCAT(app_alert_events.ref_id, '_', app_alert_events.route_label)
  )";
  $params[':uid'] = $userId;
}
if ($where !== []) {
  $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$hasRouteLabelColumn) {
  foreach ($events as &$row) {
    $row['route_label'] = null;
  }
  unset($row);
}

// v1.4-06: mark shown as delivered (insert/upsert by unique user_id, event_id, channel)
foreach ($events as $e) {
  try {
    record_alert_delivery((int)$e['id'], $userId, 'web', 'shown');
  } catch (Throwable $t) {
    // UNIQUE not yet applied or missing table — skip
  }
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
$base = APP_BASE . '/user';
$adminBase = APP_BASE . '/admin';
$q = array_filter([
  'type' => $typeFilter,
  'route_label' => $routeFilter,
  'subscribed' => $subscribedOnly ? '1' : null,
]);
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
    <span>type:</span>
    <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_filter(['route_label' => $routeFilter, 'subscribed' => $subscribedOnly ? '1' : null])) ?>">전체</a>
    <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['type' => 'strike'])) ?>">strike</a>
    <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['type' => 'event'])) ?>">event</a>
    <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['type' => 'update'])) ?>">update</a>
    <span style="margin-left:12px;">subscribed only:</span>
    <?php if ($subscribedOnly): ?>
      <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_filter(['type' => $typeFilter, 'route_label' => $routeFilter])) ?>">off</a>
    <?php else: ?>
      <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['subscribed' => '1'])) ?>">on</a>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if ($events === []): ?>
      <p class="muted">알림이 없습니다.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>유형</th><th>제목</th><th>본문</th><th>발행일</th><th>Review</th></tr></thead>
        <tbody>
          <?php foreach ($events as $e):
            $docId = isset($e['ref_id']) ? (int)$e['ref_id'] : 0;
            $rl = isset($e['route_label']) ? trim((string)$e['route_label']) : '';
            $reviewUrl = ($docId > 0 && $rl !== '')
              ? $adminBase . '/route_review.php?source_doc_id=' . $docId . '&route_label=' . urlencode($rl) . '&quick_mode=1&show_advanced=0'
              : ($docId > 0 ? $adminBase . '/doc.php?id=' . $docId : '');
          ?>
            <tr>
              <td><?= h($e['event_type'] ?? '') ?></td>
              <td><?= h($e['title'] ?? '') ?></td>
              <td><?= h(mb_substr((string)($e['body'] ?? ''), 0, 80)) ?><?= mb_strlen((string)($e['body'] ?? '')) > 80 ? '…' : '' ?></td>
              <td><?= h($e['published_at'] ?? '') ?></td>
              <td><?= $reviewUrl !== '' ? '<a href="' . h($reviewUrl) . '" target="_blank" rel="noopener">Review</a>' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
