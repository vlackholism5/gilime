<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';
$userBase = APP_BASE . '/user';

// v1.7-06: current user role (for approver gate + UI)
$currentUserId = current_user_id();
$currentUserRole = 'user';
if ($currentUserId > 0) {
  try {
    $stRole = $pdo->prepare("SELECT role FROM app_users WHERE id = :uid");
    $stRole->execute([':uid' => $currentUserId]);
    $row = $stRole->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['role']) && $row['role'] !== '') {
      $currentUserRole = trim((string)$row['role']);
    }
  } catch (Throwable $e) {
    // role column may not exist before v1.7-06 schema
  }
}

// POST: v1.7-02 Publish. v1.7-04: guard target_user_cnt. v1.7-05: queue. v1.7-06: approver only + approval log
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $publishEventId = isset($_POST['publish_event_id']) ? (int)$_POST['publish_event_id'] : 0;
  if ($publishEventId > 0) {
    $stApproval = null;
    try {
      $stApproval = $pdo->prepare("INSERT INTO app_alert_approvals (alert_event_id, actor_user_id, action, note) VALUES (:eid, :uid, :action, :note)");
    } catch (Throwable $e) {}
    if ($currentUserRole !== 'approver') {
      try {
        if ($stApproval) $stApproval->execute([':eid' => $publishEventId, ':uid' => $currentUserId, ':action' => 'publish_blocked', ':note' => 'not_approver']);
      } catch (Throwable $e) {}
      header('Location: ' . $base . '/alert_ops.php?flash=blocked_not_approver&event_id=' . $publishEventId);
      exit;
    }
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
        $stCnt = $pdo->prepare("SELECT COUNT(DISTINCT s.user_id) AS c FROM app_subscriptions s WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND FIND_IN_SET(:etype, REPLACE(s.alert_type, ' ', '')) > 0");
        $stCnt->execute([':tid' => $targetId, ':etype' => $eventType]);
        $targetUserCnt = (int)($stCnt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($targetUserCnt === 0) {
          try {
            if ($stApproval) $stApproval->execute([':eid' => $publishEventId, ':uid' => $currentUserId, ':action' => 'publish_blocked', ':note' => 'no_targets']);
          } catch (Throwable $e) {}
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
            $stUsers = $pdo->prepare("SELECT DISTINCT s.user_id FROM app_subscriptions s WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND FIND_IN_SET(:etype, REPLACE(s.alert_type, ' ', '')) > 0 ORDER BY s.user_id LIMIT 1000");
            $stUsers->execute([':tid' => $targetId, ':etype' => $eventType]);
            $userIdList = $stUsers->fetchAll(PDO::FETCH_COLUMN);
            $stIns = $pdo->prepare("INSERT IGNORE INTO app_alert_deliveries (alert_event_id, user_id, channel, status, sent_at, created_at) VALUES (:eid, :uid, 'web', 'pending', NULL, NOW())");
            foreach ($userIdList as $uid) {
              $uid = (int)$uid;
              if ($uid <= 0) continue;
              $stIns->execute([':eid' => $publishEventId, ':uid' => $uid]);
              $queuedCnt++;
            }
          }
          try {
            if ($stApproval) $stApproval->execute([':eid' => $publishEventId, ':uid' => $currentUserId, ':action' => 'publish_success', ':note' => null]);
          } catch (Throwable $e) {}
          $flashParam = $queuedCnt > 0 ? 'published_with_queue&queued_cnt=' . $queuedCnt : 'published';
          header('Location: ' . $base . '/alert_ops.php?flash=' . $flashParam . '&event_id=' . $publishEventId);
          exit;
        }
      } catch (Throwable $e) {
        error_log('OPS alert_publish_failed: ' . $e->getMessage());
      }
    }
    try {
      if ($stApproval) $stApproval->execute([':eid' => $publishEventId, ':uid' => $currentUserId, ':action' => 'publish_failed', ':note' => null]);
    } catch (Throwable $e) {}
    header('Location: ' . $base . '/alert_ops.php?flash=failed&event_id=' . $publishEventId);
    exit;
  }

  // POST: 새 알림 생성 (ref_type=route). v1.6-06 + v1.7-02: draft(published_at NULL) or publish. P0: 입력 길이 제한(XSS/레이아웃).
  $eventType = isset($_POST['event_type']) ? trim((string)$_POST['event_type']) : '';
  $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
  $title = $title !== '' ? mb_substr($title, 0, 255) : '';
  $body = isset($_POST['body']) ? trim((string)$_POST['body']) : '';
  $body = $body !== '' ? mb_substr($body, 0, 2000) : '';
  $refIdRaw = isset($_POST['ref_id']) ? trim((string)$_POST['ref_id']) : '';
  $refId = $refIdRaw !== '' ? (int)$refIdRaw : 0;
  $routeLabelRaw = isset($_POST['route_label']) ? trim((string)$_POST['route_label']) : '';
  $routeLabel = $routeLabelRaw !== '' ? mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $routeLabelRaw), 0, 64) : '';
  $publishedAtRaw = isset($_POST['published_at']) ? trim((string)$_POST['published_at']) : '';
  $publishNow = isset($_POST['publish_now']) && $_POST['publish_now'] === '1';
  $refType = 'route';

  $valid = $eventType !== '' && $title !== '' && $refId > 0 && $refId <= 999999999 && $routeLabel !== '';
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
    $stCnt = $pdo->prepare("
      SELECT COUNT(DISTINCT s.user_id) AS target_user_cnt
      FROM app_subscriptions s
      WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND FIND_IN_SET(:etype, REPLACE(s.alert_type, ' ', '')) > 0
    ");
    $stCnt->execute([':tid' => $targetId, ':etype' => $eventType]);
    $previewTargetCnt = (int)($stCnt->fetch(PDO::FETCH_ASSOC)['target_user_cnt'] ?? 0);
    $stList = $pdo->prepare("
      SELECT u.id AS user_id, u.display_name, u.email, s.target_id AS subscription_target_id, s.alert_type
      FROM app_subscriptions s
      JOIN app_users u ON u.id = s.user_id
      WHERE s.is_active = 1 AND s.target_type = 'route' AND s.target_id = :tid AND FIND_IN_SET(:etype, REPLACE(s.alert_type, ' ', '')) > 0
      ORDER BY s.user_id
      LIMIT 20
    ");
    $stList->execute([':tid' => $targetId, ':etype' => $eventType]);
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
  <title>관리자 - 알림 운영</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '알림 운영', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2 class="h3">알림 운영</h2>
    <p class="helper mb-0">초안 생성, 대상 미리보기, 발행(Publish)까지 한 화면에서 처리합니다. <span class="text-muted-g">권한: <?= h($currentUserRole) ?></span></p>
  </div>
  <div class="d-flex gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h($base) ?>/alert_event_audit.php">알림 감사</a>
  </div>
  <details class="kbd-help mb-3">
    <summary>단축키 안내</summary>
    <div class="body">/ : 검색 입력으로 이동 · Esc : 닫기 · Ctrl+Enter : 주요 폼 제출(지원 페이지)</div>
  </details>

  <!-- 새 알림 작성 -->
  <div class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5 mb-3">새 알림 작성</h3>
    <form method="post" action="<?= h($base) ?>/alert_ops.php">
      <input type="hidden" name="ref_type" value="route" />
      <div class="g-form-inline mb-2">
        <label>event_type</label>
        <select class="form-select form-select-sm w-auto" name="event_type" required>
          <option value="strike">strike</option>
          <option value="event">event</option>
          <option value="update">update</option>
          <option value="e2e_test">e2e_test</option>
        </select>
        <label>title</label>
        <input class="form-control form-control-sm" type="text" name="title" required maxlength="255" size="40" />
        <label>본문(선택)</label>
        <input class="form-control form-control-sm" type="text" name="body" maxlength="500" size="30" />
      </div>
      <div class="g-form-inline mb-3">
        <label>ref_id</label>
        <input class="form-control form-control-sm w-auto" type="number" name="ref_id" required min="1" value="1" />
        <label>route_label</label>
        <input class="form-control form-control-sm w-auto" type="text" name="route_label" required maxlength="64" value="R1" />
        <label>published_at</label>
        <input class="form-control form-control-sm w-auto" type="datetime-local" name="published_at" /> <span class="text-muted-g small">(비우면 초안)</span>
        <label><input type="checkbox" name="publish_now" value="1" /> 즉시 발행(Publish)</label>
      </div>
      <button class="btn btn-gilaime-primary" type="submit">생성(Create)</button>
    </form>
    </div>
  </div>

  <?php if ($flash !== null): ?>
  <p class="text-muted-g small"><?= $flash === 'created' ? '생성 완료' : ($flash === 'duplicate ignored' ? '중복: 기존 항목이 있어 건너뜀' : ($flash === 'published' ? '발행 완료' : ($flash === 'published_with_queue' ? '발행 완료: 대상 ' . (int)$queuedCnt . '명 큐 적재' : ($flash === 'blocked_no_targets' ? '차단: 대상 사용자가 없습니다(구독 조건 불일치)' : ($flash === 'blocked_not_approver' ? '차단: 승인 권한이 없습니다' : '실패: 처리 중 오류가 발생했습니다'))))) ?></p>
  <?php endif; ?>

  <?php if ($focusEventId > 0 && $previewEvent !== null): ?>
  <!-- v1.7-03: Targeting Preview (read-only) -->
  <div class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5">대상 미리보기 (Targeting Preview)</h3>
    <p class="text-muted-g small">event_id=<?= (int)$focusEventId ?> · event_type=<?= h($previewEvent['event_type'] ?? '') ?> · ref_id=<?= (int)($previewEvent['ref_id'] ?? 0) ?> · route_label=<?= h($previewEvent['route_label'] ?? '') ?> · published_at=<?= h($previewEvent['published_at'] ?? '') ?></p>
    <p><strong>대상 사용자: <?= (int)$previewTargetCnt ?></strong></p>
    <?php if ($previewTargetList !== []): ?>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table mb-0">
      <thead><tr><th class="mono">user_id</th><th>이름</th><th>이메일</th><th class="mono">subscription_target_id</th><th>alert_type</th></tr></thead>
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
    </div>
    <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- 필터. v1.7-02: draft_only, published_only -->
  <div class="card g-card mb-3">
  <div class="card-body py-3">
  <div class="g-search-tab mb-2">
    <?php $noFilter = !$filterDraftOnly && !$filterPublishedOnly && ($filterType ?? '') === '' && $filterRoute === null && $filterFrom === null && $filterTo === null; ?>
    <a href="<?= h($base) ?>/alert_ops.php" class="btn btn-sm <?= $noFilter ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">전체</a>
    <a href="<?= h($base) ?>/alert_ops.php?draft_only=1" class="btn btn-sm <?= $filterDraftOnly ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">초안만</a>
    <a href="<?= h($base) ?>/alert_ops.php?published_only=1" class="btn btn-sm <?= $filterPublishedOnly ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">발행만</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=strike<?= $filterDraftOnly ? '&draft_only=1' : '' ?><?= $filterPublishedOnly ? '&published_only=1' : '' ?>" class="btn btn-sm <?= ($filterType ?? '') === 'strike' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">strike</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=event<?= $filterDraftOnly ? '&draft_only=1' : '' ?><?= $filterPublishedOnly ? '&published_only=1' : '' ?>" class="btn btn-sm <?= ($filterType ?? '') === 'event' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">event</a>
    <a href="<?= h($base) ?>/alert_ops.php?event_type=update<?= $filterDraftOnly ? '&draft_only=1' : '' ?><?= $filterPublishedOnly ? '&published_only=1' : '' ?>" class="btn btn-sm <?= ($filterType ?? '') === 'update' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">update</a>
  </div>
  <form method="get" action="<?= h($base) ?>/alert_ops.php" class="g-form-inline">
    <input type="hidden" name="event_type" value="<?= h($filterType ?? '') ?>" />
    <input type="hidden" name="draft_only" value="<?= $filterDraftOnly ? '1' : '' ?>" />
    <input type="hidden" name="published_only" value="<?= $filterPublishedOnly ? '1' : '' ?>" />
    <label class="small text-muted-g">route_label</label>
    <input class="form-control form-control-sm w-auto" type="text" name="route_label" value="<?= h($filterRoute ?? '') ?>" placeholder="R1" size="6" />
    <label class="small text-muted-g">from</label>
    <input class="form-control form-control-sm w-auto" type="text" name="published_from" value="<?= h($filterFrom ?? '') ?>" placeholder="YYYY-MM-DD" size="12" />
    <label class="small text-muted-g">to</label>
    <input class="form-control form-control-sm w-auto" type="text" name="published_to" value="<?= h($filterTo ?? '') ?>" placeholder="YYYY-MM-DD" size="12" />
    <button class="btn btn-outline-secondary btn-sm" type="submit">필터 적용</button>
  </form>
  </div>
  </div>

  <div class="card g-card">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th class="mono g-nowrap">id</th><th class="g-nowrap">event_type</th><th>title</th><th class="g-nowrap">ref_type</th><th class="mono g-nowrap">ref_id</th><th class="g-nowrap">route_label</th><th class="g-nowrap">published_at</th><th class="g-nowrap">상태</th><th class="g-nowrap">created_at</th><th class="g-nowrap">액션</th><th class="g-nowrap">링크</th>
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
          <td class="g-nowrap"><?= (int)$e['id'] ?></td>
          <td class="g-nowrap"><?= h($e['event_type'] ?? '') ?></td>
          <td><?= h($e['title'] ?? '') ?></td>
          <td><?= h($e['ref_type'] ?? '') ?></td>
          <td class="g-nowrap"><?= $refId ?></td>
          <td class="g-nowrap"><?= h($rl) ?></td>
          <td class="g-nowrap"><?= h($publishedAt ?? '') ?></td>
          <td><span class="badge badge-<?= $isDraft ? 'draft' : 'published' ?>"><?= $isDraft ? '초안' : '발행' ?></span></td>
          <td class="g-nowrap"><?= h($e['created_at'] ?? '') ?></td>
          <td>
            <?php if ($isDraft): ?>
            <form method="post" action="<?= h($base) ?>/alert_ops.php" class="d-inline">
              <input type="hidden" name="publish_event_id" value="<?= $eid ?>" />
              <button class="btn btn-gilaime-primary btn-sm" type="submit">발행(Publish)</button>
            </form>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="g-nowrap">
            <?php if ($userAlertsUrl !== ''): ?><a href="<?= h($userAlertsUrl) ?>" target="_blank" rel="noopener">사용자 알림</a><?php endif; ?>
            <?php if ($reviewUrl !== ''): ?> <a href="<?= h($reviewUrl) ?>">관리자 검수</a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>
  <p class="text-muted-g small mt-3">최근 200건. Pagination은 v1.6-06(확인 필요).</p>
  </main>
  <?php render_admin_tutorial_modal(); ?>
</body>
</html>
