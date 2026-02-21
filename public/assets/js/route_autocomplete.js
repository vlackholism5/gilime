/**
 * v1.8: 경로 찾기 출발/도착 추천검색어 자동완성 (공통)
 * home.php, route_finder.php 등 경로 폼이 있는 모든 페이지에서 사용
 *
 * 사용: 페이지에 window.GILAIME_API_BASE 설정 후 본 스크립트 로드
 * HTML: .g-autocomplete-wrap 안에 input + .g-autocomplete-dropdown
 */
(function () {
  'use strict';

  var apiBase = (typeof window.GILAIME_API_BASE !== 'undefined') ? window.GILAIME_API_BASE : '';
  var DEBOUNCE_MS = 200;

  function debounce(fn, ms) {
    var t;
    return function () {
      clearTimeout(t);
      t = setTimeout(fn, ms);
    };
  }

  function fetchSuggestions(q, cb) {
    if (!q || q.trim().length < 1) { cb([]); return; }
    if (!apiBase) { cb([]); return; }
    fetch(apiBase + '/api/route/suggest_stops?q=' + encodeURIComponent(q.trim()))
      .then(function (r) { return r.json(); })
      .then(function (json) { cb(json.items || []); })
      .catch(function () { cb([]); });
  }

  function initAutocomplete(inputEl, dropdownEl) {
    if (!inputEl || !dropdownEl) return;

    function showDropdown(items) {
      dropdownEl.innerHTML = '';
      dropdownEl.setAttribute('aria-hidden', 'true');
      if (!items || items.length === 0) return;
      items.forEach(function (item) {
        var el = document.createElement('button');
        el.type = 'button';
        el.className = 'g-autocomplete-item';
        var label = item.display_label || (item.stop_name ? item.stop_name + ' · ' + (item.stop_id || '') : '');
        el.textContent = label || item.stop_name || '';
        el.dataset.stopName = item.stop_name || '';
        el.dataset.stopId = String(item.stop_id || '');
        el.dataset.displayLabel = label || item.stop_name || '';
        if (item.lat != null && item.lng != null) {
          el.dataset.lat = String(item.lat);
          el.dataset.lng = String(item.lng);
        }
        el.addEventListener('click', function (e) {
          e.preventDefault();
          inputEl.value = el.dataset.displayLabel || item.stop_name || '';
          dropdownEl.innerHTML = '';
          dropdownEl.setAttribute('aria-hidden', 'true');
          var inputId = inputEl.id || (inputEl.name === 'from' ? 'from' : inputEl.name === 'to' ? 'to' : '');
          if (inputId && el.dataset.lat && el.dataset.lng) {
            try {
              document.dispatchEvent(new CustomEvent('gilaime:route:place-select', {
                detail: {
                  inputId: inputId,
                  lat: parseFloat(el.dataset.lat),
                  lng: parseFloat(el.dataset.lng),
                  displayLabel: el.dataset.displayLabel || ''
                }
              }));
            } catch (err) {}
          }
        });
        dropdownEl.appendChild(el);
      });
      dropdownEl.setAttribute('aria-hidden', 'false');
    }

    function hideDropdown() {
      dropdownEl.innerHTML = '';
      dropdownEl.setAttribute('aria-hidden', 'true');
    }

    var doFetch = debounce(function () {
      var q = inputEl.value.trim();
      if (q.length < 1) { hideDropdown(); return; }
      fetchSuggestions(q, function (items) {
        showDropdown(items);
      });
    }, DEBOUNCE_MS);

    inputEl.addEventListener('input', doFetch);
    inputEl.addEventListener('focus', function () {
      if (inputEl.value.trim().length >= 1) { doFetch(); }
    });
    inputEl.addEventListener('blur', function () {
      setTimeout(hideDropdown, 150);
    });
    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { hideDropdown(); inputEl.blur(); }
    });
    document.addEventListener('click', function (e) {
      if (!dropdownEl.contains(e.target) && e.target !== inputEl) {
        hideDropdown();
      }
    });
  }

  function initAll(root) {
    var rootEl = (root && typeof root.querySelectorAll === 'function') ? root : document;
    var wraps = rootEl.querySelectorAll('.g-autocomplete-wrap');
    wraps.forEach(function (wrap) {
      var input = wrap.querySelector('input');
      var dropdown = wrap.querySelector('.g-autocomplete-dropdown');
      // 이미 초기화된 경우 건너뛰기
      if (input && dropdown && !input.dataset.autocompleteInit) {
        initAutocomplete(input, dropdown);
        input.dataset.autocompleteInit = 'true';
      }
    });
  }

  // Expose API
  window.GilimeAutocomplete = { initAll: initAll };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { initAll(document); });
  } else {
    initAll(document);
  }
})();
