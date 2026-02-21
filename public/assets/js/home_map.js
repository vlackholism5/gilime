/* public/assets/js/home_map.js
 * GILIME Home Map (Leaflet)
 * - Map init (must call initializeMap)
 * - Bottom sheet: collapsed/half/full (toggle + drag)
 * - Tabs switch
 * - Search trigger -> route_finder.php
 * - Route recommendation -> pan map
 * - Location tracking toggle (optional)
 */
document.addEventListener('DOMContentLoaded', function () {
  // 1) required DOM
  const mapContainer = document.getElementById('g-home-map');
  if (!mapContainer) return; // not home page

  const loadingEl = document.getElementById('g-home-map-loading');
  if (loadingEl) {
    // 로딩 UI 업데이트 (스피너 추가)
    loadingEl.innerHTML = '<div class="spinner-border" role="status"></div><div>지도를 불러오는 중입니다...</div>';
  }

  const searchTrigger = document.getElementById('g-home-search-trigger');
  const startRouteBtn = document.getElementById('g-home-start-route');
  const bottomSheet = document.getElementById('g-home-bottom-sheet');

  // Overlay Elements
  const searchOverlay = document.getElementById('g-search-overlay');
  const searchCloseBtn = document.getElementById('g-search-close');
  const searchInput = document.getElementById('g-search-input');
  const searchSubmitBtn = document.getElementById('g-search-submit');
  const routeInputPanel = document.getElementById('g-route-input-panel');
  const fromInput = document.getElementById('g-route-from');
  const toInput = document.getElementById('g-route-to');
  const searchContentDefault = document.getElementById('g-search-content-default');
  const routeSubmitBtn = document.getElementById('g-route-submit');
  const shuttleCtaBtn = document.getElementById('g-shuttle-cta');
  const myRouteCtaBtn = document.getElementById('g-myroute-cta');
  const modeBtns = document.querySelectorAll('.g-mode-btn');
  const modeShuttleBtn = document.getElementById('g-mode-shuttle');
  
  const sheetToggle = document.getElementById('g-home-sheet-toggle');
  const trackToggle = document.getElementById('g-home-track-toggle');

  const tabButtons = document.querySelectorAll('.g-home-main-tabs button[data-home-tab]');
  const panels = document.querySelectorAll('.g-home-panel[data-home-panel]');
  const routeItems = document.querySelectorAll('.g-home-route-item[data-map-lat][data-map-lng]');
  const routeResultList = document.getElementById('g-route-result-list');

  // 2) state
  let map = null;
  let centerMarker = null;

  let uiState = 'HOME_MAP'; // HOME_MAP, SEARCH_OVERLAY, ROUTE_INPUT, ROUTE_RESULTS
  let activeRouteField = 'from'; // 'from' or 'to'
  let recentSearches = [];
  let searchModes = {
    bus: true,
    subway: true,
    shuttle: false
  };
  
  let activeRouteLayer = null;
  let selectedRouteId = null;
  let currentRouteSelection = null;

  let geoWatchId = null;
  let userMarker = null;
  let sheetDetents = null; // detents controller instance

  // ---- helpers
  function h(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function hideLoading() {
    if (!loadingEl) return;
    loadingEl.style.display = 'none';
  }

  function showLoadingError(msg) {
    if (!loadingEl) return;
    loadingEl.style.display = 'flex';
    loadingEl.textContent = msg || '지도를 표시할 수 없습니다.';
  }

  function invalidateSoon() {
    if (!map) return;
    setTimeout(function () {
      try { map.invalidateSize(); } catch (_) {}
    }, 240);
  }

  // ---- bottom sheet state helpers
  function ensureInitialHalf() {
    if (!bottomSheet) return;
    // 요구: 첫 화면 무조건 is-half
    bottomSheet.classList.remove('is-collapsed', 'is-full');
    bottomSheet.classList.add('is-half');
  }

  function setSheetState(next) {
    if (sheetDetents) {
      sheetDetents.applyState(next);
    } else {
      if (!bottomSheet) return;

      bottomSheet.classList.remove('is-collapsed', 'is-half', 'is-full');
      if (next === 'collapsed') bottomSheet.classList.add('is-collapsed');
      else if (next === 'full') bottomSheet.classList.add('is-full');
      else bottomSheet.classList.add('is-half');
    }

    invalidateSoon();
  }

  function getSheetState() {
    if (!bottomSheet) return 'half';
    if (bottomSheet.classList.contains('is-collapsed')) return 'collapsed';
    if (bottomSheet.classList.contains('is-full')) return 'full';
    return 'half';
  }

  function activateTab(tabName) {
    tabButtons.forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-home-tab') === tabName);
    });
    panels.forEach(function (p) {
      p.classList.toggle('active', p.getAttribute('data-home-panel') === tabName);
    });
    invalidateSoon();
  }

  function parseLatLng(el) {
    const lat = Number(el.getAttribute('data-map-lat'));
    const lng = Number(el.getAttribute('data-map-lng'));
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    return { lat, lng };
  }

  // 3) map init
  function initializeMap() {
    // [INIT] Home map boot (6 lines + guard)
    if (window.__GILIME_HOME_BOOTED__) return;
    window.__GILIME_HOME_BOOTED__ = true;
    if (typeof L === 'undefined') return;
    if (!bottomSheet) return; // 홈 UI가 깨진 경우 방어
    ensureInitialHalf(); // 요구: 첫 화면 무조건 is-half

    // required data
    if (!window.GILAIME_HOME_MAP || !window.GILAIME_HOME_MAP.lat || !window.GILAIME_HOME_MAP.lng) {
      showLoadingError('지도 중심 좌표가 없습니다.');
      return;
    }

    const center = {
      lat: Number(window.GILAIME_HOME_MAP.lat),
      lng: Number(window.GILAIME_HOME_MAP.lng)
    };
    if (!Number.isFinite(center.lat) || !Number.isFinite(center.lng)) {
      showLoadingError('지도 좌표가 올바르지 않습니다.');
      return;
    }

    // create map
    try {
      map = L.map(mapContainer, { zoomControl: false }).setView([center.lat, center.lng], 14);
      L.control.zoom({ position: 'bottomright' }).addTo(map);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      centerMarker = L.marker([center.lat, center.lng]).addTo(map);

      hideLoading();
      invalidateSoon();
    } catch (e) {
      console.error('Home map init failed', e);
      showLoadingError('지도를 초기화할 수 없습니다.');
      return;
    }
  }

  // ---- State Machine
  function setUiState(nextState, payload) {
    uiState = nextState;
    switch (nextState) {
      case 'HOME_MAP':
        if (document.activeElement && searchOverlay && searchOverlay.contains(document.activeElement)) document.activeElement.blur();
        if (searchOverlay) searchOverlay.style.display = 'none';
        setSheetState(payload?.sheetState || 'half');
        break;
      case 'SEARCH_OVERLAY':
        if (searchOverlay) searchOverlay.style.display = 'flex';
        if (searchContentDefault) searchContentDefault.style.display = 'block';
        if (routeInputPanel) routeInputPanel.style.display = 'none';
        if (searchInput) setTimeout(() => searchInput.focus(), 80);
        if (window.GilimeAutocomplete) window.GilimeAutocomplete.initAll(searchOverlay);
        loadRecentSearches();
        renderRecentSearches();
        break;
      case 'ROUTE_INPUT':
        if (searchOverlay) searchOverlay.style.display = 'flex';
        if (searchContentDefault) searchContentDefault.style.display = 'none';
        if (routeInputPanel) routeInputPanel.style.display = 'block';
        if (window.GilimeAutocomplete) window.GilimeAutocomplete.initAll(searchOverlay);
        if (payload?.focusField === 'from' && fromInput) setTimeout(() => fromInput.focus(), 80);
        if (payload?.focusField === 'to' && toInput) setTimeout(() => toInput.focus(), 80);
        if (payload?.prefill) fillRouteField(payload.prefill.field, payload.prefill.value);
        break;
      case 'ROUTE_RESULTS':
        // Mock API Response handling
        if (routeResultList) {
          // Show banner only if shuttle is ON
          const showBanner = searchModes.shuttle;
          renderMockRouteResults(routeResultList, showBanner);
        }
        if (searchOverlay) searchOverlay.style.display = 'none';
        activateTab('route');
        setSheetState('half');
        // TODO: Render route results in bottom sheet
        break;
    }
  }

  // ---- Recent Search
  function loadRecentSearches() {
    try {
      recentSearches = JSON.parse(localStorage.getItem('gilime_recent_searches') || '[]');
    } catch (e) { recentSearches = []; }
  }
  function saveRecentSearches() {
    localStorage.setItem('gilime_recent_searches', JSON.stringify(recentSearches));
  }
  function addRecentSearch(query, skipRender) {
    const q = query.trim();
    if (q.length < 2) return;
    recentSearches = recentSearches.filter(item => item !== q);
    recentSearches.unshift(q);
    if (recentSearches.length > 20) recentSearches.pop();
    saveRecentSearches();
    if (!skipRender) renderRecentSearches();
  }
  function renderRecentSearches() {
    const listEl = document.getElementById('g-recent-list');
    if (!listEl) return;
    if (recentSearches.length === 0) {
      listEl.innerHTML = '<li class="list-group-item text-muted small text-center py-3">최근 검색 내역이 없습니다.</li>';
      return;
    }
    listEl.innerHTML = recentSearches.map(q =>
      `<li class="list-group-item list-group-item-action" data-query="${encodeURIComponent(q)}">${h(q)}</li>`
    ).join('');
  }

  // ---- Overlay/Route Logic
  function submitOverlaySearch(query, source) {
    if (!query || query.trim().length === 0) return;
    addRecentSearch(query);
    
    console.log(`Search triggered for: "${query}" from ${source}`);
    // For now, let's switch to route input as a demo
    setUiState('ROUTE_INPUT', { prefill: { field: activeRouteField, value: query } });
  }

  function setActiveRouteField(field) {
    activeRouteField = (field === 'from' || field === 'to') ? field : 'from';
  }

  function fillRouteField(field, value) {
    const input = (field === 'from') ? fromInput : toInput;
    if (input) input.value = value;
    checkRouteReady();
  }

  function checkRouteReady() {
    const fromVal = fromInput ? fromInput.value.trim() : '';
    const toVal = toInput ? toInput.value.trim() : '';
    if (routeSubmitBtn) {
      routeSubmitBtn.disabled = !(fromVal && toVal);
    }
  }

  // ---- Mode & Banner Logic
  function updateModeUi() {
    modeBtns.forEach(btn => {
      const mode = btn.dataset.mode;
      if (searchModes[mode]) btn.classList.add('active');
      else btn.classList.remove('active');
    });
  }

  if (shuttleCtaBtn) {
    shuttleCtaBtn.addEventListener('click', () => {
      searchModes.shuttle = true;
      updateModeUi();
      // Auto-switch to route input if not already
      if (uiState !== 'ROUTE_INPUT') {
        setUiState('ROUTE_INPUT', { focusField: 'from' });
      }
    });
  }

  if (myRouteCtaBtn) {
    myRouteCtaBtn.addEventListener('click', function() {
      const msgEl = document.getElementById('g-issue-msg');
      const fromVal = fromInput ? fromInput.value.trim() : '';
      const toVal = toInput ? toInput.value.trim() : '';

      if (!fromVal || !toVal) {
        if (msgEl) {
          msgEl.textContent = '출발지와 도착지를 먼저 입력해주세요.';
          msgEl.style.display = 'block';
          msgEl.className = 'mt-2 small text-danger';
          setTimeout(() => { msgEl.style.display = 'none'; }, 3000);
        }
        return;
      }

      const fav = {
        id: 'fav_' + Date.now(),
        from_text: fromVal,
        to_text: toVal,
        modes: { ...searchModes },
        selected_route_id: selectedRouteId,
        geo: currentRouteSelection?.geo || null,
        created_at: new Date().toISOString()
      };

      try {
        const key = 'gilime_favorites_routes_v1';
        const list = JSON.parse(localStorage.getItem(key) || '[]');
        // Simple de-dup: check last 10
        const isDup = list.slice(0, 10).some(item => 
          item.from_text === fav.from_text && item.to_text === fav.to_text && 
          JSON.stringify(item.modes) === JSON.stringify(fav.modes)
        );

        if (isDup) {
          if (msgEl) {
            msgEl.textContent = '이미 저장된 경로입니다.';
            msgEl.style.display = 'block';
            msgEl.className = 'mt-2 small text-warning';
            setTimeout(() => { msgEl.style.display = 'none'; }, 3000);
          }
          return;
        }

        list.unshift(fav);
        localStorage.setItem(key, JSON.stringify(list));
        
        if (msgEl) {
          msgEl.textContent = '마이노선에 저장되었습니다.';
          msgEl.style.display = 'block';
          msgEl.className = 'mt-2 small text-success';
          setTimeout(() => { msgEl.style.display = 'none'; }, 3000);
        }
      } catch (e) {
        console.error('Save failed', e);
      }
    });
  }

  modeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.mode;
      if (mode) {
        searchModes[mode] = !searchModes[mode];
        updateModeUi();
      }
    });
  });

  // 4) events
  // -- Search Overlay Triggers
  if (searchTrigger && !searchTrigger.dataset.bound) {
    searchTrigger.addEventListener('click', (ev) => {
      ev.preventDefault();
      setUiState('SEARCH_OVERLAY');
    });
    searchTrigger.dataset.bound = 'true';
  }
  if (startRouteBtn && !startRouteBtn.dataset.bound) {
    startRouteBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      setUiState('ROUTE_INPUT', { focusField: 'from' });
    });
    startRouteBtn.dataset.bound = 'true';
  }
  const navRouteLinks = document.querySelectorAll('.g-bottom-nav a[href*="route_finder.php"]');
  navRouteLinks.forEach(function (link) {
    if (!link.dataset.bound) {
      link.addEventListener('click', function (ev) {
        if (document.getElementById('g-home-map')) {
          ev.preventDefault();
          setUiState('ROUTE_INPUT', { focusField: 'from' });
        }
      });
      link.dataset.bound = 'true';
    }
  });
  routeItems.forEach(function (btn) {
    if (!btn.dataset.bound) {
      btn.addEventListener('click', function (ev) {
        const label = btn.getAttribute('data-map-label') || btn.textContent.trim();
        setUiState('ROUTE_INPUT', { prefill: { field: 'to', value: label } });
        const ll = parseLatLng(btn);
        if (ll && map) {
          try { map.panTo([ll.lat, ll.lng], { animate: true, duration: 0.5 }); } catch (_) {}
        }
      });
      btn.dataset.bound = 'true';
    }
  });

  // -- Overlay Internal Events
  if (searchCloseBtn) searchCloseBtn.addEventListener('click', () => setUiState('HOME_MAP'));
  if (searchSubmitBtn) searchSubmitBtn.addEventListener('click', () => submitOverlaySearch(searchInput.value, 'icon_click'));
  if (searchInput) searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') submitOverlaySearch(searchInput.value, 'enter');
  });

  const recentListEl = document.getElementById('g-recent-list');
  if (recentListEl) recentListEl.addEventListener('click', (e) => {
    const target = e.target.closest('[data-query]');
    if (target) {
      const query = decodeURIComponent(target.dataset.query);
      if (uiState === 'ROUTE_INPUT') {
        fillRouteField(activeRouteField, query);
      } else {
        submitOverlaySearch(query, 'recent_click');
      }
    }
  });

  const recentClearBtn = document.getElementById('g-recent-clear');
  if (recentClearBtn) recentClearBtn.addEventListener('click', () => {
    recentSearches = [];
    saveRecentSearches();
    renderRecentSearches();
  });

  // -- Route Input Panel Events
  if (fromInput) fromInput.addEventListener('focus', () => setActiveRouteField('from'));
  if (toInput) toInput.addEventListener('focus', () => setActiveRouteField('to'));
  if (fromInput) fromInput.addEventListener('input', checkRouteReady);
  if (toInput) toInput.addEventListener('input', checkRouteReady);

  if (routeSubmitBtn) routeSubmitBtn.addEventListener('click', () => {
    setUiState('ROUTE_RESULTS');
    // TODO: Pass from/to values to route search engine
    console.log(`Route requested: ${fromInput.value} -> ${toInput.value}`, searchModes);
  });

  // Listen for autocomplete selection to enable submit button
  document.addEventListener('gilaime:route:place-select', function(e) {
    setTimeout(checkRouteReady, 50); // Wait for value to be set
  });

  // -- Route Result Click (Draw Polyline)
  if (routeResultList) {
    routeResultList.addEventListener('click', (e) => {
      const item = e.target.closest('[data-route-id]');
      if (!item) return;

      // UI highlight
      const siblings = routeResultList.querySelectorAll('.g-home-route-item');
      siblings.forEach(el => el.classList.remove('is-selected'));
      item.classList.add('is-selected');

      // Parse Geo
      try {
        const geo = JSON.parse(item.dataset.routeGeo || '[]');
        if (!Array.isArray(geo) || geo.length < 2) {
          console.warn('Invalid route geometry');
          return;
        }

        if (activeRouteLayer) map.removeLayer(activeRouteLayer);
        activeRouteLayer = L.polyline(geo, { weight: 6, opacity: 0.9, color: '#4d7c0f' }).addTo(map);
        map.fitBounds(activeRouteLayer.getBounds(), { padding: [24, 24] });

        selectedRouteId = item.dataset.routeId;
        currentRouteSelection = {
          routeId: selectedRouteId,
          from: fromInput.value,
          to: toInput.value,
          modes: { ...searchModes },
          geo: geo
        };
      } catch (err) {
        console.warn('Route geo parse error', err);
      }
    });
  }

  // -- Bottom Sheet
  // sheet toggle: 클릭은 "half<->collapsed" 순환
  if (sheetToggle && bottomSheet) {
    sheetToggle.addEventListener('click', function () {
      const cur = getSheetState();
      if (cur === 'collapsed') setSheetState('half');
      else if (cur === 'half') setSheetState('collapsed');
      else setSheetState('half'); // full에서 클릭하면 half로
    });
    
    // init detents (physics drag)
    if (window.GilaimeBottomSheetDetents) {
      sheetDetents = window.GilaimeBottomSheetDetents(bottomSheet, sheetToggle);
    }
  }

  tabButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      activateTab(btn.getAttribute('data-home-tab'));
    });
  });

  // location tracking toggle (optional)
  function stopTracking() {
    if (geoWatchId !== null) {
      try { navigator.geolocation.clearWatch(geoWatchId); } catch (_) {}
      geoWatchId = null;
    }
    if (trackToggle) {
      trackToggle.classList.remove('is-live');
      trackToggle.setAttribute('aria-pressed', 'false');
      trackToggle.setAttribute('aria-label', '실시간 위치 추적 끔');
      trackToggle.title = '실시간 위치 추적 끔';
    }
  }

  function startTracking() {
    if (!navigator.geolocation) return;

    geoWatchId = navigator.geolocation.watchPosition(function (pos) {
      if (!map) return;
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;

      if (!userMarker) {
        userMarker = L.circleMarker([lat, lng], { radius: 6 }).addTo(map);
      } else {
        userMarker.setLatLng([lat, lng]);
      }

      try { map.panTo([lat, lng], { animate: true, duration: 0.4 }); } catch (_) {}
    }, function (err) {
      console.warn('geolocation error', err);
      stopTracking();
    }, {
      enableHighAccuracy: true,
      maximumAge: 3000,
      timeout: 8000
    });

    if (trackToggle) {
      trackToggle.classList.add('is-live');
      trackToggle.setAttribute('aria-pressed', 'true');
      trackToggle.setAttribute('aria-label', '실시간 위치 추적 켬');
      trackToggle.title = '실시간 위치 추적 켬';
    }
  }

  if (trackToggle) {
    trackToggle.addEventListener('click', function () {
      if (!navigator.geolocation) {
        trackToggle.classList.add('is-unsupported');
        return;
      }
      if (geoWatchId !== null) stopTracking();
      else startTracking();
    });
  }

  // resize safety
  window.addEventListener('resize', function () {
    invalidateSoon();
  });

  // 5) IMPORTANT: call init
  try {
    initializeMap();
  } catch (e) {
    console.error('Home map init failed (outer)', e);
    showLoadingError('지도를 표시할 수 없습니다.');
  }

  function renderMockRouteResults(container, showBanner) {
    let html = '';
    if (showBanner) {
      html += `
        <div class="g-issue-banner-full mb-3" style="border:1px solid #e2e8f0; border-radius:8px;">
          <div class="g-issue-content mb-0">
            <div class="g-issue-icon-box"><span class="g-issue-emoji">🚌</span></div>
            <div class="g-issue-text">
              <strong>임시셔틀 포함 경로</strong>
              <p>현재 이슈로 인해 대체 경로가 자동 적용됩니다.</p>
            </div>
          </div>
        </div>`;
    }
    // Mock geometry: roughly Seoul area path
    const mockGeo = JSON.stringify([[37.48, 126.88], [37.49, 126.89], [37.50, 126.90], [37.51, 126.92]]);
    
    html += `
      <div class="g-home-route-item" data-route-id="r1" data-route-geo='${mockGeo}'>
        <strong>추천 경로 1 (클릭하여 지도 보기)</strong>
        <span>약 35분 · 도보 5분</span>
      </div>
    `;
    container.innerHTML = html;
  }
});