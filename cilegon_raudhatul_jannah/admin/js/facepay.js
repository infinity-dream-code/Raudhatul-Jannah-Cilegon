(function () {
  var SISWA_KEY = "presensi_data_siswa";
  var SALDO_KEY = "presensi_saldo_siswa";
  var FACEPAY_LOG_KEY = "presensi_facepay_log";

  var MODEL_URL = "https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights";
  var MATCH_THRESHOLD = 0.52;
  var COOLDOWN_MS = 5500;
  var DETECT_MS = 450;
  var MAX_LOG_ROWS = 40;
  var NOMINAL_MIN = 1000;
  var NOMINAL_MAX = 999999999;

  var root;
  var stream = null;
  var detectTimer = null;
  var modelsReady = false;
  var refs = [];
  var busyDetect = false;
  var lastAnnounceById = {};
  var handsInstance = null;
  var lastHandsResults = null;
  var handsReady = false;
  var lockedNominal = 0;
  /** Siswa terakhir yang sukses bayar; siswa sama harus menunggu siswa lain dulu. */
  var lastSuccessfulSiswaId = null;
  var openHandStreak = 0;
  var OPEN_HAND_FRAMES = 1;
  var activeNominalSpeechRec = null;

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

  function loadSaldoMap() {
    try {
      var raw = localStorage.getItem(SALDO_KEY);
      if (!raw) return {};
      var o = JSON.parse(raw);
      return o && typeof o === "object" && !Array.isArray(o) ? o : {};
    } catch (e) {
      return {};
    }
  }

  var saldoLiveCache = {};
  var saldoFetchById = {};
  var overlaySaldoSiswaId = null;
  var payInFlight = false;
  var SALDO_CACHE_TTL_MS = 45000;

  function getSaldo(siswaId) {
    var live = saldoLiveCache[siswaId];
    if (live && live.saldo != null) return Number(live.saldo) || 0;
    var v = Number(loadSaldoMap()[siswaId]);
    return isNaN(v) ? 0 : v;
  }

  function saldoCacheFresh(siswaId) {
    var c = saldoLiveCache[siswaId];
    if (!c || c.saldo == null) return false;
    return Date.now() - (c.ts || 0) < SALDO_CACHE_TTL_MS;
  }

  function formatSaldoOverlayText(saldo) {
    return "Saldo Rp " + formatRupiah(saldo);
  }

  function paintSaldoOverlay(siswa, saldo) {
    var saldoEl = $("facepay-target-saldo");
    if (!saldoEl || !siswa || siswa.id == null) return;
    saldoEl.hidden = false;
    saldoEl.textContent = formatSaldoOverlayText(saldo);
  }

  function fetchSaldoLive(siswa, opts) {
    opts = opts || {};
    if (!siswa || siswa.id == null) return Promise.resolve(0);
    if (!window.PresensiApiExtras || !window.PresensiApiExtras.inquirySaldo) {
      return Promise.resolve(getSaldo(siswa.id));
    }
    var id = siswa.id;
    if (!opts.force && saldoCacheFresh(id)) {
      return Promise.resolve(getSaldo(id));
    }
    if (saldoFetchById[id]) {
      return saldoFetchById[id];
    }
    var saldoEl = $("facepay-target-saldo");
    if (!opts.silent && saldoEl && overlaySaldoSiswaId === id && !saldoCacheFresh(id)) {
      saldoEl.textContent = "Memuat saldo…";
      saldoEl.hidden = false;
    }
    var p = window.PresensiApiExtras.inquirySaldo(siswa)
      .then(function (d) {
        var saldo = Number(d && d.saldo) || 0;
        saldoLiveCache[id] = {
          saldo: saldo,
          status: d && d.status,
          nama: d && d.nama,
          ts: Date.now(),
        };
        var map = loadSaldoMap();
        map[id] = saldo;
        saveSaldoMap(map);
        if (overlaySaldoSiswaId === id) {
          paintSaldoOverlay(siswa, saldo);
        }
        return saldo;
      })
      .catch(function () {
        return getSaldo(id);
      })
      .finally(function () {
        delete saldoFetchById[id];
      });
    saldoFetchById[id] = p;
    return p;
  }

  function updateOverlaySaldo(siswa) {
    if (!siswa || siswa.id == null) return;
    var id = siswa.id;
    overlaySaldoSiswaId = id;
    if (saldoCacheFresh(id)) {
      paintSaldoOverlay(siswa, getSaldo(id));
      return;
    }
    if (saldoFetchById[id]) {
      var cached = saldoLiveCache[id];
      if (cached && cached.saldo != null) {
        paintSaldoOverlay(siswa, cached.saldo);
      }
      return;
    }
    if (!window.PresensiApiExtras || !window.PresensiApiExtras.inquirySaldo) {
      paintSaldoOverlay(siswa, getSaldo(id));
      return;
    }
    var saldoEl = $("facepay-target-saldo");
    if (saldoEl) {
      saldoEl.hidden = false;
      saldoEl.textContent = "Memuat saldo…";
    }
    fetchSaldoLive(siswa, { silent: true });
  }

  function saveSaldoMap(m) {
    try {
      localStorage.setItem(SALDO_KEY, JSON.stringify(m || {}));
    } catch (e) {}
  }

  /** @returns {{ before: number, after: number } | null} */
  function tryDebit(siswaId, amount) {
    var map = loadSaldoMap();
    var cur = Number(map[siswaId]) || 0;
    if (cur < amount) return null;
    map[siswaId] = cur - amount;
    saveSaldoMap(map);
    return { before: cur, after: map[siswaId] };
  }

  /** Debit dari saldo live/inquiry; tidak memanggil api/saldo/debit (belum ada di server). */
  function debitFromLiveSaldo(siswa, amount, saldoBefore) {
    var before = saldoBefore != null ? Number(saldoBefore) : getSaldo(siswa.id);
    if (isNaN(before) || before < amount) return null;
    var after = before - amount;
    var map = loadSaldoMap();
    map[siswa.id] = after;
    saveSaldoMap(map);
    saldoLiveCache[siswa.id] = {
      saldo: after,
      ts: Date.now(),
      nama: (saldoLiveCache[siswa.id] && saldoLiveCache[siswa.id].nama) || siswa.nama,
    };
    if (overlaySaldoSiswaId === siswa.id) {
      paintSaldoOverlay(siswa, after);
    }
    return { before: before, after: after };
  }

  function formatRupiah(n) {
    var x = Math.floor(Number(n) || 0);
    return x.toLocaleString("id-ID");
  }

  function formatWaktu(iso) {
    try {
      return new Date(iso).toLocaleString("id-ID");
    } catch (e) {
      return "—";
    }
  }

  function loadFacepayLogs() {
    try {
      var raw = localStorage.getItem(FACEPAY_LOG_KEY);
      if (!raw) return [];
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function saveFacepayLogs(rows) {
    try {
      localStorage.setItem(FACEPAY_LOG_KEY, JSON.stringify(rows || []));
    } catch (e) {}
  }

  function renderLogTable() {
    var body = $("facepay-log-body");
    var meta = $("facepay-log-meta");
    if (!body) return;
    var logs = loadFacepayLogs();
    if (!logs.length) {
      body.innerHTML = '<tr><td class="data-table__empty" colspan="5">Belum ada transaksi FacePay.</td></tr>';
      if (meta) meta.textContent = "Belum ada transaksi.";
      return;
    }
    if (meta) meta.textContent = "Total transaksi: " + logs.length + ".";
    body.innerHTML = logs
      .map(function (r) {
        return (
          "<tr>" +
          "<td>" +
          escapeHtml(r.nama || "—") +
          "</td>" +
          "<td>" +
          escapeHtml(formatWaktu(r.waktu)) +
          "</td>" +
          "<td>Rp " +
          escapeHtml(formatRupiah(r.nominal)) +
          "</td>" +
          "<td>Rp " +
          escapeHtml(formatRupiah(r.saldoSebelum)) +
          "</td>" +
          "<td>Rp " +
          escapeHtml(formatRupiah(r.saldoSesudah)) +
          "</td>" +
          "</tr>"
        );
      })
      .join("");
  }

  function appendFacepayLog(entry) {
    var logs = loadFacepayLogs();
    logs.unshift(entry);
    if (logs.length > MAX_LOG_ROWS) logs = logs.slice(0, MAX_LOG_ROWS);
    saveFacepayLogs(logs);
    renderLogTable();
  }

  function readNominalInput() {
    var inp = $("facepay-nominal");
    if (!inp) return null;
    var raw = String(inp.value).trim();
    if (!raw) return null;
    var n = parseInt(raw, 10);
    return isNaN(n) ? null : n;
  }

  /** Nominal yang dipakai transaksi: selalu dari input jika valid. */
  function getActiveNominal() {
    var n = readNominalInput();
    if (n != null && n >= NOMINAL_MIN) return Math.min(n, NOMINAL_MAX);
    return lockedNominal >= NOMINAL_MIN ? lockedNominal : 0;
  }

  function updateNominalDisplay() {
    var el = $("facepay-nominal-active");
    if (!el) return;
    var n = getActiveNominal();
    el.textContent = n >= NOMINAL_MIN ? "Nominal aktif: Rp " + formatRupiah(n) : "Nominal aktif: —";
  }

  function applyNominalFromInput() {
    var inp = $("facepay-nominal");
    if (!inp) return false;
    var n = readNominalInput();
    if (n == null || n < NOMINAL_MIN) {
      if (lockedNominal >= NOMINAL_MIN) inp.value = String(lockedNominal);
      updateNominalDisplay();
      return false;
    }
    if (n > NOMINAL_MAX) {
      n = NOMINAL_MAX;
      inp.value = String(n);
    }
    lockedNominal = n;
    updateNominalDisplay();
    if (stream) {
      setStatus(
        "<strong>Kamera aktif.</strong> Nominal Rp " +
          formatRupiah(lockedNominal) +
          " (bisa diubah kapan saja). Wajah zona kanan; telapak kiri di bingkai kiri.",
        "ok"
      );
    }
    return true;
  }

  function adjustNominalBy(delta) {
    var inp = $("facepay-nominal");
    if (!inp) return;
    var cur = parseInt(inp.value, 10);
    if (isNaN(cur)) cur = NOMINAL_MIN;
    var next = cur + delta;
    if (next < NOMINAL_MIN) next = NOMINAL_MIN;
    if (next > NOMINAL_MAX) next = NOMINAL_MAX;
    inp.value = String(next);
    applyNominalFromInput();
  }

  function normSpeechNominal(s) {
    return String(s || "")
      .trim()
      .toLowerCase()
      .replace(/[.,]/g, " ")
      .replace(/\brp\b|\brupiah\b|\bidr\b/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function basicNumWord(w) {
    var map = {
      nol: 0,
      kosong: 0,
      satu: 1,
      dua: 2,
      tiga: 3,
      empat: 4,
      lima: 5,
      enam: 6,
      tujuh: 7,
      delapan: 8,
      sembilan: 9,
    };
    return Object.prototype.hasOwnProperty.call(map, w) ? map[w] : null;
  }

  /** Ubah ucapan (angka atau kata Indonesia) menjadi nominal Rupiah. */
  function parseSmallIndonesianNumber(tokens, start, end) {
    var section = 0;
    var pending = null;
    var i = start;
    while (i < end) {
      var w = tokens[i];
      if (w === "juta") {
        section = section || 1;
        return { value: section * 1000000, next: i + 1 };
      }
      if (w === "ribu") {
        section = section || 1;
        return { value: section * 1000, next: i + 1 };
      }
      if (w === "ratus") {
        section = (section || 0) + (pending != null ? pending : 1) * 100;
        pending = null;
        i++;
        continue;
      }
      if (w === "puluh") {
        section = section + (pending != null ? pending : section > 0 ? 0 : 1) * 10;
        pending = null;
        i++;
        continue;
      }
      if (w === "belas") {
        section = section + (pending != null ? pending : 1) + 10;
        pending = null;
        i++;
        continue;
      }
      if (w === "seratus") {
        section = section + 100;
        i++;
        continue;
      }
      if (w === "seribu") {
        return { value: 1000, next: i + 1 };
      }
      if (w === "sejuta") {
        return { value: 1000000, next: i + 1 };
      }
      if (w === "sepuluh") {
        section = section + 10;
        i++;
        continue;
      }
      if (w === "sebelas") {
        section = section + 11;
        i++;
        continue;
      }
      if (/^\d+$/.test(w)) {
        section = section + parseInt(w, 10);
        i++;
        continue;
      }
      var n = basicNumWord(w);
      if (n != null) {
        var next = tokens[i + 1];
        if (next === "puluh" || next === "ratus" || next === "belas") {
          pending = n;
        } else {
          section = section + n;
        }
        i++;
        continue;
      }
      return null;
    }
    if (pending != null) section = section + pending;
    return section > 0 ? { value: section, next: end } : null;
  }

  function parseNominalFromSpeech(raw) {
    var s = normSpeechNominal(raw);
    if (!s) return null;

    var digitsOnly = s.replace(/[^\d]/g, "");
    if (digitsOnly.length > 0) {
      var n0 = parseInt(digitsOnly, 10);
      if (!isNaN(n0) && n0 >= NOMINAL_MIN) return Math.min(n0, NOMINAL_MAX);
    }

    var ribuNum = s.match(/(\d+)\s*ribu/);
    if (ribuNum) {
      var nr = parseInt(ribuNum[1], 10) * 1000;
      if (nr >= NOMINAL_MIN) return Math.min(nr, NOMINAL_MAX);
    }

    var digitChunk = s.match(/\d[\d\s]{0,}/);
    if (digitChunk) {
      var n1 = parseInt(digitChunk[0].replace(/\D/g, ""), 10);
      if (!isNaN(n1) && n1 >= NOMINAL_MIN) return Math.min(n1, NOMINAL_MAX);
    }

    var tokens = s.split(/\s+/).filter(Boolean);
    if (!tokens.length) return null;

    var total = 0;
    var idx = 0;
    while (idx < tokens.length) {
      var part = parseSmallIndonesianNumber(tokens, idx, tokens.length);
      if (!part) return null;
      total += part.value;
      idx = part.next;
    }
    return total >= NOMINAL_MIN ? total : null;
  }

  function setSpeechNominalLine(msg, ok) {
    var el = $("facepay-speech-nominal-status");
    if (!el) return;
    el.textContent = msg || "";
    el.className = "facepay-speech-line" + (ok ? " facepay-speech-line--ok" : "");
  }

  function setMicButtonListening(listening) {
    var btn = $("facepay-btn-speech-nominal");
    if (!btn) return;
    btn.classList.toggle("facepay-mic-btn--listening", Boolean(listening));
    btn.setAttribute("aria-pressed", listening ? "true" : "false");
    var label = listening ? "Mendengarkan… tekan untuk batalkan" : "Buka mikrofon — ucapkan nominal";
    btn.setAttribute("aria-label", label);
    btn.title = listening ? "Mendengarkan (klik batalkan)" : "Buka mikrofon";
    var textEl = btn.querySelector(".facepay-mic-btn__text");
    if (textEl) textEl.textContent = listening ? "On" : "Mic";
  }

  function abortActiveNominalSpeechRec() {
    if (!activeNominalSpeechRec) return;
    try {
      activeNominalSpeechRec.abort();
    } catch (e1) {}
    try {
      activeNominalSpeechRec.stop();
    } catch (e2) {}
    activeNominalSpeechRec = null;
    setMicButtonListening(false);
  }

  function nominalSpeechErrorMessage(code) {
    var m = {
      "not-allowed": "Mikrofon ditolak. Izinkan akses mikrofon untuk situs ini.",
      "service-not-allowed": "Pengenalan suara memerlukan HTTPS atau localhost.",
      "no-speech": "Tidak terdengar suara. Ucapkan nominal lebih jelas, misalnya «lima ribu».",
      "audio-capture": "Mikrofon tidak terdeteksi atau sedang dipakai aplikasi lain.",
      network: "Jaringan bermasalah untuk layanan pengenalan suara.",
      aborted: "Pengenalan dibatalkan.",
    };
    return m[code] || "Pengenalan suara gagal (" + (code || "?") + ").";
  }

  function applyNominalFromSpeech(n) {
    var inp = $("facepay-nominal");
    if (!inp || n == null) return false;
    if (n > NOMINAL_MAX) n = NOMINAL_MAX;
    inp.value = String(n);
    inp.dispatchEvent(new Event("input", { bubbles: true }));
    inp.dispatchEvent(new Event("change", { bubbles: true }));
    return applyNominalFromInput();
  }

  function pickIndonesianVoice() {
    var synth = window.speechSynthesis;
    if (!synth) return null;
    var voices = synth.getVoices();
    var i;
    for (i = 0; i < voices.length; i++) {
      if ((voices[i].lang || "").toLowerCase().indexOf("id") === 0) return voices[i];
    }
    for (i = 0; i < voices.length; i++) {
      if (/indonesia|indonesian/i.test(voices[i].name || "")) return voices[i];
    }
    return voices[0] || null;
  }

  function speakText(text) {
    if (!text || !window.speechSynthesis) return;
    function utterNow() {
      try {
        window.speechSynthesis.cancel();
      } catch (e) {}
      var u = new SpeechSynthesisUtterance(text);
      u.lang = "id-ID";
      u.rate = 0.94;
      var voice = pickIndonesianVoice();
      if (voice) u.voice = voice;
      window.speechSynthesis.speak(u);
    }
    var voices = window.speechSynthesis.getVoices();
    if (voices && voices.length) {
      utterNow();
      return;
    }
    var done = false;
    function once() {
      if (done) return;
      done = true;
      window.speechSynthesis.removeEventListener("voiceschanged", once);
      utterNow();
    }
    window.speechSynthesis.addEventListener("voiceschanged", once);
    window.setTimeout(once, 500);
  }

  function warmSpeechVoices() {
    if (!window.speechSynthesis) return;
    try {
      window.speechSynthesis.getVoices();
    } catch (e) {}
  }

  function beginNominalSpeechRecognition() {
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      setSpeechNominalLine(
        "Browser tidak mendukung pengenalan suara (gunakan Chrome atau Edge, HTTPS / localhost).",
        false
      );
      setMicButtonListening(false);
      return;
    }

    var btn = $("facepay-btn-speech-nominal");
    var rec = new SpeechRecognition();
    activeNominalSpeechRec = rec;
    rec.lang = "id-ID";
    rec.continuous = false;
    rec.interimResults = false;
    rec.maxAlternatives = 5;

    rec.onresult = function (ev) {
      activeNominalSpeechRec = null;
      setMicButtonListening(false);
      if (btn) btn.disabled = false;
      var said = "";
      var parsed = null;
      var alt;
      var row = ev.results[0];
      if (row) {
        for (alt = 0; alt < row.length; alt++) {
          said = row[alt] && row[alt].transcript ? row[alt].transcript : "";
          parsed = parseNominalFromSpeech(said);
          if (parsed != null) break;
        }
      }
      if (parsed == null) {
        setSpeechNominalLine(
          "Tidak dikenali: «" +
            (said || "?") +
            "». Coba «lima ribu», «sepuluh ribu», atau «5000».",
          false
        );
        return;
      }
      if (parsed < NOMINAL_MIN) {
        setSpeechNominalLine(
          "Nominal terlalu kecil (min. Rp " + formatRupiah(NOMINAL_MIN) + "). Anda: «" + (said || "?") + "».",
          false
        );
        return;
      }
      applyNominalFromSpeech(parsed);
      setSpeechNominalLine(
        "Nominal Rp " + formatRupiah(parsed) + " dari ucapan: «" + (said || "").trim() + "».",
        true
      );
      speakText("Nominal " + formatRupiah(parsed) + " rupiah.");
    };

    rec.onerror = function (ev) {
      activeNominalSpeechRec = null;
      setMicButtonListening(false);
      if (btn) btn.disabled = false;
      var code = ev && ev.error ? ev.error : "";
      if (code !== "aborted") setSpeechNominalLine(nominalSpeechErrorMessage(code), false);
    };

    rec.onend = function () {
      setMicButtonListening(false);
      if (btn) btn.disabled = false;
      activeNominalSpeechRec = null;
    };

    rec.onstart = function () {
      setSpeechNominalLine("Mendengarkan… ucapkan nominal, misalnya «lima ribu».", false);
    };

    try {
      rec.start();
    } catch (err) {
      activeNominalSpeechRec = null;
      setMicButtonListening(false);
      if (btn) btn.disabled = false;
      setSpeechNominalLine("Tidak bisa memulai pengenalan: " + (err.message || String(err)), false);
    }
  }

  function runNominalSpeechRecognition() {
    if (activeNominalSpeechRec) {
      abortActiveNominalSpeechRec();
      setSpeechNominalLine("Pengenalan suara dibatalkan.", false);
      return;
    }

    if (!window.isSecureContext) {
      setSpeechNominalLine("Pengenalan suara memerlukan HTTPS atau localhost.", false);
      return;
    }

    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      setSpeechNominalLine(
        "Browser tidak mendukung pengenalan suara (gunakan Chrome atau Edge, HTTPS / localhost).",
        false
      );
      return;
    }

    setSpeechNominalLine("Meminta izin mikrofon…", false);
    setMicButtonListening(true);

    function afterMicReady() {
      beginNominalSpeechRecognition();
    }

    if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === "function") {
      navigator.mediaDevices
        .getUserMedia({ audio: true })
        .then(function (mediaStream) {
          mediaStream.getTracks().forEach(function (t) {
            t.stop();
          });
          afterMicReady();
        })
        .catch(function () {
          setMicButtonListening(false);
          setSpeechNominalLine(nominalSpeechErrorMessage("not-allowed"), false);
        });
    } else {
      afterMicReady();
    }
  }

  function canTransactSiswa(siswaId) {
    if (!lastSuccessfulSiswaId) return true;
    return siswaId !== lastSuccessfulSiswaId;
  }

  function setStatus(html, kind) {
    var el = $("facepay-status");
    if (!el) return;
    el.className = "face-status" + (kind ? " face-status--" + kind : "");
    el.innerHTML = html;
  }

  function lmDist(a, b) {
    var dx = a.x - b.x;
    var dy = a.y - b.y;
    return Math.sqrt(dx * dx + dy * dy);
  }

  /** Panjang telapak (wrist → middle MCP) untuk skala relatif. */
  function palmScale(lm) {
    if (!lm || lm.length < 21) return 0;
    return lmDist(lm[0], lm[9]);
  }

  /** Satu jari dianggap terbuka (ambang longgar agar telapak halo lebih mudah terdeteksi). */
  function isFingerExtendedRelaxed(lm, tipIdx, pipIdx, mcpIdx) {
    var wrist = lm[0];
    var palm = lm[9];
    var tip = lm[tipIdx];
    var pip = lm[pipIdx];
    var mcp = lm[mcpIdx];
    var dTipW = lmDist(wrist, tip);
    var dPipW = lmDist(wrist, pip);
    var dMcpW = lmDist(wrist, mcp);
    if (dTipW < dPipW * 1.06) return false;
    if (dTipW < dMcpW * 1.12) return false;
    if (lmDist(palm, tip) < lmDist(palm, pip) * 1.02) return false;
    return true;
  }

  /** Genggaman kuat: banyak ujung jari menempel ke telapak. */
  function isGraspOrFist(lm) {
    var palm = lm[9];
    var scale = palmScale(lm);
    if (scale < 0.02) return true;
    var fingerTips = [8, 12, 16, 20];
    var fingerPips = [6, 10, 14, 18];
    var curled = 0;
    var tipNearPalm = 0;
    for (var i = 0; i < 4; i++) {
      var tip = lm[fingerTips[i]];
      var pip = lm[fingerPips[i]];
      if (lmDist(palm, tip) < lmDist(palm, pip) * 1.05) curled++;
      if (lmDist(palm, tip) < scale * 0.55) tipNearPalm++;
    }
    if (curled >= 3) return true;
    if (tipNearPalm >= 4) return true;
    var spread =
      (lmDist(lm[8], lm[12]) + lmDist(lm[12], lm[16]) + lmDist(lm[16], lm[20])) / 3;
    if (spread < scale * 0.32) return true;
  }

  /** Telapak terbuka (halo): minimal 3 jari terbuka, jari terbentang, tolak genggaman jelas. */
  function isPalmOpenRelaxed(lm) {
    if (!lm || lm.length < 21) return false;
    var scale = palmScale(lm);
    if (scale < 0.018) return false;
    if (isGraspOrFist(lm)) return false;
    var extended = 0;
    if (isFingerExtendedRelaxed(lm, 8, 6, 5)) extended++;
    if (isFingerExtendedRelaxed(lm, 12, 10, 9)) extended++;
    if (isFingerExtendedRelaxed(lm, 16, 14, 13)) extended++;
    if (isFingerExtendedRelaxed(lm, 20, 18, 17)) extended++;
    if (extended < 3) return false;
    var spread =
      (lmDist(lm[8], lm[12]) + lmDist(lm[12], lm[16]) + lmDist(lm[16], lm[20])) / 3;
    if (spread < scale * 0.36) return false;
    return true;
  }

  function handednessLabel(results, index) {
    if (!results.multiHandedness || !results.multiHandedness[index]) return "";
    var h = results.multiHandedness[index];
    return (h.label || h.categoryName || h.displayName || "").trim();
  }

  /**
   * Zona tangan di samping KIRI wajah pada preview cermin (video scaleX(-1)).
   * Buffer: wajah kanan layar ≈ x kecil; tangan kiri layar ≈ x besar → refX > faceCx.
   */
  function isHandInPayZone(lm, faceBox, vid) {
    if (!lm || !faceBox || !vid || !vid.videoWidth || !vid.videoHeight) return false;
    var vw = vid.videoWidth;
    var vh = vid.videoHeight;
    var faceCx = (faceBox.x + faceBox.width * 0.5) / vw;
    var faceRight = (faceBox.x + faceBox.width) / vw;
    var wrist = lm[0];
    var palm = lm[9];
    var refX = (wrist.x + palm.x) * 0.5;
    var refY = (wrist.y + palm.y) * 0.5;
    var faceTop = faceBox.y / vh;
    var faceBot = (faceBox.y + faceBox.height) / vh;
    var faceMidY = (faceTop + faceBot) / 2;
    if (refX <= faceCx + 0.02) return false;
    if (refX > Math.min(0.98, faceRight + 0.28)) return false;
    if (Math.abs(refY - faceMidY) > 0.42) return false;
    return true;
  }

  /**
   * Telapak terbuka di samping kiri wajah (buffer). Prioritas tangan kiri (Left).
   */
  function handRefX(lm) {
    return (lm[0].x + lm[9].x) * 0.5;
  }

  function pickHandGateLandmarks(results, faceBox, vid) {
    if (!results || !results.multiHandLandmarks || !results.multiHandLandmarks.length) return null;
    if (!faceBox || !vid || !vid.videoWidth || !vid.videoHeight) return null;
    var labeledLeft = null;
    var bestInZone = null;
    var bestX = -1;
    var i;
    for (i = 0; i < results.multiHandLandmarks.length; i++) {
      var lm = results.multiHandLandmarks[i];
      if (!isHandInPayZone(lm, faceBox, vid)) continue;
      if (!isPalmOpenRelaxed(lm)) continue;
      var rx = handRefX(lm);
      if (rx > bestX) {
        bestX = rx;
        bestInZone = lm;
      }
      if (handednessLabel(results, i) === "Left") labeledLeft = lm;
    }
    return labeledLeft || bestInZone;
  }

  /** Tangan di zona tapi jari tidak terbuka (genggaman / semi-genggam). */
  function pickHandInZoneClosed(results, faceBox, vid) {
    if (!results || !results.multiHandLandmarks) return false;
    for (var i = 0; i < results.multiHandLandmarks.length; i++) {
      var lm = results.multiHandLandmarks[i];
      if (!isHandInPayZone(lm, faceBox, vid)) continue;
      if (!isPalmOpenRelaxed(lm)) return true;
    }
    return false;
  }

  function updateHandDot(lm) {
    var dot = $("facepay-hand-dot");
    if (!dot || !lm || !lm[0]) {
      if (dot) {
        dot.hidden = true;
        dot.classList.remove("facepay-hand-dot--on");
      }
      return;
    }
    var w = lm[0];
    dot.style.left = (1 - w.x) * 100 + "%";
    dot.style.top = w.y * 100 + "%";
    dot.hidden = false;
    dot.classList.add("facepay-hand-dot--on");
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
    var boxEl = $("facepay-target-box");
    var info = $("facepay-target-info");
    var nameEl = $("facepay-target-name");
    var saldoEl = $("facepay-target-saldo");
    if (boxEl) {
      boxEl.hidden = true;
      boxEl.classList.remove("face-target-box--matched");
    }
    if (info) info.hidden = true;
    if (nameEl) nameEl.textContent = "";
    if (saldoEl) {
      saldoEl.textContent = "";
      saldoEl.hidden = true;
    }
    overlaySaldoSiswaId = null;
  }

  function updateFaceTarget(vid, box, matchedNama, siswaForSaldo) {
    var boxEl = $("facepay-target-box");
    var info = $("facepay-target-info");
    var nameEl = $("facepay-target-name");
    var saldoEl = $("facepay-target-saldo");
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
      if (info && nameEl) {
        nameEl.textContent = matchedNama;
        if (saldoEl) {
          if (siswaForSaldo && siswaForSaldo.id != null) {
            if (overlaySaldoSiswaId !== siswaForSaldo.id) {
              updateOverlaySaldo(siswaForSaldo);
            } else if (saldoCacheFresh(siswaForSaldo.id)) {
              paintSaldoOverlay(siswaForSaldo, getSaldo(siswaForSaldo.id));
            }
          } else {
            saldoEl.textContent = "";
            saldoEl.hidden = true;
          }
        }
        info.hidden = false;
      }
    } else {
      boxEl.classList.remove("face-target-box--matched");
      if (info) info.hidden = true;
      if (nameEl) nameEl.textContent = "";
      if (saldoEl) {
        saldoEl.textContent = "";
        saldoEl.hidden = true;
      }
    }
  }

  function faceInUpperZone(box, vid) {
    var vh = vid.videoHeight;
    if (!vh) return false;
    var cy = box.y + box.height / 2;
    return cy < vh * 0.68;
  }

  function setZoneOk(faceOk, handOk) {
    var zf = $("facepay-zone-face");
    var zh = $("facepay-zone-hand");
    if (zf) zf.classList.toggle("facepay-zone--ok", Boolean(faceOk));
    if (zh) zh.classList.toggle("facepay-zone--ok", Boolean(handOk));
  }

  function loadSiswaAktifDenganFoto() {
    return loadRows(SISWA_KEY).filter(function (s) {
      return s.aktif && s.fotoWajah && String(s.fotoWajah).length > 80;
    });
  }

  function buildRefs() {
    refs = [];
    var list = loadSiswaAktifDenganFoto();
    if (!list.length) {
      return Promise.resolve(0);
    }
    var chain = Promise.resolve();
    var ok = 0;
    var fail = 0;
    list.forEach(function (siswa) {
      chain = chain.then(function () {
        return faceapi
          .fetchImage(siswa.fotoWajah)
          .then(function (img) {
            return faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();
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
      if (fail) parts.push(" " + fail + " foto tidak terbaca.");
      setStatus(parts.join(""), ok ? "ok" : "warn");
      return ok;
    });
  }

  function loadModels() {
    if (typeof faceapi === "undefined") {
      setStatus("<strong>face-api.js gagal dimuat.</strong>", "warn");
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

  function initHands() {
    return new Promise(function (resolve, reject) {
      if (typeof Hands === "undefined") {
        reject(new Error("MediaPipe Hands tidak tersedia"));
        return;
      }
      try {
        var h = new Hands({
          locateFile: function (file) {
            return "https://cdn.jsdelivr.net/npm/@mediapipe/hands@0.4.1675469240/" + file;
          },
        });
        h.setOptions({
          selfieMode: false,
          maxNumHands: 2,
          modelComplexity: 1,
          minDetectionConfidence: 0.5,
          minTrackingConfidence: 0.5,
        });
        h.onResults(function (results) {
          lastHandsResults = results;
        });
        var initP = typeof h.initialize === "function" ? h.initialize() : Promise.resolve();
        initP
          .then(function () {
            handsInstance = h;
            handsReady = true;
            resolve();
          })
          .catch(reject);
      } catch (e) {
        reject(e);
      }
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

  function inPayCooldown(siswaId) {
    var t = Date.now();
    var prev = lastAnnounceById[siswaId] || 0;
    return t - prev < COOLDOWN_MS;
  }

  function markPayCooldown(siswaId) {
    lastAnnounceById[siswaId] = Date.now();
  }

  function speakPaySuccess(siswa, debit, amount) {
    var nom = amount != null ? amount : getActiveNominal();
    var nama = siswa && siswa.nama ? String(siswa.nama).trim() : "Siswa";
    var saldoStr = formatRupiah(debit.after);
    var belanjaStr = formatRupiah(nom);
    speakText(nama + ". Nominal saldo " + saldoStr + " rupiah. Belanja " + belanjaStr + " rupiah.");
  }

  function speakPayFail(reason) {
    var text = reason && String(reason).trim() ? String(reason).trim() : "Pembayaran gagal.";
    speakText(text);
  }

  function onPaySuccess(siswa, debit, amount) {
    var nom = amount != null ? amount : getActiveNominal();
    appendFacepayLog({
      id: "fp-" + Date.now(),
      waktu: new Date().toISOString(),
      siswaId: siswa.id,
      nama: siswa.nama,
      nominal: nom,
      saldoSebelum: debit.before,
      saldoSesudah: debit.after,
    });
    if (typeof window.presensiShowMasukModal === "function") {
      window.presensiShowMasukModal({
        title: "Pembayaran berhasil",
        nama: siswa.nama,
        nis: siswa.nis || "—",
        subtitle: "Terdebir Rp " + formatRupiah(nom) + " · Saldo sisa Rp " + formatRupiah(debit.after),
        durationMs: 4500,
      });
    }
    speakPaySuccess(siswa, debit, nom);
    var wrap = $("facepay-video-wrap");
    if (wrap) {
      wrap.classList.add("face-video-wrap--pulse");
      window.setTimeout(function () {
        wrap.classList.remove("face-video-wrap--pulse");
      }, 900);
    }
  }

  function onPayFail(siswa, reason) {
    speakPayFail(reason);
    setStatus("<strong>" + escapeHtml(reason) + "</strong>", "warn");
  }

  function onMatch(siswa, distance, handOk, faceZoneOk) {
    var hint = $("facepay-hint");
    if (!handOk || !faceZoneOk) {
      if (hint) {
        hint.textContent =
          "Wajah cocok (jarak " +
          distance.toFixed(2) +
          "). Pastikan wajah di zona panduan dan telapak kiri terbuka (halo), bukan genggaman.";
      }
      return;
    }
    if (payInFlight) return;
    if (inPayCooldown(siswa.id)) return;
    if (!canTransactSiswa(siswa.id)) {
      if (hint) {
        hint.textContent =
          "Wajah " +
          (siswa.nama || "siswa") +
          " baru bayar. Minta siswa lain bayar dulu sebelum transaksi kedua.";
      }
      return;
    }
    if (hint) {
      hint.textContent =
        "Wajah cocok (jarak " + distance.toFixed(2) + "). Verifikasi lengkap — pembayaran diproses.";
    }

    var amount = getActiveNominal();
    if (amount < NOMINAL_MIN) return;

    payInFlight = true;
    busyDetect = true;

    var saldoCheck = Promise.resolve(getSaldo(siswa.id));
    if (window.PresensiApiExtras && window.PresensiApiExtras.inquirySaldo) {
      saldoCheck = fetchSaldoLive(siswa, { silent: true, force: !saldoCacheFresh(siswa.id) });
    }

    saldoCheck
      .then(function (saldo) {
        if (saldo < amount) {
          payInFlight = false;
          busyDetect = false;
          onPayFail(siswa, "Saldo tidak mencukupi untuk nominal ini.");
          return;
        }

        var debit = debitFromLiveSaldo(siswa, amount, saldo);
        payInFlight = false;
        busyDetect = false;
        if (!debit) {
          onPayFail(siswa, "Saldo tidak mencukupi.");
          return;
        }
        markPayCooldown(siswa.id);
        lastSuccessfulSiswaId = siswa.id;
        onPaySuccess(siswa, debit, amount);
      })
      .catch(function (e) {
        payInFlight = false;
        busyDetect = false;
        onPayFail(
          siswa,
          (e && e.message ? e.message : "Gagal cek saldo.") || "Gagal cek saldo."
        );
      });
  }

  function runDetect() {
    if (!stream || busyDetect || !modelsReady || !refs.length || getActiveNominal() < NOMINAL_MIN) return;
    var vid = $("facepay-video");
    if (!vid || vid.readyState < 2) return;
    busyDetect = true;

    var seq = Promise.resolve();
    if (handsReady && handsInstance) {
      seq = handsInstance.send({ image: vid }).catch(function () {});
    }

    seq
      .then(function () {
        return faceapi
          .detectSingleFace(vid, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.45 }))
          .withFaceLandmarks()
          .withFaceDescriptor();
      })
      .then(function (res) {
        var box = res && res.detection ? res.detection.box : null;
        var gateLm = handsReady && box ? pickHandGateLandmarks(lastHandsResults, box, vid) : null;
        if (gateLm) openHandStreak++;
        else openHandStreak = 0;
        var handOk = Boolean(gateLm) && openHandStreak >= OPEN_HAND_FRAMES;
        var handClosedInZone =
          handsReady && box && !gateLm ? pickHandInZoneClosed(lastHandsResults, box, vid) : false;
        updateHandDot(handOk ? gateLm : null);

        if (!res || !box) {
          hideFaceTarget();
          openHandStreak = 0;
          setZoneOk(false, false);
          busyDetect = false;
          return;
        }
        var faceZoneOk = faceInUpperZone(box, vid);
        var matchedNama = null;
        var matchSiswa = null;
        var hint = $("facepay-hint");
        if (res.descriptor) {
          var m = bestMatch(res.descriptor);
          if (m && faceZoneOk && handOk) {
            matchedNama = m.ref.siswa.nama || "Siswa";
            matchSiswa = m.ref.siswa;
            onMatch(m.ref.siswa, m.distance, handOk, faceZoneOk);
          } else if (m && faceZoneOk) {
            matchedNama = m.ref.siswa.nama || "Siswa";
            matchSiswa = m.ref.siswa;
            if (hint) {
              hint.textContent = handClosedInZone
                ? "Wajah cocok — buka telapak kiri (halo), jangan genggaman."
                : "Wajah cocok — telapak kiri terbuka di bingkai kiri (cukup di samping kiri wajah).";
            }
          }
        }
        updateFaceTarget(vid, box, matchedNama, matchSiswa);
        setZoneOk(faceZoneOk, handOk);
        if (!payInFlight) busyDetect = false;
      })
      .catch(function () {
        busyDetect = false;
        hideFaceTarget();
        setZoneOk(false, false);
      });
  }

  function stopCamera() {
    abortActiveNominalSpeechRec();
    if (detectTimer) {
      clearInterval(detectTimer);
      detectTimer = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (t) {
        t.stop();
      });
      stream = null;
    }
    var vid = $("facepay-video");
    if (vid) vid.srcObject = null;
    busyDetect = false;
    lockedNominal = 0;
    lastSuccessfulSiswaId = null;
    openHandStreak = 0;
    payInFlight = false;
    overlaySaldoSiswaId = null;
    saldoFetchById = {};
    hideFaceTarget();
    var dot = $("facepay-hand-dot");
    if (dot) {
      dot.hidden = true;
      dot.classList.remove("facepay-hand-dot--on");
    }
    setZoneOk(false, false);
  }

  function startCamera() {
    var nomInp = $("facepay-nominal");
    var n = nomInp ? parseInt(nomInp.value, 10) : 0;
    if (!n || n < NOMINAL_MIN) {
      setStatus("<strong>Nominal minimal " + NOMINAL_MIN + " Rp.</strong> Isi nominal debit terlebih dahulu.", "warn");
      return Promise.reject(new Error("nominal"));
    }

    stopCamera();
    lockedNominal = n;
    updateNominalDisplay();
    var vid = $("facepay-video");
    if (!vid) {
      stopCamera();
      setStatus("<strong>Video tidak ditemukan.</strong>", "warn");
      return Promise.reject(new Error("no video"));
    }
    if (typeof window.presensiOpenWebcam !== "function") {
      stopCamera();
      setStatus("<strong>Skrip kamera tidak dimuat.</strong>", "warn");
      return Promise.reject(new Error("no camera api"));
    }
    return window.presensiOpenWebcam(vid).then(function (s) {
      stream = s;
      detectTimer = window.setInterval(runDetect, DETECT_MS);
      setStatus(
        "<strong>Kamera aktif.</strong> Nominal Rp " +
          formatRupiah(lockedNominal) +
          " (bisa diubah tanpa hentikan kamera). Siswa yang sama harus menunggu siswa lain bayar dulu.",
        "ok"
      );
      $("facepay-btn-start").hidden = true;
      $("facepay-btn-stop").hidden = false;
      return s;
    }).catch(function (err) {
      stopCamera();
      setStatus(
        "<strong>Kamera tidak bisa dibuka.</strong> " + escapeHtml(err && err.message ? err.message : String(err)),
        "warn"
      );
      return Promise.reject(err);
    });
  }

  function onClick(e) {
    var t = e.target;
    var stepBtn = t && t.closest ? t.closest("[data-facepay-delta]") : null;
    if (stepBtn && root && root.contains(stepBtn)) {
      var raw = stepBtn.getAttribute("data-facepay-delta");
      var d = raw != null ? parseInt(raw, 10) : NaN;
      if (!isNaN(d)) adjustNominalBy(d);
      return;
    }
    if (t.id === "facepay-btn-start") {
      if (!modelsReady) {
        setStatus("Model belum siap. Tunggu atau muat ulang.", "warn");
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
    if (t.id === "facepay-btn-stop") {
      stopCamera();
      $("facepay-btn-start").hidden = false;
      $("facepay-btn-stop").hidden = true;
      setStatus("<strong>Kamera dihentikan.</strong>", "");
      return;
    }
    if (t.id === "facepay-btn-reload") {
      stopCamera();
      $("facepay-btn-start").hidden = false;
      $("facepay-btn-stop").hidden = true;
      buildRefs().catch(function (err) {
        setStatus(escapeHtml(err.message || String(err)), "warn");
      });
      return;
    }
  }

  function init() {
    root = document.getElementById("facepay-root");
    if (!root || root.dataset.facepayBound) return;
    root.dataset.facepayBound = "1";
    renderLogTable();
    root.addEventListener("click", onClick);
    window.addEventListener("beforeunload", function () {
      abortActiveNominalSpeechRec();
      stopCamera();
    });
    warmSpeechVoices();
    if (window.speechSynthesis) {
      window.speechSynthesis.addEventListener("voiceschanged", warmSpeechVoices);
    }

    var nomInp = $("facepay-nominal");
    if (nomInp) {
      nomInp.addEventListener("input", applyNominalFromInput);
      nomInp.addEventListener("change", applyNominalFromInput);
    }
    applyNominalFromInput();

    var micBtn = $("facepay-btn-speech-nominal");
    if (micBtn) {
      micBtn.setAttribute("aria-pressed", "false");
      micBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        runNominalSpeechRecognition();
      });
    }

    var handsChain = initHands().catch(function (err) {
      setStatus(
        "<strong>MediaPipe Hands gagal dimuat.</strong> " +
          escapeHtml(err && err.message ? err.message : String(err)) +
          " Coba HTTPS / localhost.",
        "warn"
      );
    });

    function bootModels() {
      Promise.all([loadModels(), handsChain.catch(function () {})])
        .then(function (results) {
          var n = results[0];
          if (n > 0 && handsReady) {
            setStatus(
              "<strong>Siap.</strong> Wajah zona kanan, telapak kiri di bingkai kiri (ukuran seperti gambar). Deteksi tetap dari kamera, bukan dari gambar panduan.",
              "ok"
            );
          } else if (n > 0) {
            setStatus(
              "<strong>Model wajah siap, tanpa Hands.</strong> Untuk keamanan penuh, muat ulang dengan koneksi ke CDN MediaPipe.",
              "warn"
            );
          }
        })
        .catch(function (err) {
          setStatus("<strong>Gagal memuat model wajah.</strong> " + escapeHtml(err.message || String(err)), "warn");
        });
    }

    if (window.PresensiData && window.PresensiData.isRemote()) {
      if (window.PresensiLoading) {
        window.PresensiLoading.show(root, "Memuat data siswa & saldo…");
      }
      setStatus("<strong>Menyinkronkan data siswa & saldo dari server…</strong>", "");
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
