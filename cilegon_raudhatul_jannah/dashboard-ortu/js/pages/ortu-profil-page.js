/**
 * Halaman profil anak — data dari session login Smart Payment.
 */
(function (global) {
  function text(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value != null && value !== '' ? value : '—';
  }

  function renderProfile(s) {
    if (!s) return;
    var vaSpp = s.vaSpp || '—';
    var vaDu = s.vaDu || '—';
    var displayId = s.username || s.nis || '—';

    text('heroNis', displayId);
    text('heroNama', s.nama);
    text('valVaSpp', vaSpp);
    text('valVaDu', vaDu);
    text('valUnit', s.unit);
    text('valJenjang', s.jenjang || s.kelompok);
    text('valKelas', s.kelas);
    text('valSaldo', global.OrtuAuth ? OrtuAuth.formatRupiah(s.saldoSpp) : '—');

    var copySpp = document.getElementById('btnCopyVaSpp');
    var copyDu = document.getElementById('btnCopyVaDu');
    if (copySpp && vaSpp !== '—') copySpp.setAttribute('data-ortu-copy', vaSpp);
    if (copyDu && vaDu !== '—') copyDu.setAttribute('data-ortu-copy', vaDu);
  }

  function loadSaldo(username) {
    var saldoEl = document.getElementById('valSaldo');
    if (saldoEl) saldoEl.textContent = 'Memuat…';

    return OrtuAuth.fetchSaldo(username).then(function (result) {
      if (result.ok && result.session) {
        renderProfile(result.session);
        return;
      }
      var session = OrtuAuth.getSession();
      if (session) {
        text('valSaldo', OrtuAuth.formatRupiah(session.saldoSpp));
      } else if (saldoEl) {
        saldoEl.textContent = '—';
      }
    });
  }

  function initProfilPage() {
    if (!global.OrtuAuth || !OrtuAuth.requireAuth('ortu-login.html')) return;
    var s = OrtuAuth.getSession();
    if (!s) return;

    renderProfile(s);
    loadSaldo(s.username || s.nis);

    if (global.OrtuAccounts && typeof OrtuAccounts.ensureCurrentAccountSaved === 'function') {
      OrtuAccounts.ensureCurrentAccountSaved();
    }

    if (global.OrtuShell) {
      OrtuShell.bindCopyButtons(document);
      OrtuShell.setupLogout('ortuBtnLogout');
      OrtuShell.markNavActive('profil');
      OrtuShell.setupPasswordModal('btnGantiPassword', s.username || s.nis);
    }

    var multiBtn = document.getElementById('btnMultiAkun');
    if (multiBtn) {
      multiBtn.addEventListener('click', function () {
        global.location.href = 'ortu-multi-akun.html';
      });
    }
  }

  global.OrtuProfilPage = { init: initProfilPage };
})(typeof window !== 'undefined' ? window : globalThis);
