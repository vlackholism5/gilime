<?php
declare(strict_types=1);
/**
 * v1.8 U-HOME — 출발/도착 + 이슈 Top3 (길찾기 우선)
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';
require_once __DIR__ . '/../../app/inc/alert/alert_event_type.php';

$pdo = pdo();
user_session_user_id();
$base = APP_BASE . '/user';

// 이슈 Top3 (app_alert_events = 이슈, published만)
$issues = [];
try {
  $stmt = $pdo->query("
    SELECT id, event_type, title, body, route_label, published_at, created_at
    FROM app_alert_events
    WHERE published_at IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 3
  ");
  $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $issues = [];
}

function issue_status(string $publishedAt): string {
  return strtotime($publishedAt) > strtotime('-7 days') ? 'Active' : 'Ended';
}
function issue_summary(?string $body, int $len = 80): string {
  if ($body === null || $body === '') return '';
  $s = trim(preg_replace('/\s+/u', ' ', $body));
  return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
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
  <title>GILIME - 홈</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
    <nav class="nav g-topnav mb-3">
      <a class="nav-link active" href="<?= $base ?>/home.php">홈</a>
      <a class="nav-link" href="<?= $base ?>/issues.php">이슈</a>
      <a class="nav-link" href="<?= $base ?>/route_finder.php">길찾기</a>
      <a class="nav-link" href="<?= $base ?>/my_routes.php">마이노선</a>
    </nav>

    <div class="g-page-head mb-3">
      <h1>길라임</h1>
      <p class="helper mb-0">출발지와 도착지를 입력해 경로를 찾습니다.</p>
    </div>

    <!-- 1번 영역: 출발/도착 입력 (공통: g-autocomplete-wrap + route_autocomplete.js) -->
    <div class="card g-card g-home-route mb-4">
      <div class="card-body">
        <form method="post" action="<?= $base ?>/route_finder.php">
          <div class="mb-3 g-autocomplete-wrap">
            <label for="from" class="form-label">출발지</label>
            <input type="text" id="from" name="from" class="form-control form-control-sm"
              placeholder="정류장명, 역명, 주소 검색..." autocomplete="off" />
            <div class="g-autocomplete-dropdown" aria-hidden="true"></div>
          </div>
          <div class="mb-3 g-autocomplete-wrap">
            <label for="to" class="form-label">도착지</label>
            <input type="text" id="to" name="to" class="form-control form-control-sm"
              placeholder="정류장명, 역명, 주소 검색..." autocomplete="off" />
            <div class="g-autocomplete-dropdown" aria-hidden="true"></div>
          </div>
          <button type="submit" name="search" class="btn btn-gilaime-primary">경로 찾기</button>
        </form>
      </div>
    </div>

    <!-- 2번 영역: 긴급 이슈 Top3 -->
    <div class="card g-card">
      <div class="card-body">
        <h2 class="h5 mb-3">긴급 이슈 Top3</h2>
        <?php if ($issues === []): ?>
          <p class="text-muted-g small mb-2">현재 긴급 이슈 없음</p>
          <a href="<?= $base ?>/issues.php" class="btn btn-outline-secondary btn-sm">전체 이슈 보기</a>
        <?php else: ?>
          <?php foreach ($issues as $issue): ?>
            <div class="border-bottom pb-3 mb-3 <?= $issue === end($issues) ? 'border-0 pb-0 mb-0' : '' ?>">
              <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                <strong><?= h($issue['title'] ?? '') ?></strong>
                <span class="text-muted-g small text-nowrap"><?= issue_status($issue['published_at'] ?? '') ?> <?= event_type_to_impact($issue['event_type'] ?? '') ?></span>
              </div>
              <p class="text-muted-g small mb-2"><?= h(issue_summary($issue['body'] ?? null)) ?></p>
              <div class="d-flex gap-2 flex-wrap">
                <a href="<?= $base ?>/issue.php?id=<?= (int)$issue['id'] ?>" class="btn btn-outline-secondary btn-sm">이슈 보기</a>
                <a href="<?= $base ?>/route_finder.php?from=&to=&issue_id=<?= (int)$issue['id'] ?>" class="btn btn-gilaime-primary btn-sm">이슈 기반 길찾기</a>
              </div>
            </div>
          <?php endforeach; ?>
          <a href="<?= $base ?>/issues.php" class="btn btn-outline-secondary btn-sm mt-2">전체 이슈 보기</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <script>window.GILAIME_API_BASE = '<?= APP_BASE ?>';</script>
  <script src="<?= APP_BASE ?>/public/assets/js/route_autocomplete.js"></script>
</body>
</html>
