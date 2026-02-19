<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/user_session.php';

$pdo = pdo();
$userId = user_session_user_id();

// v1.1 observability: POST removed, now handled by /api/subscription/toggle

// List: distinct (doc_id, route_label) from shuttle_stop_candidate + latest PARSE_MATCH job
$routes = [];
try {
  $stmt = $pdo->query("
    SELECT c.source_doc_id AS doc_id, c.route_label
    FROM shuttle_stop_candidate c
    INNER JOIN (
      SELECT j.source_doc_id, j.id AS job_id
      FROM shuttle_doc_job_log j
      INNER JOIN (
        SELECT source_doc_id, MAX(id) AS mid
        FROM shuttle_doc_job_log
        WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
        GROUP BY source_doc_id
      ) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
      WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
    ) latest ON c.source_doc_id = latest.source_doc_id AND c.created_job_id = latest.job_id
    GROUP BY c.source_doc_id, c.route_label
    ORDER BY c.source_doc_id, c.route_label
  ");
  $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $routes = [];
}

$subscribed = [];
$st = $pdo->prepare("SELECT target_id FROM app_subscriptions WHERE user_id = :uid AND target_type = 'route' AND is_active = 1");
$st->execute([':uid' => $userId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $subscribed[$row['target_id']] = true;
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
  <title>GILIME - 노선 구독/해제</title>
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
    <h1>노선 구독/해제</h1>
    <p class="helper mb-0">알림을 받을 노선을 구독·해제할 수 있습니다.</p>
    <a href="<?= $base ?>/my_routes.php" class="btn btn-outline-secondary btn-sm mt-1">마이노선으로</a>
  </div>
  <details class="kbd-help mb-3">
    <summary>단축키 안내</summary>
    <div class="body">/ : 검색 입력으로 이동 · Esc : 닫기 · Ctrl+Enter : 주요 폼 제출(지원 페이지)</div>
  </details>
  <div class="card g-card">
    <div class="card-body">
    <?php if ($routes === []): ?>
      <p class="text-muted-g small mb-0">노선이 없습니다.</p>
    <?php else: ?>
      <div class="table-responsive">
      <table class="table table-hover align-middle g-table g-table-dense mb-0">
        <thead><tr><th class="mono">문서 ID</th><th>노선</th><th>구독</th></tr></thead>
        <tbody>
          <?php foreach ($routes as $r):
            $tid = (int)$r['doc_id'] . '_' . $r['route_label'];
            $isSub = isset($subscribed[$tid]);
          ?>
            <tr>
              <td><?= (int)$r['doc_id'] ?></td>
              <td><?= h($r['route_label']) ?><?php if ($isSub): ?> <span class="badge badge-g-published ms-1">구독중</span><?php endif; ?></td>
              <td>
                <?php if ($isSub): ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm sub-toggle" data-doc-id="<?= (int)$r['doc_id'] ?>" data-route-label="<?= h($r['route_label']) ?>" data-action="unsubscribe">구독 해제</button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-gilaime-primary sub-toggle" data-doc-id="<?= (int)$r['doc_id'] ?>" data-route-label="<?= h($r['route_label']) ?>" data-action="subscribe">구독</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
    </div>
  </div>

  <script src="<?= APP_BASE ?>/admin/trace-helper.js"></script>
  <script src="<?= APP_BASE ?>/public/assets/js/gilaime_ui.js"></script>
  <script>
    <?php require_once __DIR__ . '/../../app/inc/lib/observability.php'; ?>
    window.__GILIME_DEBUG__ = <?= is_debug_enabled() ? 'true' : 'false' ?>;

    (function () {
      var buttons = document.querySelectorAll('.sub-toggle');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var docId = btn.getAttribute('data-doc-id');
          var routeLabel = btn.getAttribute('data-route-label');
          var action = btn.getAttribute('data-action');
          var tid = GilimeTrace.createId();
          if (window.__GILIME_DEBUG__) console.log('[TRACE ' + tid + '] click', { action: action, doc_id: docId, route_label: routeLabel });
          btn.disabled = true;
          GilimeTrace.fetch('<?= APP_BASE ?>/api/subscription/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ doc_id: parseInt(docId), route_label: routeLabel, action: action })
          }, tid).then(function (r) {
            return r.json();
          }).then(function (data) {
            if (data.ok) {
              window.location.reload();
            } else {
              alert('오류: ' + (data.error || '알 수 없음'));
              btn.disabled = false;
            }
          }).catch(function (err) {
            alert('요청에 실패했습니다');
            btn.disabled = false;
          });
        });
      });
    })();
  </script>
  </main>
</body>
</html>
