/**
 * Halaman lihat transaksi — TransaksiRequest + filter tanggal.
 */
(function (global) {
  function initTransaksiPage() {
    if (!global.OrtuShell || !OrtuShell.setupSubPage({ tab: 'keuangan' })) return;

    var listEl = document.getElementById('txList');
    var countEl = document.getElementById('txCount');
    var rangeEl = document.getElementById('txRange');
    var filterForm = document.getElementById('txFilterForm');
    var dateFromEl = document.getElementById('txDateFrom');
    var dateToEl = document.getElementById('txDateTo');
    var resetBtn = document.getElementById('txFilterReset');
    var username = global.OrtuKeu ? OrtuKeu.getUsername() : '';
    var allItems = [];
    var hasFilter = false;

    if (!username) {
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml('Session habis. Silakan login ulang.');
      return;
    }

    function renderFiltered() {
      var from = dateFromEl ? dateFromEl.value : '';
      var to = dateToEl ? dateToEl.value : '';
      hasFilter = !!(from || to);
      var items = OrtuKeu.filterTransaksiByDate(allItems, from, to);
      var summary = OrtuKeu.transaksiSummary(items);
      if (countEl) countEl.textContent = String(summary.count);
      if (rangeEl) {
        rangeEl.textContent = hasFilter
          ? (summary.range !== '—' ? summary.range : 'Tidak ada data')
          : summary.range;
      }
      if (listEl) {
        listEl.innerHTML = OrtuKeu.renderTransaksiList(items, { filtered: hasFilter });
      }
    }

    if (filterForm) {
      filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        renderFiltered();
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (dateFromEl) dateFromEl.value = '';
        if (dateToEl) dateToEl.value = '';
        renderFiltered();
      });
    }

    if (listEl) listEl.innerHTML = OrtuKeu.loadingHtml('Memuat transaksi…');

    OrtuApi.fetchTransaksi(username).then(function (res) {
      allItems = res.datas || [];
      renderFiltered();
    }).catch(function (err) {
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml(err.message);
    });
  }

  global.OrtuTransaksiPage = { init: initTransaksiPage };
})(typeof window !== 'undefined' ? window : globalThis);
