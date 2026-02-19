<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
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
  <title>관리자 - 알림 이벤트 감사</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '알림 감사', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2 class="h3">알림 이벤트 감사 (전달 이력)</h2>
    <p class="helper mb-0">읽기 전용. 최근 200건.</p>
  </div>
  <div class="d-flex gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h($base) ?>/alert_ops.php">알림 운영</a>
  </div>
  <?php
  $cnt = (int)($summary['delivery_cnt'] ?? 0);
  $minC = $summary['min_created'] ?? null;
  $maxC = $summary['max_created'] ?? null;
  ?>
  <p class="text-muted-g small mb-3">요약: 전달 건수=<strong><?= $cnt ?></strong><?= $minC !== null && $minC !== '' ? ', 최초 생성=' . h($minC) : '' ?><?= $maxC !== null && $maxC !== '' ? ', 최신 생성=' . h($maxC) : '' ?>.</p>

  <div class="card g-card mb-3">
    <div class="card-body">
      <form method="get" action="<?= h($base) ?>/alert_event_audit.php" class="g-form-inline">
        <label class="small text-muted-g">이벤트 ID</label>
        <input class="form-control form-control-sm w-auto" type="number" name="event_id" value="<?= $filterEventId !== null ? (int)$filterEventId : '' ?>" min="1" placeholder="알림 이벤트 ID" />
        <label class="small text-muted-g">사용자 ID</label>
        <input class="form-control form-control-sm w-auto" type="number" name="user_id" value="<?= $filterUserId !== null ? (int)$filterUserId : '' ?>" min="1" />
        <label class="small text-muted-g">채널</label>
        <input class="form-control form-control-sm w-auto" type="text" name="channel" value="<?= h($filterChannel ?? '') ?>" size="8" placeholder="웹(web)" />
        <label class="small text-muted-g">상태</label>
        <input class="form-control form-control-sm w-auto" type="text" name="status" value="<?= h($filterStatus ?? '') ?>" size="8" placeholder="표시됨(shown)" />
        <label class="small text-muted-g">조회 시작 시각(created_at 이상)</label>
        <input class="form-control form-control-sm w-auto" type="datetime-local" name="since" value="<?= h($filterSince ?? '') ?>" />
        <button class="btn btn-gilaime-primary btn-sm" type="submit">필터 적용</button>
      </form>
    </div>
  </div>

  <div class="card g-card">
    <div class="card-body">
    <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th class="g-nowrap">ID</th>
        <th class="g-nowrap">이벤트 ID</th>
        <th class="g-nowrap">사용자 ID</th>
        <th class="g-nowrap">채널</th>
        <th class="g-nowrap">상태</th>
        <th class="g-nowrap">전달 시각</th>
        <th class="g-nowrap">생성 시각</th>
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
    </div>
    </div>
  </div>
  </main>
</body>
</html>
