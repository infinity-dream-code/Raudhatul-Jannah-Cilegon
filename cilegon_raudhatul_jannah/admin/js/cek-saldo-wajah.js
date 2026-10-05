/**
 * Cek saldo via face recognition (admin) — InquirySALDO saja.
 */
(function () {
  "use strict";

  var MODEL_URL = "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  var MATCH_THRESHOLD = 0.48;
  var COOLDOWN_MS = 6000;
  var DETECT_MS = 500;

  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var lastById = {};
  var modalOpen = false;

  function $(id) {
    return document.getElementById(id);
  }

  function formatRupiah(n) {
    var v = Number(n) || 0;
    try {
      return v.toLocaleString("id-ID");
    } catch (e) {
      return String(v);
    }
  }

  function setStatus(html, kind) {
    var el = $("cek-saldo-status");
    if (!el) return;
    el.className = "face-status" + (kind ? " face-status--" + kind : "");
    el.innerHTML = html;
  }

  function normalizeFotoSrc(foto) {
    if (window.PresensiRekamService && window.PresensiRekamService.normalizeFotoSrc) {
      return window.PresensiRekamService.normalizeFotoSrc(foto);
    }
    var s = String(foto || "").trim();
    if (!s) return "";
    if (s.indexOf("data:") === 0) return s;
    if (s.indexOf("/9j/") === 0) return "data:image/jpeg;base64," + s;
    return s;
  }

  function loadRefs() {
    refs = [];
    if (!window.PresensiRekamService || !window.PresensiRekamService.listWithFoto) {
      setStatus("Layanan foto referensi tidak tersedia.", "warn");
      return Promise.resolve(0);
    }
    setStatus("Memuat foto referensi…", "");
    return window.PresensiRekamService.listWithFoto().then(function (rows) {
      var list = (rows || []).filter(function (s) {
        var f = normalizeFotoSrc(s.fotoWajah);
        return f && f.length > 80;
      });
      if (!list.length) {
        setStatus("Belum ada foto referensi. Rekam wajah siswa terlebih dahulu.", "warn");
        return 0;
      }
      var ok = 0;
      var chain = Promise.resolve();
      list.forEach(function (siswa) {
        chain = chain.then(function () {
          return faceapi
            .fetchImage(normalizeFotoSrc(siswa.fotoWajah))
            .then(function (img) {
              return faceapi
                .detectSingleFace(img, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.4 }))
                .withFaceLandmarks()
                .withFaceDescriptor();
            })
            .then(function (det) {
              if (det && det.descriptor) {
                refs.push({ siswa: siswa, descriptor: det.descriptor });
                ok++;
              }
            })
            .catch(function () {});
        });
      });
      return chain.then(function () {
        setStatus("Siap — " + ok + " wajah terindeks.", ok ? "ok" : "warn");
        return ok;
      });
    });
  }

  function loadModels() {
    if (typeof faceapi === "undefined") {
      setStatus("Model wajah gagal dimuat.", "warn");
      return Promise.reject(new Error("no faceapi"));
    }
    setStatus("Memuat model…", "");
    return Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(function () {
      modelsReady = true;
      return loadRefs();
    });
  }

  function bestMatch(descriptor) {
    var best = null;
    var bestD = Infinity;
    for (var i = 0; i < refs.length; i++) {
      var d = faceapi.euclideanDistance(descriptor, refs[i].descriptor);
      if (d < bestD) {
        bestD = d;
        best = refs[i];
      }
    }
    if (!best || bestD > MATCH_THRESHOLD) return null;
    return best;
  }

  function canFire(id) {
    if (modalOpen) return false;
    var t = Date.now();
    if (t - (lastById[id] || 0) < COOLDOWN_MS) return false;
    lastById[id] = t;
    return true;
  }

  function closeModal() {
    var m = $("cek-saldo-modal");
    if (m) {
      m.classList.remove("modal--open");
      m.setAttribute("aria-hidden", "true");
    }
    modalOpen = false;
  }

  function openSaldoModal(siswa) {
    modalOpen = true;
    var m = $("cek-saldo-modal");
    var nama = $("cek-saldo-nama");
    var nis = $("cek-saldo-nis");
    var nilai = $("cek-saldo-nilai");
    var err = $("cek-saldo-err");
    if (nama) nama.textContent = siswa.nama || "—";
    if (nis) nis.textContent = siswa.nis || "—";
    if (nilai) nilai.textContent = "Memuat…";
    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    if (m) {
      m.classList.add("modal--open");
      m.setAttribute("aria-hidden", "false");
    }

    var nokartu = String(siswa.nis || "").replace(/\D/g, "");
    if (!nokartu || !window.PresensiApiExtras || !window.PresensiApiExtras.inquirySaldo) {
      if (nilai) nilai.textContent = "—";
      if (err) {
        err.hidden = false;
        err.textContent = "NIS tidak valid atau layanan saldo tidak aktif.";
      }
      return;
    }

    window.PresensiApiExtras.inquirySaldo(siswa)
      .then(function (data) {
        if (!modalOpen) return;
        var saldo = data && (data.saldo != null ? data.saldo : data.SALDO);
        var nm = data && (data.nama || data.NAMA);
        if (nm && nama) nama.textContent = nm;
        if (nilai) nilai.textContent = "Rp " + formatRupiah(saldo);
      })
      .catch(function (e) {
        if (!modalOpen) return;
        if (nilai) nilai.textContent = "—";
        if (err) {
          err.hidden = false;
          err.textContent = (e && e.message) || "Gagal cek saldo";
        }
      });
  }

  function mapBox(vid, box) {
    var vw = vid.videoWidth;
    var vh = vid.videoHeight;
    var cw = vid.clientWidth;
    var ch = vid.clientHeight;
    if (!vw || !vh || !cw || !ch) return null;
    var scale = Math.max(cw / vw, ch / vh);
    var dw = vw * scale;
    var dh = vh * scale;
    var offX = (cw - dw) / 2;
    var offY = (ch - dh) / 2;
    var left = offX + box.x * scale;
    var top = offY + box.y * scale;
    var w = box.width * scale;
    var h = box.height * scale;
    left = cw - left - w;
    return { left: left, top: top, width: w, height: h };
  }

  function updateTarget(vid, box, label) {
    var el = $("cek-saldo-box");
    var lab = $("cek-saldo-label");
    if (!el || !box) {
      if (el) el.hidden = true;
      return;
    }
    var r = mapBox(vid, box);
    if (!r) {
      el.hidden = true;
      return;
    }
    el.hidden = false;
    el.style.left = r.left + "px";
    el.style.top = r.top + "px";
    el.style.width = r.width + "px";
    el.style.height = r.height + "px";
    if (label) {
      el.classList.add("face-target-box--matched");
      if (lab) {
        lab.hidden = false;
        lab.textContent = label;
      }
    } else {
      el.classList.remove("face-target-box--matched");
      if (lab) lab.hidden = true;
    }
  }

  function runDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length || modalOpen) return;
    var vid = $("cek-saldo-video");
    if (!vid || vid.readyState < 2) return;
    busyDetect = true;
    faceapi
      .detectSingleFace(vid, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.45 }))
      .withFaceLandmarks()
      .withFaceDescriptor()
      .then(function (res) {
        busyDetect = false;
        if (!res || !res.detection) {
          updateTarget(vid, null);
          return;
        }
        var name = null;
        if (res.descriptor) {
          var m = bestMatch(res.descriptor);
          if (m) {
            name = m.siswa.nama || "Siswa";
            if (canFire(m.siswa.id)) openSaldoModal(m.siswa);
          }
        }
        updateTarget(vid, res.detection.box, name);
      })
      .catch(function () {
        busyDetect = false;
      });
  }

  function stopCamera() {
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    var vid = $("cek-saldo-video");
    if (typeof window.presensiStopWebcam === "function") {
      window.presensiStopWebcam(vid);
    } else if (stream) {
      stream.getTracks().forEach(function (t) {
        t.stop();
      });
      if (vid) vid.srcObject = null;
    }
    stream = null;
    var start = $("cek-saldo-start");
    var stop = $("cek-saldo-stop");
    var flip = $("cek-saldo-flip");
    if (start) start.hidden = false;
    if (stop) stop.hidden = true;
    if (flip) flip.hidden = true;
  }

  function syncFlipLabel() {
    var flip = $("cek-saldo-flip");
    if (!flip || typeof window.presensiGetFacingMode !== "function") return;
    var back = window.presensiGetFacingMode() === "environment";
    flip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
    flip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
  }

  function startCamera() {
    stopCamera();
    var vid = $("cek-saldo-video");
    return window.presensiOpenWebcam(vid).then(function (s) {
      stream = s;
      detectTimer = setInterval(runDetect, DETECT_MS);
      setStatus("Kamera aktif — hadapkan wajah siswa.", "ok");
      $("cek-saldo-start").hidden = true;
      $("cek-saldo-stop").hidden = false;
      var flip = $("cek-saldo-flip");
      if (flip) flip.hidden = false;
      syncFlipLabel();
    });
  }

  function flipCamera() {
    var vid = $("cek-saldo-video");
    if (!vid || typeof window.presensiSwitchCamera !== "function") return;
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    setStatus("Mengganti kamera…", "");
    window
      .presensiSwitchCamera(vid)
      .then(function (s) {
        stream = s;
        detectTimer = setInterval(runDetect, DETECT_MS);
        syncFlipLabel();
        var label =
          typeof window.presensiFacingLabel === "function"
            ? window.presensiFacingLabel()
            : "kamera";
        setStatus("Kamera aktif (" + label + ") — hadapkan wajah siswa.", "ok");
      })
      .catch(function (e) {
        setStatus((e && e.message) || "Gagal ganti kamera", "warn");
      });
  }

  function init() {
    document.querySelectorAll("[data-cek-close]").forEach(function (el) {
      el.addEventListener("click", closeModal);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeModal();
    });

    $("cek-saldo-start").addEventListener("click", function () {
      if (!modelsReady || !refs.length) {
        loadRefs().then(function (n) {
          if (n) startCamera().catch(function () {});
        });
        return;
      }
      startCamera().catch(function (e) {
        setStatus((e && e.message) || "Kamera gagal", "warn");
      });
    });
    $("cek-saldo-stop").addEventListener("click", function () {
      stopCamera();
      setStatus("Kamera dihentikan.", "");
    });
    var flipBtn = $("cek-saldo-flip");
    if (flipBtn) flipBtn.addEventListener("click", flipCamera);
    $("cek-saldo-reload").addEventListener("click", function () {
      stopCamera();
      loadRefs();
    });
    window.addEventListener("beforeunload", stopCamera);

    loadModels().catch(function (e) {
      setStatus((e && e.message) || "Gagal memuat model", "warn");
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
