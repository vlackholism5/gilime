(function () {
  function toSearchBox() {
    var q = document.querySelector('input[type="search"], input[name="q"], input[name="route_label"]');
    if (q) q.focus();
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      var tag = (document.activeElement && document.activeElement.tagName) || '';
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
        e.preventDefault();
        toSearchBox();
      }
    }
  });
})();
