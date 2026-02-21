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
      <a class="<?= $currentScript === 'route_finder.php' ? 'active' : '' ?>" href="<?= $base ?>/route_finder.php" data-action="open-route">
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
  <?php if ($currentScript === 'home.php'): ?>
  <script>
    window.GILAIME_API_BASE = <?= json_encode(APP_BASE, JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <!-- 홈 화면 지도 연동 스크립트 -->
  <script src="<?= APP_BASE ?>/public/assets/js/home_map.js"></script>
  <!-- 자동완성 스크립트 -->
  <script src="<?= APP_BASE ?>/public/assets/js/route_autocomplete.js"></script>
  <?php endif; ?>
</body>
</html>