/**
 * Helper API PHP: sinkron siswa MobileMerchant, log presensi, simpan rekam.
 */
(function (global) {
  "use strict";

  var http = global.PresensiApiHttp;

  function cfgPath(key, fallback) {
    var c = global.PresensiApiConfig || {};
    return (c.paths && c.paths[key]) || fallback;
  }

  function postJson(path, body) {
    var url = http.buildUrl(path);
    return fetch(url, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body || {}),
      credentials: "same-origin",
    }).then(function (res) {
      return res.text().then(function (text) {
        var parsed = null;
        if (text) {
          try {
            parsed = JSON.parse(text);
          } catch (e) {
            parsed = { ok: false, error: text };
          }
        }
        if (!res.ok || (parsed && parsed.ok === false)) {
          var msg = (parsed && parsed.error) || "HTTP " + res.status;
          throw new Error(String(msg));
        }
        return parsed;
      });
    });
  }

  function fetchMerchantUnits() {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak diaktifkan"));
    }
    return postJson(cfgPath("unitRequest", "api/unit-request.php")).then(function (body) {
      var rows = (body && body.data) || [];
      return Array.isArray(rows) ? rows : [];
    });
  }

  function syncSiswaFromMerchant(kodeUnit) {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak diaktifkan"));
    }
    var kode = kodeUnit == null ? "" : String(kodeUnit).trim();
    if (!kode) {
      return Promise.reject(new Error("Pilih unit terlebih dahulu"));
    }
    return postJson(cfgPath("siswaSync", "api/siswa-sync.php"), {
      KODEUNIT: /^\d+$/.test(kode) ? parseInt(kode, 10) : kode,
    });
  }

  function savePresensiLog(row) {
    if (!http.isEnabled() || !row) {
      return Promise.resolve(null);
    }
    return postJson(cfgPath("presensiLog", "api/presensi-log.php"), row);
  }

  /**
   * Daftar log presensi dari tabel presensi_log.
   * @param {{ limit?: number, date?: string, kegiatan?: string }} opts date = YYYY-MM-DD (lokal)
   */
  function listPresensiLog(opts) {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak diaktifkan"));
    }
    opts = opts || {};
    var q = [];
    if (opts.limit != null) q.push("limit=" + encodeURIComponent(String(opts.limit)));
    if (opts.date) q.push("date=" + encodeURIComponent(String(opts.date)));
    if (opts.kegiatan) q.push("kegiatan=" + encodeURIComponent(String(opts.kegiatan)));
    var path = cfgPath("presensiLog", "api/presensi-log.php");
    if (q.length) path += "?" + q.join("&");
    return http.request("GET", path).then(function (body) {
      var rows = http.extractArray(body);
      return Array.isArray(rows) ? rows : [];
    });
  }

  /** NOKARTU di API MobileMerchant = NIS siswa (angka). */
  function resolveNoKartu(siswaOrNis) {
    if (siswaOrNis == null) return "";
    if (typeof siswaOrNis === "number") return String(siswaOrNis);
    if (typeof siswaOrNis === "string") {
      var digits = siswaOrNis.trim().replace(/\D/g, "");
      return digits || "";
    }
    var s = siswaOrNis || {};
    var nis = String(s.nis || s.NIS || "").trim().replace(/\D/g, "");
    return nis;
  }

  function inquirySaldo(siswaOrNis) {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak diaktifkan"));
    }
    var nk = resolveNoKartu(siswaOrNis);
    var body = {};
    if (nk) {
      body.nokartu = nk;
    } else if (siswaOrNis && siswaOrNis.id) {
      body.siswaId = siswaOrNis.id;
    } else {
      return Promise.reject(new Error("NIS siswa belum diisi atau tidak valid untuk cek saldo"));
    }
    return postJson(cfgPath("saldoInquiry", "api/saldo-inquiry.php"), body).then(function (res) {
      return res.data || res;
    });
  }

  function hapusFotoByNis(nis) {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak diaktifkan"));
    }
    var n = String(nis || "").trim();
    if (!n) {
      return Promise.reject(new Error("NIS wajib"));
    }
    return postJson(cfgPath("rekamHapusFoto", "api/rekam-hapus-foto.php"), { nis: n }).then(
      function (body) {
        var saved = body && body.data;
        if (saved && saved.id) {
          try {
            var key = "presensi_data_siswa";
            var raw = localStorage.getItem(key);
            var list = raw ? JSON.parse(raw) : [];
            if (Array.isArray(list)) {
              var ix = list.findIndex(function (r) {
                return String(r.nis || "").trim() === n || r.id === saved.id;
              });
              if (ix >= 0) {
                list[ix].fotoWajah = "";
                list[ix]._hasFoto = false;
                list[ix].hasFoto = false;
                if (global.PresensiData && global.PresensiData.writeSiswaCache) {
                  global.PresensiData.writeSiswaCache(list);
                } else {
                  localStorage.setItem(key, JSON.stringify(list));
                }
              }
            }
          } catch (eCache) {
            /* abaikan */
          }
        }
        return body;
      }
    );
  }

  function saveRekam(siswa) {
    if (!http.isEnabled() || !siswa || !siswa.id) {
      return Promise.resolve(null);
    }
    return postJson(cfgPath("rekamSimpan", "api/rekam-simpan.php"), {
      siswaId: siswa.id,
      rfidUid: siswa.rfidUid,
      fotoWajah: siswa.fotoWajah,
      kodeSuara: siswa.kodeSuara,
    }).then(function (body) {
      var saved = body && body.data;
      if (saved && saved.id) {
        try {
          var key = "presensi_data_siswa";
          var raw = localStorage.getItem(key);
          var list = raw ? JSON.parse(raw) : [];
          if (Array.isArray(list)) {
            var ix = list.findIndex(function (r) {
              return r.id === saved.id;
            });
            var hasFoto = Boolean(saved.fotoWajah && String(saved.fotoWajah).length > 100);
            var patch = {
              rfidUid: saved.rfidUid || "",
              kodeSuara: saved.kodeSuara || "",
              _hasFoto: hasFoto,
              hasFoto: hasFoto,
              fotoWajah: "",
            };
            if (ix >= 0) {
              Object.assign(list[ix], patch);
            } else {
              list.push(Object.assign({ id: saved.id, nis: saved.nis || "", nama: saved.nama || "" }, patch));
            }
            if (global.PresensiData && global.PresensiData.writeSiswaCache) {
              global.PresensiData.writeSiswaCache(list);
            } else {
              localStorage.setItem(key, JSON.stringify(list));
            }
          }
        } catch (eCache) {
          /* abaikan */
        }
      }
      return body;
    });
  }

  global.PresensiApiExtras = {
    fetchMerchantUnits: fetchMerchantUnits,
    syncSiswaFromMerchant: syncSiswaFromMerchant,
    inquirySaldo: inquirySaldo,
    resolveNoKartu: resolveNoKartu,
    hapusFotoByNis: hapusFotoByNis,
    savePresensiLog: savePresensiLog,
    listPresensiLog: listPresensiLog,
    saveRekam: saveRekam,
  };
})(typeof window !== "undefined" ? window : globalThis);
