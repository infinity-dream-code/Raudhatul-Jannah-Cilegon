/**
 * Halaman pembayaran — PaymentRequest.
 */
(function (global) {
  function initPembayaranPage() {
    if (!global.OrtuShell || !OrtuShell.setupSubPage({ tab: 'keuangan' })) return;

    var listEl = document.getElementById('paymentList');
    var username = global.OrtuKeu ? OrtuKeu.getUsername() : '';

    if (!username) {
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml('Session habis. Silakan login ulang.');
      return;
    }

    if (listEl) listEl.innerHTML = OrtuKeu.loadingHtml('Memuat pembayaran…');

    OrtuApi.fetchPayment(username).then(function (res) {
      var items = res.datas || [];
      if (listEl) listEl.innerHTML = OrtuKeu.renderPaymentList(items);
      OrtuShell.bindAccordions(document);

      listEl.querySelectorAll('[data-ortu-receipt]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          OrtuShell.showToast('Cetak kuitansi — segera hadir');
        });
      });
    }).catch(function (err) {
      if (listEl) listEl.innerHTML = OrtuKeu.errorHtml(err.message);
    });
  }

  global.OrtuPembayaranPage = { init: initPembayaranPage };
})(typeof window !== 'undefined' ? window : globalThis);
