(function (global) {
  "use strict";

  var SESSION_KEY = "ortu_siswa_session";
  var PORTAL_HOME = "https://raudhatuljannah.smartpayment.co.id/portal";

  function goPortal() {
    location.replace(PORTAL_HOME);
  }

  function getProjectBase() {
    if (typeof location === "undefined") return "";
    var p = location.pathname || "";
    var idx = p.indexOf("/ortu/");
    if (idx >= 0) return p.slice(0, idx);
    if (/\/ortu\/?$/i.test(p)) {
      return p.replace(/\/ortu\/?$/i, "");
    }
    return "";
  }

  function adminApi(file) {
    var name = String(file || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(name)) name += ".php";
    var base = getProjectBase();
    return (base || "") + "/admin/api/" + name.replace(/^admin\/api\//, "");
  }

  function normalizeNis(value) {
    return String(value || "")
      .trim()
      .replace(/\s+/g, "");
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
            if (text.indexOf("<") === 0) {
              throw new Error("Server mengembalikan HTML (cek URL API atau upload ortu-siswa.php)");
            }
            parsed = { ok: false, error: text.slice(0, 200) };
          }
        }
        if (!res.ok || (parsed && parsed.ok === false)) {
          var msg =
            (parsed && parsed.error) ||
            (parsed && parsed.message) ||
            "HTTP " + res.status + " " + res.statusText;
          throw new Error(String(msg));
        }
        return parsed;
      });
    });
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function saveSession(siswa) {
    try {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(siswa || {}));
    } catch (e) {}
  }

  function loadSession() {
    try {
      var raw = sessionStorage.getItem(SESSION_KEY);
      if (!raw) return null;
      var data = JSON.parse(raw);
      return data && data.id ? data : null;
    } catch (e) {
      return null;
    }
  }

  function clearSession() {
    try {
      sessionStorage.removeItem(SESSION_KEY);
    } catch (e) {}
  }

  function loginByNis(nis) {
    var n = normalizeNis(nis);
    if (!n) {
      return Promise.reject(new Error("NIS wajib diisi"));
    }
    return requestJson("POST", adminApi("ortu-siswa.php"), { nis: n, nim: n }).then(function (body) {
      var data = body && body.data;
      if (!data || !data.id) {
        throw new Error("Data siswa tidak valid");
      }
      saveSession(data);
      return data;
    });
  }

  function refreshSiswa() {
    var cur = loadSession();
    if (!cur || !cur.nis) {
      return Promise.reject(new Error("Sesi tidak ditemukan"));
    }
    if (global.PresensiRekamService && global.PresensiRekamService.getDetail && cur.id) {
      return global.PresensiRekamService.getDetail(cur.id).then(function (data) {
        if (!data || !data.id) throw new Error("Data siswa tidak valid");
        saveSession(data);
        return data;
      });
    }
    return requestJson("POST", adminApi("ortu-siswa.php"), {
      nis: normalizeNis(cur.nis),
      nim: normalizeNis(cur.nis),
    }).then(function (body) {
      var data = body && body.data;
      if (!data || !data.id) {
        throw new Error("Data siswa tidak valid");
      }
      saveSession(data);
      return data;
    });
  }

  function saveFotoWajah(siswa, dataUrl) {
    if (!siswa || !siswa.id) {
      return Promise.reject(new Error("Siswa tidak valid"));
    }
    var savePromise =
      global.PresensiRekamService && global.PresensiRekamService.save
        ? global.PresensiRekamService.save({ siswaId: siswa.id, fotoWajah: dataUrl })
        : requestJson("POST", adminApi("rekam-data.php"), {
            siswaId: siswa.id,
            fotoWajah: dataUrl,
          }).then(function (body) {
            return (body && body.data) || null;
          });
    return savePromise.then(function (saved) {
      if (saved) {
        saveSession(saved);
      } else {
        saveSession(Object.assign({}, siswa, { fotoWajah: dataUrl, hasFoto: true }));
      }
      return saved || siswa;
    });
  }

  function hapusFotoWajah(siswa) {
    var nis = normalizeNis(siswa && siswa.nis);
    if (!nis) {
      return Promise.reject(new Error("NIS tidak ditemukan"));
    }
    var delPromise =
      global.PresensiRekamService && global.PresensiRekamService.hapusFoto
        ? global.PresensiRekamService.hapusFoto(nis)
        : requestJson("POST", adminApi("rekam-hapus-foto.php"), { nis: nis }).then(function (body) {
            return (body && body.data) || null;
          });
    return delPromise.then(function (saved) {
      var row = saved || { id: siswa.id, nis: nis, fotoWajah: "", hasFoto: false };
      saveSession(row);
      return row;
    });
  }

  function requireSession() {
    var s = loadSession();
    if (!s) {
      location.replace("index.html");
      return null;
    }
    return s;
  }

  global.OrtuApp = {
    SESSION_KEY: SESSION_KEY,
    PORTAL_HOME: PORTAL_HOME,
    escapeHtml: escapeHtml,
    adminApi: adminApi,
    normalizeNis: normalizeNis,
    saveSession: saveSession,
    loadSession: loadSession,
    clearSession: clearSession,
    loginByNis: loginByNis,
    loginByNim: loginByNis,
    refreshSiswa: refreshSiswa,
    saveFotoWajah: saveFotoWajah,
    hapusFotoWajah: hapusFotoWajah,
    requireSession: requireSession,
    goPortal: goPortal,
  };
})(typeof window !== "undefined" ? window : globalThis);
