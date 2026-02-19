<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/alert/alert_delivery.php';

$pdo = pdo();
$userId = user_session_user_id();

$typeFilter = isset($_GET['type']) && trim((string)$_GET['type']) !== '' ? trim((string)$_GET['type']) : null;
$routeFilter = isset($_GET['route_label']) && trim((string)$_GET['route_label']) !== '' ? trim((string)$_GET['route_label']) : null;
$subscribedOnly = isset($_GET['subscribed']) && $_GET['subscribed'] === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

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

// v1.7-02: user sees only published alerts (drafts never shown)
$sql = "
  SELECT id, event_type, title, body, ref_type, ref_id" . ($hasRouteLabelColumn ? ", route_label" : "") . ", published_at, created_at
  FROM app_alert_events
";
$params = [];
$where = [" published_at IS NOT NULL"];
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
$sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$hasRouteLabelColumn) {
  foreach ($events as &$row) {
    $row['route_label'] = null;
  }
  unset($row);
}

// v1.4-06 / v1.5-01 / v1.6-08: delivery only for rendered list; user_id 미확정이면 기록 안 함 (확인 필요 시 docs 참고)
if ($userId > 0) {
  foreach ($events as $e) {
    try {
      record_alert_delivery((int)$e['id'], $userId, 'web', 'shown');
      error_log(sprintf('OPS delivery_written user_id=%d alert_event_id=%d channel=web status=shown', $userId, (int)$e['id']));
    } catch (Throwable $t) {
      // UNIQUE not yet applied or missing table — skip; do not break UX
    }
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
  'page' => $page > 1 ? (string)$page : null,
]);
$qPrev = array_merge($q, ['page' => (string)max(1, $page - 1)]);
$qNext = array_merge($q, ['page' => (string)($page + 1)]);
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>GILIME - 구독 노선 알림</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <nav class="nav g-topnav mb-3">
    <a class="nav-link" href="<?= $base ?>/home.php">홈</a>
    <a class="nav-link" href="<?= $base ?>/issues.php">이슈</a>
    <a class="nav-link" href="<?= $base ?>/route_finder.php">길찾기</a>
    <a class="nav-link active" href="<?= $base ?>/my_routes.php">마이노선</a>
  </nav>

  <div class="g-page-head mb-3">
    <h1>구독 노선 알림</h1>
    <p class="helper mb-0">구독한 노선의 변경·업데이트 알림을 확인합니다.</p>
    <a href="<?= $base ?>/my_routes.php" class="btn btn-outline-secondary btn-sm mt-1">마이노선으로</a>
  </div>
  <details class="kbd-help mb-3">
    <summary>단축키 안내</summary>
    <div class="body">/ : 검색 입력으로 이동 · Esc : 닫기 · Ctrl+Enter : 주요 폼 제출(지원 페이지)</div>
  </details>
  <div class="card g-card mb-3">
    <div class="card-body py-3">
      <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <span class="small text-muted">유형:</span>
        <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_filter(['route_label' => $routeFilter, 'subscribed' => $subscribedOnly ? '1' : null])) ?>" class="btn btn-sm <?= $typeFilter === null ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">전체</a>
        <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['type' => 'strike'])) ?>" class="btn btn-sm <?= $typeFilter === 'strike' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">파업</a>
        <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['type' => 'event'])) ?>" class="btn btn-sm <?= $typeFilter === 'event' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">이벤트</a>
        <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['type' => 'update'])) ?>" class="btn btn-sm <?= $typeFilter === 'update' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">업데이트</a>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted">구독 노선만:</span>
        <?php if ($subscribedOnly): ?>
          <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_filter(['type' => $typeFilter, 'route_label' => $routeFilter])) ?>" class="btn btn-sm btn-gilaime-primary">해제</a>
        <?php else: ?>
          <a href="<?= $base ?>/alerts.php?<?= http_build_query(array_merge($q, ['subscribed' => '1'])) ?>" class="btn btn-sm btn-outline-secondary">켜기</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card g-card">
    <div class="card-body">
    <?php if ($events === []): ?>
      <p class="text-muted-g small mb-0">알림이 없습니다.</p>
    <?php else: ?>
      <div class="table-responsive">
      <table class="table table-hover align-middle g-table g-table-dense mb-0">
        <thead><tr><th class="g-nowrap">유형</th><th>제목</th><th>본문</th><th class="g-nowrap">발행일</th><th class="g-nowrap">검수 링크</th></tr></thead>
        <tbody>
          <?php foreach ($events as $e):
            $docId = isset($e['ref_id']) ? (int)$e['ref_id'] : 0;
            $rl = isset($e['route_label']) ? trim((string)$e['route_label']) : '';
            $reviewUrl = ($docId > 0 && $rl !== '')
              ? $adminBase . '/route_review.php?source_doc_id=' . $docId . '&route_label=' . urlencode($rl) . '&quick_mode=1&show_advanced=0'
              : ($docId > 0 ? $adminBase . '/doc.php?id=' . $docId : '');
          ?>
            <tr>
              <td class="g-nowrap"><?= h($e['event_type'] ?? '') ?></td>
              <td><?= h($e['title'] ?? '') ?></td>
              <td><?= h(mb_substr((string)($e['body'] ?? ''), 0, 80)) ?><?= mb_strlen((string)($e['body'] ?? '')) > 80 ? '…' : '' ?></td>
              <td class="g-nowrap"><?= h($e['published_at'] ?? '') ?></td>
              <td class="g-nowrap"><?= $reviewUrl !== '' ? '<a class="btn btn-outline-secondary btn-sm" href="' . h($reviewUrl) . '" target="_blank" rel="noopener">검수 보기</a>' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
    </div>
  </div>
  <?php if ($events !== []): ?>
  <p class="text-muted-g small mt-3 mb-0">
    페이지 <?= (int)$page ?> (페이지당 <?= (int)$perPage ?>건)
    <?php if ($page > 1): ?>
      <a href="<?= $base ?>/alerts.php?<?= http_build_query($qPrev) ?>">이전</a>
    <?php endif; ?>
    <?php if (count($events) >= $perPage): ?>
      <a href="<?= $base ?>/alerts.php?<?= http_build_query($qNext) ?>">다음</a>
    <?php endif; ?>
  </p>
  <?php endif; ?>
  <script src="<?= APP_BASE ?>/public/assets/js/gilaime_ui.js"></script>
  </main>
</body>
</html>
