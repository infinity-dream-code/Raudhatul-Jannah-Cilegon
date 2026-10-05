/**
 * Penghubung data siswa & saldo: server (api/*) ↔ cache localStorage.
 * Modul wajah/FacePay tetap membaca cache agar deteksi cepat.
 */
(function (global) {
  "use strict";

  var SISWA_KEY = "presensi_data_siswa";
  var SALDO_KEY = "presensi_saldo_siswa";
  var SYNC_EVENT = "presensi-data-synced";

  var syncInFlight = null;

  function readJson(key, fallback) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return fallback;
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  /** Ringkas record siswa untuk localStorage (hemat kuota, tetap simpan field SIE tampilan). */
  function slimSiswaRow(row) {
    if (!row || typeof row !== "object") return row;
    var foto = row.fotoWajah != null ? String(row.fotoWajah) : "";
    var hasFoto = Boolean(
      row.hasFoto ||
        row._hasFoto ||
        foto.length > 100
    );
    return {
      id: row.id,
      nis: row.nis || "",
      nisn: row.nisn || "",
      nama: row.nama || "",
      kelasId: row.kelasId || row.kelas_id || "",
      jenisKelamin: row.jenisKelamin || row.jenis_kelamin || "L",
      telepon: row.telepon || "",
      aktif: row.aktif !== false,
      rfidUid: row.rfidUid || row.rfid_uid || "",
      kodeSuara: row.kodeSuara || row.kode_suara || "",
      fotoWajah: hasFoto ? "" : foto,
      hasFoto: hasFoto,
      _hasFoto: hasFoto,
      code02: row.code02 || row.CODE02 || "",
      desc02: row.desc02 || row.DESC02 || "",
      desc03: row.desc03 || row.DESC03 || "",
      desc04: row.desc04 || row.DESC04 || "",
      merchantSyncedAt: row.merchantSyncedAt || row.merchant_synced_at || null,
      _slim: 1,
    };
  }

  function writeSiswaCache(rows) {
    var list = rows || [];
    try {
      localStorage.setItem(SISWA_KEY, JSON.stringify(list));
      return;
    } catch (eFull) {
      console.warn("[PresensiData] cache siswa penuh, simpan versi ringkas:", eFull.message || eFull);
    }
    try {
      localStorage.setItem(
        SISWA_KEY,
        JSON.stringify(list.map(slimSiswaRow))
      );
    } catch (eSlim) {
      console.warn("[PresensiData] cache siswa ringkas juga gagal:", eSlim.message || eSlim);
    }
  }

  function writeSaldoCache(map) {
    localStorage.setItem(SALDO_KEY, JSON.stringify(map || {}));
  }

  function isRemote() {
    return global.PresensiApi && global.PresensiApi.isEnabled();
  }

  function dispatchSynced(detail) {
    try {
      global.dispatchEvent(
        new CustomEvent(SYNC_EVENT, { detail: detail || {} })
      );
    } catch (e) {
      /* IE tidak mendukung CustomEvent */
    }
  }

  function pullAll(force) {
    if (!isRemote()) {
      return Promise.resolve({ siswa: readJson(SISWA_KEY, []), saldoMap: readJson(SALDO_KEY, {}) });
    }
    if (syncInFlight && !force) return syncInFlight;
    syncInFlight = global.PresensiApi.pullAll()
      .then(function (payload) {
        if (payload.siswa && Array.isArray(payload.siswa)) {
          writeSiswaCache(payload.siswa);
        }
        if (payload.saldoMap && typeof payload.saldoMap === "object") {
          writeSaldoCache(payload.saldoMap);
        }
        dispatchSynced(payload);
        return payload;
      })
      .finally(function () {
        syncInFlight = null;
      });
    return syncInFlight;
  }

  function pullSiswa() {
    if (!isRemote()) return Promise.resolve(readJson(SISWA_KEY, []));
    return global.PresensiApi.siswa.list().then(function (rows) {
      writeSiswaCache(rows || []);
      dispatchSynced({ siswa: rows || [] });
      return rows || [];
    });
  }

  function pullSaldo() {
    if (!isRemote()) return Promise.resolve(readJson(SALDO_KEY, {}));
    return global.PresensiApi.saldo.listMap().then(function (map) {
      writeSaldoCache(map);
      dispatchSynced({ saldoMap: map });
      return map;
    });
  }

  function pushSiswaAll(rows) {
    writeSiswaCache(rows);
    if (!isRemote()) return Promise.resolve(rows);
    return global.PresensiApi.siswa.replaceAll(rows).then(function (serverRows) {
      if (serverRows && serverRows.length) writeSiswaCache(serverRows);
      return serverRows || rows;
    });
  }

  function pushSaldoMap(map) {
    writeSaldoCache(map);
    if (!isRemote()) return Promise.resolve(map);
    return global.PresensiApi.saldo.saveMap(map).then(function (serverMap) {
      var next = serverMap && typeof serverMap === "object" ? serverMap : map;
      writeSaldoCache(next);
      return next;
    });
  }

  function updateSiswaOnServer(row) {
    if (!isRemote() || !row || !row.id) return Promise.resolve(row);
    return global.PresensiApi.siswa.update(row.id, row).then(function (saved) {
      return saved || row;
    });
  }

  function deleteSiswaOnServer(id) {
    if (!isRemote() || !id) return Promise.resolve();
    return global.PresensiApi.siswa.remove(id);
  }

  function getSaldoSync(siswaId) {
    var map = readJson(SALDO_KEY, {});
    var v = Number(map[siswaId]);
    return isNaN(v) ? 0 : v;
  }

  function debit(siswaId, amount) {
    if (!isRemote()) {
      var map = readJson(SALDO_KEY, {});
      var cur = Number(map[siswaId]) || 0;
      var amt = Number(amount);
      if (cur < amt) return Promise.resolve(null);
      map[siswaId] = cur - amt;
      writeSaldoCache(map);
      return Promise.resolve({ before: cur, after: map[siswaId] });
    }
    return global.PresensiApi.saldo.debit(siswaId, amount).then(function (result) {
      var map = readJson(SALDO_KEY, {});
      map[siswaId] = result.after;
      writeSaldoCache(map);
      return result;
    });
  }

  function initAutoSync() {
    if (!isRemote()) return Promise.resolve();
    return pullSiswa().catch(function (err) {
      console.warn("[PresensiData] muat siswa dari DB gagal:", err.message || err);
    });
  }

  global.PresensiData = {
    SISWA_KEY: SISWA_KEY,
    SALDO_KEY: SALDO_KEY,
    SYNC_EVENT: SYNC_EVENT,
    isRemote: isRemote,
    pullAll: pullAll,
    pullSiswa: pullSiswa,
    pullSaldo: pullSaldo,
    pushSiswaAll: pushSiswaAll,
    pushSaldoMap: pushSaldoMap,
    updateSiswaOnServer: updateSiswaOnServer,
    deleteSiswaOnServer: deleteSiswaOnServer,
    getSaldoSync: getSaldoSync,
    debit: debit,
    initAutoSync: initAutoSync,
    readSiswaCache: function () {
      return readJson(SISWA_KEY, []);
    },
    writeSiswaCache: writeSiswaCache,
    slimSiswaRow: slimSiswaRow,
    readSaldoCache: function () {
      return readJson(SALDO_KEY, {});
    },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initAutoSync();
    });
  } else {
    initAutoSync();
  }
})(typeof window !== "undefined" ? window : globalThis);
