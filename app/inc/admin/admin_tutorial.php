<?php
declare(strict_types=1);
/**
 * 관리자 튜토리얼 모달 마크업 (한 화면 전체 순서 안내)
 * render_admin_tutorial_modal()에서 사용. APP_BASE 필요.
 */
$base = defined('APP_BASE') ? APP_BASE : '';
$adminBase = $base . '/admin';
?>
<div class="modal fade" id="gilaime-admin-tutorial-modal" tabindex="-1" aria-labelledby="gilaime-admin-tutorial-title" aria-describedby="gilaime-admin-tutorial-body" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="gilaime-admin-tutorial-title">관리자 작업 순서 안내</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
      </div>
      <div class="modal-body" id="gilaime-admin-tutorial-body">
        <p class="text-muted-g small mb-3">아래 순서대로 진행하면 문서 등록부터 노선 반영까지 한 번에 처리할 수 있습니다.</p>
        <ol class="mb-0 pe-2">
          <li class="mb-2"><strong>PDF 업로드</strong> — <a href="<?= htmlspecialchars($adminBase . '/upload_pdf.php', ENT_QUOTES, 'UTF-8') ?>">PDF 업로드</a>에서 단일 파일 또는 ZIP으로 업로드해 문서를 등록합니다.</li>
          <li class="mb-2"><strong>문서 허브</strong> — <a href="<?= htmlspecialchars($adminBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">문서 허브</a>에서 등록된 문서 목록과 OCR·파싱·검증 상태를 확인합니다.</li>
          <li class="mb-2"><strong>문서 상세 · 파싱·매칭</strong> — 문서를 클릭해 들어간 뒤 <strong>파싱·매칭 실행</strong>을 수행합니다. 실행이 끝나면 노선 후보가 생성됩니다.</li>
          <li class="mb-2"><strong>노선 검수</strong> — <a href="<?= htmlspecialchars($adminBase . '/review_queue.php', ENT_QUOTES, 'UTF-8') ?>">검수 대기열</a> 또는 문서별 노선 검수에서 후보를 승인/거절합니다.</li>
          <li class="mb-2"><strong>승격 반영</strong> — 노선 검수 화면에서 승인한 후보를 <strong>승격 반영</strong>으로 실제 노선 데이터에 적용합니다. 이 단계까지 완료하면 사용자 길찾기에 노선이 반영됩니다.</li>
        </ol>
        <p class="text-muted-g small mt-3 mb-0">이외에 <strong>운영 대시보드</strong>, <strong>알림 운영</strong>, <strong>운영 제어</strong>에서 일상 점검을 할 수 있습니다.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="gilaime-admin-tutorial-skip" data-bs-dismiss="modal">건너뛰기</button>
        <button type="button" class="btn btn-gilaime-primary" id="gilaime-admin-tutorial-hide-today">오늘 다시 보지 않기</button>
      </div>
    </div>
  </div>
</div>
