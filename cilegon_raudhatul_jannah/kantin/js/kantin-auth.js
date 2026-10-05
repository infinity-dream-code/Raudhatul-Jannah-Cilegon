/**
 * Auth kantin — session PHP via /kantin/api/*
 */
(function (global) {
  "use strict";

  var PORTAL_HOME = "https://raudhatuljannah.smartpayment.co.id/portal";

  function goPortal() {
    location.replace(PORTAL_HOME);
  }

  function apiUrl(file) {
    var name = String(file || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(name)) name += ".php";
    var path = location.pathname || "";
    var base = path.replace(/\/[^/]*$/, "/");
    if (/\/kantin\/?$/i.test(path)) base = path.replace(/\/?$/, "/");
    // login.html & index.html di /kantin/
    if (path.indexOf("/kantin/") >= 0) {
      var root = path.slice(0, path.indexOf("/kantin/") + "/kantin/".length);
      return root + "api/" + name;
    }
    return base + "api/" + name;
  }

  function requestJson(method, url, body) {
    var init = {
      method: method,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    };
    if (body != null && method !== "GET" && method !== "HEAD") {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }
    return fetch(url, init).then(function (res) {
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
          var msg =
            (parsed && parsed.error) ||
            (parsed && parsed.message) ||
            "HTTP " + res.status;
          var err = new Error(String(msg));
          err.status = res.status;
          err.body = parsed;
          throw err;
        }
        return parsed;
      });
    });
  }

  function me() {
    return requestJson("GET", apiUrl("me.php")).then(function (body) {
      return body && body.data ? body.data : null;
    });
  }

  function login(username, password) {
    return requestJson("POST", apiUrl("login.php"), {
      username: username,
      password: password,
    }).then(function (body) {
      return body && body.data ? body.data : null;
    });
  }

  function logout() {
    return requestJson("POST", apiUrl("logout.php"), {});
  }

  function requireSession() {
    return me().catch(function () {
      location.replace("login.html");
      return null;
    });
  }

  function initLoginPage() {
    var form = document.getElementById("kantin-login-form");
    var errEl = document.getElementById("kantin-login-error");
    var btn = document.getElementById("kantin-login-submit");
    if (!form) return;

    // Sudah login? → index
    me()
      .then(function () {
        location.replace("index.html");
      })
      .catch(function () {});

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (errEl) {
        errEl.hidden = true;
        errEl.textContent = "";
      }
      var user = (document.getElementById("kantin-username") || {}).value || "";
      var pass = (document.getElementById("kantin-password") || {}).value || "";
      user = String(user).trim();
      if (!user || !pass) {
        if (errEl) {
          errEl.hidden = false;
          errEl.textContent = "Isi username dan kata sandi.";
        }
        return;
      }
      if (btn) {
        btn.disabled = true;
        btn.textContent = "Masuk…";
      }
      login(user, pass)
        .then(function () {
          location.replace("index.html");
        })
        .catch(function (err) {
          if (errEl) {
            errEl.hidden = false;
            errEl.textContent = (err && err.message) || "Login gagal";
          }
        })
        .finally(function () {
          if (btn) {
            btn.disabled = false;
            btn.textContent = "Masuk";
          }
        });
    });
  }

  function changePassword(password, newPassword, newPassword2) {
    return requestJson("POST", apiUrl("change-password.php"), {
      password: password,
      newPassword: newPassword,
      newPassword2: newPassword2,
    });
  }

  function toastAuth(msg, kind) {
    var text = String(msg || "").trim();
    if (!text) return;
    var type = kind === "danger" || kind === "warn" || kind === "ok" ? kind : "ok";
    var titles = {
      ok: "Berhasil",
      warn: "Perhatian",
      danger: "Gagal",
    };
    var icons = { ok: "✓", warn: "!", danger: "!" };

    if (typeof Toastify === "function") {
      var html =
        '<div class="k-toastify__inner">' +
        '<span class="k-toastify__icon" aria-hidden="true">' +
        icons[type] +
        "</span>" +
        '<div class="k-toastify__body">' +
        '<p class="k-toastify__title">' +
        titles[type] +
        "</p>" +
        '<p class="k-toastify__msg">' +
        text.replace(/[&<>"']/g, function (c) {
          return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c];
        }) +
        "</p></div></div>";

      Toastify({
        text: html,
        duration: 3800,
        gravity: "top",
        position: "right",
        close: true,
        stopOnFocus: true,
        escapeMarkup: false,
        className: "k-toastify k-toastify--" + type,
        offset: { x: 16, y: 72 },
      }).showToast();
      return;
    }
    window.alert(msg);
  }

  function initChangePasswordUi() {
    var openBtn = document.getElementById("kantin-change-password");
    var modal = document.getElementById("kantin-pw-modal");
    var form = document.getElementById("kantin-pw-form");
    var errEl = document.getElementById("kantin-pw-error");
    var cancel = document.getElementById("kantin-pw-cancel");
    var submit = document.getElementById("kantin-pw-submit");
    if (!openBtn || !modal || !form) return;
    if (openBtn.dataset.pwBound === "1") return;
    openBtn.dataset.pwBound = "1";

    function showError(msg) {
      if (!errEl) return;
      errEl.textContent = msg || "";
      errEl.hidden = !msg;
    }

    function closeModal() {
      modal.setAttribute("hidden", "");
      modal.style.display = "";
      form.reset();
      showError("");
      if (submit) {
        submit.disabled = false;
        submit.textContent = "Simpan";
      }
    }

    function openModal(e) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      showError("");
      form.reset();
      modal.removeAttribute("hidden");
      modal.style.display = "flex";
      var first = document.getElementById("kantin-pw-old");
      window.setTimeout(function () {
        if (first) {
          try {
            first.focus();
          } catch (errFocus) {}
        }
      }, 50);
    }

    openBtn.addEventListener("click", openModal);
    if (cancel) cancel.addEventListener("click", closeModal);
    var backdrop = modal.querySelector("[data-close-pw]");
    if (backdrop) backdrop.addEventListener("click", closeModal);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hasAttribute("hidden")) closeModal();
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      showError("");
      var oldPw = (document.getElementById("kantin-pw-old") || {}).value || "";
      var newPw = (document.getElementById("kantin-pw-new") || {}).value || "";
      var newPw2 = (document.getElementById("kantin-pw-new2") || {}).value || "";
      if (!oldPw || !newPw || !newPw2) {
        showError("Lengkapi semua kolom kata sandi.");
        return;
      }
      if (newPw !== newPw2) {
        showError("Konfirmasi kata sandi baru tidak cocok.");
        return;
      }
      if (oldPw === newPw) {
        showError("Kata sandi baru harus berbeda dari yang lama.");
        return;
      }
      if (submit) {
        submit.disabled = true;
        submit.textContent = "Menyimpan…";
      }
      changePassword(oldPw, newPw, newPw2)
        .then(function () {
          toastAuth("Kata sandi berhasil diubah.", "ok");
          closeModal();
        })
        .catch(function (err) {
          var msg = (err && err.message) || "Gagal mengganti kata sandi";
          showError(msg);
          toastAuth(msg, "danger");
        })
        .finally(function () {
          if (submit) {
            submit.disabled = false;
            submit.textContent = "Simpan";
          }
        });
    });
  }

  global.KantinAuth = {
    PORTAL_HOME: PORTAL_HOME,
    apiUrl: apiUrl,
    requestJson: requestJson,
    me: me,
    login: login,
    logout: logout,
    changePassword: changePassword,
    requireSession: requireSession,
    initLoginPage: initLoginPage,
    initChangePasswordUi: initChangePasswordUi,
    goPortal: goPortal,
  };

  // Bind segera (jangan menunggu facepay/session) agar tombol selalu aktif di index kantin
  function bootChangePassword() {
    if (document.getElementById("kantin-change-password")) {
      initChangePasswordUi();
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootChangePassword);
  } else {
    bootChangePassword();
  }
})(typeof window !== "undefined" ? window : globalThis);
