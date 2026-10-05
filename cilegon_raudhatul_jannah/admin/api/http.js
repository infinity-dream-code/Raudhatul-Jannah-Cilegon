(function (global) {
  "use strict";

  function getConfig() {
    return global.PresensiApiConfig || {};
  }

  function isEnabled() {
    var c = getConfig();
    return Boolean(c.enabled);
  }

  function joinUrl(base, path) {
    var b = String(base || "").replace(/\/+$/, "");
    var p = String(path || "").replace(/^\/+/, "");
    return b + "/" + p;
  }

  /**
   * Prefix ke root app dari halaman saat ini (data-app-root di <body>).
   * settings/*.html pakai ".." → ../api/...
   */
  function getAppPrefix() {
    if (typeof document === "undefined" || !document.body) {
      return "";
    }
    var root = document.body.getAttribute("data-app-root");
    if (root == null || String(root).trim() === "") {
      return "";
    }
    return String(root).replace(/\/?$/, "/");
  }

  /** Deteksi base path app dari URL (mis. /Malang_Artri_Face/admin). */
  function detectAppPath() {
    if (typeof location === "undefined") {
      return "";
    }
    var p = location.pathname || "";
    var markers = ["/settings/", "/modules/", "/ortu/"];
    for (var i = 0; i < markers.length; i++) {
      var idx = p.indexOf(markers[i]);
      if (idx > 0) {
        return p.slice(0, idx);
      }
    }
    var adminIdx = p.indexOf("/admin/");
    if (adminIdx >= 0) {
      return p.slice(0, adminIdx + "/admin".length);
    }
    if (/\/admin$/i.test(p)) {
      return p;
    }
    return "";
  }

  function resolveAppBase(c) {
    var configured = String(c.appPath || "").trim();
    if (configured) {
      return configured.replace(/\/+$/, "");
    }
    return detectAppPath();
  }

  function buildUrl(pathSegment) {
    var c = getConfig();
    var path = String(pathSegment || "").replace(/^\/+/, "");

    var base = String(c.baseUrl || "").trim();
    if (base && /^https?:\/\//i.test(base)) {
      return joinUrl(base, path);
    }

    var appBase = resolveAppBase(c);
    if (appBase) {
      return joinUrl(appBase, path);
    }
    if (base) {
      return joinUrl(base, path);
    }

    var prefix = getAppPrefix();
    return prefix ? prefix + path : path;
  }

  function extractArray(body) {
    if (Array.isArray(body)) return body;
    if (!body || typeof body !== "object") return [];
    if (Array.isArray(body.data)) return body.data;
    if (Array.isArray(body.items)) return body.items;
    if (Array.isArray(body.siswa)) return body.siswa;
    if (Array.isArray(body.results)) return body.results;
    return [];
  }

  function extractObject(body) {
    if (!body || typeof body !== "object" || Array.isArray(body)) return body || {};
    if (body.data && typeof body.data === "object" && !Array.isArray(body.data)) return body.data;
    return body;
  }

  function request(method, pathSegment, options) {
    var c = getConfig();
    var opts = options || {};
    var url = buildUrl(pathSegment);
    var timeoutMs = c.timeoutMs || 30000;
    var controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = controller
      ? setTimeout(function () {
          controller.abort();
        }, timeoutMs)
      : null;

    var headers = Object.assign(
      { Accept: "application/json" },
      c.headers || {},
      opts.headers || {}
    );

    var init = {
      method: method,
      headers: headers,
      credentials: opts.credentials || "omit",
      mode: "cors",
    };

    if (controller) init.signal = controller.signal;

    if (opts.body != null && method !== "GET" && method !== "HEAD") {
      if (typeof opts.body === "string") {
        init.body = opts.body;
      } else {
        headers["Content-Type"] = headers["Content-Type"] || "application/json";
        init.body = JSON.stringify(opts.body);
      }
      init.headers = headers;
    }

    return fetch(url, init)
      .then(function (res) {
        if (timer) clearTimeout(timer);
        return res.text().then(function (text) {
          var parsed = null;
          if (text) {
            try {
              parsed = JSON.parse(text);
            } catch (e) {
              parsed = text;
            }
          }
          if (!res.ok) {
            var msg =
              (parsed && parsed.message) ||
              (parsed && parsed.error) ||
              "HTTP " + res.status + " " + res.statusText;
            var err = new Error(String(msg));
            err.status = res.status;
            err.body = parsed;
            throw err;
          }
          return parsed;
        });
      })
      .catch(function (err) {
        if (timer) clearTimeout(timer);
        if (err && err.name === "AbortError") {
          throw new Error("Permintaan ke server melebihi batas waktu (" + timeoutMs + " ms).");
        }
        throw err;
      });
  }

  global.PresensiApiHttp = {
    isEnabled: isEnabled,
    getConfig: getConfig,
    buildUrl: buildUrl,
    joinUrl: joinUrl,
    request: request,
    extractArray: extractArray,
    extractObject: extractObject,
  };
})(typeof window !== "undefined" ? window : globalThis);
