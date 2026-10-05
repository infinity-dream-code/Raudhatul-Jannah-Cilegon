/**
 * Auth & session dashboard orang tua — Smart Payment LoginRequest.
 */
(function (global) {
  var STORAGE_KEY = 'cilegon_rj_ortu_session';

  function normUsername(value) {
    return String(value || '').replace(/\D/g, '').trim();
  }

  function formatRupiah(n) {
    var num = Number(n) || 0;
    return 'Rp ' + num.toLocaleString('id-ID');
  }

  function mapLoginResponse(username, apiData) {
    var d = apiData || {};
    return {
      username: normUsername(username),
      nis: normUsername(username),
      nama: String(d.Mahasiswa || '').trim() || '—',
      unit: String(d.Jenjang || '').trim() || '—',
      jenjang: String(d.Kelompok || '').trim() || '—',
      kelas: String(d.Kelas || '').trim() || '—',
      kelompok: String(d.Kelompok || '').trim() || '—',
      vaSpp: String(d.NOVA || '').trim(),
      vaDu: String(d.NOVASAKU || '').trim(),
      saldoSpp: 0,
      kodeRespon: Number(d.KodeRespon) || 0,
      loginAt: Date.now(),
      source: 'smartpayment',
    };
  }

  function getSession() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (_e) {
      return null;
    }
  }

  function setSession(profile) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
    } catch (_e) {}
    return profile;
  }

  function clearSession() {
    localStorage.removeItem(STORAGE_KEY);
  }

  function login(username, password, options) {
    options = options || {};
    var user = normUsername(username);
    var pw = String(password || '').trim();
    if (!user) {
      return Promise.resolve({ ok: false, message: 'Masukkan username terlebih dahulu.' });
    }
    if (!pw) {
      return Promise.resolve({ ok: false, message: 'Masukkan password terlebih dahulu.' });
    }
    if (!global.OrtuApi || typeof OrtuApi.login !== 'function') {
      return Promise.resolve({ ok: false, message: 'Modul API belum dimuat.' });
    }

    return OrtuApi.login(user, pw)
      .then(function (res) {
        var profile = mapLoginResponse(res.username || user, res.data);
        profile.loginPassword = pw;
        var linkWith = normUsername(options.linkWithUsername || '');
        if (!linkWith) {
          try {
            linkWith = normUsername(sessionStorage.getItem('ortu_add_link_with') || '');
          } catch (_e) {}
        }
        if (linkWith && linkWith !== user) {
          profile.linkedToUsername = linkWith;
        }
        setSession(profile);
        var savePromise = Promise.resolve(null);
        if (global.OrtuAccounts && typeof OrtuAccounts.saveAccount === 'function') {
          savePromise = OrtuAccounts.saveAccount(
            user,
            pw,
            profile,
            linkWith
          ).then(function (accounts) {
            if (accounts && accounts.length > 1 && profile.linkedToUsername) {
              delete profile.linkedToUsername;
              setSession(profile);
            }
            return accounts;
          }).catch(function (err) {
            if (typeof console !== 'undefined' && console.warn) {
              console.warn('Gagal menghubungkan multi akun:', err && err.message);
            }
            return null;
          });
        }
        return savePromise.then(function () {
          return { ok: true, profile: profile };
        });
      })
      .catch(function (err) {
        return {
          ok: false,
          message: (err && err.message) || 'Login gagal. Periksa username dan password.',
        };
      });
  }

  function parseSaldo(value) {
    if (value == null || value === '') return 0;
    if (typeof value === 'number' && !isNaN(value)) return value;
    var digits = String(value).replace(/\D/g, '');
    var num = Number(digits);
    return isNaN(num) ? 0 : num;
  }

  function applySaldoToSession(apiData, saldo) {
    var session = getSession();
    if (!session) return null;
    var d = apiData || {};
    session.saldoSpp = saldo;
    if (d.NOVA) session.vaSpp = String(d.NOVA).trim();
    if (d.NOVASAKU) session.vaDu = String(d.NOVASAKU).trim();
    if (d.Mahasiswa) session.nama = String(d.Mahasiswa).trim();
    if (d.Jenjang) session.unit = String(d.Jenjang).trim();
    if (d.Kelas) session.kelas = String(d.Kelas).trim();
    if (d.Kelompok) {
      session.kelompok = String(d.Kelompok).trim();
      session.jenjang = session.kelompok;
    }
    session.saldoAt = Date.now();
    setSession(session);
    return session;
  }

  function fetchSaldo(username) {
    var user = normUsername(username);
    if (!user) {
      return Promise.resolve({ ok: false, message: 'Username tidak valid.' });
    }
    if (!global.OrtuApi || typeof OrtuApi.fetchSaldo !== 'function') {
      return Promise.resolve({ ok: false, message: 'Modul API belum dimuat.' });
    }

    return OrtuApi.fetchSaldo(user)
      .then(function (res) {
        var saldo = parseSaldo(res.saldo != null ? res.saldo : (res.data && res.data.SALDO));
        var session = applySaldoToSession(res.data, saldo);
        return { ok: true, saldo: saldo, session: session, data: res.data };
      })
      .catch(function (err) {
        return {
          ok: false,
          message: (err && err.message) || 'Gagal mengambil saldo SPP.',
        };
      });
  }

  function changePassword(username, currentPassword, newPassword, confirmPassword) {
    var user = normUsername(username);
    var pw = String(currentPassword || '').trim();
    var newPw = String(newPassword || '').trim();
    var confirm = String(confirmPassword || '').trim();

    if (!user) {
      return Promise.resolve({ ok: false, message: 'Username tidak valid.' });
    }
    if (!pw) {
      return Promise.resolve({ ok: false, message: 'Masukkan password lama.' });
    }
    if (!newPw) {
      return Promise.resolve({ ok: false, message: 'Masukkan password baru.' });
    }
    if (newPw.length < 3) {
      return Promise.resolve({ ok: false, message: 'Password baru minimal 3 karakter.' });
    }
    if (newPw !== confirm) {
      return Promise.resolve({ ok: false, message: 'Konfirmasi password tidak cocok.' });
    }
    if (!global.OrtuApi || typeof OrtuApi.changePassword !== 'function') {
      return Promise.resolve({ ok: false, message: 'Modul API belum dimuat.' });
    }

    return OrtuApi.changePassword(user, pw, newPw, confirm)
      .then(function (res) {
        var session = getSession();
        if (session && String(session.username || session.nis) === user) {
          session.passwordChanged = true;
          session.passwordChangedAt = Date.now();
          setSession(session);
        }
        return {
          ok: true,
          message: res.message || 'Password berhasil diubah.',
        };
      })
      .catch(function (err) {
        return {
          ok: false,
          message: (err && err.message) || 'Gagal mengubah password.',
        };
      });
  }

  function switchAccount(username) {
    var user = normUsername(username);
    if (!user || !global.OrtuAccounts) {
      return Promise.resolve({ ok: false, message: 'Akun tidak ditemukan.' });
    }

    return OrtuAccounts.loadAccountsForUser(user).then(function (accounts) {
      var account = OrtuAccounts.getAccountFromList(accounts, user);
      var password = account && account.password ? account.password : '';
      if (!password) {
        var session = getSession();
        if (session && normUsername(session.username || session.nis) === user) {
          password = String(session.loginPassword || '').trim();
        }
      }
      if (!password) {
        return { ok: false, message: 'Akun tidak ditemukan. Login ulang.' };
      }
      return login(user, password).then(function (result) {
        return result;
      });
    });
  }

  function requireAuth(loginPath) {
    if (getSession()) return true;
    global.location.href = loginPath || 'ortu-login.html';
    return false;
  }

  global.OrtuAuth = {
    STORAGE_KEY: STORAGE_KEY,
    login: login,
    fetchSaldo: fetchSaldo,
    changePassword: changePassword,
    switchAccount: switchAccount,
    getSession: getSession,
    setSession: setSession,
    clearSession: clearSession,
    requireAuth: requireAuth,
    formatRupiah: formatRupiah,
    mapLoginResponse: mapLoginResponse,
  };
})(typeof window !== 'undefined' ? window : globalThis);
