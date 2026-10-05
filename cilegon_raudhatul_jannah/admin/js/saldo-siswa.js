(function () {
  var SISWA_KEY = "presensi_data_siswa";
  var SALDO_KEY = "presensi_saldo_siswa";

  var saldoCache = {};

  var state = {
    page: 1,
    pageSize: 8,
  };

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function formatRupiah(n) {
    var x = Math.floor(Number(n) || 0);
    return x.toLocaleString("id-ID");
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

  function saveSaldoMap(map) {
    try {
      localStorage.setItem(SALDO_KEY, JSON.stringify(map || {}));
    } catch (e) {}
  }

  function setStatus(msg, isError) {
    var el = document.getElementById("saldo-status");
    if (!el) return;
    el.textContent = msg || "";
    el.className = "saldo-siswa__note" + (isError ? " saldo-siswa__note--error" : "");
  }

  function normSearch(s) {
    return String(s || "")
      .trim()
      .toLowerCase()
      .replace(/\s+/g, " ");
  }

  function filterSiswa(rows, q) {
    var t = normSearch(q);
    if (!t) return rows.slice();
    return rows.filter(function (s) {
      var nama = normSearch(s.nama);
      var nis = String(s.nis || "")
        .trim()
        .toLowerCase();
      return nama.indexOf(t) !== -1 || nis.indexOf(t) !== -1;
    });
  }

  function bindSaldoSearchFocus(root) {
    if (!root || root.dataset.searchFocus !== "1") return;
    var inp = root.querySelector("#saldo-search");
    if (!inp) return;
    var q = root.dataset.searchQ || "";
    inp.value = q;
    var len = inp.value.length;
    var restore = function () {
      try {
        inp.focus();
        inp.setSelectionRange(len, len);
      } catch (eFocus) {
        /* IE */
      }
    };
    if (typeof requestAnimationFrame === "function") {
      requestAnimationFrame(restore);
    } else {
      restore();
    }
  }

  function resolveNisForSaldo(s) {
    if (window.PresensiApiExtras && window.PresensiApiExtras.resolveNoKartu) {
      return window.PresensiApiExtras.resolveNoKartu(s);
    }
    return String((s && s.nis) || "")
      .trim()
      .replace(/\D/g, "");
  }

  function inquiryOne(s) {
    if (!window.PresensiApiExtras || !window.PresensiApiExtras.inquirySaldo) {
      return Promise.reject(new Error("API saldo tidak tersedia"));
    }
    return window.PresensiApiExtras.inquirySaldo(s);
  }

  function renderPagination(total, totalAll, searchQ) {
    var pag = document.getElementById("saldo-pagination");
    if (!pag) return;

    var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > totalPages) state.page = totalPages;

    if (total === 0) {
      pag.hidden = true;
      pag.innerHTML = "";
      return;
    }

    var start = (state.page - 1) * state.pageSize;
    var pageInfo =
      start +
      1 +
      "–" +
      Math.min(start + state.pageSize, total) +
      " dari " +
      total +
      (searchQ && totalAll !== total ? " (filter, total " + totalAll + ")" : "");

    pag.hidden = false;
    pag.innerHTML =
      '<span class="siswa-pagination__info">' +
      escapeHtml(pageInfo) +
      "</span>" +
      '<div class="siswa-pagination__nav">' +
      '<button type="button" class="btn btn--ghost btn--small" id="saldo-prev"' +
      (state.page <= 1 ? " disabled" : "") +
      ">Sebelumnya</button>" +
      '<span class="siswa-pagination__page">Halaman ' +
      state.page +
      " / " +
      totalPages +
      "</span>" +
      '<button type="button" class="btn btn--ghost btn--small" id="saldo-next"' +
      (state.page >= totalPages ? " disabled" : "") +
      ">Berikutnya</button></div>";
  }

  function syncPageSizeSelect() {
    var sel = document.getElementById("saldo-page-size");
    if (sel) sel.value = String(state.pageSize);
  }

  function render() {
    var root = document.getElementById("saldo-root");
    var body = document.getElementById("saldo-body");
    if (!body) return;
    var all = loadRows(SISWA_KEY);
    var searchQ = (root && root.dataset.searchQ) || "";
    var hasFilterMod = Boolean(window.PresensiSiswaFilter);
    var dimSel = hasFilterMod
      ? window.PresensiSiswaFilter.read(root)
      : { unit: "", kelompok: "", kelas: "", tahun: "" };
    var gateOpen =
      !hasFilterMod ||
      !window.PresensiSiswaFilter.needsGate(all.length) ||
      window.PresensiSiswaFilter.isOpen(dimSel, searchQ);
    var dimRows = hasFilterMod ? window.PresensiSiswaFilter.apply(all, dimSel) : all;
    var list = gateOpen ? filterSiswa(dimRows, searchQ) : [];
    var total = list.length;
    var totalAll = all.length;

    syncPageSizeSelect();

    var filterHost = document.getElementById("saldo-filter");
    if (filterHost && hasFilterMod) {
      filterHost.innerHTML = window.PresensiSiswaFilter.controlsHtml(all, dimSel, "saldo");
    }

    var meta = document.getElementById("saldo-search-meta");
    if (meta) {
      meta.innerHTML = !gateOpen
        ? "Belum ada filter aktif — daftar disembunyikan agar ringan."
        : searchQ
        ? "Menampilkan <strong>" +
          total +
          "</strong> dari <strong>" +
          totalAll +
          "</strong> siswa"
        : "Menampilkan <strong>" + total + "</strong> siswa (filter aktif, total " + totalAll + ")";
    }

    if (!all.length) {
      body.innerHTML =
        '<tr><td class="data-table__empty" colspan="4">Belum ada data siswa. Tambah atau sinkron di menu Data siswa.</td></tr>';
      renderPagination(0, 0, searchQ);
      if (root) bindSaldoSearchFocus(root);
      return;
    }

    if (!gateOpen) {
      body.innerHTML = window.PresensiSiswaFilter.gateRowHtml(4);
      renderPagination(0, totalAll, searchQ);
      if (root) bindSaldoSearchFocus(root);
      return;
    }

    if (!list.length) {
      body.innerHTML =
        '<tr><td class="data-table__empty" colspan="4">Tidak ada siswa yang cocok dengan pencarian «' +
        escapeHtml(searchQ) +
        "».</td></tr>";
      renderPagination(0, totalAll, searchQ);
      if (root) bindSaldoSearchFocus(root);
      return;
    }

    var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > totalPages) state.page = totalPages;
    var start = (state.page - 1) * state.pageSize;
    var pageRows = list.slice(start, start + state.pageSize);

    body.innerHTML = pageRows
      .map(function (s) {
        var nis = resolveNisForSaldo(s);
        var cached = saldoCache[s.id];
        var saldoCell;
        if (!nis) {
          saldoCell =
            '<span class="saldo-siswa__warn">NIS belum diisi</span>';
        } else if (!cached) {
          saldoCell = '<span class="saldo-siswa__muted">— (tekan Perbarui)</span>';
        } else if (cached.status && cached.status !== "OK") {
          saldoCell =
            '<span class="saldo-siswa__warn">' +
            escapeHtml(cached.status || "Gagal") +
            "</span>";
        } else {
          saldoCell =
            "<strong>Rp " + escapeHtml(formatRupiah(cached.saldo)) + "</strong>";
          if (cached.nama && cached.nama !== s.nama) {
            saldoCell +=
              '<br /><span class="saldo-siswa__muted">' +
              escapeHtml(cached.nama) +
              " (SIE)</span>";
          }
        }
        return (
          "<tr data-siswa-id=\"" +
          escapeHtml(s.id) +
          "\">" +
          "<td>" +
          escapeHtml(s.nama || "—") +
          "</td>" +
          "<td>" +
          escapeHtml(s.nis || "—") +
          "</td>" +
          "<td class=\"saldo-siswa__amount\">" +
          saldoCell +
          "</td>" +
          "<td>" +
          '<button type="button" class="btn btn--ghost btn--small" data-saldo-refresh="' +
          escapeHtml(s.id) +
          '"' +
          (nis ? "" : " disabled") +
          ">Perbarui</button></td>" +
          "</tr>"
        );
      })
      .join("");
    renderPagination(total, totalAll, searchQ);
    if (root) bindSaldoSearchFocus(root);
  }

  /** Daftar siswa yang sedang tampil (mengikuti filter + pencarian). */
  function getVisibleList() {
    var root = document.getElementById("saldo-root");
    var all = loadRows(SISWA_KEY);
    var searchQ = (root && root.dataset.searchQ) || "";
    var dimRows = all;
    if (window.PresensiSiswaFilter) {
      var sel = window.PresensiSiswaFilter.read(root);
      var gateOpen =
        !window.PresensiSiswaFilter.needsGate(all.length) ||
        window.PresensiSiswaFilter.isOpen(sel, searchQ);
      if (!gateOpen) return [];
      dimRows = window.PresensiSiswaFilter.apply(all, sel);
    }
    return filterSiswa(dimRows, searchQ);
  }

  function refreshOne(id) {
    var list = loadRows(SISWA_KEY);
    var s = list.find(function (r) {
      return r.id === id;
    });
    if (!s) return Promise.resolve();
    var nk = resolveNisForSaldo(s);
    if (!nk) {
      alert("NIS siswa belum diisi.");
      return Promise.resolve();
    }
    setStatus("Memuat saldo " + (s.nama || "") + "…", false);
    return inquiryOne(s)
      .then(function (d) {
        saldoCache[s.id] = d;
        if (d && d.status === "OK") {
          var map = {};
          try {
            map = JSON.parse(localStorage.getItem(SALDO_KEY) || "{}") || {};
          } catch (e) {
            map = {};
          }
          map[s.id] = Number(d.saldo) || 0;
          saveSaldoMap(map);
        }
        setStatus("Saldo diperbarui untuk " + (s.nama || "siswa") + ".", false);
        render();
      })
      .catch(function (e) {
        setStatus("Gagal: " + (e && e.message ? e.message : e), true);
      });
  }

  function onClick(e) {
    var t = e.target;
    var root = document.getElementById("saldo-root");
    if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleClick(root, t, "saldo")) {
      state.page = 1;
      render();
      return;
    }
    if (t.closest("#saldo-prev")) {
      state.page = Math.max(1, state.page - 1);
      render();
      return;
    }
    if (t.closest("#saldo-next")) {
      var filtered = getVisibleList();
      var totalPages = Math.max(1, Math.ceil(filtered.length / state.pageSize));
      state.page = Math.min(totalPages, state.page + 1);
      render();
      return;
    }
    var one = t.closest("[data-saldo-refresh]");
    if (one) {
      refreshOne(one.getAttribute("data-saldo-refresh"));
    }
  }

  function init() {
    var root = document.getElementById("saldo-root");
    if (!root || root.dataset.bound) return;
    root.dataset.bound = "1";
    root.addEventListener("input", function (e) {
      if (e.target.id === "saldo-search") {
        root.dataset.searchQ = e.target.value;
        root.dataset.searchFocus = "1";
        state.page = 1;
        render();
      }
    });
    root.addEventListener("change", function (e) {
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleChange(root, e.target, "saldo")) {
        state.page = 1;
        render();
        return;
      }
      if (e.target.id === "saldo-page-size") {
        state.pageSize = parseInt(e.target.value, 10) || 8;
        state.page = 1;
        render();
      }
    });
    root.addEventListener("click", onClick);

    function show() {
      render();
    }

    if (window.PresensiData && window.PresensiData.isRemote()) {
      // Optimistik: tampilkan cache lokal dulu agar UI cepat muncul.
      show();

      var cached = loadRows(SISWA_KEY);
      if (!cached.length && window.PresensiLoading) {
        window.PresensiLoading.show(root, "Memuat daftar siswa…");
      }

      // Sinkron DB di background; render ulang saat data terbaru masuk.
      window.PresensiData.pullSiswa()
        .then(function () {
          show();
        })
        .catch(function () {
          show();
        })
        .finally(function () {
          if (window.PresensiLoading) {
            window.PresensiLoading.hide(root);
          }
        });
    } else {
      show();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
