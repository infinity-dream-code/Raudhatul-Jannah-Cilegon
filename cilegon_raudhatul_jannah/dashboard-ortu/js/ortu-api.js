/**
 * Klien API Smart Payment — via proxy PHP same-origin.
 */
(function (global) {
  function endpoint(key, fallback) {
    var cfg = global.OrtuConfig || {};
    return cfg[key] || fallback;
  }

  function normUser(username) {
    return String(username || '').replace(/\D/g, '').trim();
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, message: 'Respon server tidak valid' };
      }).then(function (data) {
        if (!res.ok || !data.ok) {
          var err = new Error(data.message || 'Permintaan gagal');
          err.response = data;
          throw err;
        }
        return data;
      });
    });
  }

  function login(username, password) {
    return postJson(endpoint('loginEndpoint', 'api/login.php'), {
      username: String(username || '').trim(),
      password: String(password || '').trim(),
    });
  }

  function fetchSaldo(username) {
    return postJson(endpoint('saldoEndpoint', 'api/saldo.php'), {
      username: normUser(username),
    });
  }

  function fetchBill(username) {
    return postJson(endpoint('billEndpoint', 'api/bill.php'), {
      username: normUser(username),
    });
  }

  function fetchPayment(username) {
    return postJson(endpoint('paymentEndpoint', 'api/payment.php'), {
      username: normUser(username),
    });
  }

  function fetchTransaksi(username) {
    return postJson(endpoint('transaksiEndpoint', 'api/transaksi.php'), {
      username: normUser(username),
    });
  }

  function fetchTransaksiKantin(username) {
    return postJson(endpoint('transaksiKantinEndpoint', 'api/transaksi-kantin.php'), {
      username: normUser(username),
    });
  }

  function changePassword(username, password, newPassword, newPassword2) {
    return postJson(endpoint('changePasswordEndpoint', 'api/change-password.php'), {
      username: normUser(username),
      password: String(password || '').trim(),
      newPassword: String(newPassword || '').trim(),
      newPassword2: String(newPassword2 || '').trim(),
    });
  }

  global.OrtuApi = {
    login: login,
    fetchSaldo: fetchSaldo,
    fetchBill: fetchBill,
    fetchPayment: fetchPayment,
    fetchTransaksi: fetchTransaksi,
    fetchTransaksiKantin: fetchTransaksiKantin,
    changePassword: changePassword,
  };
})(typeof window !== 'undefined' ? window : globalThis);
