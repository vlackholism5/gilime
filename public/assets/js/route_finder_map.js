/**
 * 길찾기 지도 기반 UI — Leaflet 지도, 출발/도착 마커, 경로 폴리라인
 * @see docs/ux/UX_ROUTE_FINDER_MAP_BASED_v1.md
 */
(function () {
  'use strict';

  var SEOUL_CENTER = [37.5665, 126.978];
  var DEFAULT_ZOOM = 12;

  var map = null;
  var markerFrom = null;
  var markerTo = null;
  var routeLine = null;
  var routeLines = [];

  function initMap() {
    var wrap = document.getElementById('g-route-map');
    if (!wrap) return;

    map = L.map('g-route-map').setView(SEOUL_CENTER, DEFAULT_ZOOM);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    var cfg = window.GILAIME_ROUTE_MAP || {};
    var fromCoords = cfg.fromCoords;
    var toCoords = cfg.toCoords;
    var step = cfg.step || '';
    var routeOptions = Array.isArray(cfg.routeOptions) ? cfg.routeOptions : [];

    if (fromCoords && fromCoords.lat != null && fromCoords.lng != null) {
      setMarker('from', fromCoords.lat, fromCoords.lng);
    }
    if (toCoords && toCoords.lat != null && toCoords.lng != null) {
      setMarker('to', toCoords.lat, toCoords.lng);
    }

    if (step === 'result' && fromCoords && toCoords && fromCoords.lat != null && toCoords.lat != null) {
      drawRouteOptions(routeOptions, [fromCoords.lat, fromCoords.lng], [toCoords.lat, toCoords.lng]);
      fitMapToMarkers();
    } else if (markerFrom || markerTo) {
      fitMapToMarkers();
    }

    document.addEventListener('gilaime:route:place-select', function (e) {
      var d = e.detail || {};
      var inputId = d.inputId;
      var lat = d.lat;
      var lng = d.lng;
      if (inputId !== 'from' && inputId !== 'to') return;
      if (typeof lat !== 'number' || typeof lng !== 'number') return;
      setMarker(inputId, lat, lng);
      fitMapToMarkers();
    });
    document.addEventListener('gilaime:route:select', function (e) {
      var d = e.detail || {};
      highlightRoute(typeof d.idx === 'number' ? d.idx : 0);
    });
  }

  function setMarker(which, lat, lng) {
    var latlng = [lat, lng];
    var opts = {
      draggable: false,
      title: which === 'from' ? '출발지' : '도착지'
    };
    var color = which === 'from' ? '#2563eb' : '#dc2626';
    var icon = L.divIcon({
      className: 'g-route-marker g-route-marker-' + which,
      html: '<span style="background:' + color + ';width:24px;height:24px;border-radius:50%;display:block;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);"></span>',
      iconSize: [24, 24],
      iconAnchor: [12, 12]
    });
    var marker = L.marker(latlng, { icon: icon, ...opts }).addTo(map);

    if (which === 'from') {
      if (markerFrom) map.removeLayer(markerFrom);
      markerFrom = marker;
    } else {
      if (markerTo) map.removeLayer(markerTo);
      markerTo = marker;
    }
  }

  function drawRouteLine(latLngs) {
    if (routeLine) {
      map.removeLayer(routeLine);
      routeLine = null;
    }
    if (!latLngs || latLngs.length < 2) return;
    routeLine = L.polyline(latLngs, {
      color: '#4d7c0f',
      weight: 5,
      opacity: 0.8
    }).addTo(map);
  }

  function drawRouteOptions(routeOptions, fromLatLng, toLatLng) {
    routeLines.forEach(function (line) { map.removeLayer(line); });
    routeLines = [];
    if (!Array.isArray(routeOptions) || routeOptions.length === 0) {
      drawRouteLine([fromLatLng, toLatLng]);
      return;
    }
    routeOptions.forEach(function (r, idx) {
      var line = createOffsetLine(fromLatLng, toLatLng, idx, routeOptions.length);
      var color = (r.route_type === 'shuttle_temp') ? '#7c3aed' : '#2563eb';
      var poly = L.polyline(line, {
        color: color,
        weight: idx === 0 ? 7 : 5,
        opacity: idx === 0 ? 0.9 : 0.5
      }).addTo(map);
      routeLines.push(poly);
    });
  }

  function createOffsetLine(fromLatLng, toLatLng, idx, total) {
    var fLat = fromLatLng[0], fLng = fromLatLng[1];
    var tLat = toLatLng[0], tLng = toLatLng[1];
    var mx = (fLng + tLng) / 2;
    var my = (fLat + tLat) / 2;
    var dx = tLng - fLng;
    var dy = tLat - fLat;
    var len = Math.sqrt(dx * dx + dy * dy) || 0.00001;
    var nx = -dy / len;
    var ny = dx / len;
    var centerOffset = (idx - (total - 1) / 2) * 0.0025;
    var cLat = my + ny * centerOffset;
    var cLng = mx + nx * centerOffset;
    return [[fLat, fLng], [cLat, cLng], [tLat, tLng]];
  }

  function highlightRoute(idx) {
    if (!routeLines || routeLines.length === 0) return;
    routeLines.forEach(function (line, i) {
      line.setStyle({
        weight: i === idx ? 7 : 5,
        opacity: i === idx ? 0.92 : 0.35
      });
    });
  }

  function fitMapToMarkers() {
    var bounds = [];
    if (markerFrom) bounds.push(markerFrom.getLatLng());
    if (markerTo) bounds.push(markerTo.getLatLng());
    if (bounds.length === 0) return;
    if (bounds.length === 1) {
      map.setView(bounds[0], 15);
    } else {
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
  } else {
    initMap();
  }
})();
