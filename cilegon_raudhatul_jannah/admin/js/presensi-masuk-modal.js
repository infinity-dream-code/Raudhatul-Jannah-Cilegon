(function () {
  var hideTimer = null;

  function buildModal() {
    var wrap = document.createElement("div");
    wrap.id = "presensi-masuk-modal";
    wrap.className = "presensi-masuk-modal";
    wrap.setAttribute("hidden", "");
    wrap.setAttribute("role", "dialog");
    wrap.setAttribute("aria-modal", "true");
    wrap.innerHTML =
      '<div class="presensi-masuk-modal__backdrop" aria-hidden="true"></div>' +
      '<div class="presensi-masuk-modal__dialog">' +
      '<div class="presensi-masuk-modal__icon" aria-hidden="true">✓</div>' +
      '<h2 class="presensi-masuk-modal__title" id="presensi-masuk-modal-title">Berhasil masuk</h2>' +
      '<p class="presensi-masuk-modal__nama" id="presensi-masuk-modal-nama"></p>' +
      '<p class="presensi-masuk-modal__subtitle" id="presensi-masuk-modal-subtitle" hidden></p>' +
      '<dl class="presensi-masuk-modal__dl">' +
      "<div><dt>NIS</dt><dd id=\"presensi-masuk-modal-nis\"></dd></div>" +
      "</dl>" +
      '<button type="button" class="btn btn--ghost presensi-masuk-modal__close" id="presensi-masuk-modal-close">Tutup</button>' +
      "</div>";
    document.body.appendChild(wrap);

    function close() {
      hideModal(wrap);
    }
    wrap.querySelector(".presensi-masuk-modal__backdrop").addEventListener("click", close);
    wrap.querySelector("#presensi-masuk-modal-close").addEventListener("click", close);
    return wrap;
  }

  if (!window.__presensiMasukModalEscBound) {
    window.__presensiMasukModalEscBound = true;
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var m = document.getElementById("presensi-masuk-modal");
      if (!m || m.hidden) return;
      if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
      }
      m.hidden = true;
    });
  }

  function hideModal(el) {
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    el.hidden = true;
  }

  function ensureModal() {
    var el = document.getElementById("presensi-masuk-modal");
    return el || buildModal();
  }

  /**
   * @param {{ title?: string, nama?: string, nis?: string, subtitle?: string, durationMs?: number }} opts
   */
  window.presensiShowMasukModal = function (opts) {
    opts = opts || {};
    var el = ensureModal();
    var title = opts.title || "Berhasil masuk";
    el.querySelector("#presensi-masuk-modal-title").textContent = title;
    el.querySelector("#presensi-masuk-modal-nama").textContent = opts.nama != null ? String(opts.nama) : "—";
    var sub = el.querySelector("#presensi-masuk-modal-subtitle");
    if (sub) {
      if (opts.subtitle != null && String(opts.subtitle).trim() !== "") {
        sub.textContent = String(opts.subtitle).trim();
        sub.hidden = false;
      } else {
        sub.textContent = "";
        sub.hidden = true;
      }
    }
    el.querySelector("#presensi-masuk-modal-nis").textContent = opts.nis != null ? String(opts.nis) : "—";
    el.hidden = false;
    if (hideTimer) clearTimeout(hideTimer);
    var ms = typeof opts.durationMs === "number" ? opts.durationMs : 4000;
    hideTimer = window.setTimeout(function () {
      hideTimer = null;
      el.hidden = true;
    }, ms);
  };
})();
