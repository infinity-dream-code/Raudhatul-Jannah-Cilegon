(function (global) {
  "use strict";

  var counts = new WeakMap();

  function resolveHost(host) {
    if (!host) return null;
    if (typeof host === "string") return document.getElementById(host);
    if (host.nodeType === 1) return host;
    return null;
  }

  function ensureOverlay(el, message) {
    el.classList.add("presensi-loading-host");
    var overlay = el.querySelector(":scope > .presensi-loading");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.className = "presensi-loading";
      overlay.setAttribute("role", "status");
      overlay.setAttribute("aria-live", "polite");
      overlay.innerHTML =
        '<div class="presensi-loading__panel">' +
        '<div class="presensi-loading__spinner" aria-hidden="true"></div>' +
        '<p class="presensi-loading__text"></p>' +
        "</div>";
      el.appendChild(overlay);
    }
    var textEl = overlay.querySelector(".presensi-loading__text");
    if (textEl) textEl.textContent = message || "Memuat data…";
    overlay.hidden = false;
    return overlay;
  }

  function show(host, message) {
    var el = resolveHost(host);
    if (!el) return;
    var n = (counts.get(el) || 0) + 1;
    counts.set(el, n);
    ensureOverlay(el, message);
  }

  function setMessage(host, message) {
    var el = resolveHost(host);
    if (!el) return;
    var textEl = el.querySelector(":scope > .presensi-loading .presensi-loading__text");
    if (textEl) textEl.textContent = message || "Memuat data…";
  }

  function hide(host) {
    var el = resolveHost(host);
    if (!el) return;
    var n = (counts.get(el) || 1) - 1;
    if (n <= 0) {
      counts.delete(el);
      var overlay = el.querySelector(":scope > .presensi-loading");
      if (overlay) overlay.remove();
      el.classList.remove("presensi-loading-host");
      return;
    }
    counts.set(el, n);
  }

  global.PresensiLoading = {
    show: show,
    hide: hide,
    setMessage: setMessage,
  };
})(typeof window !== "undefined" ? window : globalThis);
