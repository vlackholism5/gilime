(function () {
  "use strict";

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  function nowMs() {
    return (typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now();
  }

  /**
   * Bottom Sheet detents controller (MVP)
   * - states: collapsed / half / full
   * - uses CSS var: --g-sheet-ty (translateY px)
   *
   * Required DOM:
   * - sheetEl: #g-home-bottom-sheet
   * - handleEl: #g-home-sheet-toggle OR .g-home-sheet-handle
   * - bottom nav height token: --g-bottom-nav-h
   */
  window.GilaimeBottomSheetDetents = function initDetents(sheetEl, handleEl, opts) {
    if (!sheetEl || !handleEl) return null;

    const options = Object.assign({
      collapsedVisible: 64,      // px
      halfRatio: 0.45,           // viewport ratio (visible height)
      fullRatio: 0.78,           // viewport ratio (visible height)
      velocityThreshold: 0.45    // px/ms
    }, opts || {});

    const STATE = {
      COLLAPSED: "collapsed",
      HALF: "half",
      FULL: "full"
    };

    function getBottomNavH() {
      const cs = getComputedStyle(document.documentElement);
      const v = cs.getPropertyValue("--g-bottom-nav-h").trim();
      const n = parseFloat(v || "54");
      return Number.isFinite(n) ? n : 54;
    }

    function viewportH() {
      return Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
    }

    function computeDetents() {
      const vh = viewportH();
      const navH = getBottomNavH();
      const available = Math.max(320, vh - navH); // safe min

      const collapsed = clamp(options.collapsedVisible, 56, 120);
      const half = clamp(Math.round(available * options.halfRatio), 220, Math.round(available * 0.72));
      const full = clamp(Math.round(available * options.fullRatio), Math.round(available * 0.55), Math.round(available * 0.92));

      return { collapsed, half, full, available };
    }

    function tyFromVisible(visible, detents) {
      // translateY: how much we push sheet down from "full open"
      // We'll treat "full open" as ty=0; lower states increase ty.
      const maxVisible = detents.full;
      return clamp(maxVisible - visible, 0, maxVisible - detents.collapsed);
    }

    function applyState(state, detents) {
      sheetEl.classList.remove("is-collapsed", "is-half", "is-full");
      if (state === STATE.COLLAPSED) sheetEl.classList.add("is-collapsed");
      if (state === STATE.HALF) sheetEl.classList.add("is-half");
      if (state === STATE.FULL) sheetEl.classList.add("is-full");

      const visible = detents[state] || detents.half;
      const ty = tyFromVisible(visible, detents);
      sheetEl.style.setProperty("--g-sheet-ty", ty + "px");
      sheetEl.dataset.sheetState = state;
    }

    function nearestStateByTy(ty, detents) {
      const tCollapsed = tyFromVisible(detents.collapsed, detents);
      const tHalf = tyFromVisible(detents.half, detents);
      const tFull = tyFromVisible(detents.full, detents);
      const candidates = [
        { s: STATE.COLLAPSED, d: Math.abs(ty - tCollapsed) },
        { s: STATE.HALF, d: Math.abs(ty - tHalf) },
        { s: STATE.FULL, d: Math.abs(ty - tFull) }
      ];
      candidates.sort((a, b) => a.d - b.d);
      return candidates[0].s;
    }

    function pickByVelocity(v, fallbackState) {
      if (Math.abs(v) < options.velocityThreshold) return fallbackState;
      // v > 0 : dragging down (collapse)
      // v < 0 : dragging up (expand)
      return (v > 0) ? STATE.COLLAPSED : STATE.FULL;
    }

    // initial
    let detents = computeDetents();
    const initial = sheetEl.dataset.sheetState || STATE.HALF;
    applyState(initial, detents);

    // dragging
    let dragging = false;
    let startY = 0;
    let startTy = 0;
    let lastY = 0;
    let lastT = 0;

    function currentTy() {
      const v = sheetEl.style.getPropertyValue("--g-sheet-ty").trim();
      const n = parseFloat(v || "0");
      return Number.isFinite(n) ? n : 0;
    }

    function onDown(e) {
      dragging = true;
      sheetEl.classList.add("is-dragging");
      startY = (e.touches ? e.touches[0].clientY : e.clientY);
      startTy = currentTy();
      lastY = startY;
      lastT = nowMs();
    }

    function onMove(e) {
      if (!dragging) return;
      const y = (e.touches ? e.touches[0].clientY : e.clientY);
      const dy = y - startY;
      detents = computeDetents();

      // ty increases when dragging down
      const nextTy = clamp(startTy + dy, 0, tyFromVisible(detents.collapsed, detents));
      sheetEl.style.setProperty("--g-sheet-ty", nextTy + "px");

      lastY = y;
      lastT = nowMs();
      e.preventDefault();
    }

    function onUp(e) {
      if (!dragging) return;
      dragging = false;
      sheetEl.classList.remove("is-dragging");

      detents = computeDetents();
      const endTy = currentTy();

      // estimate velocity using last move segment if possible
      const y = (e.changedTouches ? e.changedTouches[0].clientY : (e.clientY || lastY));
      const t = nowMs();
      const dt = Math.max(1, t - lastT);
      const v = (y - lastY) / dt; // px/ms (down positive)

      const near = nearestStateByTy(endTy, detents);
      const picked = pickByVelocity(v, near);
      applyState(picked, detents);
    }

    // listeners
    handleEl.addEventListener("mousedown", onDown, { passive: true });
    window.addEventListener("mousemove", onMove, { passive: false });
    window.addEventListener("mouseup", onUp, { passive: true });

    handleEl.addEventListener("touchstart", onDown, { passive: true });
    window.addEventListener("touchmove", onMove, { passive: false });
    window.addEventListener("touchend", onUp, { passive: true });

    // resize
    window.addEventListener("resize", function () {
      detents = computeDetents();
      const s = sheetEl.dataset.sheetState || STATE.HALF;
      applyState(s, detents);
    });

    return { applyState };
  };
})();