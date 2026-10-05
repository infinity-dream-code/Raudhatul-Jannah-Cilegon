(function () {
  var SISWA_KEY = "presensi_data_siswa";
  var FACE_LOG_KEY = "presensi_face_log";
  var MODUL_CONTEXT_KEY = "presensi_modul_context";
  var DEFAULT_KEGIATAN = "Uji wajah";

  var MODEL_URL = "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  var MATCH_THRESHOLD = 0.48;
  var COOLDOWN_MS = 5000;
  var DETECT_MS = 500;
  var MAX_LOG_ROWS = 30;

  var root;
  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var lastAnnounceById = {};
  /** Log hari ini dari DB (presensi_log) bila API aktif */
  var faceLogsFromDb = [];

  function $(id) {
    return root ? root.querySelector("#" + id) : null;
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function loadRows(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return [];
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function loadFaceLogs() {
    return loadRows(FACE_LOG_KEY).filter(function (r) {
      return r && r.id && (r.waktu || r.waktuMasuk);
    });
  }

  function useDbPresensiLog() {
    return Boolean(
      window.PresensiApiHttp &&
        window.PresensiApiHttp.isEnabled() &&
        window.PresensiApiExtras &&
        window.PresensiApiExtras.listPresensiLog
    );
  }

  function todayDateKey() {
    return localDateKeyFromIso(new Date().toISOString());
  }

  function normalizeDbLogRow(row) {
    if (!row || typeof row !== "object") return row;
    var out = Object.assign({}, row);
    // Biarkan DATETIME lokal apa adanya (jangan paksa jadi ISO tanpa zona → salah parse).
    if (!out.waktu && out.waktuMasuk) out.waktu = out.waktuMasuk;
    return out;
  }

  function getLogsForTable() {
    if (useDbPresensiLog()) {
      return faceLogsFromDb;
    }
    return loadFaceLogs();
  }

  function fetchFaceLogsFromDb() {
    if (!useDbPresensiLog()) {
      return Promise.resolve(getLogsForTable());
    }
    var keg = getKegiatanPresensi();
    return window.PresensiApiExtras
      .listPresensiLog({
        limit: 200,
        date: todayDateKey(),
        kegiatan: String(keg || "").trim() || DEFAULT_KEGIATAN,
      })
      .then(function (rows) {
        faceLogsFromDb = (rows || []).map(normalizeDbLogRow).filter(function (r) {
          return r && r.id && (r.waktu || r.waktuMasuk);
        });
        return faceLogsFromDb;
      })
      .catch(function (e) {
        console.warn("[face] muat log presensi DB:", e && e.message ? e.message : e);
        return faceLogsFromDb;
      });
  }

  function saveFaceLogs(rows) {
    try {
      localStorage.setItem(FACE_LOG_KEY, JSON.stringify(rows || []));
    } catch (e) {}
  }

  function pad2(n) {
    return n < 10 ? "0" + n : String(n);
  }

  /** Waktu dinding lokal (WIB di server ID) — hindari toISOString() UTC. */
  function nowLocalSql() {
    var d = new Date();
    return (
      d.getFullYear() +
      "-" +
      pad2(d.getMonth() + 1) +
      "-" +
      pad2(d.getDate()) +
      " " +
      pad2(d.getHours()) +
      ":" +
      pad2(d.getMinutes()) +
      ":" +
      pad2(d.getSeconds())
    );
  }

  /**
   * Ambil jam:menit:detik dari string lokal `YYYY-MM-DD HH:MM:SS` / ISO.
   * String tanpa zona waktu ditampilkan apa adanya (bukan dikonversi UTC).
   */
  function formatLogJamDetik(iso) {
    if (!iso) return "—";
    var s = String(iso).trim();
    var m = s.match(/(?:^|T|\s)(\d{2}):(\d{2})(?::(\d{2}))?/);
    if (m && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(s)) {
      return m[1] + ":" + m[2] + ":" + (m[3] || "00");
    }
    try {
      var d = new Date(s);
      if (isNaN(d.getTime())) return "—";
      return pad2(d.getHours()) + ":" + pad2(d.getMinutes()) + ":" + pad2(d.getSeconds());
    } catch (e) {
      return "—";
    }
  }

  /** Hari & tanggal untuk judul tabel (bukan kolom). */
  function formatJudulHariTanggal(d) {
    d = d || new Date();
    try {
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString("id-ID", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });
    } catch (e) {
      return "";
    }
  }

  function isoMasukUtama(row) {
    return row.waktuMasuk != null ? row.waktuMasuk : row.waktu;
  }

  function buildLogMasukCell(row) {
    return '<td class="presensi-log__jam">' + escapeHtml(formatLogJamDetik(isoMasukUtama(row))) + "</td>";
  }

  function buildLogKeluarCell(row) {
    var v = row.waktuKeluar;
    return '<td class="presensi-log__jam">' + escapeHtml(v ? formatLogJamDetik(v) : "—") + "</td>";
  }

  function getKegiatanPresensi() {
    return DEFAULT_KEGIATAN;
  }

  function rowKegiatanNorm(row) {
    var k = row && row.kegiatan;
    if (k != null && String(k).trim() !== "") return String(k).trim();
    return DEFAULT_KEGIATAN;
  }

  function localDateKeyFromIso(iso) {
    if (!iso) return "";
    var s = String(iso).trim();
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(s)) {
      return m[1] + "-" + m[2] + "-" + m[3];
    }
    try {
      var d = new Date(s);
      if (isNaN(d.getTime())) return "";
      return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
    } catch (e) {
      return "";
    }
  }

  /** Satu baris per siswa per hari kalender (lokal) per kegiatan; deteksi berikutnya memperbarui keluar saja. */
  function findPresensiRowToday(logs, siswaId, kegiatanNorm) {
    var todayKey = localDateKeyFromIso(new Date().toISOString());
    for (var i = 0; i < logs.length; i++) {
      var r = logs[i];
      if (!r) continue;
      if (String(r.siswaId || "") !== String(siswaId)) continue;
      if (rowKegiatanNorm(r) !== kegiatanNorm) continue;
      if (localDateKeyFromIso(r.waktuMasuk || r.waktu) === todayKey) return i;
    }
    return -1;
  }

  function updateFaceLogHeading() {
    var h = $("face-log-heading");
    if (!h) return;
    h.textContent = "Hasil uji coba · " + formatJudulHariTanggal(new Date());
  }

  function renderFaceLogTable() {
    var body = $("face-log-body");
    var meta = $("face-log-meta");
    if (!body) return;
    var logs = getLogsForTable();
    if (!logs.length) {
      body.innerHTML =
        '<tr><td class="data-table__empty" colspan="3">Belum ada hasil uji wajah.</td></tr>';
      if (meta) meta.textContent = "Belum ada deteksi.";
      updateFaceLogHeading();
      return;
    }
    if (meta) {
      meta.textContent = "Total hasil uji: " + logs.length + " deteksi.";
    }
    body.innerHTML = logs
      .map(function (row) {
        var masukCell = buildLogMasukCell(row);
        var keluarCell = buildLogKeluarCell(row);
        var namaNis =
          '<td class="presensi-log__stack">' +
          '<span class="presensi-log__stack-main">' +
          escapeHtml(row.nama || "Tidak dikenal") +
          "</span>" +
          '<span class="presensi-log__stack-sub">' +
          escapeHtml(row.nis || "—") +
          "</span></td>";
        return (
          "<tr>" +
          namaNis +
          masukCell +
          keluarCell +
          "</tr>"
        );
      })
      .join("");
    updateFaceLogHeading();
  }

  function newFaceLogId() {
    return "face-log-" + Date.now() + "-" + Math.random().toString(16).slice(2, 8);
  }

  /**
   * Satu baris per siswa per hari kalender (lokal) per kegiatan; deteksi berikutnya hanya memperbarui waktu keluar.
   */
  function appendFaceLog(siswa, metode) {
    if (!siswa || !siswa.id) return;
    var sid = siswa.id;
    var logs = getLogsForTable().slice();
    var now = nowLocalSql();
    var keg = getKegiatanPresensi();
    var kegNorm = String(keg || "").trim() || DEFAULT_KEGIATAN;
    var base = {
      metode: metode || "face recognition",
      nama: siswa.nama || "Tidak dikenal",
      nis: siswa.nis || "—",
      siswaId: sid,
      kegiatan: keg,
    };

    var idx = findPresensiRowToday(logs, sid, kegNorm);
    if (idx !== -1) {
      var row = logs.splice(idx, 1)[0];
      row.waktuKeluar = now;
      row.waktu = row.waktuMasuk || row.waktu;
      row.metode = base.metode;
      row.nama = base.nama;
      row.nis = base.nis;
      row.kegiatan = getKegiatanPresensi();
      logs.unshift(row);
    } else {
      var rid = newFaceLogId();
      logs.unshift({
        id: rid,
        waktu: now,
        waktuMasuk: now,
        waktuKeluar: null,
        metode: base.metode,
        nama: base.nama,
        nis: base.nis,
        siswaId: base.siswaId,
        kegiatan: base.kegiatan,
      });
      if (typeof window.presensiShowMasukModal === "function") {
        window.presensiShowMasukModal({
          title: "Uji wajah berhasil",
          nama: base.nama,
          nis: base.nis,
          subtitle: "Mode uji coba — bukan absensi resmi.",
          durationMs: 4000,
        });
      }
    }

    if (logs.length > MAX_LOG_ROWS) logs = logs.slice(0, MAX_LOG_ROWS);

    var head = logs[0];
    if (useDbPresensiLog()) {
      faceLogsFromDb = logs;
      if (head && window.PresensiApiExtras && window.PresensiApiExtras.savePresensiLog) {
        window.PresensiApiExtras
          .savePresensiLog(head)
          .then(function () {
            return fetchFaceLogsFromDb();
          })
          .then(function () {
            renderFaceLogTable();
          })
          .catch(function (e) {
            console.warn("[face] log presensi DB:", e && e.message ? e.message : e);
            renderFaceLogTable();
          });
        return;
      }
    } else {
      saveFaceLogs(logs);
    }
    renderFaceLogTable();
  }

  function loadSiswaAktifDenganFoto() {
    return loadRows(SISWA_KEY).filter(function (s) {
      return s.aktif && s.fotoWajah && String(s.fotoWajah).length > 80;
    });
  }

  function useRekamService() {
    return Boolean(
      window.PresensiRekamService &&
        window.PresensiRekamService.isEnabled() &&
        window.PresensiRekamService.listWithFoto
    );
  }

  function normalizeFotoSrc(foto) {
    if (window.PresensiRekamService && window.PresensiRekamService.normalizeFotoSrc) {
      return window.PresensiRekamService.normalizeFotoSrc(foto);
    }
    if (foto == null) return "";
    var s = String(foto).trim();
    if (!s) return "";
    if (s.indexOf("data:") === 0) return s;
    if (s.indexOf("/9j/") === 0) return "data:image/jpeg;base64," + s;
    if (s.indexOf("iVBOR") === 0) return "data:image/png;base64," + s;
    if (s.indexOf("UklGR") === 0) return "data:image/webp;base64," + s;
    return s;
  }

  /**
   * Daftar siswa aktif berfoto. Sumber: DB (rekam-data.php?withFoto=1) bila API aktif,
   * jatuh ke cache localStorage bila offline. Cache localStorage sengaja membuang base64
   * untuk hemat kuota, jadi mode remote WAJIB ambil foto langsung dari server.
   */
  function loadReferensiSiswa() {
    if (useRekamService()) {
      return window.PresensiRekamService.listWithFoto()
        .then(function (rows) {
          return (rows || [])
            .map(function (s) {
              return Object.assign({}, s, { fotoWajah: normalizeFotoSrc(s.fotoWajah) });
            })
            .filter(function (s) {
              return s.fotoWajah && s.fotoWajah.length > 80;
            });
        })
        .catch(function (e) {
          console.warn("[face] muat foto referensi dari DB:", e && e.message ? e.message : e);
          return loadSiswaAktifDenganFoto().map(function (s) {
            return Object.assign({}, s, { fotoWajah: normalizeFotoSrc(s.fotoWajah) });
          });
        });
    }
    return Promise.resolve(
      loadSiswaAktifDenganFoto().map(function (s) {
        return Object.assign({}, s, { fotoWajah: normalizeFotoSrc(s.fotoWajah) });
      })
    );
  }

  function setStatus(html, kind) {
    var el = $("face-status");
    if (!el) return;
    el.className = "face-status" + (kind ? " face-status--" + kind : "");
    el.innerHTML = html;
  }

  function speakHasil(siswa) {
    if (!window.speechSynthesis) return;
    try {
      window.speechSynthesis.cancel();
    } catch (e) {}
    var text = "Uji wajah berhasil. " + (siswa.nama || "Siswa") + ".";
    var u = new SpeechSynthesisUtterance(text);
    u.lang = "id-ID";
    u.rate = 0.95;
    var voices = window.speechSynthesis.getVoices();
    if (voices && voices.length) {
      for (var i = 0; i < voices.length; i++) {
        if (voices[i].lang && voices[i].lang.toLowerCase().indexOf("id") === 0) {
          u.voice = voices[i];
          break;
        }
      }
    }
    window.speechSynthesis.speak(u);
  }

  /**
   * Memetakan kotak deteksi (koordinat video intrinsik) ke piksel relatif terhadap elemen video,
   * dengan koreksi object-fit: cover dan cermin horizontal (video memakai scaleX(-1)).
   */
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
    var boxEl = $("face-target-box");
    var labelEl = $("face-target-label");
    if (boxEl) {
      boxEl.hidden = true;
      boxEl.classList.remove("face-target-box--matched");
    }
    if (labelEl) {
      labelEl.hidden = true;
      labelEl.textContent = "";
    }
  }

  function updateFaceTarget(vid, box, matchedNama) {
    var boxEl = $("face-target-box");
    var labelEl = $("face-target-label");
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
      boxEl.classList.add("face-target-box--matched");
      if (labelEl) {
        labelEl.textContent = matchedNama;
        labelEl.hidden = false;
      }
    } else {
      boxEl.classList.remove("face-target-box--matched");
      if (labelEl) {
        labelEl.hidden = true;
        labelEl.textContent = "";
      }
    }
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
    var t = Date.now();
    var prev = lastAnnounceById[siswaId] || 0;
    if (t - prev < COOLDOWN_MS) return false;
    lastAnnounceById[siswaId] = t;
    return true;
  }

  function onMatch(siswa, descriptorDistance) {
    var hint = $("face-hint");
    if (hint) {
      hint.textContent =
        "Cocok dengan data siswa (jarak " +
        descriptorDistance.toFixed(2) +
        "). Uji akan diulang setelah jeda beberapa detik.";
    }
    if (canAnnounce(siswa.id)) {
      speakHasil(siswa);
      appendFaceLog(siswa, "face recognition");
      var wrap = $("face-video-wrap");
      if (wrap) {
        wrap.classList.add("face-video-wrap--pulse");
        window.setTimeout(function () {
          wrap.classList.remove("face-video-wrap--pulse");
        }, 900);
      }
    }
  }

  function stopCamera() {
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    var vid = $("face-video");
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
    var flip = $("face-btn-flip");
    if (flip) flip.hidden = true;
  }

  function syncFlipLabel() {
    var flip = $("face-btn-flip");
    if (!flip || typeof window.presensiGetFacingMode !== "function") return;
    var back = window.presensiGetFacingMode() === "environment";
    flip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
    flip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
  }

  function flipCamera() {
    var vid = $("face-video");
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
          "<strong>Kamera aktif (" + escapeHtml(label) + ").</strong> Posisikan wajah.",
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

  function runDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length) return;
    var vid = $("face-video");
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
        var box = res.detection.box;
        var matchedNama = null;
        if (res.descriptor) {
          var m = bestMatch(res.descriptor);
          if (m) {
            matchedNama = m.ref.siswa.nama || "Siswa";
            onMatch(m.ref.siswa, m.distance);
          }
        }
        updateFaceTarget(vid, box, matchedNama);
      })
      .catch(function () {
        busyDetect = false;
        hideFaceTarget();
      });
  }

  function startCamera() {
    stopCamera();
    var vid = $("face-video");
    if (!vid) {
      setStatus("<strong>Elemen video tidak ditemukan.</strong>", "warn");
      return Promise.reject(new Error("no video"));
    }
    if (typeof window.presensiOpenWebcam !== "function") {
      setStatus(
        "<strong>Skrip kamera tidak dimuat.</strong> Muat ulang halaman sepenuhnya (segarkan cache jika perlu).",
        "warn"
      );
      return Promise.reject(new Error("no presensi camera"));
    }
    return window
      .presensiOpenWebcam(vid)
      .then(function (s) {
        stream = s;
        detectTimer = window.setInterval(runDetect, DETECT_MS);
        setStatus(
          "<strong>Kamera aktif.</strong> Posisikan wajah; sistem membandingkan dengan foto referensi siswa.",
          "ok"
        );
        var btnStart = $("face-btn-start");
        var btnStop = $("face-btn-stop");
        var btnFlip = $("face-btn-flip");
        if (btnStart) btnStart.hidden = true;
        if (btnStop) btnStop.hidden = false;
        if (btnFlip) btnFlip.hidden = false;
        syncFlipLabel();
        return s;
      })
      .catch(function (err) {
        var msg = err && (err.message || err.name) ? err.message || err.name : String(err);
        setStatus(
          "<strong>Tidak bisa mengaktifkan kamera.</strong> " +
            escapeHtml(msg) +
            " Pastikan izin kamera untuk situs ini, gunakan HTTPS atau localhost, dan coba tutup aplikasi lain yang memakai webcam.",
          "warn"
        );
        throw err;
      });
  }

  function buildRefs() {
    refs = [];
    setStatus("<strong>Memuat foto referensi…</strong> Mohon tunggu.", "");
    return loadReferensiSiswa().then(function (list) {
      if (!list.length) {
        setStatus(
          '<strong>Belum ada foto referensi.</strong> Siswa aktif perlu foto wajah di ' +
            '<a href="../settings/rekam-data.html">Rekam data</a>.',
          "warn"
        );
        return 0;
      }
      setStatus(
        "<strong>Memproses " +
          list.length +
          " foto referensi…</strong> Mohon tunggu.",
        ""
      );
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
        var parts = [];
        parts.push("<strong>Referensi siap:</strong> " + ok + " wajah terindeks.");
        if (fail) parts.push(" " + fail + " foto tidak terbaca (ganti foto di Rekam data).");
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
    setStatus("<strong>Memuat model pengenalan wajah…</strong> Butuh unduhan sekali.", "");
    return Promise.all([
      faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]).then(function () {
      modelsReady = true;
      return buildRefs();
    });
  }

  function onClick(e) {
    var t = e.target;
    if (t.id === "face-btn-start") {
      if (!modelsReady) {
        setStatus("Model belum siap. Tunggu atau muat ulang halaman.", "warn");
        return;
      }
      if (!refs.length) {
        buildRefs().then(function (n) {
          if (n) startCamera().catch(function () {});
        });
        return;
      }
      startCamera().catch(function () {});
      return;
    }
    if (t.id === "face-btn-stop") {
      stopCamera();
      var btnStart = $("face-btn-start");
      var btnStop = $("face-btn-stop");
      if (btnStart) btnStart.hidden = false;
      if (btnStop) btnStop.hidden = true;
      setStatus(
        "<strong>Kamera dihentikan.</strong> Tekan Aktifkan kamera untuk melanjutkan.",
        ""
      );
      return;
    }
    if (t.id === "face-btn-flip") {
      flipCamera();
      return;
    }
    if (t.id === "face-btn-reload") {
      stopCamera();
      var btnStart = $("face-btn-start");
      var btnStop = $("face-btn-stop");
      if (btnStart) btnStart.hidden = false;
      if (btnStop) btnStop.hidden = true;
      buildRefs().catch(function (err) {
        setStatus(escapeHtml(err.message || String(err)), "warn");
      });
      return;
    }
  }

  function init() {
    root = document.getElementById("face-root");
    if (!root || root.dataset.faceBound) return;
    root.dataset.faceBound = "1";

    function afterLogsReady() {
      renderFaceLogTable();
    }

    if (useDbPresensiLog()) {
      fetchFaceLogsFromDb().then(afterLogsReady).catch(afterLogsReady);
    } else {
      afterLogsReady();
    }

    if (window.speechSynthesis) {
      var warmVoices = function () {
        try {
          window.speechSynthesis.getVoices();
        } catch (e) {}
      };
      warmVoices();
      window.speechSynthesis.addEventListener("voiceschanged", warmVoices);
    }

    root.addEventListener("click", onClick);
    window.addEventListener("beforeunload", stopCamera);

    function bootModels() {
      loadModels()
        .then(function (n) {
          if (n > 0) {
            setStatus(
              "<strong>Model dan referensi siap.</strong> Tekan «Aktifkan kamera» — izin kamera memerlukan tindakan Anda (terutama di Safari / iOS).",
              "ok"
            );
          }
        })
        .catch(function (err) {
          setStatus("<strong>Gagal memuat model.</strong> " + escapeHtml(err.message || String(err)), "warn");
        });
    }

    if (window.PresensiData && window.PresensiData.isRemote()) {
      if (window.PresensiLoading) {
        window.PresensiLoading.show(root, "Memuat data siswa & saldo…");
      }
      setStatus("<strong>Menyinkronkan data siswa dari server…</strong>", "");
      window.PresensiData.pullAll()
        .then(bootModels)
        .catch(function () {
          bootModels();
        })
        .finally(function () {
          if (window.PresensiLoading) {
            window.PresensiLoading.hide(root);
          }
        });
    } else {
      bootModels();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
