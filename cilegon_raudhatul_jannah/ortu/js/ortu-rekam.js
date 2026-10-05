(function () {
  "use strict";

  var MAX_FOTO_BYTES = 750 * 1024;
  var webcamStream = null;

  function esc(s) {
    return window.OrtuApp ? window.OrtuApp.escapeHtml(s) : String(s || "");
  }

  function fotoSizeApprox(dataUrl) {
    if (!dataUrl || typeof dataUrl !== "string") return 0;
    var base64 = dataUrl.split(",")[1];
    if (!base64) return dataUrl.length;
    return Math.floor((base64.length * 3) / 4);
  }

  function stopWebcam() {
    if (webcamStream) {
      webcamStream.getTracks().forEach(function (t) {
        t.stop();
      });
      webcamStream = null;
    }
  }

  function hasFoto(siswa) {
    var f = siswa && siswa.fotoWajah ? String(siswa.fotoWajah) : "";
    return f.length > 30 || Boolean(siswa && siswa.hasFoto);
  }

  function initials(nama) {
    var parts = String(nama || "S")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (!parts.length) return "S";
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function renderIdentity(root, siswa) {
    if (!root) return;
    var fotoOk = hasFoto(siswa);
    var fotoVal = siswa.fotoWajah && String(siswa.fotoWajah).length > 20 ? siswa.fotoWajah : "";
    var avatarHtml = fotoVal
      ? '<img class="ortu-profile__avatar' + (fotoOk ? " ortu-profile__avatar--ok" : "") + '" src="' + fotoVal + '" alt="Foto siswa" />'
      : '<div class="ortu-profile__avatar ortu-profile__avatar--empty" aria-hidden="true">' +
        esc(initials(siswa.nama)) +
        "</div>";

    root.innerHTML =
      '<section class="ortu-profile">' +
      '<div class="ortu-profile__avatar-wrap">' +
      avatarHtml +
      '<div class="ortu-profile__status-dot' + (fotoOk ? " ortu-profile__status-dot--ok" : "") + '" title="' + (fotoOk ? "Foto Terdaftar" : "Belum Rekam Foto") + '"></div>' +
      "</div>" +
      '<h2 class="ortu-profile__name">' +
      esc(siswa.nama) +
      "</h2>" +
      '<p class="ortu-profile__sub">NIS ' +
      esc(siswa.nis) +
      (siswa.nisn ? " · NISN " + esc(siswa.nisn) : "") +
      "</p>" +
      '<div class="ortu-chips">' +
      '<span class="ortu-chip' + (fotoOk ? " ortu-chip--ok" : "") + '">' +
      (fotoOk ? "✓ Foto wajah sudah ada" : "○ Belum ada foto wajah") +
      "</span>" +
      "</div></section>";
  }

  function renderRekam(root, siswa) {
    if (!root) return;
    stopWebcam();
    var fotoVal = siswa.fotoWajah != null ? siswa.fotoWajah : "";
    var hasFotoSaved = fotoVal && fotoVal.length > 20;

    root.innerHTML =
      '<section class="ortu-card ortu-rekam">' +
      '<h3 class="ortu-section-title">Rekam foto wajah</h3>' +
      '<p class="ortu-section-desc">Rekam dari kamera (depan atau belakang). Wajah menghadap kamera, pencahayaan terang, tanpa masker/topi.</p>' +
      '<div class="rekam-webcam">' +
      '<div class="rekam-webcam__wrap">' +
      '<video id="ortu-webcam-video" class="rekam-webcam__video" playsinline muted></video>' +
      '<div id="ortu-webcam-overlay" class="rekam-webcam__overlay">Tekan «Mulai kamera» untuk merekam</div>' +
      "</div>" +
      '<canvas id="ortu-webcam-canvas" hidden></canvas>' +
      '<div class="rekam-webcam__actions">' +
      '<button type="button" class="ortu-btn ortu-btn--primary" id="ortu-webcam-start">Mulai kamera</button>' +
      '<button type="button" class="ortu-btn ortu-btn--ghost" id="ortu-webcam-stop" hidden>Stop</button>' +
      '<button type="button" class="ortu-btn ortu-btn--ghost" id="ortu-webcam-flip" hidden title="Tukar kamera depan / belakang">Ganti kamera</button>' +
      '<button type="button" class="ortu-btn ortu-btn--primary" id="ortu-webcam-capture" hidden>Rekam foto</button>' +
      "</div>" +
      '<p id="ortu-webcam-status" class="rekam-webcam__status" aria-live="polite"></p>' +
      "</div>" +

      (hasFotoSaved
        ? '<div class="rekam-foto ortu-saved-foto">' +
          '<p class="ortu-section-desc">Foto tersimpan saat ini</p>' +
          '<div class="rekam-foto__preview-wrap"><img class="rekam-foto__preview" src="' +
          fotoVal +
          '" alt="Pratinjau wajah" /></div>' +
          '<button type="button" class="ortu-btn ortu-btn--danger" id="ortu-foto-hapus">Hapus foto</button>' +
          "</div>"
        : "") +
      '<div id="ortu-save-status" class="ortu-save-status" hidden aria-live="polite"></div>' +
      "</section>";

    bindRekamEvents(root, siswa);
  }

  function setStatus(el, msg, isError) {
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = "";
      el.classList.remove("ortu-save-status--error");
      return;
    }
    el.hidden = false;
    el.textContent = msg;
    el.classList.toggle("ortu-save-status--error", Boolean(isError));
  }

  function bindRekamEvents(root, siswa) {
    var wVid = root.querySelector("#ortu-webcam-video");
    var wOver = root.querySelector("#ortu-webcam-overlay");
    var wStart = root.querySelector("#ortu-webcam-start");
    var wStop = root.querySelector("#ortu-webcam-stop");
    var wFlip = root.querySelector("#ortu-webcam-flip");
    var wCap = root.querySelector("#ortu-webcam-capture");
    var wCan = root.querySelector("#ortu-webcam-canvas");
    var saveStatus = root.querySelector("#ortu-save-status");

    function setWebcamStatus(msg) {
      var el = root.querySelector("#ortu-webcam-status");
      if (el) el.textContent = msg || "";
    }

    function syncOrtuFlipLabel() {
      if (!wFlip || typeof window.presensiGetFacingMode !== "function") return;
      var back = window.presensiGetFacingMode() === "environment";
      wFlip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
      wFlip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
      if (wVid) {
        wVid.classList.toggle("rekam-webcam__video--back", back);
      }
    }

    function hideOverlay() {
      if (!wOver) return;
      wOver.hidden = true;
      wOver.setAttribute("hidden", "");
      wOver.style.display = "none";
    }

    function showOverlay(text) {
      if (!wOver) return;
      wOver.hidden = false;
      wOver.removeAttribute("hidden");
      wOver.style.display = "flex";
      if (text != null) wOver.textContent = text;
    }

    function startWebcam(auto) {
      if (!wVid) return Promise.resolve();
      if (typeof window.presensiOpenWebcam !== "function") {
        setWebcamStatus("Kamera tidak tersedia di browser ini.");
        if (wStart) {
          wStart.hidden = false;
          wStart.disabled = true;
        }
        showOverlay("Kamera tidak tersedia");
        return Promise.resolve();
      }
      if (auto) {
        setWebcamStatus("Menyalakan kamera…");
        showOverlay("Menyalakan kamera…");
      }
      return window
        .presensiOpenWebcam(wVid)
        .then(function (stream) {
          webcamStream = stream;
          hideOverlay();
          if (wStart) wStart.hidden = true;
          if (wStop) wStop.hidden = false;
          if (wFlip) wFlip.hidden = false;
          if (wCap) wCap.hidden = false;
          syncOrtuFlipLabel();
          setWebcamStatus("Atur posisi wajah, lalu tekan Rekam foto.");
        })
        .catch(function (err) {
          showOverlay("Tekan «Mulai kamera» untuk mencoba lagi");
          if (wStart) wStart.hidden = false;
          if (wFlip) wFlip.hidden = true;
          if (wStop) wStop.hidden = true;
          if (wCap) wCap.hidden = true;
          setWebcamStatus("Kamera gagal: " + (err && err.message ? err.message : err));
        });
    }

    function applySaved(dataUrl) {
      return window.OrtuApp.saveFotoWajah(siswa, dataUrl).then(function (saved) {
        Object.assign(siswa, saved || { fotoWajah: dataUrl, hasFoto: true });
        setStatus(saveStatus, "Foto wajah berhasil disimpan.", false);
        renderIdentity(document.getElementById("ortu-identity-root"), siswa);
        renderRekam(root, siswa);
      });
    }

    function handleSaveError(e) {
      setStatus(saveStatus, "Gagal menyimpan: " + (e && e.message ? e.message : e), true);
    }

    if (wStart && wVid) {
      wStart.addEventListener("click", function () {
        startWebcam(false).then(function () {
          if (webcamStream) hideOverlay();
        });
      });
      startWebcam(true);
    }

    if (wFlip && wVid && typeof window.presensiSwitchCamera === "function") {
      wFlip.addEventListener("click", function () {
        if (!webcamStream && !wVid.srcObject) return;
        setWebcamStatus("Mengganti kamera…");
        wFlip.disabled = true;
        window
          .presensiSwitchCamera(wVid)
          .then(function (stream) {
            webcamStream = stream;
            hideOverlay();
            syncOrtuFlipLabel();
            var label =
              typeof window.presensiFacingLabel === "function"
                ? window.presensiFacingLabel()
                : "kamera";
            setWebcamStatus("Kamera aktif (" + label + "). Atur posisi, lalu Rekam foto.");
          })
          .catch(function (err) {
            setWebcamStatus("Gagal ganti kamera: " + (err && err.message ? err.message : err));
          })
          .finally(function () {
            wFlip.disabled = false;
          });
      });
    }

    if (wStop && wVid && wStart && wCap) {
      wStop.addEventListener("click", function () {
        stopWebcam();
        if (typeof window.presensiStopWebcam === "function") {
          window.presensiStopWebcam(wVid);
        } else {
          wVid.srcObject = null;
        }
        showOverlay("Tekan «Mulai kamera» untuk menyalakan kembali");
        wStart.hidden = false;
        wStop.hidden = true;
        if (wFlip) wFlip.hidden = true;
        wCap.hidden = true;
        setWebcamStatus("");
      });
    }

    if (wCap && wVid && wCan) {
      wCap.addEventListener("click", function () {
        var vw = wVid.videoWidth;
        var vh = wVid.videoHeight;
        if (!vw || !vh) {
          alert("Video belum siap. Tunggu sebentar.");
          return;
        }
        wCan.width = vw;
        wCan.height = vh;
        var ctx = wCan.getContext("2d");
        var mirrored =
          typeof window.presensiGetFacingMode === "function" &&
          window.presensiGetFacingMode() === "user";
        if (mirrored) {
          ctx.translate(vw, 0);
          ctx.scale(-1, 1);
        }
        ctx.drawImage(wVid, 0, 0, vw, vh);
        var dataUrl = wCan.toDataURL("image/jpeg", 0.88);
        if (fotoSizeApprox(dataUrl) > MAX_FOTO_BYTES) {
          alert("Foto terlalu besar (maks. ±750 KB).");
          return;
        }
        setStatus(saveStatus, "Menyimpan foto…", false);
        wCap.disabled = true;
        applySaved(dataUrl)
          .then(function () {
            stopWebcam();
            wVid.srcObject = null;
          })
          .catch(handleSaveError)
          .finally(function () {
            wCap.disabled = false;
          });
      });
    }

    var hapus = root.querySelector("#ortu-foto-hapus");
    if (hapus) {
      hapus.addEventListener("click", function () {
        if (!confirm("Hapus foto wajah yang tersimpan?")) return;
        setStatus(saveStatus, "Menghapus foto…", false);
        window.OrtuApp.hapusFotoWajah(siswa)
          .then(function (saved) {
            Object.assign(siswa, saved);
            siswa.fotoWajah = "";
            siswa.hasFoto = false;
            setStatus(saveStatus, "Foto dihapus.", false);
            renderIdentity(document.getElementById("ortu-identity-root"), siswa);
            renderRekam(root, siswa);
          })
          .catch(handleSaveError);
      });
    }
  }

  function initDashboard() {
    var siswa = window.OrtuApp.requireSession();
    if (!siswa) return;

    var identityRoot = document.getElementById("ortu-identity-root");
    var rekamRoot = document.getElementById("ortu-rekam-root");
    var frame = document.getElementById("ortu-dashboard-frame");
    if (!identityRoot || !rekamRoot) return;

    if (frame && window.PresensiLoading) {
      window.PresensiLoading.show(frame, "Memuat data siswa…");
    }

    renderIdentity(identityRoot, siswa);
    renderRekam(rekamRoot, siswa);

    var logoutBtn = document.getElementById("ortu-logout");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        stopWebcam();
        window.OrtuApp.clearSession();
        if (window.OrtuApp.goPortal) {
          window.OrtuApp.goPortal();
        } else {
          location.replace("https://raudhatuljannah.smartpayment.co.id/portal");
        }
      });
    }

    window.OrtuApp.refreshSiswa()
      .then(function (fresh) {
        renderIdentity(identityRoot, fresh);
        renderRekam(rekamRoot, fresh);
      })
      .catch(function () {})
      .finally(function () {
        if (frame && window.PresensiLoading) {
          window.PresensiLoading.hide(frame);
        }
      });
  }

  function initLogin() {
    var form = document.getElementById("ortu-login-form");
    if (!form) return;

    if (window.OrtuApp.loadSession()) {
      location.replace("dashboard.html");
      return;
    }

    var errEl = document.getElementById("ortu-login-error");
    var submitBtn = document.getElementById("ortu-login-submit");
    var loginCard = document.getElementById("ortu-login-card");

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var nisInput = document.getElementById("ortu-nis");
      var nis = nisInput ? nisInput.value : "";
      if (errEl) {
        errEl.hidden = true;
        errEl.textContent = "";
      }
      if (submitBtn) submitBtn.disabled = true;
      if (loginCard && window.PresensiLoading) {
        window.PresensiLoading.show(loginCard, "Memverifikasi NIS…");
      }
      window.OrtuApp.loginByNis(nis)
        .then(function () {
          location.replace("dashboard.html");
        })
        .catch(function (err) {
          if (errEl) {
            errEl.hidden = false;
            errEl.textContent = err && err.message ? err.message : "Login gagal";
          }
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
          if (loginCard && window.PresensiLoading) {
            window.PresensiLoading.hide(loginCard);
          }
        });
    });
  }

  window.addEventListener("pagehide", stopWebcam);

  if (document.body && document.body.dataset.ortuPage === "login") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initLogin);
    } else {
      initLogin();
    }
  }

  if (document.body && document.body.dataset.ortuPage === "dashboard") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initDashboard);
    } else {
      initDashboard();
    }
  }
})();
