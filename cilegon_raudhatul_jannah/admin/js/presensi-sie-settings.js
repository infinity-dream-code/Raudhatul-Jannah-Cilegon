/**
 * Unit / jenjang / kelas dari field SIE siswa (code02, desc02, desc03).
 */
(function (global) {
  "use strict";

  var UNIT_KEY = "presensi_setting_unit";
  var JENJANG_KEY = "presensi_setting_jenjang";
  var KELAS_KEY = "presensi_setting_kelas";

  function slug(s) {
    return String(s || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "") || "x";
  }

  function pick(row, keys) {
    if (!row) return "";
    var list = typeof keys === "string" ? [keys] : keys;
    for (var i = 0; i < list.length; i++) {
      var v = row[list[i]];
      if (v != null && v !== "") {
        var s = String(v).trim();
        if (s && s !== "-") return s;
      }
    }
    return "";
  }

  function loadRows(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return [];
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function saveRows(key, rows) {
    localStorage.setItem(key, JSON.stringify(rows || []));
  }

  /** Bangun hierarki unit → jenjang → kelas dari baris siswa SIE. */
  function buildFromSiswaRows(rows) {
    var units = [];
    var jenjang = [];
    var kelas = [];
    var unitMap = {};
    var jenjangMap = {};
    var kelasMap = {};

    (rows || []).forEach(function (r) {
      var code02 = pick(r, ["code02", "CODE02"]);
      var desc02 = pick(r, ["desc02", "DESC02"]);
      var desc03 = pick(r, ["desc03", "DESC03", "kelasId", "kelas_id"]);
      if (!code02 && !desc02 && !desc03) return;

      if (code02 && !unitMap[code02]) {
        var unitId = "sie-u-" + slug(code02);
        unitMap[code02] = unitId;
        units.push({
          id: unitId,
          kode: code02.length > 16 ? code02.slice(0, 16) : code02,
          nama: code02,
          alamat: "",
          sieCode02: code02,
          fromSie: true,
        });
      }

      var unitId = code02 ? unitMap[code02] : null;
      var tingkat = desc02 || "—";
      var jKey = (code02 || "_") + "|" + tingkat;

      if (unitId && !jenjangMap[jKey]) {
        var jenjangId = "sie-j-" + slug(code02) + "-" + slug(tingkat);
        jenjangMap[jKey] = jenjangId;
        jenjang.push({
          id: jenjangId,
          unitId: unitId,
          kode: tingkat,
          nama: tingkat,
          namaPanjang: tingkat === "—" ? "Tingkat" : "Tingkat " + tingkat,
          keterangan: "SIE DESC02",
          sieDesc02: desc02,
          fromSie: true,
        });
      }

      var jenjangId = jenjangMap[jKey];
      if (jenjangId && desc03) {
        var kKey = jKey + "|" + desc03;
        if (!kelasMap[kKey]) {
          var kelasId = "sie-k-" + slug(code02 || "u") + "-" + slug(tingkat) + "-" + slug(desc03);
          kelasMap[kKey] = kelasId;
          kelas.push({
            id: kelasId,
            jenjangId: jenjangId,
            nama: desc03,
            tingkat: desc02 || "",
            wali: "",
            sieDesc03: desc03,
            fromSie: true,
          });
        }
      }
    });

    units.sort(function (a, b) {
      return a.nama.localeCompare(b.nama, "id");
    });
    jenjang.sort(function (a, b) {
      return a.nama.localeCompare(b.nama, "id", { numeric: true });
    });
    kelas.sort(function (a, b) {
      return a.nama.localeCompare(b.nama, "id", { numeric: true });
    });

    return { units: units, jenjang: jenjang, kelas: kelas };
  }

  function applySettings(data) {
    saveRows(UNIT_KEY, data.units || []);
    saveRows(JENJANG_KEY, data.jenjang || []);
    saveRows(KELAS_KEY, data.kelas || []);
    try {
      localStorage.removeItem("presensi_modul_context");
    } catch (e) {}
    return data;
  }

  function syncFromSiswaRows(rows) {
    var built = buildFromSiswaRows(rows);
    if (!built.units.length && !built.kelas.length) {
      throw new Error("Tidak ada data unit/kelas SIE pada daftar siswa.");
    }
    return applySettings(built);
  }

  function fetchSiswaRows() {
    if (global.PresensiData && global.PresensiData.isRemote()) {
      if (global.PresensiData.readSiswaCache) {
        var cached = global.PresensiData.readSiswaCache();
        if (cached && cached.length) {
          return Promise.resolve(cached);
        }
      }
      if (global.PresensiApi && global.PresensiApi.siswa && global.PresensiApi.siswa.list) {
        return global.PresensiApi.siswa.list();
      }
    }
    return Promise.resolve([]);
  }

  function syncFromApi() {
    return fetchSiswaRows().then(function (rows) {
      if (!rows || !rows.length) {
        throw new Error("Belum ada data siswa. Sinkron dari SIE di menu Data siswa terlebih dahulu.");
      }
      return syncFromSiswaRows(rows);
    });
  }

  function loadAll() {
    return {
      units: loadRows(UNIT_KEY),
      jenjang: loadRows(JENJANG_KEY),
      kelas: loadRows(KELAS_KEY),
    };
  }

  function isDemoOrEmpty(settings) {
    settings = settings || loadAll();
    if (!settings.units.length) return true;
    return settings.units.every(function (u) {
      return !u.fromSie && String(u.id || "").indexOf("seed-") === 0;
    });
  }

  function syncIfNeeded() {
    if (!global.PresensiData || !global.PresensiData.isRemote()) {
      return Promise.resolve(loadAll());
    }
    if (!isDemoOrEmpty()) {
      return Promise.resolve(loadAll());
    }
    return syncFromApi().catch(function (e) {
      console.warn("[PresensiSieSettings]", e.message || e);
      return loadAll();
    });
  }

  function unitDisplay(u) {
    if (!u) return "—";
    return u.sieCode02 || u.nama || u.kode || "—";
  }

  function jenjangDisplay(j) {
    if (!j) return "—";
    return j.sieDesc02 || j.nama || j.kode || "—";
  }

  function kelasDisplay(k) {
    if (!k) return "—";
    return k.sieDesc03 || k.nama || "—";
  }

  function kelasOptionLabel(k, jenjangById, unitsById) {
    var j = jenjangById[k.jenjangId];
    var u = j ? unitsById[j.unitId] : null;
    return (u ? unitDisplay(u) + " · " : "") + (j ? jenjangDisplay(j) + " · " : "") + kelasDisplay(k);
  }

  global.PresensiSieSettings = {
    UNIT_KEY: UNIT_KEY,
    JENJANG_KEY: JENJANG_KEY,
    KELAS_KEY: KELAS_KEY,
    pick: pick,
    buildFromSiswaRows: buildFromSiswaRows,
    applySettings: applySettings,
    syncFromSiswaRows: syncFromSiswaRows,
    syncFromApi: syncFromApi,
    syncIfNeeded: syncIfNeeded,
    loadAll: loadAll,
    isDemoOrEmpty: isDemoOrEmpty,
    unitDisplay: unitDisplay,
    jenjangDisplay: jenjangDisplay,
    kelasDisplay: kelasDisplay,
    kelasOptionLabel: kelasOptionLabel,
  };
})(typeof window !== "undefined" ? window : globalThis);
