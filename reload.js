// reload.js
// Safe noop live-reload stub to avoid missing WebSocket warnings in production
(function reloadStub(window) {
  if (!window) {
    return;
  }
  const logPrefix = '[reload]';

  function noop() {
    return {
      close: function close() {},
      send: function send() {}
    };
  }

  // Guard against third-party scripts attempting to create a live-reload socket.
  if (!('WebSocket' in window)) {
    console.debug(logPrefix, 'WebSocket unavailable; live reload disabled.');
    return;
  }

  // Provide a defensive wrapper that swallows connection failures.
  const NativeWebSocket = window.WebSocket;
  window.WebSocket = function SafeWebSocket(url, protocols) {
    if (typeof url === 'string') {
      const normalizedUrl = url.split('?')[0];
      if (/\/(ws|sockjs)(\/|$)/i.test(normalizedUrl)) {
        console.debug(logPrefix, 'Live reload socket disabled for', url);
        return noop();
      }
    }
    try {
      return new NativeWebSocket(url, protocols);
    } catch (error) {
      console.debug(logPrefix, 'WebSocket fallback used:', error && error.message ? error.message : error);
      return noop();
    }
  };
  window.WebSocket.prototype = NativeWebSocket.prototype;
})(typeof window !== 'undefined' ? window : undefined);
