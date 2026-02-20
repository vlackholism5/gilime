/**
 * Route finder mobile-like interactions:
 * - bottom sheet collapse/expand
 * - sort options modal
 */
(function () {
  'use strict';

  function byId(id) { return document.getElementById(id); }

  function initBottomSheet() {
    var sheet = byId('g-route-result-sheet');
    var toggle = byId('g-sheet-toggle');
    if (!sheet || !toggle) return;
    toggle.addEventListener('click', function () {
      sheet.classList.toggle('is-collapsed');
    });
  }

  function initSortModal() {
    var openBtn = byId('g-open-sort-modal');
    var closeBtn = byId('g-close-sort-modal');
    var applyBtn = byId('g-apply-sort-modal');
    var backdrop = byId('g-sort-modal-backdrop');
    if (!openBtn || !backdrop) return;

    var labelEl = openBtn;
    var options = Array.prototype.slice.call(document.querySelectorAll('.g-sort-option'));
    var selectedSortValue = 'best';

    function open() { backdrop.hidden = false; }
    function close() { backdrop.hidden = true; }

    openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        var params = new URLSearchParams(window.location.search);
        params.set('route_sort', selectedSortValue || 'best');
        var stair = byId('g-stair-avoid');
        params.set('stair_avoid', stair && stair.checked ? '1' : '0');
        params.set('step', 'result');
        window.location.search = params.toString();
      });
    }
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) close();
    });

    options.forEach(function (btn) {
      btn.addEventListener('click', function () {
        options.forEach(function (x) { x.classList.remove('active'); });
        btn.classList.add('active');
        var text = btn.getAttribute('data-sort-label') || '최적 경로순';
        selectedSortValue = btn.getAttribute('data-sort-value') || 'best';
        labelEl.textContent = text + ', 옵션';
      });
      if (btn.classList.contains('active')) {
        selectedSortValue = btn.getAttribute('data-sort-value') || 'best';
      }
    });
  }

  function initRouteCardSelection() {
    var cards = Array.prototype.slice.call(document.querySelectorAll('.g-route-card-mobile[data-route-idx]'));
    if (cards.length === 0) return;
    cards.forEach(function (card) {
      card.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('a,button')) return;
        cards.forEach(function (c) { c.classList.remove('active'); });
        card.classList.add('active');
        var idx = parseInt(card.getAttribute('data-route-idx') || '0', 10);
        var routeType = card.getAttribute('data-route-type') || 'bus';
        document.dispatchEvent(new CustomEvent('gilaime:route:select', {
          detail: { idx: idx, routeType: routeType }
        }));
      });
    });
  }

  function init() {
    initBottomSheet();
    initSortModal();
    initRouteCardSelection();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

