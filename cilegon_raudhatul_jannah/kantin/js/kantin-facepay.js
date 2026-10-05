/**
 * Kantin FacePay — deteksi wajah → modal → InquirySALDO → PaymentBELANJAKantin
 */
(function () {
  "use strict";

  var MODEL_URL = "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  var MATCH_THRESHOLD = 0.48;
  var COOLDOWN_MS = 5000;
  var DETECT_MS = 500;
  var NOMINAL_MIN = 100;

  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var lastAnnounceById = {};
  var modalOpen = false;
  var payInFlight = false;
  var pendingPay = null; // { siswa, nokartu, nama, saldo, nominal }

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function formatRupiah(n) {
    if (window.KantinLog && window.KantinLog.formatRupiah) {
      return window.KantinLog.formatRupiah(n);
    }
    var v = Number(n) || 0;
    try {
      return v.toLocaleString("id-ID");
    } catch (e) {
      return String(v);
    }
  }

  function adminApi(file) {
    var p = location.pathname || "";
    var idx = p.indexOf("/kantin/");
    var base = idx >= 0 ? p.slice(0, idx) : "";
    var name = String(file || "").replace(/^\/+/, "");
    if (!/\.php$/i.test(name)) name += ".php";
    return base + "/admin/api/" + name;
  }

  function setStatus(html, kind) {
    var el = $("kantin-status");
    if (!el) return;
    el.className = "k-status" + (kind ? " k-status--" + kind : "");
    el.innerHTML = html;
  }

  function toast(msg, kind) {
    var text = String(msg || "").trim();
    if (!text) return;
    var type = kind === "danger" || kind === "warn" || kind === "ok" ? kind : "ok";
    var titles = {
      ok: "Berhasil",
      warn: "Perhatian",
      danger: "Transaksi gagal",
    };
    var icons = {
      ok: "✓",
      warn: "!",
      danger: "!",
    };

    if (typeof Toastify === "function") {
      var html =
        '<div class="k-toastify__inner">' +
        '<span class="k-toastify__icon" aria-hidden="true">' +
        icons[type] +
        "</span>" +
        '<div class="k-toastify__body">' +
        '<p class="k-toastify__title">' +
        titles[type] +
        "</p>" +
        '<p class="k-toastify__msg">' +
        escapeHtml(text) +
        "</p>" +
        "</div></div>";

      Toastify({
        text: html,
        duration: 4200,
        gravity: "top",
        position: "right",
        close: true,
        stopOnFocus: true,
        escapeMarkup: false,
        className: "k-toastify k-toastify--" + type,
        offset: { x: 16, y: 72 },
      }).showToast();
      return;
    }

    var el = $("kantin-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "kantin-toast";
      document.body.appendChild(el);
    }
    el.hidden = false;
    el.className = "k-toast k-toast--" + type;
    el.innerHTML =
      '<strong class="k-toast__title">' +
      titles[type] +
      "</strong><span class=\"k-toast__msg\">" +
      escapeHtml(text) +
      "</span>";
    window.clearTimeout(toast._t);
    toast._t = window.setTimeout(function () {
      el.hidden = true;
    }, 3800);
  }

  function getNominal() {
    var inp = $("kantin-pay-nominal-input");
    var v = inp ? Number(inp.value) : 0;
    if (isNaN(v)) v = 0;
    return Math.floor(v);
  }

  function setNominal(v) {
    var n = Math.max(0, Math.floor(Number(v) || 0));
    var inp = $("kantin-pay-nominal-input");
    if (inp) inp.value = String(n);
    if (pendingPay) pendingPay.nominal = n;
    refreshPayValidation();
  }

  function refreshPayValidation() {
    if (!pendingPay) return;
    var conf = $("kantin-pay-confirm");
    var err = $("kantin-pay-error");
    var sisaEl = $("kantin-pay-sisa");
    var nominal = getNominal();
    pendingPay.nominal = nominal;

    if (pendingPay.saldo == null) {
      if (conf) conf.disabled = true;
      if (sisaEl) sisaEl.textContent = "—";
      return;
    }

    var sisa = pendingPay.saldo - nominal;
    if (sisaEl) {
      if (nominal < NOMINAL_MIN) {
        sisaEl.textContent = "—";
      } else if (sisa < 0) {
        sisaEl.textContent = "Tidak cukup";
        sisaEl.classList.add("k-modal__sisa--bad");
      } else {
        sisaEl.textContent = "Rp " + formatRupiah(sisa);
        sisaEl.classList.remove("k-modal__sisa--bad");
      }
    }

    if (nominal < NOMINAL_MIN) {
      if (conf) conf.disabled = true;
      if (err) {
        err.hidden = false;
        err.textContent = "Nominal minimal Rp " + formatRupiah(NOMINAL_MIN) + ".";
      }
      return;
    }

    // Kantin = selalu pengurangan: nominal tidak boleh melebihi saldo
    if (pendingPay.saldo < nominal) {
      if (conf) conf.disabled = true;
      if (err) {
        err.hidden = false;
        err.textContent = "Saldo tidak cukup untuk pengurangan ini.";
      }
      return;
    }

    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    if (conf && !payInFlight) conf.disabled = false;
  }

  function normalizeFotoSrc(foto) {
    if (foto == null) return "";
    var s = String(foto).trim();
    if (!s) return "";
    if (s.indexOf("data:") === 0) return s;
    if (s.indexOf("/9j/") === 0) return "data:image/jpeg;base64," + s;
    if (s.indexOf("iVBOR") === 0) return "data:image/png;base64," + s;
    return s;
  }

  function loadReferensiSiswa() {
    var url = adminApi("rekam-data.php") + "?withFoto=1";
    return fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (res) {
        return res.text().then(function (text) {
          var parsed = null;
          try {
            parsed = text ? JSON.parse(text) : null;
          } catch (e) {
            throw new Error("Respons foto referensi tidak valid");
          }
          if (!res.ok || (parsed && parsed.ok === false)) {
            throw new Error((parsed && parsed.error) || "HTTP " + res.status);
          }
          return (parsed && parsed.data) || [];
        });
      })
      .then(function (rows) {
        return (rows || [])
          .map(function (s) {
            return Object.assign({}, s, { fotoWajah: normalizeFotoSrc(s.fotoWajah) });
          })
          .filter(function (s) {
            return s.aktif !== false && s.fotoWajah && s.fotoWajah.length > 80;
          });
      });
  }

  function buildRefs() {
    refs = [];
    setStatus("<strong>Memuat foto referensi…</strong>", "");
    return loadReferensiSiswa().then(function (list) {
      if (!list.length) {
        setStatus(
          "<strong>Belum ada foto referensi.</strong> Rekam wajah siswa di panel Admin terlebih dahulu.",
          "warn"
        );
        return 0;
      }
      setStatus("<strong>Memproses " + list.length + " foto…</strong>", "");
      var ok = 0;
      var fail = 0;
      var chain = Promise.resolve();
      list.forEach(function (siswa) {
        chain = chain.then(function () {
          return faceapi
            .fetchImage(siswa.fotoWajah)
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
              } else {
                fail++;
              }
            })
            .catch(function () {
              fail++;
            });
        });
      });
      return chain.then(function () {
        var parts = ["<strong>Referensi siap:</strong> " + ok + " wajah."];
        if (fail) parts.push(" " + fail + " foto gagal dibaca.");
        setStatus(parts.join(""), ok ? "ok" : "warn");
        return ok;
      });
    });
  }

  function loadModels() {
    if (typeof faceapi === "undefined") {
      setStatus("<strong>face-api.js gagal dimuat.</strong> Periksa koneksi internet.", "warn");
      return Promise.reject(new Error("no faceapi"));
    }
    setStatus("<strong>Memuat model wajah…</strong>", "");
    return Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(function () {
      modelsReady = true;
      return buildRefs();
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
    return { ref: best, distance: bestD };
  }

  function canAnnounce(siswaId) {
    if (modalOpen || payInFlight) return false;
    var t = Date.now();
    var prev = lastAnnounceById[siswaId] || 0;
    if (t - prev < COOLDOWN_MS) return false;
    lastAnnounceById[siswaId] = t;
    return true;
  }

  function mapFaceBoxToOverlayPixels(vid, box) {
    if (!vid || !box) return null;
    var vw = vid.videoWidth;
    var vh = vid.videoHeight;
    if (!vw || !vh) return null;
    var cw = vid.clientWidth;
    var ch = vid.clientHeight;
    if (!cw || !ch) return null;
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

  function hideFaceTarget() {
    var boxEl = $("kantin-target-box");
    var labelEl = $("kantin-target-label");
    if (boxEl) {
      boxEl.hidden = true;
      boxEl.classList.remove("k-target--matched");
    }
    if (labelEl) {
      labelEl.hidden = true;
      labelEl.textContent = "";
    }
  }

  function updateFaceTarget(vid, box, matchedNama) {
    var boxEl = $("kantin-target-box");
    var labelEl = $("kantin-target-label");
    if (!boxEl || !vid || !box) {
      hideFaceTarget();
      return;
    }
    var r = mapFaceBoxToOverlayPixels(vid, box);
    if (!r) {
      hideFaceTarget();
      return;
    }
    boxEl.hidden = false;
    boxEl.style.left = r.left + "px";
    boxEl.style.top = r.top + "px";
    boxEl.style.width = r.width + "px";
    boxEl.style.height = r.height + "px";
    if (matchedNama) {
      boxEl.classList.add("k-target--matched");
      if (labelEl) {
        labelEl.textContent = matchedNama;
        labelEl.hidden = false;
      }
    } else {
      boxEl.classList.remove("k-target--matched");
      if (labelEl) {
        labelEl.hidden = true;
        labelEl.textContent = "";
      }
    }
  }

  function closePayModal() {
    var modal = $("kantin-pay-modal");
    if (modal) modal.hidden = true;
    modalOpen = false;
    pendingPay = null;
    var err = $("kantin-pay-error");
    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    var hint = $("kantin-hint");
    if (hint) {
      hint.textContent =
        "Aktifkan kamera dan hadapkan wajah siswa. Setelah cocok, isi nominal di modal lalu bayar.";
    }
    // Lanjut deteksi siswa berikutnya
    if (modelsReady && refs.length) {
      startCamera().catch(function () {});
    }
  }

  function openPayModal(siswa) {
    var nokartu = String(siswa.nis || "").replace(/\D/g, "");
    if (!nokartu) {
      toast("NIS siswa tidak valid untuk pembayaran", "danger");
      return;
    }

    modalOpen = true;
    // Matikan kamera agar tidak deteksi ulang saat modal terbuka
    stopCamera();
    setStatus("<strong>Kamera dijeda.</strong> Selesaikan atau batalkan pembayaran di modal.", "");

    pendingPay = {
      siswa: siswa,
      nokartu: nokartu,
      nama: siswa.nama || "Siswa",
      saldo: null,
      nominal: getNominal() || 1000,
    };

    var modal = $("kantin-pay-modal");
    var namaEl = $("kantin-pay-nama");
    var saldoEl = $("kantin-pay-saldo");
    var conf = $("kantin-pay-confirm");
    var err = $("kantin-pay-error");
    var nomInp = $("kantin-pay-nominal-input");

    if (namaEl) namaEl.textContent = pendingPay.nama;
    if (saldoEl) saldoEl.textContent = "Memuat…";
    if (nomInp) {
      nomInp.value = String(pendingPay.nominal);
      window.setTimeout(function () {
        nomInp.focus();
        nomInp.select();
      }, 80);
    }
    if (conf) conf.disabled = true;
    if (err) {
      err.hidden = true;
      err.textContent = "";
    }
    if (modal) modal.hidden = false;

    window.KantinAuth.requestJson("POST", window.KantinAuth.apiUrl("saldo-inquiry.php"), {
      nokartu: nokartu,
      siswaId: siswa.id,
    })
      .then(function (body) {
        if (!pendingPay || pendingPay.nokartu !== nokartu) return;
        var data = (body && body.data) || {};
        pendingPay.saldo = Number(data.saldo) || 0;
        if (data.nama) {
          pendingPay.nama = data.nama;
          if (namaEl) namaEl.textContent = data.nama;
        }
        if (saldoEl) saldoEl.textContent = "Rp " + formatRupiah(pendingPay.saldo);
        refreshPayValidation();
      })
      .catch(function (e) {
        if (!pendingPay || pendingPay.nokartu !== nokartu) return;
        if (saldoEl) saldoEl.textContent = "Gagal";
        if (err) {
          err.hidden = false;
          err.textContent = (e && e.message) || "Gagal inquiry saldo";
        }
        if (conf) conf.disabled = true;
      });
  }

  function confirmPay() {
    if (!pendingPay || payInFlight) return;
    var nominal = getNominal();
    pendingPay.nominal = nominal;
    refreshPayValidation();
    if (nominal < NOMINAL_MIN) {
      toast("Nominal minimal Rp " + formatRupiah(NOMINAL_MIN), "warn");
      return;
    }
    if (pendingPay.saldo != null && pendingPay.saldo < nominal) {
      toast("Saldo tidak cukup untuk pengurangan", "warn");
      return;
    }

    var conf = $("kantin-pay-confirm");
    var err = $("kantin-pay-error");
    payInFlight = true;
    if (conf) {
      conf.disabled = true;
      conf.textContent = "Memotong…";
    }
    window.KantinAuth.requestJson("POST", window.KantinAuth.apiUrl("payment.php"), {
      nokartu: pendingPay.nokartu,
      nominal: pendingPay.nominal,
      namaSiswa: pendingPay.nama || "",
      saldoSebelum: pendingPay.saldo != null ? Number(pendingPay.saldo) : null,
    })
      .then(function () {
        var wrap = $("kantin-video-wrap");
        if (wrap) {
          wrap.classList.add("k-stage--pulse");
          window.setTimeout(function () {
            wrap.classList.remove("k-stage--pulse");
          }, 900);
        }
        toast(
          "Dipotong Rp " +
            formatRupiah(pendingPay.nominal) +
            " · " +
            (pendingPay.nama || "Siswa"),
          "ok"
        );
        try {
          sessionStorage.setItem("kantin_last_nominal", String(pendingPay.nominal));
        } catch (eSave) {}
        closePayModal();
        if (window.KantinLog) window.KantinLog.load();
      })
      .catch(function (e) {
        var msg = (e && e.message) || "Pengurangan gagal";
        var code = "";
        try {
          if (e && e.body && e.body.code) code = String(e.body.code);
        } catch (eCode) {}
        if (/TRANSAKSI_LIMIT/i.test(code) || /limit/i.test(msg)) {
          msg = "Transaksi melebihi limit. Kurangi nominal lalu coba lagi.";
        } else if (/SALDO/i.test(code) && /CUKUP|INSUFFICIENT/i.test(code + msg)) {
          msg = "Saldo tidak cukup untuk transaksi ini.";
        }
        if (err) {
          err.hidden = false;
          err.textContent = msg;
        }
        toast(msg, "danger");
        // Modal tetap terbuka agar petugas bisa ubah nominal
        if (conf) conf.disabled = false;
      })
      .finally(function () {
        payInFlight = false;
        if (conf) {
          conf.textContent = "Potong saldo";
        }
        refreshPayValidation();
      });
  }

  function onMatch(siswa) {
    var hint = $("kantin-hint");
    if (hint) {
      hint.textContent = "Wajah cocok: " + (siswa.nama || "Siswa") + ". Menyiapkan konfirmasi…";
    }
    if (canAnnounce(siswa.id)) {
      openPayModal(siswa);
    }
  }

  function runDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length || modalOpen) return;
    var vid = $("kantin-video");
    if (!vid || vid.readyState < 2) return;
    busyDetect = true;
    faceapi
      .detectSingleFace(vid, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.45 }))
      .withFaceLandmarks()
      .withFaceDescriptor()
      .then(function (res) {
        busyDetect = false;
        if (!res || !res.detection || !res.detection.box) {
          hideFaceTarget();
          return;
        }
        var matchedNama = null;
        if (res.descriptor) {
          var m = bestMatch(res.descriptor);
          if (m) {
            matchedNama = m.ref.siswa.nama || "Siswa";
            onMatch(m.ref.siswa);
          }
        }
        updateFaceTarget(vid, res.detection.box, matchedNama);
      })
      .catch(function () {
        busyDetect = false;
        hideFaceTarget();
      });
  }

  function stopCamera() {
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    var vid = $("kantin-video");
    if (typeof window.presensiStopWebcam === "function") {
      window.presensiStopWebcam(vid);
    } else if (stream) {
      stream.getTracks().forEach(function (t) {
        t.stop();
      });
      if (vid) vid.srcObject = null;
    }
    stream = null;
    busyDetect = false;
    hideFaceTarget();
    var btnStart = $("kantin-cam-start");
    var btnStop = $("kantin-cam-stop");
    var btnFlip = $("kantin-cam-flip");
    if (btnStart) btnStart.hidden = false;
    if (btnStop) btnStop.hidden = true;
    if (btnFlip) btnFlip.hidden = true;
  }

  function syncFlipLabel() {
    var flip = $("kantin-cam-flip");
    if (!flip || typeof window.presensiGetFacingMode !== "function") return;
    var back = window.presensiGetFacingMode() === "environment";
    flip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
    flip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
  }

  function flipCamera() {
    var vid = $("kantin-video");
    if (!vid || !stream || typeof window.presensiSwitchCamera !== "function") return;
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    setStatus("<strong>Mengganti kamera…</strong>", "");
    window
      .presensiSwitchCamera(vid)
      .then(function (s) {
        stream = s;
        detectTimer = window.setInterval(runDetect, DETECT_MS);
        syncFlipLabel();
        var label =
          typeof window.presensiFacingLabel === "function"
            ? window.presensiFacingLabel()
            : "kamera";
        setStatus(
          "<strong>Kamera aktif (" + escapeHtml(label) + ").</strong> Hadapkan wajah siswa.",
          "ok"
        );
      })
      .catch(function (err) {
        setStatus(
          "<strong>Gagal ganti kamera.</strong> " +
            escapeHtml((err && err.message) || String(err)),
          "warn"
        );
      });
  }

  function startCamera() {
    stopCamera();
    var vid = $("kantin-video");
    if (!vid) return Promise.reject(new Error("no video"));
    if (typeof window.presensiOpenWebcam !== "function") {
      setStatus("<strong>Skrip kamera tidak dimuat.</strong>", "warn");
      return Promise.reject(new Error("no camera helper"));
    }
    return window
      .presensiOpenWebcam(vid)
      .then(function (s) {
        stream = s;
        detectTimer = window.setInterval(runDetect, DETECT_MS);
        setStatus("<strong>Kamera aktif.</strong> Hadapkan wajah siswa — nominal diisi di modal.", "ok");
        var btnStart = $("kantin-cam-start");
        var btnStop = $("kantin-cam-stop");
        var btnFlip = $("kantin-cam-flip");
        if (btnStart) btnStart.hidden = true;
        if (btnStop) btnStop.hidden = false;
        if (btnFlip) btnFlip.hidden = false;
        syncFlipLabel();
      })
      .catch(function (err) {
        setStatus(
          "<strong>Kamera gagal.</strong> " + escapeHtml((err && err.message) || String(err)),
          "warn"
        );
        throw err;
      });
  }

  function bindUi() {
    var lastNominal = 1000;
    try {
      var saved = Number(sessionStorage.getItem("kantin_last_nominal"));
      if (!isNaN(saved) && saved >= NOMINAL_MIN) lastNominal = saved;
    } catch (e) {}
    setNominal(lastNominal);

    var nom = $("kantin-pay-nominal-input");
    if (nom) {
      nom.addEventListener("input", function () {
        if (pendingPay) {
          pendingPay.nominal = getNominal();
          refreshPayValidation();
        }
      });
      nom.addEventListener("change", function () {
        var n = getNominal();
        if (n < NOMINAL_MIN && n !== 0) setNominal(NOMINAL_MIN);
        else if (pendingPay) refreshPayValidation();
        try {
          sessionStorage.setItem("kantin_last_nominal", String(getNominal() || lastNominal));
        } catch (e2) {}
      });
      nom.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          confirmPay();
        }
      });
    }

    var start = $("kantin-cam-start");
    if (start) {
      start.addEventListener("click", function () {
        if (!modelsReady) {
          setStatus("Model belum siap. Tunggu sebentar.", "warn");
          return;
        }
        if (!refs.length) {
          buildRefs().then(function (n) {
            if (n) startCamera().catch(function () {});
          });
          return;
        }
        startCamera().catch(function () {});
      });
    }

    var stop = $("kantin-cam-stop");
    if (stop) {
      stop.addEventListener("click", function () {
        stopCamera();
        setStatus("<strong>Kamera dihentikan.</strong>", "");
      });
    }

    var flip = $("kantin-cam-flip");
    if (flip) flip.addEventListener("click", flipCamera);

    var reload = $("kantin-reload-faces");
    if (reload) {
      reload.addEventListener("click", function () {
        stopCamera();
        buildRefs().catch(function (err) {
          setStatus(escapeHtml((err && err.message) || String(err)), "warn");
        });
      });
    }

    var cancel = $("kantin-pay-cancel");
    if (cancel) cancel.addEventListener("click", closePayModal);

    var backdrop = document.querySelector("#kantin-pay-modal .k-modal__backdrop");
    if (backdrop) {
      backdrop.addEventListener("click", closePayModal);
    }

    var confirmBtn = $("kantin-pay-confirm");
    if (confirmBtn) confirmBtn.addEventListener("click", confirmPay);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modalOpen) closePayModal();
    });

    var logout = $("kantin-logout");
    if (logout) {
      logout.addEventListener("click", function () {
        stopCamera();
        window.KantinAuth.logout()
          .catch(function () {})
          .then(function () {
            if (window.KantinAuth.goPortal) {
              window.KantinAuth.goPortal();
            } else {
              location.replace("https://raudhatuljannah.smartpayment.co.id/portal");
            }
          });
      });
    }

    window.addEventListener("beforeunload", stopCamera);
  }

  function init() {
    if (!window.KantinAuth) return;

    window.KantinAuth.requireSession().then(function (user) {
      if (!user) return;
      var label = $("kantin-user-label");
      if (label) {
        label.textContent = user.displayName || user.username || "Kantin";
      }
      if (window.KantinAuth.initChangePasswordUi) {
        window.KantinAuth.initChangePasswordUi();
      }
      bindUi();
      if (window.KantinLog) {
        window.KantinLog.init();
        window.KantinLog.load();
      }
      loadModels().catch(function (err) {
        setStatus("<strong>Gagal memuat model.</strong> " + escapeHtml((err && err.message) || err), "warn");
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
