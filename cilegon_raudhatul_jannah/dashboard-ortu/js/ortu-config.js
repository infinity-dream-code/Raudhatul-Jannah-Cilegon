/**
 * Konfigurasi klien dashboard orang tua (browser).
 */
(function (global) {
  global.OrtuConfig = {
    loginEndpoint: 'api/login.php',
    saldoEndpoint: 'api/saldo.php',
    billEndpoint: 'api/bill.php',
    paymentEndpoint: 'api/payment.php',
    transaksiEndpoint: 'api/transaksi.php',
    transaksiKantinEndpoint: 'api/transaksi-kantin.php',
    changePasswordEndpoint: 'api/change-password.php',
    multiAccountEndpoint: 'api/multi-account.php',
  };
})(typeof window !== 'undefined' ? window : globalThis);
