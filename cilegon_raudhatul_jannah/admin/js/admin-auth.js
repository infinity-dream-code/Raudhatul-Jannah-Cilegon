/**
 * Autentikasi admin (sisi klien).
 *
 * Mode saat ini: admin tanpa login (guard dilewati).
 * Tombol Keluar mengarah ke portal pusat SmartPayment.
 */
(function (global) {
  "use strict";

  var PORTAL_HOME = "https://raudhatuljannah.smartpayment.co.id/portal";

  function appPrefix() {
    if (typeof document === "undefined" || !document.body) return "";
    var root = document.body.getAttribute("data-app-root");
    if (root == null || String(root).trim() === "") return "";
    return String(root).replace(/\/?$/, "/");
  }

  function apiUrl(name) {
    var n = String(name || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(n)) n += ".php";
    if (global.PresensiApiHttp && global.PresensiApiHttp.buildUrl) {
      return global.PresensiApiHttp.buildUrl("api/" + n);
    }
    return appPrefix() + "api/" + n;
  }

  function pageUrl(name) {
    return appPrefix() + name;
  }

  function goPortal() {
    location.replace(PORTAL_HOME);
  }

  function requestJson(method, name, body) {
    var init = {
      method: method,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    };
    if (body != null && method !== "GET") {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }
    return fetch(apiUrl(name), init).then(function (res) {
      return res.text().then(function (text) {
        var parsed = null;
        if (text) {
          try {
            parsed = JSON.parse(text);
          } catch (e) {
            parsed = { ok: false, error: text.slice(0, 200) };
          }
        }
        if (!res.ok || (parsed && parsed.ok === false)) {
          var msg = (parsed && parsed.error) || "HTTP " + res.status + " " + res.statusText;
          var err = new Error(String(msg));
          err.status = res.status;
          throw err;
        }
        return parsed;
      });
    });
  }

  function me() {
    return Promise.resolve({
      username: "admin",
      nama: "Admin",
      role: "admin",
    });
  }

  function login() {
    return me();
  }

  function logout() {
    return requestJson("POST", "auth-logout.php", {}).catch(function () {
      return null;
    });
  }

  function changePassword(oldPassword, newPassword) {
    return requestJson("POST", "auth-change-password.php", {
      oldPassword: oldPassword,
      newPassword: newPassword,
    });
  }

  function injectHeaderUser() {
    var bar = document.querySelector(".top-bar__inner");
    if (!bar || bar.querySelector(".admin-user")) return;
    var box = document.createElement("div");
    box.className = "admin-user";
    box.innerHTML =
      '<button type="button" class="btn btn--ghost btn--small admin-user__logout" id="admin-logout">Keluar</button>';
    bar.appendChild(box);
    var btn = box.querySelector("#admin-logout");
    if (btn) {
      btn.addEventListener("click", function () {
        btn.disabled = true;
        logout().finally(function () {
          goPortal();
        });
      });
    }
  }

  /** Tanpa login: langsung izinkan akses admin. */
  function guard() {
    document.documentElement.dataset.adminAuthed = "1";
    injectHeaderUser();
  }

  function initLoginPage() {
    // Login admin dinonaktifkan — arahkan ke beranda admin.
    location.replace(pageUrl("index.html"));
  }

  global.AdminAuth = {
    PORTAL_HOME: PORTAL_HOME,
    apiUrl: apiUrl,
    me: me,
    login: login,
    logout: logout,
    changePassword: changePassword,
    guard: guard,
    goPortal: goPortal,
  };

  function boot() {
    if (document.body && document.body.hasAttribute("data-admin-login")) {
      initLoginPage();
    } else {
      guard();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(typeof window !== "undefined" ? window : globalThis);
