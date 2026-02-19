<?php
declare(strict_types=1);
/**
 * v1.8 U-ISSUES — 전체 이슈 목록 (필터/정렬)
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/alert/alert_event_type.php';
require_once __DIR__ . '/../../app/inc/alert/alert_delivery.php';

$userId = user_session_user_id();
$base = APP_BASE . '/user';
$pdo = pdo();

$filter = isset($_GET['filter']) ? trim((string)$_GET['filter']) : '';
$sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'created';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [" published_at IS NOT NULL"];
$params = [];
$eventTypes = filter_to_event_types($filter);
if ($eventTypes !== []) {
  $placeholders = [];
  foreach ($eventTypes as $i => $t) {
    $key = ':ft' . $i;
    $placeholders[] = $key;
    $params[$key] = $t;
  }
  $where[] = " event_type IN (" . implode(', ', $placeholders) . ")";
}

$orderBy = $sort === 'impact' ? " FIELD(event_type, 'strike', 'event', 'update'), created_at DESC" : " created_at DESC";

$sql = "SELECT id, event_type, title, body, route_label, published_at, created_at
  FROM app_alert_events
  WHERE " . implode(' AND ', $where) . "
  ORDER BY " . $orderBy . "
  LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $issues = [];
}

// v1.8: delivery 기록 (alerts.php와 동일)
if ($userId > 0) {
  foreach ($issues as $e) {
    try {
      record_alert_delivery((int)$e['id'], $userId, 'web', 'shown');
    } catch (Throwable $t) {
      // UNIQUE 미적용 등 — 무시
    }
  }
}

function issue_status(string $publishedAt): string {
  return strtotime($publishedAt) > strtotime('-7 days') ? 'Active' : 'Ended';
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>GILIME - 전체 이슈</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
    <nav class="nav g-topnav mb-3">
      <a class="nav-link" href="<?= $base ?>/home.php">홈</a>
      <a class="nav-link active" href="<?= $base ?>/issues.php">이슈</a>
      <a class="nav-link" href="<?= $base ?>/route_finder.php">길찾기</a>
      <a class="nav-link" href="<?= $base ?>/my_routes.php">마이노선</a>
    </nav>

    <div class="g-page-head mb-3">
      <h1>길라임</h1>
      <p class="helper mb-0">전체 이슈</p>
    </div>

    <div class="card g-card mb-3">
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
          <span class="small text-muted">필터:</span>
          <a href="<?= $base ?>/issues.php?filter=&sort=<?= h($sort) ?>" class="btn btn-sm <?= $filter === '' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">전체</a>
          <a href="<?= $base ?>/issues.php?filter=긴급&sort=<?= h($sort) ?>" class="btn btn-sm <?= $filter === '긴급' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">긴급</a>
          <a href="<?= $base ?>/issues.php?filter=운행중단&sort=<?= h($sort) ?>" class="btn btn-sm <?= $filter === '운행중단' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">운행중단</a>
          <a href="<?= $base ?>/issues.php?filter=행사&sort=<?= h($sort) ?>" class="btn btn-sm <?= $filter === '행사' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">행사</a>
          <a href="<?= $base ?>/issues.php?filter=공지&sort=<?= h($sort) ?>" class="btn btn-sm <?= $filter === '공지' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">공지</a>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="small text-muted">정렬:</span>
          <a href="<?= $base ?>/issues.php?filter=<?= urlencode($filter) ?>&sort=impact" class="btn btn-sm <?= $sort === 'impact' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">영향도순</a>
          <a href="<?= $base ?>/issues.php?filter=<?= urlencode($filter) ?>&sort=created" class="btn btn-sm <?= $sort === 'created' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">최신순</a>
        </div>
      </div>
    </div>

    <div class="card g-card">
      <div class="card-body">
        <?php if ($issues === []): ?>
          <p class="text-muted-g small mb-0">이슈가 없습니다.</p>
        <?php else: ?>
          <?php foreach ($issues as $issue): ?>
            <div class="border-bottom pb-3 mb-3 <?= $issue === end($issues) ? 'border-0 pb-0 mb-0' : '' ?>">
              <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                <strong><?= h($issue['title'] ?? '') ?></strong>
                <span class="text-muted-g small text-nowrap"><?= issue_status($issue['published_at'] ?? '') ?> <?= event_type_to_impact($issue['event_type'] ?? '') ?></span>
              </div>
              <p class="text-muted-g small mb-2">
                <?= $issue['route_label'] ? h($issue['route_label']) . ' · ' : '' ?>
                <?= h($issue['published_at'] ?? '') ?>
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <a href="<?= $base ?>/issue.php?id=<?= (int)$issue['id'] ?>" class="btn btn-outline-secondary btn-sm">이슈 보기</a>
                <a href="<?= $base ?>/route_finder.php?issue_id=<?= (int)$issue['id'] ?>" class="btn btn-gilaime-primary btn-sm">이슈 기반 길찾기</a>
              </div>
            </div>
          <?php endforeach; ?>
          <a href="<?= $base ?>/issues.php?filter=<?= urlencode($filter) ?>&sort=<?= h($sort) ?>&page=<?= $page + 1 ?>" class="btn btn-outline-secondary btn-sm mt-2">더보기</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
