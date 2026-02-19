<?php
declare(strict_types=1);
/**
 * v1.8 마이노선 — 구독·알림 통합 랜딩
 * 노선 구독/해제 현황 + 구독 노선 알림
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';

$pdo = pdo();
$userId = user_session_user_id();
$base = APP_BASE . '/user';

$subscribedRoutes = [];
$st = $pdo->prepare("SELECT target_id FROM app_subscriptions WHERE user_id = :uid AND target_type = 'route' AND is_active = 1 ORDER BY updated_at DESC LIMIT 3");
$st->execute([':uid' => $userId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $parts = explode('_', (string)$row['target_id'], 2);
  $subscribedRoutes[] = ['doc_id' => $parts[0] ?? '', 'route_label' => $parts[1] ?? $row['target_id']];
}
$st = $pdo->prepare("SELECT COUNT(*) FROM app_subscriptions WHERE user_id = :uid AND target_type = 'route' AND is_active = 1");
$st->execute([':uid' => $userId]);
$subCount = (int)$st->fetchColumn();

$recentAlerts = [];
try {
  $hasRouteLabel = (bool) array_filter($pdo->query("SHOW COLUMNS FROM app_alert_events LIKE 'route_label'")->fetchAll());
  if ($hasRouteLabel) {
    $st = $pdo->prepare("
      SELECT e.id, e.event_type, e.title, e.published_at, e.route_label
      FROM app_alert_events e
      WHERE e.published_at IS NOT NULL AND e.ref_id IS NOT NULL AND e.route_label IS NOT NULL
        AND EXISTS (
          SELECT 1 FROM app_subscriptions s
          WHERE s.user_id = :uid AND s.is_active = 1 AND s.target_type = 'route'
            AND s.target_id = CONCAT(e.ref_id, '_', e.route_label)
        )
      ORDER BY e.published_at DESC
      LIMIT 3
    ");
    $st->execute([':uid' => $userId]);
    $recentAlerts = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $recentAlerts = [];
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
  <title>GILIME - 마이노선</title>
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
      <h1>마이노선</h1>
      <p class="helper mb-0">구독 노선 관리와 알림을 한 곳에서 확인합니다.</p>
    </div>

    <div class="card g-card mb-3">
      <div class="card-body">
        <h2 class="h5 mb-2">노선 구독/해제</h2>
        <p class="text-muted-g small mb-2">알림을 받을 노선을 구독·해제할 수 있습니다. 현재 구독 <?= $subCount ?>건</p>
        <?php if ($subscribedRoutes === []): ?>
          <p class="text-muted-g small mb-2">구독 중인 노선이 없습니다.</p>
        <?php else: ?>
          <div class="table-responsive mb-2">
            <table class="table table-hover align-middle g-table g-table-dense mb-0">
              <thead><tr><th class="mono">문서 ID</th><th>노선</th><th>구독</th></tr></thead>
              <tbody>
                <?php foreach ($subscribedRoutes as $sr): ?>
                  <tr>
                    <td class="mono"><?= (int)$sr['doc_id'] ?></td>
                    <td>
                      <a href="<?= $base ?>/alerts.php?route_label=<?= urlencode($sr['route_label']) ?>"><?= h($sr['route_label']) ?></a>
                      <a href="<?= $base ?>/journey.php?doc_id=<?= (int)($sr['doc_id'] ?? 0) ?>&route_label=<?= urlencode((string)($sr['route_label'] ?? '')) ?>" class="btn btn-outline-secondary btn-sm ms-1">경로 안내</a>
                    </td>
                    <td><span class="badge badge-g-published">구독중</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <a href="<?= $base ?>/routes.php" class="btn btn-gilaime-primary btn-sm">노선 구독 관리</a>
      </div>
    </div>

    <div class="card g-card">
      <div class="card-body">
        <h2 class="h5 mb-2">구독 노선 알림</h2>
        <p class="text-muted-g small mb-2">구독한 노선의 변경·업데이트 알림을 확인합니다.</p>
        <?php if ($recentAlerts === []): ?>
          <p class="text-muted-g small mb-2">최근 알림이 없습니다.</p>
        <?php else: ?>
          <div class="table-responsive mb-2">
            <table class="table table-hover align-middle g-table g-table-dense mb-0">
              <thead><tr><th class="g-nowrap">유형</th><th>제목</th><th class="g-nowrap">발행일</th></tr></thead>
              <tbody>
                <?php foreach ($recentAlerts as $a): ?>
                  <tr>
                    <td class="g-nowrap"><?= h($a['event_type'] ?? '') ?></td>
                    <td><a href="<?= $base ?>/issue.php?id=<?= (int)($a['id'] ?? 0) ?>"><?= h($a['title'] ?? '') ?></a></td>
                    <td class="g-nowrap"><?= h($a['published_at'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <a href="<?= $base ?>/alerts.php?subscribed=1" class="btn btn-gilaime-primary btn-sm">구독 노선 알림 보기</a>
      </div>
    </div>
  </main>
</body>
</html>
