/**
 * Multi account — hanya akun dalam kelompok DB (ortu_akun_kelompok).
 */
(function (global) {
  var ACCOUNTS_CACHE_KEY = 'cilegon_rj_ortu_accounts_cache';

  function normUser(value) {
    return String(value || '').replace(/\D/g, '').trim();
  }

  function endpoint() {
    var cfg = global.OrtuConfig || {};
    return cfg.multiAccountEndpoint || 'api/multi-account.php';
  }

  function postAction(body) {
    return fetch(endpoint(), {
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

  function cacheAccounts(username, accounts) {
    try {
      localStorage.setItem(ACCOUNTS_CACHE_KEY, JSON.stringify({
        username: normUser(username),
        accounts: accounts || [],
        cachedAt: Date.now(),
      }));
    } catch (_e) {}
  }

  function markActive(accounts, activeUsername) {
    var active = normUser(activeUsername);
    return (accounts || []).map(function (row) {
      row.isActive = normUser(row.studentUsername) === active;
      return row;
    });
  }

  function clearCache() {
    try {
      localStorage.removeItem(ACCOUNTS_CACHE_KEY);
    } catch (_e) {}
  }

  function fetchAccounts(username, activeUsername) {
    var user = normUser(username);
    return postAction({ action: 'list', username: user })
      .then(function (res) {
        var accounts = markActive(res.accounts || [], activeUsername || user);
        cacheAccounts(user, accounts);
        return accounts;
      });
  }

  function saveAccount(username, password, profile, linkWithUsername) {
    var user = normUser(username);
    var linkWith = normUser(linkWithUsername || '');
    var body = {
      action: 'save',
      username: user,
      password: String(password || ''),
      profile: profile || {},
    };
    if (linkWith) body.linkWithUsername = linkWith;
    return postAction(body).then(function (res) {
      var accounts = markActive(res.accounts || [], user);
      cacheAccounts(user, accounts);
      return accounts;
    });
  }

  function linkAccount(newUsername, anchorUsername, activeUsername) {
    var user = normUser(newUsername);
    var anchor = normUser(anchorUsername);
    if (!user || !anchor || user === anchor) {
      return Promise.reject(new Error('Data hubung akun tidak valid.'));
    }
    return postAction({
      action: 'link',
      username: user,
      linkWithUsername: anchor,
    }).then(function (res) {
      var accounts = markActive(res.accounts || [], activeUsername || user);
      cacheAccounts(activeUsername || user, accounts);
      return accounts;
    });
  }

  function syncAccount(username, password, profile, activeUsername) {
    var user = normUser(username);
    return postAction({
      action: 'sync',
      username: user,
      password: String(password || ''),
      profile: profile || {},
    }).then(function (res) {
      var accounts = markActive(res.accounts || [], activeUsername || user);
      cacheAccounts(activeUsername || user, accounts);
      return accounts;
    });
  }

  function deleteAccount(_currentUsername, targetUsername) {
    var target = normUser(targetUsername);
    return postAction({
      action: 'remove',
      username: normUser(_currentUsername),
      targetUsername: target,
    }).then(function () {
      clearCache();
      return target;
    });
  }

  function getAccountFromList(accounts, username) {
    var user = normUser(username);
    for (var i = 0; i < (accounts || []).length; i++) {
      if (normUser(accounts[i].studentUsername) === user) return accounts[i];
    }
    return null;
  }

  function ensureCurrentAccountSaved() {
    if (!global.OrtuAuth || typeof OrtuAuth.getSession !== 'function') {
      return Promise.resolve(null);
    }
    var session = OrtuAuth.getSession();
    if (!session) return Promise.resolve(null);
    var user = normUser(session.username || session.nis);
    var password = String(session.loginPassword || '').trim();
    if (!user || !password) return Promise.resolve(null);
    return syncAccount(user, password, session, user).catch(function () {
      return null;
    });
  }

  function retryPendingLink(session, activeUsername) {
    if (!session) return Promise.resolve(null);
    var user = normUser(session.username || session.nis);
    var linkWith = normUser(session.linkedToUsername || '');
    var password = String(session.loginPassword || '').trim();
    if (!user || !linkWith || linkWith === user || !password) {
      return Promise.resolve(null);
    }
    return saveAccount(user, password, session, linkWith).then(function (accounts) {
      if (accounts && accounts.length > 1 && global.OrtuAuth) {
        var next = OrtuAuth.getSession();
        if (next && next.linkedToUsername) {
          delete next.linkedToUsername;
          OrtuAuth.setSession(next);
        }
      }
      return accounts;
    }).catch(function () {
      return linkAccount(user, linkWith, user).catch(function () {
        return null;
      });
    });
  }

  /** Daftar akun — hanya dari ortu_akun_kelompok (API list). */
  function loadAccountsForUser(activeUsername) {
    var user = normUser(activeUsername);
    var session = global.OrtuAuth && OrtuAuth.getSession ? OrtuAuth.getSession() : null;
    return ensureCurrentAccountSaved()
      .then(function () {
        return retryPendingLink(session, user);
      })
      .then(function () {
        return fetchAccounts(user, user);
      })
      .catch(function () {
        return [];
      });
  }

  function buildClassInfo(profile) {
    var kelas = String(profile.kelas || '').trim();
    var kelompok = String(profile.kelompok || profile.jenjang || '').trim();
    if (kelas && kelompok) return kelas + ' - ' + kelompok;
    return kelas || kelompok || '—';
  }

  global.OrtuAccounts = {
    fetchAccounts: fetchAccounts,
    loadAccountsForUser: loadAccountsForUser,
    ensureCurrentAccountSaved: ensureCurrentAccountSaved,
    syncAccount: syncAccount,
    saveAccount: saveAccount,
    linkAccount: linkAccount,
    deleteAccount: deleteAccount,
    removeAccount: deleteAccount,
    clearCache: clearCache,
    getAccountFromList: getAccountFromList,
    buildClassInfo: buildClassInfo,
    normUser: normUser,
  };
})(typeof window !== 'undefined' ? window : globalThis);
