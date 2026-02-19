<?php
declare(strict_types=1);
/**
 * 공통 Admin 상단 영역 (그룹 네비 + breadcrumb + 로그아웃)
 * 사용: render_admin_header($breadcrumbs)
 * $breadcrumbs: [['label' => '문서 허브', 'url' => 'index.php'], ['label' => '문서 #10', 'url' => null]]
 * url이 null이면 현재 페이지(비링크)
 */

/**
 * 그룹 네비 링크 (문서|운영|알림|감사) + 로그아웃
 */
function render_admin_nav(): void {
  $base = defined('APP_BASE') ? APP_BASE : '';
  $adminBase = $base . '/admin';
  ?>
  <nav class="g-admin-nav d-flex flex-wrap align-items-center gap-2 mb-2" aria-label="관리자 메뉴">
    <span class="g-admin-nav-brand text-muted-g small me-2">길라임 Admin</span>
    <span class="text-muted-g">|</span>
    <a href="<?= htmlspecialchars($adminBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">문서</a>
    <span class="text-muted-g">|</span>
    <a href="<?= htmlspecialchars($adminBase . '/ops_dashboard.php', ENT_QUOTES, 'UTF-8') ?>">운영</a>
    <span class="text-muted-g">|</span>
    <a href="<?= htmlspecialchars($adminBase . '/alert_ops.php', ENT_QUOTES, 'UTF-8') ?>">알림</a>
    <span class="text-muted-g">|</span>
    <a href="<?= htmlspecialchars($adminBase . '/alias_audit.php', ENT_QUOTES, 'UTF-8') ?>">감사</a>
    <a class="btn btn-outline-secondary btn-sm ms-auto" href="<?= htmlspecialchars($adminBase . '/logout.php', ENT_QUOTES, 'UTF-8') ?>">로그아웃</a>
  </nav>
  <?php
}

/**
 * Breadcrumb + 로그아웃 (기존)
 * $showLogout=false: render_admin_nav 사용 시 로그아웃 중복 방지
 */
function render_admin_header(array $breadcrumbs, bool $showLogout = true): void {
  $base = defined('APP_BASE') ? APP_BASE : '';
  $adminBase = $base . '/admin';
  ?>
  <div class="g-top">
    <div>
      <?php
      $parts = [];
      foreach ($breadcrumbs as $i => $b) {
        $label = $b['label'] ?? '';
        $url = $b['url'] ?? null;
        if ($url !== null && $url !== '') {
          $href = (strpos($url, '/') === 0 || strpos($url, 'http') === 0) ? $url : $adminBase . '/' . ltrim($url, '/');
          $parts[] = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        } else {
          $parts[] = '<span class="text-muted-g">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        }
      }
      echo implode(' <span class="text-muted-g">/</span> ', $parts);
      ?>
    </div>
    <?php if ($showLogout): ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($adminBase . '/logout.php', ENT_QUOTES, 'UTF-8') ?>">로그아웃</a>
    <?php endif; ?>
  </div>
  <?php
}
