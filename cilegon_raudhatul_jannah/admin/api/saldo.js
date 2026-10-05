(function (global) {
  "use strict";

  var http = global.PresensiApiHttp;

  function cfgPath(key) {
    var c = global.PresensiApiConfig || {};
    return (c.paths && c.paths[key]) || key;
  }

  /** Konversi respons server ke map { siswaId: number } */
  function normalizeSaldoMap(body) {
    if (!body) return {};
    if (typeof body === "object" && !Array.isArray(body)) {
      var inner = body;
      if (body.map && typeof body.map === "object" && !Array.isArray(body.map)) {
        inner = body.map;
      } else if (body.data && typeof body.data === "object" && !Array.isArray(body.data)) {
        inner = body.data;
      }
      if (!Array.isArray(inner)) {
        var map = {};
        Object.keys(inner).forEach(function (k) {
          if (k === "map" || k === "data" || k === "success" || k === "message") return;
          var v = Number(inner[k]);
          if (!isNaN(v)) map[String(k)] = v;
        });
        if (Object.keys(map).length) return map;
      }
    }
    var list = http.extractArray(body);
    var out = {};
    list.forEach(function (row) {
      if (!row || typeof row !== "object") return;
      var id = row.siswaId != null ? row.siswaId : row.siswa_id != null ? row.siswa_id : row.id;
      if (id == null) return;
      var saldo = Number(row.saldo != null ? row.saldo : row.nominal != null ? row.nominal : row.amount);
      if (!isNaN(saldo)) out[String(id)] = saldo;
    });
    return out;
  }

  function listMap() {
    if (!http.isEnabled()) {
      return Promise.reject(new Error("API tidak aktif"));
    }
    return http.request("GET", cfgPath("saldo")).then(normalizeSaldoMap);
  }

  function saveMap(map) {
    return http.request("PUT", cfgPath("saldo"), { body: { map: map || {} } }).then(function (body) {
      var parsed = normalizeSaldoMap(body);
      return Object.keys(parsed).length ? parsed : map || {};
    });
  }

  function updateOne(siswaId, saldo) {
    return http
      .request("PATCH", cfgPath("saldo") + "/" + encodeURIComponent(siswaId), {
        body: { saldo: saldo },
      })
      .then(function () {
        return { siswaId: String(siswaId), saldo: Number(saldo) || 0 };
      });
  }

  /**
   * Debit saldo di server.
   * Respons diharapkan: { before, after } atau { data: { before, after } }
   */
  function debit(siswaId, amount) {
    var amt = Number(amount);
    if (isNaN(amt) || amt <= 0) {
      return Promise.reject(new Error("Nominal debit tidak valid"));
    }
    return http
      .request("POST", cfgPath("saldoDebit"), {
        body: { siswaId: String(siswaId), amount: amt },
      })
      .then(function (body) {
        var o = http.extractObject(body);
        var before = Number(o.before != null ? o.before : o.saldo_sebelum);
        var after = Number(o.after != null ? o.after : o.saldo_sesudah != null ? o.saldo_sesudah : o.saldo);
        if (isNaN(before) || isNaN(after)) {
          throw new Error("Respons debit tidak valid dari server");
        }
        return { before: before, after: after };
      });
  }

  global.PresensiApiSaldo = {
    normalizeSaldoMap: normalizeSaldoMap,
    listMap: listMap,
    saveMap: saveMap,
    updateOne: updateOne,
    debit: debit,
  };
})(typeof window !== "undefined" ? window : globalThis);
