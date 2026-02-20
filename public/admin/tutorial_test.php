<?php
declare(strict_types=1);
/**
 * 관리자 전용: 튜토리얼 버튼·localStorage 테스트.
 * 일반 사용자 경로에 노출되지 않으며, require_admin()으로 관리자만 접근 가능.
 */
require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$base = defined('APP_BASE') ? APP_BASE : '';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - 튜토리얼 테스트</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= htmlspecialchars($base . '/public/assets/css/gilaime_ui.css', ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '튜토리얼 테스트', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2 class="h3">튜토리얼 테스트</h2>
    <p class="helper mb-0">튜토리얼 모달과 건너뛰기·오늘 다시 보지 않기 동작을 확인합니다. 관리자 페이지에서만 접근 가능합니다.</p>
  </div>

  <div class="card g-card">
    <div class="card-body">
      <h5 class="card-title">동작 확인</h5>
      <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <button type="button" class="btn btn-gilaime-primary" id="tutorial-test-show">튜토리얼 다시 보기</button>
        <button type="button" class="btn btn-outline-secondary" id="tutorial-test-clear">오늘 다시 보지 않기 초기화</button>
      </div>
      <p class="text-muted-g small mb-0">
        <strong>튜토리얼 다시 보기:</strong> 모달만 띄웁니다 (localStorage 변경 없음).<br />
        <strong>오늘 다시 보지 않기 초기화:</strong> 저장된 날짜를 지웁니다. 다른 관리자 페이지로 갔다가 오면 튜토리얼이 다시 자동으로 뜹니다.
      </p>
    </div>
  </div>

  <div class="card g-card mt-3">
    <div class="card-body">
      <h5 class="card-title">현재 저장값</h5>
      <p class="mb-0 font-monospace small" id="tutorial-test-value">—</p>
    </div>
  </div>

  </main>
  <?php render_admin_tutorial_modal(); ?>
  <script>
  (function () {
    function showValue() {
      var key = 'gilaime_admin_tutorial_hide_until';
      var val = '';
      try { val = localStorage.getItem(key) || '(없음)'; } catch (e) { val = '(접근 불가)'; }
      var el = document.getElementById('tutorial-test-value');
      if (el) el.textContent = key + ' = ' + val;
    }
    document.getElementById('tutorial-test-show').addEventListener('click', function () {
      if (window.gilaimeShowTutorialModal) window.gilaimeShowTutorialModal();
    });
    document.getElementById('tutorial-test-clear').addEventListener('click', function () {
      if (window.gilaimeClearTutorialHideUntil) window.gilaimeClearTutorialHideUntil();
      showValue();
      alert('초기화했습니다. 다른 관리자 페이지로 이동하거나 새로고침하면 튜토리얼이 다시 뜹니다.');
    });
    showValue();
  })();
  </script>
</body>
</html>
