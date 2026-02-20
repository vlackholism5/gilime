<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';

$base = APP_BASE . '/user';
$pdo = pdo();
$tab = trim((string)($_GET['tab'] ?? 'notice'));
if (!in_array($tab, ['notice', 'event'], true)) $tab = 'notice';
$showAll = trim((string)($_GET['status'] ?? 'active')) === 'all';

$where = ["category = :category"];
if (!$showAll) {
  $where[] = "status = 'published'";
  $where[] = '(starts_at IS NULL OR starts_at <= NOW())';
  $where[] = '(ends_at IS NULL OR NOW() <= ends_at)';
}
$sql = "SELECT id, category, label, title, body_md, is_pinned, starts_at, ends_at, published_at,
  (CASE WHEN ends_at IS NOT NULL AND ends_at < NOW() THEN 1 ELSE 0 END) AS is_ended
  FROM notices
  WHERE " . implode(' AND ', $where) . "
  ORDER BY is_pinned DESC, published_at DESC, id DESC
  LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute([':category' => $tab]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>GILIME - 공지/이벤트</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
    <nav class="nav g-topnav mb-3">
      <a class="nav-link" href="<?= $base ?>/home.php">홈</a>
      <a class="nav-link" href="<?= $base ?>/issues.php">이슈</a>
      <a class="nav-link" href="<?= $base ?>/route_finder.php">길찾기</a>
      <a class="nav-link active" href="<?= $base ?>/notices.php">공지/이벤트</a>
    </nav>

    <div class="g-page-head mb-3">
      <h1>공지/이벤트</h1>
      <p class="helper mb-0">운영 공지와 이벤트를 확인하세요.</p>
    </div>

    <div class="card g-card mb-3">
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <a href="<?= $base ?>/notices.php?tab=notice&status=<?= $showAll ? 'all' : 'active' ?>" class="btn btn-sm <?= $tab === 'notice' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">공지사항</a>
          <a href="<?= $base ?>/notices.php?tab=event&status=<?= $showAll ? 'all' : 'active' ?>" class="btn btn-sm <?= $tab === 'event' ? 'btn-gilaime-primary' : 'btn-outline-secondary' ?>">이벤트</a>
          <a href="<?= $base ?>/notices.php?tab=<?= h($tab) ?>&status=<?= $showAll ? 'active' : 'all' ?>" class="btn btn-sm btn-outline-secondary">
            <?= $showAll ? '진행중만 보기' : '전체 보기' ?>
          </a>
        </div>
      </div>
    </div>

    <div class="card g-card">
      <div class="card-body">
        <?php if ($items === []): ?>
          <p class="text-muted-g small mb-0">표시할 <?= $tab === 'notice' ? '공지사항' : '이벤트' ?>이 없습니다.</p>
        <?php else: ?>
          <div class="accordion" id="notice-accordion">
            <?php foreach ($items as $i => $it): ?>
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#notice-<?= (int)$it['id'] ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                    <span class="badge <?= ((int)$it['is_ended'] === 1 ? 'bg-secondary' : 'bg-success') ?> me-2"><?= h((string)$it['label']) ?></span>
                    <span class="me-2"><?= h((string)$it['title']) ?></span>
                    <?php if ((int)$it['is_pinned'] === 1): ?><span class="badge text-bg-warning ms-1">고정</span><?php endif; ?>
                  </button>
                </h2>
                <div id="notice-<?= (int)$it['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#notice-accordion">
                  <div class="accordion-body">
                    <p class="text-muted-g small mb-2">
                      게시: <?= h((string)($it['published_at'] ?? '')) ?>
                      <?php if (!empty($it['ends_at'])): ?> · 종료: <?= h((string)$it['ends_at']) ?><?php endif; ?>
                    </p>
                    <div class="small"><?= nl2br(h((string)($it['body_md'] ?? ''))) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
