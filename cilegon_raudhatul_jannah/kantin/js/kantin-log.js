/**
 * Log pembelian kantin — LogTransaksiRequest via proxy (API merchant).
 */
(function (global) {
  "use strict";

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function formatRupiah(n) {
    var v = Number(n);
    if (isNaN(v)) v = 0;
    try {
      return v.toLocaleString("id-ID");
    } catch (e) {
      return String(v);
    }
  }

  function formatTrxDate(raw) {
    if (!raw) return "—";
    var s = String(raw).trim();
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
      return s.replace(" ", " · ");
    }
    return s;
  }

  function render(rows) {
    var body = document.getElementById("kantin-log-body");
    var meta = document.getElementById("kantin-log-meta");
    if (!body) return;
    rows = Array.isArray(rows) ? rows : [];
    if (!rows.length) {
      body.innerHTML =
        '<tr><td class="k-table__empty" colspan="3">Belum ada transaksi untuk kantin ini.</td></tr>';
      if (meta) meta.textContent = "0 transaksi";
      return;
    }
    if (meta) meta.textContent = rows.length + " transaksi";
    body.innerHTML = rows
      .map(function (r) {
        return (
          "<tr>" +
          "<td>" +
          '<span class="k-log-name">' +
          escapeHtml(r.namaCust || "—") +
          "</span>" +
          (r.kantin
            ? '<div class="k-log-sub">' + escapeHtml(r.kantin) + "</div>"
            : "") +
          "</td>" +
          "<td>" +
          escapeHtml(formatTrxDate(r.trxDate)) +
          "</td>" +
          '<td class="k-table__nominal">Rp ' +
          escapeHtml(formatRupiah(r.nominal)) +
          "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function load() {
    var meta = document.getElementById("kantin-log-meta");
    if (meta) meta.textContent = "Memuat…";
    if (!global.KantinAuth) {
      render([]);
      return Promise.resolve([]);
    }
    return global.KantinAuth.requestJson("GET", global.KantinAuth.apiUrl("log-transaksi.php"))
      .then(function (body) {
        var rows = (body && body.data) || [];
        render(rows);
        return rows;
      })
      .catch(function (e) {
        if (meta) meta.textContent = "Gagal memuat: " + ((e && e.message) || e);
        render([]);
        return [];
      });
  }

  function init() {
    var btn = document.getElementById("kantin-log-refresh");
    if (btn) {
      btn.addEventListener("click", function () {
        load();
      });
    }
  }

  global.KantinLog = {
    load: load,
    init: init,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(typeof window !== "undefined" ? window : globalThis);
