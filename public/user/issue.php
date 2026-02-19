<?php
declare(strict_types=1);
/**
 * v1.8 U-ISSUE-01 — 이슈 상세 (이슈 기반 길찾기 CTA)
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/alert/alert_event_type.php';

user_session_user_id();
$base = APP_BASE . '/user';
$adminBase = APP_BASE . '/admin';
$pdo = pdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$issue = null;
if ($id > 0) {
  try {
    $stmt = $pdo->prepare("
      SELECT id, event_type, title, body, ref_type, ref_id, route_label, published_at, created_at
      FROM app_alert_events
      WHERE id = :id AND published_at IS NOT NULL
    ");
    $stmt->execute([':id' => $id]);
    $issue = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $issue = null;
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
  <title>GILIME - <?= $issue ? h($issue['title']) : '이슈' ?></title>
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

    <?php if ($issue): ?>
    <div class="g-page-head mb-3">
      <h1><?= h($issue['title']) ?></h1>
      <p class="helper mb-0"><?= issue_status($issue['published_at'] ?? '') ?> <?= event_type_to_impact($issue['event_type'] ?? '') ?></p>
    </div>

    <div class="card g-card mb-3">
      <div class="card-body">
        <p class="text-muted-g small mb-2"><?= h($issue['published_at'] ?? '') ?> 발행</p>
        <div class="mb-3">
          <?= nl2br(h($issue['body'] ?? '')) ?>
        </div>
        <?php if (!empty($issue['route_label'])): ?>
          <p class="text-muted-g small mb-2">영향 노선: <?= h($issue['route_label']) ?></p>
        <?php endif; ?>
        <?php
        $docId = isset($issue['ref_id']) ? (int)$issue['ref_id'] : 0;
        $rl = isset($issue['route_label']) ? trim((string)$issue['route_label']) : '';
        $reviewUrl = ($docId > 0 && $rl !== '')
          ? $adminBase . '/route_review.php?source_doc_id=' . $docId . '&route_label=' . urlencode($rl) . '&quick_mode=1&show_advanced=0'
          : ($docId > 0 ? $adminBase . '/doc.php?id=' . $docId : '');
        ?>
        <?php if ($reviewUrl !== ''): ?>
          <a href="<?= h($reviewUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm mb-2">검수 보기</a>
        <?php endif; ?>
        <a href="<?= $base ?>/route_finder.php?issue_id=<?= (int)$issue['id'] ?>" class="btn btn-gilaime-primary mb-2">이슈 기반 길찾기</a>
        <br />
        <a href="<?= $base ?>/issues.php" class="btn btn-outline-secondary btn-sm">전체 이슈 보기</a>
      </div>
    </div>
    <?php else: ?>
    <div class="card g-card">
      <div class="card-body">
        <p class="text-muted-g small mb-0">이슈를 찾을 수 없습니다.</p>
        <a href="<?= $base ?>/issues.php" class="btn btn-outline-secondary btn-sm mt-2">전체 이슈 보기</a>
        <a href="<?= $base ?>/home.php" class="btn btn-outline-secondary btn-sm mt-2">홈</a>
      </div>
    </div>
    <?php endif; ?>
  </main>
</body>
</html>
