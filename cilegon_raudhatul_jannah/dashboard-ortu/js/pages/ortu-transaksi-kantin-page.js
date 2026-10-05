/**
 * Halaman transaksi kantin — TransaksiRequestSaku + filter tanggal.
 */
(function (global) {
  function initTransaksiKantinPage() {
    if (!global.OrtuShell || !OrtuShell.setupSubPage({ tab: 'keuangan' })) return;

    var listEl = document.getElementById('txKantinList');
    var countEl = document.getElementById('txKantinCount');
    var rangeEl = document.getElementById('txKantinRange');
    var filterForm = document.getElementById('txKantinFilterForm');
    var dateFromEl = document.getElementById('txKantinDateFrom');
    var dateToEl = document.getElementById('txKantinDateTo');
    var resetBtn = document.getElementById('txKantinFilterReset');
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
        listEl.innerHTML = OrtuKeu.renderTransaksiKantinList(items, { filtered: hasFilter });
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

    if (listEl) listEl.innerHTML = OrtuKeu.loadingHtml('Memuat transaksi kantin…');

    OrtuApi.fetchTransaksiKantin(username).then(function (res) {
      allItems = res.datas || [];
      renderFiltered();
    }).catch(function (err) {
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml(err.message);
    });
  }

  global.OrtuTransaksiKantinPage = { init: initTransaksiKantinPage };
})(typeof window !== 'undefined' ? window : globalThis);
