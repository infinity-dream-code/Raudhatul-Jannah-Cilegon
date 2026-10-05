/**
 * Halaman lihat tagihan — BillRequest.
 */
(function (global) {
  function initTagihanPage() {
    if (!global.OrtuShell || !OrtuShell.setupSubPage({ tab: 'keuangan' })) return;

    var listEl = document.getElementById('tagihanList');
    var sisaEl = document.getElementById('tagihanSisa');
    var username = global.OrtuKeu ? OrtuKeu.getUsername() : '';

    if (!username) {
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml('Session habis. Silakan login ulang.');
      return;
    }

    if (listEl) listEl.innerHTML = OrtuKeu.loadingHtml('Memuat tagihan…');
    if (sisaEl) sisaEl.textContent = '…';

    OrtuApi.fetchBill(username).then(function (res) {
      var items = res.datas || [];
      if (sisaEl) sisaEl.textContent = OrtuKeu.formatRupiah(OrtuKeu.sumTagihan(items), { short: true });
      if (listEl) listEl.innerHTML = OrtuKeu.renderTagihanList(items);
      OrtuShell.bindAccordions(document);
    }).catch(function (err) {
      if (sisaEl) sisaEl.textContent = '—';
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml(err.message);
    });
  }

  global.OrtuTagihanPage = { init: initTagihanPage };
})(typeof window !== 'undefined' ? window : globalThis);
