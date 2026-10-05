/**
 * Shell navigasi admin production.
 * body[data-nav]: home | siswa | rekam | saldo | uji
 */
(function (global) {
  "use strict";

  function prefix() {
    var root = document.body && document.body.getAttribute("data-app-root");
    if (root == null || String(root).trim() === "") return "";
    return String(root).replace(/\/?$/, "/");
  }

  function items() {
    var p = prefix();
    return [
      { id: "home", href: p + "index.html", label: "Beranda", icon: "home" },
      { id: "siswa", href: p + "settings/data-siswa.html", label: "Siswa", icon: "users" },
      { id: "rekam", href: p + "settings/rekam-data.html", label: "Rekam", icon: "cam" },
      { id: "saldo", href: p + "settings/saldo-siswa.html", label: "Saldo", icon: "wallet" },
      { id: "uji", href: p + "modules/face.html", label: "Uji", icon: "face" },
    ];
  }

  function iconSvg(kind) {
    var paths = {
      home:
        '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/>',
      users:
        '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
      cam:
        '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3"/>',
      wallet:
        '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="16" cy="14" r="1.5"/>',
      face:
        '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>',
    };
    return (
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">' +
      (paths[kind] || paths.home) +
      "</svg>"
    );
  }

  function renderNav() {
    var host = document.getElementById("admin-bottom-nav");
    if (!host) return;
    var active = (document.body.getAttribute("data-nav") || "home").toLowerCase();
    host.className = "bottom-nav";
    host.setAttribute("aria-label", "Menu utama");
    host.innerHTML = items()
      .map(function (it) {
        var on = it.id === active;
        return (
          '<a href="' +
          it.href +
          '" class="bottom-nav__link' +
          (on ? " bottom-nav__link--active" : "") +
          '"' +
          (on ? ' aria-current="page"' : "") +
          ">" +
          '<span class="bottom-nav__icon" aria-hidden="true">' +
          iconSvg(it.icon) +
          "</span>" +
          '<span class="bottom-nav__label">' +
          it.label +
          "</span></a>"
        );
      })
      .join("");
  }

  function boot() {
    renderNav();
  }

  global.AdminShell = { renderNav: renderNav, prefix: prefix };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})(typeof window !== "undefined" ? window : globalThis);
