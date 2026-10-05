(function (global) {
  "use strict";

  var http = global.PresensiApiHttp;

  function cfgPath(key) {
    var c = global.PresensiApiConfig || {};
    return (c.paths && c.paths[key]) || key;
  }

  function pick(obj, keys, fallback) {
    for (var i = 0; i < keys.length; i++) {
      if (obj[keys[i]] != null && obj[keys[i]] !== "") return obj[keys[i]];
    }
    return fallback;
  }

  function toBool(v, defaultVal) {
    if (v === true || v === 1 || v === "1" || v === "true" || v === "ya" || v === "Ya") return true;
    if (v === false || v === 0 || v === "0" || v === "false" || v === "tidak") return false;
    return defaultVal !== undefined ? defaultVal : true;
  }

  /** Normalisasi record siswa dari berbagai format backend */
  function normalizeSiswa(raw) {
    if (!raw || typeof raw !== "object") return null;
    var id = pick(raw, ["id", "siswa_id", "siswaId", "uuid"], "");
    if (!id) return null;
    var row = {
      id: String(id),
      nis: String(pick(raw, ["nis", "NIS"], "") || ""),
      nisn: String(pick(raw, ["nisn", "NISN"], "") || ""),
      nama: String(pick(raw, ["nama", "name", "nama_siswa", "namaMerchant", "nama_merchant"], "") || ""),
      kelasId: String(pick(raw, ["kelasId", "kelas_id", "id_kelas"], "") || ""),
      jenisKelamin: String(pick(raw, ["jenisKelamin", "jenis_kelamin", "jk"], "") || ""),
      telepon: String(pick(raw, ["telepon", "phone", "no_hp"], "") || ""),
      aktif: toBool(pick(raw, ["aktif", "is_active", "active"], true), true),
      rfidUid: String(pick(raw, ["rfidUid", "rfid_uid", "uid_rfid"], "") || ""),
      fotoWajah: String(pick(raw, ["fotoWajah", "foto_wajah", "foto", "photo_url"], "") || ""),
      kodeSuara: String(pick(raw, ["kodeSuara", "kode_suara"], "") || ""),
      num2nd: String(pick(raw, ["num2nd"], "") || ""),
      stcust: String(pick(raw, ["stcust"], "") || ""),
      code01: String(pick(raw, ["code01"], "") || ""),
      desc01: String(pick(raw, ["desc01"], "") || ""),
      code02: String(pick(raw, ["code02", "CODE02"], "") || ""),
      desc02: String(pick(raw, ["desc02", "DESC02"], "") || ""),
      code03: String(pick(raw, ["code03", "CODE03"], "") || ""),
      desc03: String(
        pick(raw, ["desc03", "DESC03", "kelasId", "kelas_id"], "") || ""
      ),
      code04: String(pick(raw, ["code04", "CODE04"], "") || ""),
      desc04: String(pick(raw, ["desc04", "DESC04"], "") || ""),
      code05: String(pick(raw, ["code05"], "") || ""),
      desc05: String(pick(raw, ["desc05"], "") || ""),
      totpay: String(pick(raw, ["totpay"], "") || ""),
      genus: String(pick(raw, ["genus"], "") || ""),
    };
    var fotoStr = row.fotoWajah;
    var hasFotoFlag =
      raw.hasFoto === true ||
      raw._hasFoto === true ||
      raw.hasFoto === 1 ||
      raw._hasFoto === 1 ||
      raw.hasFoto === "1" ||
      raw._hasFoto === "1";
    row.hasFoto = hasFotoFlag || fotoStr.length > 30;
    row._hasFoto = row.hasFoto;
    return row;
  }

  function normalizeList(list) {
    var out = [];
    (list || []).forEach(function (row) {
      var n = normalizeSiswa(row);
      if (n) out.push(n);
    });
    return out;
  }

  function list() {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak aktif. Set enabled dan baseUrl di api/config.js"));
    }
    return http.request("GET", cfgPath("siswa")).then(function (body) {
      return normalizeList(http.extractArray(body));
    });
  }

  function getById(id) {
    return http
      .request("GET", cfgPath("siswa") + "?id=" + encodeURIComponent(id))
      .then(function (body) {
        var row = http.extractObject(body);
        var n = normalizeSiswa(row);
        if (!n) throw new Error("Siswa tidak ditemukan");
        return n;
      });
  }

  function create(payload) {
    return http
      .request("POST", cfgPath("siswa"), { body: payload })
      .then(function (body) {
        var row = http.extractObject(body);
        return normalizeSiswa(row) || normalizeSiswa(payload);
      });
  }

  function update(id, payload) {
    return http
      .request("PUT", cfgPath("siswa") + "?id=" + encodeURIComponent(id), { body: payload })
      .then(function (body) {
        var row = http.extractObject(body);
        return normalizeSiswa(row) || normalizeSiswa(Object.assign({}, payload, { id: id }));
      });
  }

  function replaceAll(rows) {
    return http.request("PUT", cfgPath("siswa"), { body: { data: rows } }).then(function (body) {
      return normalizeList(http.extractArray(body).length ? http.extractArray(body) : rows);
    });
  }

  function remove(id) {
    return http.request("DELETE", cfgPath("siswa") + "?id=" + encodeURIComponent(id));
  }

  global.PresensiApiSiswa = {
    normalize: normalizeSiswa,
    normalizeList: normalizeList,
    list: list,
    getById: getById,
    create: create,
    update: update,
    remove: remove,
    replaceAll: replaceAll,
  };
})(typeof window !== "undefined" ? window : globalThis);
