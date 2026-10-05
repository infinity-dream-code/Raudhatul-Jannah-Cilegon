/**
 * Halaman login orang tua.
 */
(function (global) {
  function readAnchorUser(fallback) {
    var anchor = normUser(fallback || '');
    try {
      var stored = sessionStorage.getItem('ortu_add_link_with');
      if (stored) anchor = normUser(stored);
    } catch (_e) {}
    return anchor;
  }

  function normUser(value) {
    return String(value || '').replace(/\D/g, '').trim();
  }

  function initLoginPage() {
    var errEl = document.getElementById('ortuLoginError');
    var form = document.getElementById('ortuLoginForm');
    var submitBtn = form ? form.querySelector('[type="submit"]') : null;
    var hintEl = document.querySelector('.ortu-hint');
    if (!form || !errEl) return;

    var params = new URLSearchParams(global.location.search);
    var modeAdd = params.get('mode') === 'add';
    var prevSession = modeAdd && global.OrtuAuth ? OrtuAuth.getSession() : null;
    var linkWithUsername = '';
    if (prevSession) {
      linkWithUsername = prevSession.username || prevSession.nis || '';
      try {
        sessionStorage.setItem('ortu_add_link_with', linkWithUsername);
        sessionStorage.setItem('ortu_add_link_ctx', JSON.stringify({
          username: linkWithUsername,
          password: prevSession.loginPassword || '',
          profile: prevSession,
        }));
      } catch (_e) {}
    }

    if (modeAdd && hintEl) {
      hintEl.innerHTML =
        '<strong>Tambah akun baru</strong><br>' +
        'Login dengan username anak yang berbeda. Akun akan ditambahkan ke kelompok yang sama (akun_2 / akun_3).';
    }

    try {
      if (sessionStorage.getItem('ortu_just_logged_out') === '1') {
        sessionStorage.removeItem('ortu_just_logged_out');
      } else if (!modeAdd && global.OrtuAuth && OrtuAuth.getSession()) {
        global.location.replace('ortu-profil.html');
        return;
      }
    } catch (_e) {}

    var preUser = params.get('username') || params.get('nis');
    var userInput = document.getElementById('ortuUsername');
    if (preUser && userInput) userInput.value = preUser;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      errEl.style.display = 'none';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Memproses…';
      }

      var username = userInput ? userInput.value : '';
      var password = document.getElementById('ortuPassword').value;
      var anchorUser = modeAdd ? readAnchorUser(linkWithUsername) : '';
      var loginOpts = { linkWithUsername: anchorUser };

      function runLogin() {
        return OrtuAuth.login(username, password, loginOpts);
      }

      function ensureLinked(result) {
        if (!result.ok || !modeAdd || !anchorUser || !global.OrtuAccounts) {
          return result;
        }
        var newUser = normUser(username);
        if (!newUser || newUser === anchorUser) return result;

        return OrtuAccounts.linkAccount(newUser, anchorUser, newUser)
          .then(function (accounts) {
            if (!accounts || accounts.length < 2) {
              return OrtuAccounts.saveAccount(newUser, password, result.profile, anchorUser);
            }
            return accounts;
          })
          .catch(function (err) {
            errEl.textContent = (err && err.message) || 'Gagal menghubungkan ke kelompok akun.';
            errEl.style.display = 'block';
            return null;
          })
          .then(function (linkResult) {
            if (linkResult === null) {
              return { ok: false, message: errEl.textContent };
            }
            return result;
          });
      }

      var prepare = Promise.resolve();
      var linkCtx = null;
      if (modeAdd) {
        try {
          var rawCtx = sessionStorage.getItem('ortu_add_link_ctx');
          if (rawCtx) linkCtx = JSON.parse(rawCtx);
        } catch (_e) {}
      }
      if (modeAdd && anchorUser && global.OrtuAccounts) {
        var anchorPw = (prevSession && prevSession.loginPassword) || (linkCtx && linkCtx.password) || '';
        var anchorProfile = prevSession || (linkCtx && linkCtx.profile) || null;
        if (anchorPw && anchorProfile) {
          prepare = OrtuAccounts.saveAccount(anchorUser, anchorPw, anchorProfile, '').catch(function () {
            return null;
          });
        }
      }

      prepare
        .then(runLogin)
        .then(ensureLinked)
        .then(function (result) {
          if (!result || !result.ok) {
            if (result && result.message) {
              errEl.textContent = result.message;
              errEl.style.display = 'block';
            }
            return;
          }
          if (modeAdd) {
            try {
              sessionStorage.removeItem('ortu_add_link_with');
              sessionStorage.removeItem('ortu_add_link_ctx');
            } catch (_e) {}
          }
          global.location.href = modeAdd ? 'ortu-multi-akun.html' : 'ortu-profil.html';
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Masuk';
          }
        });
    });
  }

  global.OrtuLoginPage = { init: initLoginPage };
})(typeof window !== 'undefined' ? window : globalThis);
