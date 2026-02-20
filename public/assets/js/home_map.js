/**
 * Home map (Leaflet): lightweight base map for main home.
 * Shared style classes are defined in gilaime_ui.css.
 */
(function () {
  'use strict';

  var map = null;
  var homeMarker = null;
  var placeLayer = null;
  var routeLayer = null;
  var placeFocusMarker = null;
  var targetMarker = null;
  var routeLine = null;
  var movingMarker = null;
  var movingTimer = null;
  var sheetState = 'half';
  var userWatchId = null;
  var isLiveTracking = false;
  var mapFilterState = {
    temporary_shuttle: false,
    construction: false,
    congestion: false
  };

  function byId(id) { return document.getElementById(id); }

  function buildMarkerIcon(type) {
    var fill = '#a3e635';
    var stroke = '#4d7c0f';
    var center = '#ffffff';
    if (type === 'target') {
      fill = '#84cc16';
      stroke = '#3f6212';
    } else if (type === 'route') {
      fill = '#65a30d';
      stroke = '#365314';
    }
    var svg = '' +
      '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="38" viewBox="0 0 30 38" aria-hidden="true">' +
      '  <path d="M15 1C8.4 1 3 6.4 3 13c0 9.6 12 23 12 23s12-13.4 12-23C27 6.4 21.6 1 15 1z" fill="' + fill + '" stroke="' + stroke + '" stroke-width="1.5"/>' +
      '  <circle cx="15" cy="13" r="4.2" fill="' + center + '"/>' +
      '</svg>';
    return L.divIcon({
      className: 'g-home-svg-marker g-home-svg-marker-' + type,
      html: svg,
      iconSize: [30, 38],
      iconAnchor: [15, 37],
      popupAnchor: [0, -30]
    });
  }

  function initHomeMap() {
    var el = document.getElementById('g-home-map');
    if (!el || typeof L === 'undefined') return;
    var cfg = window.GILAIME_HOME_MAP || { lat: 37.5665, lng: 126.9780 };
    map = L.map(el, { zoomControl: false, attributionControl: false }).setView([cfg.lat, cfg.lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    placeLayer = L.layerGroup().addTo(map);
    routeLayer = L.layerGroup();
    homeMarker = L.marker([cfg.lat, cfg.lng], {
      title: '현재 위치',
      icon: buildMarkerIcon('home')
    }).addTo(map);

    // 브라우저 위치 권한이 허용되면 실제 현재 위치로 갱신
    tryLocateUserPosition(cfg);

    bindTabUi();
    bindSheetUi();
    bindMapActionButtons();
    bindLiveTrackingUi();
    bindFilterChips();
  }

  function tryLocateUserPosition(cfg) {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (pos) {
      var lat = pos && pos.coords ? Number(pos.coords.latitude) : NaN;
      var lng = pos && pos.coords ? Number(pos.coords.longitude) : NaN;
      if (!isFinite(lat) || !isFinite(lng)) return;
      updateHomeMarker(lat, lng);
      // 초기 위치와 유사하면 과도한 이동 없이, 충분히 다르면 사용자 위치로 이동
      var baseLat = Number(cfg && cfg.lat);
      var baseLng = Number(cfg && cfg.lng);
      var distance = Math.abs(lat - baseLat) + Math.abs(lng - baseLng);
      if (map && distance > 0.01) {
        map.setView([lat, lng], 15);
      }
    }, function () {
      // 권한 거부/오류 시 기본 좌표 유지
    }, {
      enableHighAccuracy: true,
      timeout: 5000,
      maximumAge: 60000
    });
  }

  function updateHomeMarker(lat, lng) {
    if (!isFinite(lat) || !isFinite(lng)) return;
    if (homeMarker) {
      homeMarker.setLatLng([lat, lng]);
      return;
    }
    if (!map) return;
    homeMarker = L.marker([lat, lng], {
      title: '현재 위치',
      icon: buildMarkerIcon('home')
    }).addTo(map);
  }

  function updateLiveTrackingButton() {
    var btn = byId('g-home-track-toggle');
    if (!btn) return;
    btn.classList.toggle('is-live', isLiveTracking);
    if (isLiveTracking) btn.classList.remove('is-unsupported');
    btn.setAttribute('aria-pressed', isLiveTracking ? 'true' : 'false');
    var label = isLiveTracking ? '실시간 위치 추적 켬' : '실시간 위치 추적 끔';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }

  function stopLiveTracking() {
    if (userWatchId != null && navigator.geolocation) {
      navigator.geolocation.clearWatch(userWatchId);
    }
    userWatchId = null;
    isLiveTracking = false;
    updateLiveTrackingButton();
  }

  function bindLiveTrackingUi() {
    var btn = byId('g-home-track-toggle');
    if (!btn) return;
    updateLiveTrackingButton();
    btn.addEventListener('click', function () {
      if (!navigator.geolocation) {
        isLiveTracking = false;
        btn.classList.add('is-unsupported');
        btn.setAttribute('aria-label', '이 브라우저는 위치 기능을 지원하지 않음');
        btn.setAttribute('title', '이 브라우저는 위치 기능을 지원하지 않음');
        return;
      }
      if (isLiveTracking) {
        stopLiveTracking();
        return;
      }
      userWatchId = navigator.geolocation.watchPosition(function (pos) {
        var lat = pos && pos.coords ? Number(pos.coords.latitude) : NaN;
        var lng = pos && pos.coords ? Number(pos.coords.longitude) : NaN;
        if (!isFinite(lat) || !isFinite(lng)) return;
        isLiveTracking = true;
        btn.classList.remove('is-unsupported');
        updateLiveTrackingButton();
        updateHomeMarker(lat, lng);
        if (map) {
          map.panTo([lat, lng], { animate: true, duration: 0.35 });
        }
      }, function () {
        stopLiveTracking();
      }, {
        enableHighAccuracy: true,
        timeout: 8000,
        maximumAge: 2000
      });
    });
  }

  function bindTabUi() {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-home-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-home-panel]'));
    if (tabs.length === 0 || panels.length === 0) return;
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-home-tab');
        tabs.forEach(function (t) { t.classList.remove('active'); });
        panels.forEach(function (p) { p.classList.remove('active'); });
        tab.classList.add('active');
        var panel = document.querySelector('[data-home-panel="' + id + '"]');
        if (panel) panel.classList.add('active');
        switchMapLayer(id);
      });
    });
  }

  function bindSheetUi() {
    var sheet = byId('g-home-bottom-sheet');
    var toggle = byId('g-home-sheet-toggle');
    if (!sheet || !toggle) return;
    var dragStartY = 0;
    var dragStartTranslate = 0;
    var dragging = false;
    var dragPointerId = null;
    var sheetTranslate = 0;

    function applySheetState(next) {
      sheet.classList.remove('is-collapsed', 'is-half', 'is-full');
      sheet.classList.add('is-' + next);
      sheetState = next;
      sheet.style.transform = '';
      sheetTranslate = getTranslateForState(next);
    }

    function getTranslateForState(state) {
      var h = Math.max(220, sheet.offsetHeight || 300);
      if (state === 'collapsed') return h - 48;
      if (state === 'full') return h * -0.22;
      return h * 0.18;
    }

    function snapByTranslate(v) {
      var collapsed = getTranslateForState('collapsed');
      var half = getTranslateForState('half');
      var full = getTranslateForState('full');
      var candidates = [
        { state: 'collapsed', t: collapsed },
        { state: 'half', t: half },
        { state: 'full', t: full }
      ];
      candidates.sort(function (a, b) {
        return Math.abs(a.t - v) - Math.abs(b.t - v);
      });
      applySheetState(candidates[0].state);
    }

    toggle.addEventListener('click', function () {
      if (sheetState === 'collapsed') applySheetState('half');
      else if (sheetState === 'half') applySheetState('full');
      else applySheetState('collapsed');
    });

    var moveHistory = [];

    function setMapInteractivity(enabled) {
      if (!map) return;
      var method = enabled ? 'enable' : 'disable';
      map.dragging[method]();
      map.touchZoom[method]();
      map.doubleClickZoom[method]();
      map.scrollWheelZoom[method]();
      map.boxZoom[method]();
      map.keyboard[method]();
      if (map.tap && map.tap[method]) map.tap[method]();
    }

    toggle.addEventListener('pointerdown', function (e) {
      dragging = true;
      dragPointerId = e.pointerId;
      dragStartY = e.clientY;
      dragStartTranslate = getTranslateForState(sheetState);
      moveHistory = [{ y: e.clientY, t: Date.now() }];
      toggle.setPointerCapture(e.pointerId);
      sheet.classList.add('is-dragging');
      setMapInteractivity(false);
    });

    toggle.addEventListener('pointermove', function (e) {
      if (!dragging || e.pointerId !== dragPointerId) return;
      var delta = e.clientY - dragStartY;
      var full = getTranslateForState('full');
      var collapsed = getTranslateForState('collapsed');
      sheetTranslate = Math.max(full, Math.min(collapsed, dragStartTranslate + delta));
      sheet.style.transform = 'translateY(' + sheetTranslate + 'px)';
      moveHistory.push({ y: e.clientY, t: Date.now() });
      if (moveHistory.length > 8) moveHistory.shift();
    });

    function endDragAndSnap(e) {
      if (!dragging || e.pointerId !== dragPointerId) return;
      var velocity = 0;
      if (moveHistory.length >= 2) {
        var a = moveHistory[moveHistory.length - 2];
        var b = moveHistory[moveHistory.length - 1];
        var dt = Math.max(1, b.t - a.t);
        velocity = (b.y - a.y) / dt; // px/ms (+down, -up)
      }
      dragging = false;
      dragPointerId = null;
      sheet.classList.remove('is-dragging');
      setMapInteractivity(true);

      if (velocity > 0.55) {
        applySheetState('collapsed');
      } else if (velocity < -0.55) {
        applySheetState('full');
      } else {
        snapByTranslate(sheetTranslate);
      }
      if (e.pointerId != null && toggle.hasPointerCapture(e.pointerId)) {
        toggle.releasePointerCapture(e.pointerId);
      }
    }

    toggle.addEventListener('pointerup', function (e) {
      endDragAndSnap(e);
    });

    toggle.addEventListener('pointercancel', function (e) {
      endDragAndSnap(e);
    });
  }

  function bindMapActionButtons() {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-map-lat][data-map-lng]'));
    if (buttons.length === 0) return;
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var lat = parseFloat(btn.getAttribute('data-map-lat') || '');
        var lng = parseFloat(btn.getAttribute('data-map-lng') || '');
        var label = btn.getAttribute('data-map-label') || '';
        if (!isFinite(lat) || !isFinite(lng)) return;
        if (btn.classList.contains('g-home-route-item')) {
          switchMapLayer('route');
          drawMovingRoute([lat, lng], label);
          activateMainTab('route');
        } else {
          switchMapLayer('issue');
          focusPlace([lat, lng], label);
        }
      });
    });
  }

  function activateMainTab(tabId) {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-home-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-home-panel]'));
    tabs.forEach(function (t) {
      t.classList.toggle('active', t.getAttribute('data-home-tab') === tabId);
    });
    panels.forEach(function (p) {
      p.classList.toggle('active', p.getAttribute('data-home-panel') === tabId);
    });
  }

  function switchMapLayer(mode) {
    if (!map) return;
    if (mode === 'route') {
      if (map.hasLayer(placeLayer)) map.removeLayer(placeLayer);
      if (!map.hasLayer(routeLayer)) routeLayer.addTo(map);
    } else {
      if (map.hasLayer(routeLayer)) map.removeLayer(routeLayer);
      if (!map.hasLayer(placeLayer)) placeLayer.addTo(map);
    }
  }

  function bindFilterChips() {
    var chips = Array.prototype.slice.call(document.querySelectorAll('#g-home-filter-chips [data-chip]'));
    if (chips.length === 0) return;
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var chipId = chip.getAttribute('data-chip');
        var chipType = chip.getAttribute('data-chip-type');
        if (chip.hasAttribute('disabled')) return;
        if (chipType === 'mode') {
          chips.forEach(function (c) {
            if (c.getAttribute('data-chip-type') === 'mode') c.classList.remove('active');
          });
          chip.classList.add('active');
          if (chipId === 'issue') {
            activateMainTab('issue');
            switchMapLayer('issue');
          }
          return;
        }
        var next = !chip.classList.contains('active');
        chip.classList.toggle('active', next);
        mapFilterState[chipId] = next;
        applyRouteStyleByFilters();
      });
    });
  }

  function applyRouteStyleByFilters() {
    if (!routeLine) return;
    var color = mapFilterState.temporary_shuttle ? '#03c75a' : '#2f80ff';
    routeLine.setStyle({
      color: color,
      dashArray: mapFilterState.construction ? '8 6' : null,
      opacity: 0.92
    });
  }

  function focusPlace(toLatLng, label) {
    if (!map) return;
    if (placeFocusMarker) placeLayer.removeLayer(placeFocusMarker);
    placeFocusMarker = L.marker([toLatLng[0], toLatLng[1]], {
      title: label || '장소',
      icon: buildMarkerIcon('place')
    }).addTo(placeLayer);
    map.setView([toLatLng[0], toLatLng[1]], 15);
  }

  function drawMovingRoute(toLatLng, label) {
    if (!map || !homeMarker) return;
    var from = homeMarker.getLatLng();
    var to = L.latLng(toLatLng[0], toLatLng[1]);

    if (routeLine) routeLayer.removeLayer(routeLine);
    if (targetMarker) routeLayer.removeLayer(targetMarker);
    if (movingMarker) routeLayer.removeLayer(movingMarker);
    if (movingTimer) window.clearInterval(movingTimer);

    routeLine = L.polyline([[from.lat, from.lng], [to.lat, to.lng]], {
      color: '#2f80ff',
      weight: 6,
      opacity: 0.9
    }).addTo(routeLayer);
    targetMarker = L.marker([to.lat, to.lng], {
      title: label || '목적지',
      icon: buildMarkerIcon('target')
    }).addTo(routeLayer);
    movingMarker = L.circleMarker([from.lat, from.lng], {
      radius: 7,
      color: '#4d7c0f',
      fillColor: '#a3e635',
      fillOpacity: 1
    }).addTo(routeLayer);

    var step = 0;
    var max = 40;
    movingTimer = window.setInterval(function () {
      step += 1;
      var t = step / max;
      var lat = from.lat + ((to.lat - from.lat) * t);
      var lng = from.lng + ((to.lng - from.lng) * t);
      movingMarker.setLatLng([lat, lng]);
      if (step >= max) {
        window.clearInterval(movingTimer);
        movingTimer = null;
      }
    }, 40);
    applyRouteStyleByFilters();
    map.fitBounds(routeLine.getBounds(), { padding: [35, 35], maxZoom: 15 });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHomeMap);
  } else {
    initHomeMap();
  }
})();

