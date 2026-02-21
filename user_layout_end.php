<?php
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$base = APP_BASE . '/user';
?>
    <!-- 공통 하단 내비게이션 -->
    <nav class="g-bottom-nav g-home-bottom-nav" aria-label="하단 내비게이션">
      <a class="<?= $currentScript === 'home.php' ? 'active' : '' ?>" href="<?= $base ?>/home.php">
        <span class="g-nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-home"></use></svg>
        </span>
        <span class="g-nav-label">홈</span>
      </a>
      <a class="<?= $currentScript === 'issues.php' ? 'active' : '' ?>" href="<?= $base ?>/issues.php">
        <span class="g-nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-issue"></use></svg>
        </span>
        <span class="g-nav-label">이슈</span>
      </a>
      <a class="<?= $currentScript === 'route_finder.php' ? 'active' : '' ?>" href="<?= $base ?>/route_finder.php">
        <span class="g-nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-route"></use></svg>
        </span>
        <span class="g-nav-label">길찾기</span>
      </a>
      <a class="<?= $currentScript === 'my_routes.php' ? 'active' : '' ?>" href="<?= $base ?>/my_routes.php">
        <span class="g-nav-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" class="g-nav-icon-svg"><use href="<?= APP_BASE ?>/public/assets/icons/gilaime_nav.svg#icon-star"></use></svg>
        </span>
        <span class="g-nav-label">마이노선</span>
      </a>
    </nav>
  </main>
</body>
</html>