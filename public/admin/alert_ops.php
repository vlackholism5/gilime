<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';
$userBase = APP_BASE . '/user';

// POST: v1.7-02 Publish action. v1.7-04: guard by target_user_cnt (0 = block)
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $publishEventId = isset($_POST['publish_event_id']) ? (int)$_POST['publish_event_id'] : 0;
  if ($publishEventId > 0) {
    $allowPublish = false;
    $stEv = $pdo->prepare("SELECT id, ref_type, ref_id, route_label, event_type FROM app_alert_events WHERE id = :id AND published_at IS NULL");
    $stEv->execute([':id' => $publishEventId]);
    $ev = $stEv->fetch(PDO::FETCH_ASSOC);
    if ($ev) {
      $refType = (string)($ev['ref_type'] ?? '');
      $refId = isset($ev['ref_id']) ? (int)$ev['ref_id'] : null;
      $routeLabel = isset($ev['route_label']) ? trim((string)$ev['route_label']) : null;
      if ($refType === 'route' && $refId !== null && $routeLabel !== null && $routeLabel !== '') {
        $targetId = $refId . '_' . $routeLabel;
        $eventType = trim((string)($ev['event_type'] ?? ''));
        $likePattern = '%' . $eventType . '%';
        $stCnt = $pdo->prepare("SELECT COUNT(DISTINCT s.user_id) AS c FROM app_subscriptions s WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND s.alert_type LIKE :atype");
        $stCnt->execute([':tid' => $targetId, ':atype' => $likePattern]);
        $targetUserCnt = (int)($stCnt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($targetUserCnt === 0) {
          header('Location: ' . $base . '/alert_ops.php?flash=blocked_no_targets&event_id=' . $publishEventId);
          exit;
        }
      }
      $allowPublish = true;
    }
    if ($allowPublish) {
      try {
        $stmt = $pdo->prepare("UPDATE app_alert_events SET published_at = NOW() WHERE id = :id AND published_at IS NULL");
        $stmt->execute([':id' => $publishEventId]);
        if ($stmt->rowCount() > 0) {
          error_log('OPS alert_published event_id=' . $publishEventId);
          $queuedCnt = 0;
          $refType = (string)($ev['ref_type'] ?? '');
          $refId = isset($ev['ref_id']) ? (int)$ev['ref_id'] : null;
          $routeLabel = isset($ev['route_label']) ? trim((string)$ev['route_label']) : null;
          if ($refType === 'route' && $refId !== null && $routeLabel !== null && $routeLabel !== '') {
            $targetId = $refId . '_' . $routeLabel;
            $eventType = trim((string)($ev['event_type'] ?? ''));
            $likePattern = '%' . $eventType . '%';
            $stUsers = $pdo->prepare("SELECT DISTINCT s.user_id FROM app_subscriptions s WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND s.alert_type LIKE :atype ORDER BY s.user_id LIMIT 1000");
            $stUsers->execute([':tid' => $targetId, ':atype' => $likePattern]);
            $userIdList = $stUsers->fetchAll(PDO::FETCH_COLUMN);
            $stIns = $pdo->prepare("INSERT IGNORE INTO app_alert_deliveries (alert_event_id, user_id, channel, status, sent_at, created_at) VALUES (:eid, :uid, 'web', 'pending', NULL, NOW())");
            foreach ($userIdList as $uid) {
              $uid = (int)$uid;
              if ($uid <= 0) continue;
              $stIns->execute([':eid' => $publishEventId, ':uid' => $uid]);
              $queuedCnt++;
            }
          }
          $flashParam = $queuedCnt > 0 ? 'published_with_queue&queued_cnt=' . $queuedCnt : 'published';
          header('Location: ' . $base . '/alert_ops.php?flash=' . $flashParam . '&event_id=' . $publishEventId);
          exit;
        }
      } catch (Throwable $e) {
        error_log('OPS alert_publish_failed: ' . $e->getMessage());
      }
    }
    header('Location: ' . $base . '/alert_ops.php?flash=failed&event_id=' . $publishEventId);
    exit;
  }

  // POST: 새 알림 생성 (ref_type=route). v1.6-06 + v1.7-02: draft(published_at NULL) or publish
  $eventType = isset($_POST['event_type']) ? trim((string)$_POST['event_type']) : '';
  $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
  $body = isset($_POST['body']) ? trim((string)$_POST['body']) : '';
  $refIdRaw = isset($_POST['ref_id']) ? trim((string)$_POST['ref_id']) : '';
  $refId = $refIdRaw !== '' ? (int)$refIdRaw : 0;
  $routeLabel = isset($_POST['route_label']) ? trim((string)$_POST['route_label']) : '';
  $publishedAtRaw = isset($_POST['published_at']) ? trim((string)$_POST['published_at']) : '';
  $publishNow = isset($_POST['publish_now']) && $_POST['publish_now'] === '1';
  $refType = 'route';

  $valid = $eventType !== '' && $title !== '' && $refId > 0 && $routeLabel !== '';
  $publishedAt = null;
  if ($valid) {
    if ($publishNow) {
      $publishedAt = date('Y-m-d H:i:s');
    } elseif ($publishedAtRaw !== '') {
      if (strtotime($publishedAtRaw) !== false) {
        $publishedAt = date('Y-m-d H:i:s', strtotime($publishedAtRaw));
      } else {
        $valid = false;
      }
    }
  }
  if ($valid) {
    $hashInput = implode('|', [$eventType, $title, $refType, (string)$refId, $routeLabel, $publishedAt ?? '']);
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
      $inserted = $stmt->rowCount() > 0;
      $eventId = null;
      if ($inserted) {
        $eventId = (int)$pdo->lastInsertId();
      } else {
        $stmtRow = $pdo->prepare("SELECT id FROM app_alert_events WHERE content_hash = :chash LIMIT 1");
        $stmtRow->execute([':chash' => $contentHash]);
        $r = $stmtRow->fetch(PDO::FETCH_ASSOC);
        if ($r) {
          $eventId = (int)$r['id'];
        }
      }
      $flash = $inserted ? 'created' : 'duplicate ignored';
      $q = 'flash=' . urlencode($flash);
      if ($eventId !== null) {
        $q .= '&event_id=' . $eventId;
      }
      header('Location: ' . $base . '/alert_ops.php?' . $q);
      exit;
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
$queuedCnt = isset($_GET['queued_cnt']) ? (int)$_GET['queued_cnt'] : null;
$focusEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

// Filters (GET). v1.7-02: draft_only, published_only
$filterType = isset($_GET['event_type']) && trim((string)$_GET['event_type']) !== '' ? trim((string)$_GET['event_type']) : null;
$filterRoute = isset($_GET['route_label']) && trim((string)$_GET['route_label']) !== '' ? trim((string)$_GET['route_label']) : null;
$filterFrom = isset($_GET['published_from']) && trim((string)$_GET['published_from']) !== '' ? trim((string)$_GET['published_from']) : null;
$filterTo = isset($_GET['published_to']) && trim((string)$_GET['published_to']) !== '' ? trim((string)$_GET['published_to']) : null;
$filterDraftOnly = isset($_GET['draft_only']) && $_GET['draft_only'] === '1';
$filterPublishedOnly = isset($_GET['published_only']) && $_GET['published_only'] === '1';

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
if ($filterDraftOnly) {
  $sql .= " AND published_at IS NULL";
}
if ($filterPublishedOnly) {
  $sql .= " AND published_at IS NOT NULL";
}
if ($filterFrom !== null) {
  $sql .= " AND published_at >= :pub_from";
  $params[':pub_from'] = $filterFrom;
}
if ($filterTo !== null) {
  $sql .= " AND published_at <= :pub_to";
  $params[':pub_to'] = $filterTo;
}
$sql .= " ORDER BY published_at IS NULL DESC, published_at DESC, id DESC LIMIT 200";
$stmt = $params === [] ? $pdo->query($sql) : $pdo->prepare($sql);
if ($params !== []) {
  $stmt->execute($params);
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// v1.7-03: Targeting Preview (read-only). event_id 있을 때만, route 이벤트면 count + list 20
$previewEvent = null;
$previewTargetCnt = 0;
$previewTargetList = [];
if ($focusEventId > 0) {
  $stEv = $pdo->prepare("SELECT id, event_type, title, ref_type, ref_id, route_label, published_at FROM app_alert_events WHERE id = :id");
  $stEv->execute([':id' => $focusEventId]);
  $previewEvent = $stEv->fetch(PDO::FETCH_ASSOC);
  if ($previewEvent && (string)($previewEvent['ref_type'] ?? '') === 'route' && isset($previewEvent['ref_id'], $previewEvent['route_label']) && $previewEvent['ref_id'] !== null && $previewEvent['route_label'] !== null) {
    $refId = (int)$previewEvent['ref_id'];
    $routeLabel = trim((string)$previewEvent['route_label']);
    $eventType = trim((string)($previewEvent['event_type'] ?? ''));
    $targetId = $refId . '_' . $routeLabel;
    $likePattern = '%' . $eventType . '%';
    $stCnt = $pdo->prepare("
      SELECT COUNT(DISTINCT s.user_id) AS target_user_cnt
      FROM app_subscriptions s
      WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND s.alert_type LIKE :atype
    ");
    $stCnt->execute([':tid' => $targetId, ':atype' => $likePattern]);
    $previewTargetCnt = (int)($stCnt->fetch(PDO::FETCH_ASSOC)['target_user_cnt'] ?? 0);
    $stList = $pdo->prepare("
      SELECT u.id AS user_id, u.display_name, u.email, s.target_id AS subscription_target_id, s.alert_type
      FROM app_subscriptions s
      JOIN app_users u ON u.id = s.user_id
      WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND s.alert_type LIKE :atype
      ORDER BY s.user_id
      LIMIT 20
    ");
    $stList->execute([':tid' => $targetId, ':atype' => $likePattern]);
    $previewTargetList = $stList->fetchAll(PDO::FETCH_ASSOC);
  }
}

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
    tr.highlight{background:#fef3cd;}
    .badge{display:inline-block;padding:2px 6px;font-size:11px;border-radius:4px;}
    .badge-draft{background:#f0f0f0;color:#555;}
    .badge-published{background:#e0f0e0;color:#166;}
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
        <label>published_at</label>
        <input type="datetime-local" name="published_at" /> <span class="muted">(비우면 초안)</span>
        <label><input type="checkbox" name="publish_now" value="1" /> Publish now</label>
      </div>
      <button type="submit">Create</button>
    </form>
  </div>

  <?php if ($flash !== null): ?>
  <p class="muted"><?= $flash === 'created' ? 'created' : ($flash === 'duplicate ignored' ? 'duplicate ignored' : ($flash === 'published' ? 'published' : ($flash === 'published_with_queue' ? 'published (queued ' . (int)$queuedCnt . ')' : ($flash === 'blocked_no_targets' ? 'blocked_no_targets' : 'failed')))) ?></p>
  <?php endif; ?>

  <?php if ($focusEventId > 0 && $previewEvent !== null): ?>
  <!-- v1.7-03: Targeting Preview (read-only) -->
  <div class="card" style="margin-bottom:20px; border:1px solid #ddd;">
    <h3>Targeting Preview</h3>
    <p class="muted">event_id=<?= (int)$focusEventId ?> · event_type=<?= h($previewEvent['event_type'] ?? '') ?> · ref_id=<?= (int)($previewEvent['ref_id'] ?? 0) ?> · route_label=<?= h($previewEvent['route_label'] ?? '') ?> · published_at=<?= h($previewEvent['published_at'] ?? '') ?></p>
    <p><strong>Target users: <?= (int)$previewTargetCnt ?></strong></p>
    <?php if ($previewTargetList !== []): ?>
    <table>
      <thead><tr><th>user_id</th><th>display_name</th><th>email</th><th>subscription_target_id</th><th>alert_type</th></tr></thead>
      <tbody>
        <?php foreach ($previewTargetList as $u): ?>
        <tr>
          <td><?= (int)($u['user_id'] ?? 0) ?></td>
          <td><?= h($u['display_name'] ?? '') ?></td>
          <td><?= h($u['email'] ?? '') ?></td>
          <td><?= h($u['subscription_target_id'] ?? '') ?></td>
          <td><?= h($u['alert_type'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- 필터. v1.7-02: draft_only, published_only -->
  <p class="muted">
    <a href="<?= h($base) ?>/alert_ops.php">전체</a>
    <a href="<?= h($base) ?>/alert_ops.php?draft_only=1">초안만</a>
    <a href="<?= h($base) ?>/alert_ops.php?published_only=1">발행만</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=strike">strike</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=event">event</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=update">update</a>
    | route_label: <form method="get" action="<?= h($base) ?>/alert_ops.php" style="display:inline;">
      <input type="hidden" name="event_type" value="<?= h($filterType ?? '') ?>" />
      <input type="hidden" name="draft_only" value="<?= $filterDraftOnly ? '1' : '' ?>" />
      <input type="hidden" name="published_only" value="<?= $filterPublishedOnly ? '1' : '' ?>" />
      <input type="text" name="route_label" value="<?= h($filterRoute ?? '') ?>" placeholder="R1" size="6" />
      <input type="text" name="published_from" value="<?= h($filterFrom ?? '') ?>" placeholder="from" size="12" />
      <input type="text" name="published_to" value="<?= h($filterTo ?? '') ?>" placeholder="to" size="12" />
      <button type="submit">Filter</button>
    </form>
  </p>

  <table>
    <thead>
      <tr>
        <th>id</th><th>event_type</th><th>title</th><th>ref_type</th><th>ref_id</th><th>route_label</th><th>published_at</th><th>State</th><th>created_at</th><th>Action</th><th>Links</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($events as $e):
        $eid = (int)($e['id'] ?? 0);
        $refId = (int)($e['ref_id'] ?? 0);
        $rl = trim((string)($e['route_label'] ?? ''));
        $publishedAt = $e['published_at'] ?? null;
        $isDraft = ($publishedAt === null || $publishedAt === '');
        $userAlertsUrl = $rl !== '' ? $userBase . '/alerts.php?route_label=' . urlencode($rl) : '';
        $reviewUrl = ($refId > 0 && $rl !== '') ? $base . '/route_review.php?source_doc_id=' . $refId . '&route_label=' . urlencode($rl) . '&quick_mode=1&show_advanced=0' : ($refId > 0 ? $base . '/doc.php?id=' . $refId : '');
        $highlight = ($focusEventId !== null && $focusEventId === $eid);
      ?>
        <tr<?= $highlight ? ' id="event-' . $eid . '" class="highlight"' : '' ?>>
          <td><?= (int)$e['id'] ?></td>
          <td><?= h($e['event_type'] ?? '') ?></td>
          <td><?= h($e['title'] ?? '') ?></td>
          <td><?= h($e['ref_type'] ?? '') ?></td>
          <td><?= $refId ?></td>
          <td><?= h($rl) ?></td>
          <td><?= h($publishedAt ?? '') ?></td>
          <td><span class="badge badge-<?= $isDraft ? 'draft' : 'published' ?>"><?= $isDraft ? 'draft' : 'published' ?></span></td>
          <td><?= h($e['created_at'] ?? '') ?></td>
          <td>
            <?php if ($isDraft): ?>
            <form method="post" action="<?= h($base) ?>/alert_ops.php" style="display:inline;">
              <input type="hidden" name="publish_event_id" value="<?= $eid ?>" />
              <button type="submit">Publish</button>
            </form>
            <?php else: ?>—<?php endif; ?>
          </td>
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
