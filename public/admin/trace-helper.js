/**
 * Trace helper for API calls. Include when calling /api/* with fetch.
 * Set window.__GILIME_DEBUG__ = true (or server injects) for console logs.
 */
(function () {
  function traceId() {
    return 'trc_' + new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '').replace(' ', '_') + '_' + Math.random().toString(16).slice(2, 8);
  }
  function log(tid, event, extra) {
    if (typeof window !== 'undefined' && window.__GILIME_DEBUG__) {
      var msg = '[TRACE ' + tid + '] ' + event;
      if (extra) msg += ' ' + JSON.stringify(extra);
      console.log(msg);
    }
  }
  window.GilimeTrace = {
    createId: traceId,
    /** Augment fetch options with X-Trace-Id and optional body.trace_id. */
    withTrace: function (options, existingTraceId) {
      var tid = existingTraceId || traceId();
      options = options || {};
      options.headers = options.headers || {};
      options.headers['X-Trace-Id'] = tid;
      if (options.body && typeof options.body === 'string') {
        try {
          var o = JSON.parse(options.body);
          o.trace_id = tid;
          options.body = JSON.stringify(o);
        } catch (e) {}
      }
      return { options: options, traceId: tid };
    },
    /** Wrap fetch: add trace, log when debug. */
    fetch: function (url, options, existingTraceId) {
      var tid = existingTraceId || traceId();
      var opt = options || {};
      opt.headers = opt.headers || {};
      opt.headers['X-Trace-Id'] = tid;
      if (opt.body && typeof opt.body === 'string') {
        try {
          var o = JSON.parse(opt.body);
          o.trace_id = tid;
          opt.body = JSON.stringify(o);
        } catch (e) {}
      }
      log(tid, 'request', { url: url });
      return fetch(url, opt).then(function (r) {
        log(tid, 'response', { status: r.status });
        return r;
      });
    },
  };
})();
