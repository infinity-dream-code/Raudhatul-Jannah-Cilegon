/**
 * Halaman multi akun — hanya akun dalam kelompok DB (ortu_akun_kelompok).
 */
(function (global) {
  function normUser(value) {
    return global.OrtuAccounts ? OrtuAccounts.normUser(value) : String(value || '').replace(/\D/g, '').trim();
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function logoutToLogin() {
    try {
      sessionStorage.setItem('ortu_just_logged_out', '1');
    } catch (_e) {}
    if (global.OrtuAccounts) OrtuAccounts.clearCache();
    if (global.OrtuAuth) OrtuAuth.clearSession();
    global.location.href = 'ortu-login.html';
  }

  function renderList(accounts) {
    var listEl = document.getElementById('multiAccountList');
    if (!listEl) return;

    if (!accounts || !accounts.length) {
      listEl.innerHTML =
        '<div class="ortu-empty-state">' +
          '<h2>Belum ada kelompok akun</h2>' +
          '<p>Hanya akun dalam kelompok yang sama (maks. 3) yang ditampilkan. Login atau tambah akun untuk membuat kelompok.</p>' +
        '</div>';
      return;
    }

    listEl.innerHTML = accounts.map(function (row) {
      var active = row.isActive ? ' is-active' : '';
      var user = row.studentUsername || '';
      return (
        '<article class="ortu-account-card' + active + '" data-username="' + user + '">' +
          '<button type="button" class="ortu-account-card__main" data-ortu-switch="' + user + '">' +
            '<span class="ortu-account-card__avatar" aria-hidden="true">' +
              '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">' +
                '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>' +
              '</svg>' +
            '</span>' +
            '<span class="ortu-account-card__body">' +
              '<span class="ortu-account-card__name">' + escapeHtml(row.studentName || '—') + '</span>' +
              '<span class="ortu-account-card__meta">NIS: ' + escapeHtml(row.studentNis || user) + '</span>' +
              '<span class="ortu-account-card__meta">' + escapeHtml(row.classInfo || '—') + '</span>' +
            '</span>' +
            (row.isActive ? '<span class="ortu-account-card__badge">Aktif</span>' : '') +
          '</button>' +
          '<button type="button" class="ortu-account-card__delete" data-ortu-delete="' + user + '" aria-label="Hapus akun">' +
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
              '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
            '</svg>' +
          '</button>' +
        '</article>'
      );
    }).join('');
  }

  function bindActions(currentUsername) {
    var listEl = document.getElementById('multiAccountList');
    if (!listEl) return;

    listEl.querySelectorAll('[data-ortu-switch]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var username = btn.getAttribute('data-ortu-switch');
        var card = btn.closest('.ortu-account-card');
        if (card && card.classList.contains('is-active')) {
          global.location.href = 'ortu-profil.html';
          return;
        }
        btn.disabled = true;
        OrtuAuth.switchAccount(username).then(function (result) {
          if (!result.ok) {
            if (global.OrtuShell) OrtuShell.showToast(result.message);
            btn.disabled = false;
            return;
          }
          global.location.href = 'ortu-profil.html';
        });
      });
    });

    listEl.querySelectorAll('[data-ortu-delete]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var target = btn.getAttribute('data-ortu-delete');
        if (!target) return;
        if (!global.confirm(
          normUser(target) === normUser(currentUsername)
            ? 'Hapus akun ini? Anda akan logout. Login lagi untuk menyimpan akun.'
            : 'Hapus akun ini dari daftar tersimpan?'
        )) {
          return;
        }

        btn.disabled = true;
        var deletingCurrent = normUser(target) === normUser(currentUsername);
        OrtuAccounts.deleteAccount(currentUsername, target)
          .then(function () {
            if (deletingCurrent) {
              logoutToLogin();
              return;
            }
            return OrtuAccounts.fetchAccounts(currentUsername, currentUsername).then(function (accounts) {
              renderList(accounts);
              bindActions(currentUsername);
              if (global.OrtuShell) OrtuShell.showToast('Akun dihapus');
            });
          })
          .catch(function (err) {
            btn.disabled = false;
            if (global.OrtuShell) {
              OrtuShell.showToast(err.message || 'Gagal menghapus akun');
            }
          });
      });
    });
  }

  function initMultiAkunPage() {
    if (!global.OrtuAuth || !OrtuAuth.requireAuth('ortu-login.html')) return;

    var session = OrtuAuth.getSession();
    if (!session) return;
    var currentUser = session.username || session.nis;
    var listEl = document.getElementById('multiAccountList');
    if (listEl) listEl.innerHTML = '<div class="ortu-empty-state"><p>Memuat akun…</p></div>';

    OrtuAccounts.loadAccountsForUser(currentUser)
      .then(function (accounts) {
        renderList(accounts);
        bindActions(currentUser);
      })
      .catch(function () {
        renderList([]);
        bindActions(currentUser);
      });

    if (global.OrtuShell) {
      OrtuShell.setupLogout('ortuBtnLogout');
      OrtuShell.markNavActive('profil');
    }

    var addBtn = document.getElementById('btnTambahAkun');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        global.location.href = 'ortu-login.html?mode=add';
      });
    }
  }

  global.OrtuMultiAkunPage = { init: initMultiAkunPage };
})(typeof window !== 'undefined' ? window : globalThis);
