/**
 * Shell dashboard orang tua — header, nav bawah, toast.
 */
(function (global) {
  function showToast(message) {
    var el = document.getElementById('ortuToast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'ortuToast';
      el.className = 'ortu-toast';
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add('is-visible');
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(function () {
      el.classList.remove('is-visible');
    }, 2200);
  }

  function copyText(text) {
    if (!text) return Promise.reject();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        resolve();
      } catch (e) {
        reject(e);
      }
    });
  }

  function bindCopyButtons(root) {
    root = root || document;
    root.querySelectorAll('[data-ortu-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = btn.getAttribute('data-ortu-copy') || '';
        copyText(val)
          .then(function () {
            showToast('Disalin ke clipboard');
          })
          .catch(function () {
            showToast('Gagal menyalin');
          });
      });
    });
  }

  function setupLogout(btnId) {
    var btn = document.getElementById(btnId || 'ortuBtnLogout');
    if (!btn || !global.OrtuAuth) return;
    btn.addEventListener('click', function () {
      try {
        sessionStorage.setItem('ortu_just_logged_out', '1');
      } catch (_e) {}
      OrtuAuth.clearSession();
      global.location.href = 'ortu-login.html';
    });
  }

  function markNavActive(tab) {
    document.querySelectorAll('.ortu-nav-item').forEach(function (el) {
      el.classList.toggle('is-active', el.getAttribute('data-tab') === tab);
    });
  }

  function setupSubPage(options) {
    options = options || {};
    if (options.requireAuth !== false && global.OrtuAuth) {
      if (!OrtuAuth.requireAuth('ortu-login.html')) return false;
    }
    if (options.tab) markNavActive(options.tab);
    if (options.logoutId) setupLogout(options.logoutId);
    var back = document.querySelector('[data-ortu-back]');
    if (back && options.backHref) back.setAttribute('href', options.backHref);
    return true;
  }

  function bindAccordions(root) {
    root = root || document;
    root.querySelectorAll('[data-ortu-accordion]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var panelId = btn.getAttribute('aria-controls');
        var panel = panelId ? document.getElementById(panelId) : btn.nextElementSibling;
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (panel) panel.hidden = expanded;
      });
    });
  }

  function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function setupPasswordModal(triggerId, nis) {
    var trigger = document.getElementById(triggerId);
    var modal = document.getElementById('ortuPasswordModal');
    var form = document.getElementById('ortuPasswordForm');
    var errEl = document.getElementById('ortuPasswordError');
    var submitBtn = document.getElementById('ortuPasswordSubmit');
    if (!trigger || !modal || !form || !global.OrtuAuth) return;

    trigger.addEventListener('click', function () {
      errEl.style.display = 'none';
      form.reset();
      openModal('ortuPasswordModal');
      setTimeout(function () {
        var inp = document.getElementById('ortuCurrentPassword');
        if (inp) inp.focus();
      }, 200);
    });

    modal.querySelectorAll('[data-ortu-modal-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        closeModal('ortuPasswordModal');
      });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      errEl.style.display = 'none';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Menyimpan…';
      }

      OrtuAuth.changePassword(
        nis,
        document.getElementById('ortuCurrentPassword').value,
        document.getElementById('ortuNewPassword').value,
        document.getElementById('ortuConfirmPassword').value
      ).then(function (result) {
        if (!result.ok) {
          errEl.textContent = result.message;
          errEl.style.display = 'block';
          return;
        }
        closeModal('ortuPasswordModal');
        showToast(result.message);
      }).finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Simpan';
        }
      });
    });
  }

  global.OrtuShell = {
    showToast: showToast,
    copyText: copyText,
    bindCopyButtons: bindCopyButtons,
    setupLogout: setupLogout,
    markNavActive: markNavActive,
    setupSubPage: setupSubPage,
    bindAccordions: bindAccordions,
    setupPasswordModal: setupPasswordModal,
    openModal: openModal,
    closeModal: closeModal,
  };
})(typeof window !== 'undefined' ? window : globalThis);
