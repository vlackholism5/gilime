<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config.php';
require_once __DIR__ . '/../../app/inc/user_session.php';

$pdo = pdo();
$userId = user_session_user_id();
$subCount = user_session_subscription_count();

$subscribedRoutes = [];
$st = $pdo->prepare("SELECT target_id FROM app_subscriptions WHERE user_id = :uid AND target_type = 'route' AND is_active = 1 ORDER BY updated_at DESC LIMIT 10");
$st->execute([':uid' => $userId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $tid = (string)$row['target_id'];
  $parts = explode('_', $tid, 2);
  $subscribedRoutes[] = ['doc_id' => $parts[0] ?? '', 'route_label' => $parts[1] ?? $tid];
}

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
  <title>GILIME - 홈</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <nav class="nav g-topnav mb-3">
    <a class="nav-link" href="<?= $base ?>/home.php">홈</a>
    <a class="nav-link" href="<?= $base ?>/routes.php">노선</a>
    <a class="nav-link" href="<?= $base ?>/alerts.php">알림</a>
  </nav>
  <div class="g-page-head mb-3">
    <h1>길라임</h1>
    <p class="helper mb-0">구독과 최근 알림을 한 번에 확인합니다.</p>
  </div>
  <p class="text-muted-g small mb-3">내 구독 수: <?= (int)$subCount ?></p>
  <?php if ($subscribedRoutes !== []): ?>
  <div class="card g-card mb-3">
    <div class="card-body">
    <h2 class="h4">구독 노선 (최대 10)</h2>
    <p class="text-muted-g small mb-0">
      <?php foreach ($subscribedRoutes as $sr): ?>
        <a href="<?= $base ?>/alerts.php?route_label=<?= urlencode($sr['route_label']) ?>"><?= h($sr['route_label']) ?></a>
        (doc <?= h($sr['doc_id']) ?>)
        <?= $sr !== end($subscribedRoutes) ? ' · ' : '' ?>
      <?php endforeach; ?>
    </p>
    </div>
  </div>
  <?php endif; ?>
  <div class="card g-card">
    <div class="card-body">
    <h2 class="h4">최근 알림 (5)</h2>
    <?php if ($alerts === []): ?>
      <p class="text-muted-g small mb-0">알림이 없습니다.</p>
    <?php else: ?>
      <div class="table-responsive">
      <table class="table table-hover align-middle g-table mb-0">
        <thead><tr><th>유형</th><th>제목</th><th>발행 시각</th></tr></thead>
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
      </div>
    <?php endif; ?>
    </div>
  </div>
  </main>
</body>
</html>
