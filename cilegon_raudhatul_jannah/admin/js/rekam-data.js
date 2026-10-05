(function () {
  var SISWA_KEY = "presensi_data_siswa";

  var state = {
    page: 1,
    pageSize: 8,
    /** Daftar ringkas dari DB (rekam-data.php) */
    rows: [],
    dbLoaded: false,
    /** Detail lengkap + foto per id */
    detailById: {},
    detailLoading: {},
    detailErrors: {},
    /** Hasil upload massal terakhir (tetap tampil setelah re-render) */
    bulkLast: null,
  };

  function isRemote() {
    return window.PresensiRekamService && window.PresensiRekamService.isEnabled();
  }

  function loadRowsLocal(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return [];
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : [];
    } catch (e) {
      return [];
    }
  }

  function mapCacheToSummaryRows(rows) {
    return (rows || []).map(function (r) {
      var foto = r && r.fotoWajah ? String(r.fotoWajah) : "";
      var hasFoto = Boolean((r && (r.hasFoto || r._hasFoto)) || foto.length > 30);
      return {
        id: (r && r.id) || "",
        nis: (r && r.nis) || "",
        nisn: (r && r.nisn) || "",
        nama: (r && r.nama) || "",
        kelasId: (r && (r.kelasId || r.kelas_id)) || "",
        jenisKelamin: (r && (r.jenisKelamin || r.jenis_kelamin)) || "L",
        aktif: !r || r.aktif !== false,
        rfidUid: (r && (r.rfidUid || r.rfid_uid)) || "",
        kodeSuara: (r && (r.kodeSuara || r.kode_suara)) || "",
        fotoWajah: "",
        hasFoto: hasFoto,
        _hasFoto: hasFoto,
        code02: (r && (r.code02 || r.CODE02)) || "",
        desc02: (r && (r.desc02 || r.DESC02)) || "",
        desc03: (r && (r.desc03 || r.DESC03)) || "",
        desc04: (r && (r.desc04 || r.DESC04)) || "",
      };
    });
  }

  function hydrateFromCache() {
    var cached = loadRowsLocal(SISWA_KEY);
    if (!cached.length) return false;
    state.rows = mapCacheToSummaryRows(cached);
    state.dbLoaded = true;
    return true;
  }

  function getRows() {
    if (isRemote() && state.dbLoaded) return state.rows;
    if (isRemote() && !state.dbLoaded) return [];
    return loadRowsLocal(SISWA_KEY);
  }

  function findStudent(rows, id) {
    if (!id) return null;
    return rows.find(function (r) {
      return r.id === id;
    });
  }

  function findStudentByNis(rows, nis) {
    var n = String(nis || "").trim().toLowerCase();
    if (!n) return null;
    return (rows || []).find(function (r) {
      return String(r.nis || "").trim().toLowerCase() === n;
    }) || null;
  }

  /** Nama file → NIS. Contoh: 2024001.jpg, 2024001_andi.png */
  function nisFromFilename(filename) {
    var base = String(filename || "").replace(/^.*[\\/]/, "");
    base = base.replace(/\.[^.]+$/, "").trim();
    if (!base) return "";
    var digit = base.match(/^(\d+)/);
    if (digit) return digit[1];
    var seg = base.split(/[_\-\s]+/)[0];
    return String(seg || base).trim();
  }

  function fileToJpegDataUrl(file) {
    return new Promise(function (resolve, reject) {
      if (!file || !String(file.type || "").match(/^image\//)) {
        reject(new Error("Bukan file gambar"));
        return;
      }
      var reader = new FileReader();
      reader.onerror = function () {
        reject(new Error("Gagal membaca file"));
      };
      reader.onload = function () {
        var img = new Image();
        img.onload = function () {
          var canvas = document.createElement("canvas");
          var w = img.naturalWidth || img.width;
          var h = img.naturalHeight || img.height;
          var maxSide = 1280;
          if (w > maxSide || h > maxSide) {
            var scale = maxSide / Math.max(w, h);
            w = Math.round(w * scale);
            h = Math.round(h * scale);
          }
          canvas.width = w;
          canvas.height = h;
          canvas.getContext("2d").drawImage(img, 0, 0, w, h);
          var q = 0.88;
          var dataUrl = canvas.toDataURL("image/jpeg", q);
          while (fotoSizeApprox(dataUrl) > MAX_FOTO_BYTES && q > 0.42) {
            q -= 0.08;
            dataUrl = canvas.toDataURL("image/jpeg", q);
          }
          if (fotoSizeApprox(dataUrl) > MAX_FOTO_BYTES) {
            reject(new Error("Gambar terlalu besar (maks. ±750 KB)"));
            return;
          }
          resolve(dataUrl);
        };
        img.onerror = function () {
          reject(new Error("Gambar tidak terbaca"));
        };
        img.src = reader.result;
      };
      reader.readAsDataURL(file);
    });
  }

  function patchRowInState(saved) {
    if (!saved || !saved.id) return;
    state.detailById[saved.id] = saved;
    var ix = state.rows.findIndex(function (r) {
      return r.id === saved.id;
    });
    var summary = {
      id: saved.id,
      nis: saved.nis,
      nisn: saved.nisn || "",
      nama: saved.nama,
      kelasId: saved.kelasId || "",
      jenisKelamin: saved.jenisKelamin || "L",
      aktif: saved.aktif !== false,
      rfidUid: saved.rfidUid || "",
      kodeSuara: saved.kodeSuara || "",
      fotoWajah: "",
      hasFoto: Boolean(saved.hasFoto || (saved.fotoWajah && String(saved.fotoWajah).length > 30)),
    };
    if (ix >= 0) state.rows[ix] = summary;
    else state.rows.push(summary);
  }

  function loadListFromDb(root) {
    if (!isRemote()) {
      state.dbLoaded = true;
      return Promise.resolve(getRows());
    }
    return window.PresensiRekamService.listSummary()
      .then(function (rows) {
        state.rows = rows || [];
        state.dbLoaded = true;
        return state.rows;
      })
      .catch(function (e) {
        state.dbLoaded = true;
        state.rows = [];
        console.warn("[rekam-data] muat DB:", e && e.message ? e.message : e);
        if (root) {
          var el = document.getElementById("rekam-sync-status");
          if (el) {
            el.hidden = false;
            el.textContent = "Gagal memuat dari database: " + (e.message || e);
          }
        }
        return [];
      });
  }

  function fetchDetailFromDb(siswa, root) {
    if (!siswa || !siswa.id || !isRemote()) return;
    if (state.detailById[siswa.id] && state.detailById[siswa.id].fotoWajah) return;
    if (state.detailLoading[siswa.id]) return;
    delete state.detailErrors[siswa.id];
    state.detailLoading[siswa.id] = true;
    if (root) render(root);
    window.PresensiRekamService.getDetail(siswa.id)
      .then(function (detail) {
        if (detail) patchRowInState(detail);
      })
      .catch(function (e) {
        state.detailErrors[siswa.id] = (e && e.message) || String(e);
        console.warn("[rekam-data] muat foto:", e && e.message ? e.message : e);
      })
      .finally(function () {
        delete state.detailLoading[siswa.id];
        if (root && root.dataset.selectedId === siswa.id) render(root);
      });
  }

  function saveFotoToDb(siswaId, dataUrl, root, opts) {
    opts = opts || {};
    var skipRender = Boolean(opts.skipRender);
    if (isRemote()) {
      return window.PresensiRekamService.save({
        siswaId: siswaId,
        fotoWajah: dataUrl,
      })
        .then(function (saved) {
          patchRowInState(saved);
          return saved;
        })
        .then(function (saved) {
          if (root && !skipRender) render(root);
          return saved;
        });
    }
    var list = loadRowsLocal(SISWA_KEY);
    var cur = findStudent(list, siswaId) || findStudentByNis(list, siswaId);
    if (!cur) return Promise.reject(new Error("Siswa tidak ditemukan"));
    cur.fotoWajah = dataUrl;
    cur.hasFoto = true;
    localStorage.setItem(SISWA_KEY, JSON.stringify(list));
    patchRowInState(cur);
    if (root && !skipRender) render(root);
    return Promise.resolve(cur);
  }

  function formatFileSize(bytes) {
    var n = Number(bytes) || 0;
    if (n < 1024) return n + " B";
    if (n < 1024 * 1024) return (n / 1024).toFixed(0) + " KB";
    return (n / (1024 * 1024)).toFixed(1) + " MB";
  }

  function renderBulkPicked(root, files) {
    var el = root && root.querySelector("#rekam-bulk-picked");
    if (!el) return;
    if (!files || !files.length) {
      el.hidden = true;
      el.innerHTML = "";
      return;
    }
    var maxShow = 8;
    var items = Array.prototype.slice.call(files, 0, maxShow)
      .map(function (f) {
        var nis = nisFromFilename(f.name) || "—";
        return (
          '<li class="rekam-bulk__file">' +
          '<span class="rekam-bulk__file-name">' +
          escapeHtml(f.name) +
          "</span>" +
          '<span class="rekam-bulk__file-meta">NIS ' +
          escapeHtml(nis) +
          " · " +
          escapeHtml(formatFileSize(f.size)) +
          "</span></li>"
        );
      })
      .join("");
    var more =
      files.length > maxShow
        ? '<li class="rekam-bulk__file rekam-bulk__file--more">+' +
          (files.length - maxShow) +
          " file lainnya</li>"
        : "";
    el.hidden = false;
    el.innerHTML =
      '<div class="rekam-bulk__picked-head">' +
      "<strong>" +
      files.length +
      " file siap diunggah</strong>" +
      "<span>Pastikan nama file = NIS</span>" +
      "</div>" +
      '<ul class="rekam-bulk__files">' +
      items +
      more +
      "</ul>";
  }

  function setBulkStatus(root, msg, isError) {
    var el = root && root.querySelector("#rekam-bulk-status");
    if (!el) return;
    el.hidden = !msg;
    el.textContent = msg || "";
    el.classList.toggle("rekam-bulk__status--error", Boolean(isError));
  }

  function setBulkLog(root, lines) {
    var el = root && root.querySelector("#rekam-bulk-log");
    if (!el) return;
    if (!lines || !lines.length) {
      el.hidden = true;
      el.innerHTML = "";
      return;
    }
    el.hidden = false;
    el.innerHTML = lines
      .map(function (line) {
        var cls =
          line.ok === true
            ? "rekam-bulk__log-item rekam-bulk__log-item--ok"
            : line.ok === false
              ? "rekam-bulk__log-item rekam-bulk__log-item--err"
              : "rekam-bulk__log-item";
        return '<li class="' + cls + '">' + escapeHtml(line.text) + "</li>";
      })
      .join("");
  }

  function runBulkUpload(root, fileList) {
    var files = Array.prototype.slice.call(fileList || [], 0);
    if (!files.length) return Promise.resolve();

    var rows = getRows();
    var logLines = [];
    var okCount = 0;
    var failCount = 0;
    var input = root.querySelector("#rekam-bulk-input");
    var btn = root.querySelector("#rekam-bulk-start");
    if (btn) btn.disabled = true;
    if (input) input.disabled = true;

    setBulkLog(root, []);
    setBulkStatus(root, "Memproses " + files.length + " file…", false);

    var i = 0;
    function next() {
      if (i >= files.length) {
        state.bulkLast = {
          status:
            "Selesai: " + okCount + " berhasil, " + failCount + " gagal dari " + files.length + " file.",
          isError: failCount > 0 && okCount === 0,
          lines: logLines.slice(),
        };
        if (btn) btn.disabled = false;
        if (input) {
          input.disabled = false;
          input.value = "";
        }
        render(root);
        return Promise.resolve();
      }

      var file = files[i];
      var idx = i + 1;
      i += 1;
      var nis = nisFromFilename(file.name);
      setBulkStatus(root, "Mengunggah " + idx + "/" + files.length + ": " + file.name, false);

      if (!nis) {
        failCount += 1;
        logLines.push({ ok: false, text: file.name + " — nama file tidak valid" });
        setBulkLog(root, logLines);
        return next();
      }

      var siswa = findStudentByNis(rows, nis);
      if (!siswa) {
        failCount += 1;
        logLines.push({ ok: false, text: file.name + " — NIS " + nis + " tidak ada di data siswa" });
        setBulkLog(root, logLines);
        return next();
      }

      return fileToJpegDataUrl(file)
        .then(function (dataUrl) {
          return saveFotoToDb(siswa.id || nis, dataUrl, root, { skipRender: true });
        })
        .then(function () {
          okCount += 1;
          logLines.push({
            ok: true,
            text: file.name + " → " + (siswa.nama || "") + " (NIS " + siswa.nis + ")",
          });
          setBulkLog(root, logLines);
        })
        .catch(function (err) {
          failCount += 1;
          logLines.push({
            ok: false,
            text: file.name + " — " + (err && err.message ? err.message : err),
          });
          setBulkLog(root, logLines);
        })
        .then(next);
    }

    return next();
  }

  function hapusFotoFromDb(nis, siswaId, root) {
    if (isRemote()) {
      return window.PresensiRekamService.hapusFoto(nis).then(function (saved) {
        if (saved) {
          saved.fotoWajah = "";
          saved.hasFoto = false;
          patchRowInState(saved);
        } else {
          delete state.detailById[siswaId];
          var ix = state.rows.findIndex(function (r) {
            return r.id === siswaId;
          });
          if (ix >= 0) {
            state.rows[ix].hasFoto = false;
            state.rows[ix].fotoWajah = "";
          }
        }
        if (root) render(root);
      });
    }
    var list = loadRowsLocal(SISWA_KEY);
    var cur = findStudent(list, siswaId);
    if (cur) {
      cur.fotoWajah = "";
      cur.hasFoto = false;
      localStorage.setItem(SISWA_KEY, JSON.stringify(list));
    }
    if (root) render(root);
    return Promise.resolve();
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function normSpeech(s) {
    return String(s || "")
      .trim()
      .toLowerCase()
      .replace(/\s+/g, " ");
  }

  function fotoSizeApprox(dataUrl) {
    if (!dataUrl || typeof dataUrl !== "string") return 0;
    var base64 = dataUrl.split(",")[1];
    if (!base64) return dataUrl.length;
    return Math.floor((base64.length * 3) / 4);
  }

  var MAX_FOTO_BYTES = 750 * 1024;
  var rekamWebcamStream = null;

  function stopRekamWebcam() {
    if (rekamWebcamStream) {
      rekamWebcamStream.getTracks().forEach(function (t) {
        t.stop();
      });
      rekamWebcamStream = null;
    }
  }

  function siswaHasFoto(s) {
    if (!s) return false;
    if (s.hasFoto || s._hasFoto) return true;
    var detail = state.detailById[s.id];
    if (detail && detail.fotoWajah && String(detail.fotoWajah).length > 30) return true;
    return Boolean(s.fotoWajah && String(s.fotoWajah).length > 30);
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
    return s;
  }

  function getFotoDisplay(s) {
    if (!s) return "";
    var detail = state.detailById[s.id];
    if (detail && detail.fotoWajah && String(detail.fotoWajah).length > 20) {
      return normalizeFotoSrc(detail.fotoWajah);
    }
    if (s.fotoWajah && String(s.fotoWajah).length > 20) return normalizeFotoSrc(s.fotoWajah);
    return "";
  }

  function presensiFlags(s) {
    var fotoOk = siswaHasFoto(s);
    return { fotoOk: fotoOk, lengkap: fotoOk, ada: fotoOk };
  }

  function presensiMiniHtml(s) {
    var f = presensiFlags(s);
    var tip = "Foto wajah: " + (f.fotoOk ? "sudah" : "belum");
    return (
      '<span class="siswa-presensi-mini" title="' +
      escapeHtml(tip) +
      '"><span class="' +
      (f.fotoOk ? "siswa-dot siswa-dot--ok" : "siswa-dot") +
      '" title="Foto wajah">F</span></span>'
    );
  }

  function buildRekamLegendHtml(compact) {
    var cls = compact ? "rekam-legend rekam-legend--compact" : "rekam-legend";
    return (
      '<div class="' +
      cls +
      '" role="note"><p class="rekam-legend__title">Keterangan status rekam</p>' +
      '<ul class="rekam-legend__list">' +
      "<li><span class=\"siswa-dot siswa-dot--ok\">F</span> <strong>Foto</strong> — sudah di database</li>" +
      "<li><span class=\"siswa-dot\">F</span> abu-abu = belum ada foto</li>" +
      "</ul></div>"
    );
  }

  function buildSummaryCard(label, hint, value, filterKey, active, extraClass) {
    return (
      '<button type="button" class="siswa-summary__card rekam-summary__card' +
      (extraClass ? " " + extraClass : "") +
      (active ? " rekam-summary__card--active" : "") +
      '" data-rekam-filter="' +
      escapeHtml(filterKey) +
      '"><span class="siswa-summary__label">' +
      escapeHtml(label) +
      '</span><span class="rekam-summary__hint">' +
      escapeHtml(hint) +
      '</span><span class="siswa-summary__value">' +
      escapeHtml(String(value)) +
      "</span></button>"
    );
  }

  function buildRekamFilterChips(filterKey) {
    function chip(key, label) {
      return (
        '<button type="button" class="rekam-filter-chip' +
        (filterKey === key ? " rekam-filter-chip--on" : "") +
        '" data-rekam-filter="' +
        escapeHtml(key) +
        '">' +
        escapeHtml(label) +
        "</button>"
      );
    }
    return (
      '<div class="rekam-filter-chips"><p class="rekam-filter-chips__title">Filter cepat</p>' +
      '<div class="rekam-filter-chip-group"><span class="rekam-filter-chip-group__label">Foto wajah</span>' +
      chip("foto-ada", "Sudah ada") +
      chip("foto-belum", "Belum ada") +
      '</div><div class="rekam-filter-chip-group rekam-filter-chip-group--reset">' +
      chip("all", "Tampilkan semua") +
      "</div></div>"
    );
  }

  function rekamStats(rows) {
    var lengkap = 0;
    var belum = 0;
    (rows || []).forEach(function (s) {
      if (presensiFlags(s).lengkap) lengkap++;
      else belum++;
    });
    return { total: rows.length, lengkap: lengkap, belum: belum };
  }

  function filterSiswa(rows, q) {
    var t = normSpeech(q).replace(/\s/g, "");
    if (!t) return rows.slice();
    return rows.filter(function (s) {
      var nama = normSpeech(s.nama).replace(/\s/g, "");
      var nis = String(s.nis || "")
        .trim()
        .toLowerCase();
      return nama.indexOf(t) !== -1 || nis.indexOf(t) !== -1;
    });
  }

  var REKAM_FILTER_OPTIONS = [
    { value: "all", label: "Semua siswa (tanpa filter)" },
    { value: "lengkap", label: "Sudah ada foto wajah" },
    { value: "belum", label: "Belum ada foto wajah" },
    { value: "foto-ada", label: "Sudah ada foto" },
    { value: "foto-belum", label: "Belum ada foto" },
  ];

  function getRekamFilterKey(root) {
    return (root && root.dataset.rekamFilterValue) || "all";
  }

  function setRekamFilterKey(root, key) {
    if (root) root.dataset.rekamFilterValue = key || "all";
  }

  function rekamFilterLabel(key) {
    var opt = REKAM_FILTER_OPTIONS.find(function (o) {
      return o.value === key;
    });
    return opt ? opt.label : "Semua siswa";
  }

  function matchesRekamFilter(s, key) {
    var f = presensiFlags(s);
    switch (key) {
      case "lengkap":
      case "foto-ada":
        return f.fotoOk;
      case "belum":
      case "foto-belum":
        return !f.fotoOk;
      default:
        return true;
    }
  }

  /** Filter kartu Sudah/Belum foto: lintas semua kelas (tanpa gate unit/kelas). */
  function isPhotoScopeFilter(key) {
    return (
      key === "lengkap" ||
      key === "foto-ada" ||
      key === "belum" ||
      key === "foto-belum"
    );
  }

  function filterByRekam(rows, key) {
    if (!key || key === "all") return rows.slice();
    return rows.filter(function (s) {
      return matchesRekamFilter(s, key);
    });
  }

  function getFilteredList(rows, root) {
    var searchQ = root.dataset.searchQ || "";
    var filterKey = getRekamFilterKey(root);
    var photoScope = isPhotoScopeFilter(filterKey);
    var dimRows = rows;

    if (window.PresensiSiswaFilter) {
      var sel = window.PresensiSiswaFilter.read(root);
      var gateOpen =
        !window.PresensiSiswaFilter.needsGate(rows.length) ||
        window.PresensiSiswaFilter.isOpen(sel, searchQ) ||
        photoScope;
      if (!gateOpen) return [];
      // Sudah/Belum foto: tampilkan semua kelas; filter unit/kelas diabaikan
      dimRows = photoScope
        ? rows.slice()
        : window.PresensiSiswaFilter.apply(rows, sel);
    }

    return filterByRekam(filterSiswa(dimRows, searchQ), filterKey);
  }

  function buildRekamFilterSelect(current) {
    return (
      '<div class="rekam-filter-select-wrap"><label class="settings-form__label" for="rekam-filter">Tampilkan siswa</label>' +
      '<select id="rekam-filter" class="settings-form__input rekam-filter-select">' +
      REKAM_FILTER_OPTIONS.map(function (o) {
        return (
          '<option value="' +
          escapeHtml(o.value) +
          '"' +
          (current === o.value ? " selected" : "") +
          ">" +
          escapeHtml(o.label) +
          "</option>"
        );
      }).join("") +
      "</select></div>"
    );
  }

  function bindSearchFocus(root) {
    if (root.dataset.searchFocus !== "1") return;
    root.dataset.searchFocus = "";
    var inp = root.querySelector("#rekam-search");
    if (!inp) return;
    var len = inp.value.length;
    try {
      inp.focus();
      inp.setSelectionRange(len, len);
    } catch (e) {}
  }

  function render(root) {
    stopRekamWebcam();
    var rows = getRows();

    if (isRemote() && !state.dbLoaded) {
      root.innerHTML =
        '<header class="page-head"><h1>Rekam data</h1><p>Memuat daftar siswa…</p></header>';
      return;
    }

    if (!rows.length) {
      root.innerHTML =
        '<header class="page-head"><h1>Rekam data</h1>' +
        (isRemote()
          ? "<p>Belum ada siswa. Sinkron di menu Data siswa.</p>"
          : "<p>Belum ada data siswa.</p>") +
        '</header><p class="rekam-data__empty-actions"><a class="btn btn--primary" href="data-siswa.html">Buka Data siswa</a></p>';
      return;
    }

    var params = new URLSearchParams(window.location.search);
    var fromUrl = params.get("siswa");
    if (fromUrl && root.dataset.handledUrlSiswa !== fromUrl) {
      root.dataset.handledUrlSiswa = fromUrl;
      if (findStudent(rows, fromUrl)) {
        root.dataset.selectedId = fromUrl;
        root.dataset.rekamSyncPageTo = fromUrl;
        window.history.replaceState({}, "", "rekam-data.html?siswa=" + encodeURIComponent(fromUrl));
      } else {
        root.dataset.selectedId = "";
        window.history.replaceState({}, "", "rekam-data.html");
      }
    }

    var searchQ = root.dataset.searchQ || "";
    var filterKey = getRekamFilterKey(root);
    if (!root.dataset.rekamFilterValue) setRekamFilterKey(root, "all");
    var hasFilterMod = Boolean(window.PresensiSiswaFilter);
    var dimSel = hasFilterMod
      ? window.PresensiSiswaFilter.read(root)
      : { unit: "", kelompok: "", kelas: "", tahun: "" };
    var photoScope = isPhotoScopeFilter(filterKey);
    var gateOpen =
      !hasFilterMod ||
      !window.PresensiSiswaFilter.needsGate(rows.length) ||
      window.PresensiSiswaFilter.isOpen(dimSel, searchQ) ||
      photoScope;
    var dimRows =
      hasFilterMod && !photoScope
        ? window.PresensiSiswaFilter.apply(rows, dimSel)
        : rows;
    var filtered = gateOpen ? filterByRekam(filterSiswa(dimRows, searchQ), filterKey) : [];
    var total = filtered.length;
    var totalAll = rows.length;
    var stats = rekamStats(rows);

    var syncPageTo = root.dataset.rekamSyncPageTo;
    if (syncPageTo) {
      var syncIx = filtered.findIndex(function (r) {
        return r.id === syncPageTo;
      });
      if (syncIx >= 0) state.page = Math.floor(syncIx / state.pageSize) + 1;
      delete root.dataset.rekamSyncPageTo;
    }

    var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > totalPages) state.page = totalPages;
    var start = (state.page - 1) * state.pageSize;
    var pageRows = filtered.slice(start, start + state.pageSize);

    var selectedId = root.dataset.selectedId || "";
    var sel = findStudent(rows, selectedId);
    if (!sel) {
      selectedId = "";
      root.dataset.selectedId = "";
    }

    var tableRows;
    if (!gateOpen) {
      tableRows = window.PresensiSiswaFilter.gateRowHtml(4);
    } else if (!filtered.length) {
      tableRows =
        '<tr><td colspan="4" class="data-table__empty">Tidak ada siswa yang cocok dengan filter/pencarian.</td></tr>';
    } else {
      tableRows = pageRows
        .map(function (s) {
          var isSel = selectedId && s.id === selectedId;
          return (
            "<tr class='" +
            (isSel ? "rekam-table__row--selected " : "") +
            "rekam-table__row'><td>" +
            escapeHtml(s.nis) +
            "</td><td>" +
            escapeHtml(s.nama) +
            "</td><td class='siswa-rekam-cell'>" +
            presensiMiniHtml(s) +
            "</td><td class='data-table__actions'>" +
            '<button type="button" class="btn btn--primary btn--small" data-rekam-pilih="' +
            escapeHtml(s.id) +
            '">' +
            (isSel ? "Dipilih" : "Pilih") +
            "</button></td></tr>"
          );
        })
        .join("");
    }

    var pageInfo =
      !gateOpen
        ? "Pilih filter dulu (total " + totalAll + ")"
        : total === 0
        ? "0 data"
        : start +
          1 +
          "–" +
          Math.min(start + state.pageSize, total) +
          " dari " +
          total +
          (photoScope
            ? " · " + rekamFilterLabel(filterKey) + " (semua kelas)"
            : searchQ
            ? " (filter, total " + totalAll + ")"
            : "");

    var mappingSection = "";
    if (sel) {
      var fotoVal = getFotoDisplay(sel);
      var hasFoto = siswaHasFoto(sel);
      var fotoLoading = Boolean(state.detailLoading[sel.id]);
      var fotoError = state.detailErrors[sel.id] || "";
      var previewHtml;
      if (fotoError) {
        previewHtml =
          '<div class="rekam-foto__placeholder rekam-foto__placeholder--error">Gagal memuat foto: ' +
          escapeHtml(fotoError) +
          '</div><button type="button" class="btn btn--ghost btn--small" id="rekam-foto-retry">Coba lagi</button>';
      } else if (fotoVal) {
        previewHtml =
          '<div class="rekam-foto__preview-wrap"><img id="rekam-foto-preview" class="rekam-foto__preview" alt="Pratinjau wajah" /></div>';
      } else if (hasFoto && fotoLoading) {
        previewHtml = '<div class="rekam-foto__placeholder">Memuat foto dari database…</div>';
      } else if (hasFoto) {
        previewHtml = '<div class="rekam-foto__placeholder">Memuat pratinjau dari database…</div>';
      } else {
        previewHtml = '<div class="rekam-foto__placeholder">Belum ada foto tersimpan</div>';
      }

      mappingSection =
        '<div class="module-shell rekam-mapping-intro"><h3 class="rekam-mapping-intro__title">' +
        escapeHtml(sel.nama) +
        "</h3>" +
        "<p class='rekam-mapping-intro__meta'>NIS " +
        escapeHtml(sel.nis) +
        " · " +
        presensiMiniHtml(sel) +
        "</p></div>" +
        '<div class="rekam-data__grid"><section class="module-shell rekam-card">' +
        '<h3 class="rekam-card__title">Foto wajah</h3>' +
        "<p class=\"rekam-card__desc\">Rekam dari kamera, lalu simpan.</p>" +
        '<div class="rekam-webcam"><div class="rekam-webcam__wrap">' +
        '<video id="rekam-webcam-video" class="rekam-webcam__video" playsinline muted></video>' +
        '<div id="rekam-webcam-overlay" class="rekam-webcam__overlay">Tekan «Mulai kamera»</div></div>' +
        '<canvas id="rekam-webcam-canvas" hidden></canvas>' +
        '<div class="rekam-webcam__actions">' +
        '<button type="button" class="btn btn--primary" id="rekam-webcam-start">Mulai kamera</button>' +
        '<button type="button" class="btn btn--ghost" id="rekam-webcam-stop" hidden>Stop</button>' +
        '<button type="button" class="btn btn--ghost" id="rekam-webcam-flip" hidden title="Tukar kamera depan / belakang">Ganti kamera</button>' +
        '<button type="button" class="btn btn--primary" id="rekam-webcam-capture" hidden>Rekam foto</button>' +
        "</div>" +
        '<p id="rekam-webcam-status" class="rekam-webcam__status" aria-live="polite"></p></div>' +
        '<div class="rekam-foto">' +
        previewHtml +
        (hasFoto
          ? '<button type="button" class="btn btn--ghost btn--small" id="rekam-foto-hapus">Hapus foto</button>'
          : "") +
        "</div></section></div>";
    } else {
      mappingSection =
        '<div class="module-shell rekam-mapping-placeholder"><p>Pilih siswa di daftar untuk merekam foto.</p></div>';
    }

    root.innerHTML =
      '<header class="page-head"><h1>Rekam data</h1>' +
      "<p>Pilih siswa untuk rekam kamera, atau unggah banyak foto sekaligus (nama file = NIS).</p>" +
      '<p id="rekam-sync-status" hidden class="settings-form__hint"></p></header>' +
      '<div class="siswa-summary rekam-summary">' +
      buildSummaryCard("Semua", "", stats.total, "all", filterKey === "all", "") +
      buildSummaryCard("Sudah foto", "", stats.lengkap, "lengkap", filterKey === "lengkap", "siswa-summary__card--ok") +
      buildSummaryCard("Belum foto", "", stats.belum, "belum", filterKey === "belum", "siswa-summary__card--muted") +
      "</div>" +
      '<section class="module-shell rekam-bulk" aria-labelledby="rekam-bulk-heading">' +
      '<div class="rekam-bulk__head">' +
      '<div class="rekam-bulk__head-text">' +
      '<p class="rekam-bulk__eyebrow">Impor massal</p>' +
      '<h3 id="rekam-bulk-heading" class="rekam-bulk__title">Upload foto serentak</h3>' +
      '<p class="rekam-bulk__desc">Satu file gambar per siswa. Nama file = NIS agar langsung terhubung ke data siswa.</p>' +
      "</div>" +
      '<ol class="rekam-bulk__steps">' +
      "<li><span>1</span> Namai file dengan NIS</li>" +
      "<li><span>2</span> Pilih banyak file</li>" +
      "<li><span>3</span> Unggah</li>" +
      "</ol>" +
      "</div>" +
      '<div class="rekam-bulk__drop">' +
      '<input type="file" id="rekam-bulk-input" class="rekam-bulk__input" accept="image/jpeg,image/png,image/webp,image/jpg" multiple />' +
      '<label for="rekam-bulk-input" class="rekam-bulk__drop-label">' +
      '<span class="rekam-bulk__drop-icon" aria-hidden="true">' +
      '<svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 16V4m0 0l-4 4m4-4l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 16.5V18a2 2 0 002 2h12a2 2 0 002-2v-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' +
      "</span>" +
      '<span class="rekam-bulk__drop-title">Pilih file foto</span>' +
      '<span class="rekam-bulk__drop-hint">JPG, PNG, atau WEBP · bisa banyak sekaligus · contoh <code>2024001.jpg</code></span>' +
      "</label>" +
      "</div>" +
      '<div class="rekam-bulk__footer">' +
      '<div id="rekam-bulk-picked" class="rekam-bulk__picked" hidden></div>' +
      '<button type="button" class="btn btn--primary rekam-bulk__submit" id="rekam-bulk-start" disabled>Unggah ke data siswa</button>' +
      "</div>" +
      '<p id="rekam-bulk-status" class="rekam-bulk__status" hidden aria-live="polite"></p>' +
      '<ul id="rekam-bulk-log" class="rekam-bulk__log" hidden></ul>' +
      "</section>" +
      '<div class="module-shell rekam-toolbar">' +
      '<div class="rekam-toolbar__row">' +
      '<label class="rekam-toolbar__per" for="rekam-page-size">Baris' +
      '<select id="rekam-page-size" class="settings-form__input siswa-toolbar__select">' +
      [5, 8, 10, 20]
        .map(function (n) {
          return (
            "<option value='" +
            n +
            "'" +
            (state.pageSize === n ? " selected" : "") +
            ">" +
            n +
            "</option>"
          );
        })
        .join("") +
      "</select></label>" +
      '<div class="rekam-toolbar__actions">' +
      (isRemote()
        ? '<button type="button" class="btn btn--ghost btn--small" id="rekam-refresh-db">Muat ulang</button>'
        : "") +
      "</div></div></div>" +
      (hasFilterMod ? window.PresensiSiswaFilter.controlsHtml(rows, dimSel, "rekam") : "") +
      '<div class="module-shell rekam-search-panel">' +
      '<label class="settings-form__label" for="rekam-search">Cari</label>' +
      '<input type="search" id="rekam-search" class="settings-form__input rekam-search-input" placeholder="Nama atau NIS…" value="' +
      escapeHtml(searchQ) +
      '" /><p class="rekam-search-meta">' +
      pageInfo +
      "</p></div>" +
      '<div class="module-shell data-settings__table-shell">' +
      '<div class="table-wrap"><table class="data-table rekam-search-table">' +
      "<thead><tr><th>NIS</th><th>Nama</th><th>Status</th><th class='data-table__actions'>Aksi</th></tr></thead><tbody>" +
      tableRows +
      "</tbody></table></div>" +
      '<div class="siswa-pagination"><span class="siswa-pagination__info">' +
      escapeHtml(pageInfo) +
      '</span><div class="siswa-pagination__nav">' +
      '<button type="button" class="btn btn--ghost btn--small" id="rekam-prev"' +
      (state.page <= 1 ? " disabled" : "") +
      ">Sebelumnya</button><span class=\"siswa-pagination__page\">" +
      state.page +
      " / " +
      totalPages +
      '</span><button type="button" class="btn btn--ghost btn--small" id="rekam-next"' +
      (state.page >= totalPages ? " disabled" : "") +
      ">Berikutnya</button></div></div></div>" +
      mappingSection;

    bindSearchFocus(root);

    root.querySelectorAll("[data-rekam-pilih]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-rekam-pilih");
        root.dataset.selectedId = id;
        root.dataset.handledUrlSiswa = id;
        root.dataset.rekamSyncPageTo = id;
        window.history.replaceState({}, "", "rekam-data.html?siswa=" + encodeURIComponent(id));
        render(root);
      });
    });

    var refreshBtn = root.querySelector("#rekam-refresh-db");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", function () {
        state.detailById = {};
        state.detailErrors = {};
        loadListFromDb(root).then(function () {
          render(root);
        });
      });
    }

    var bulkInput = root.querySelector("#rekam-bulk-input");
    var bulkStart = root.querySelector("#rekam-bulk-start");
    var bulkDrop = root.querySelector(".rekam-bulk__drop");
    var pendingBulkFiles = null;

    function applyBulkFiles(fileList) {
      pendingBulkFiles = fileList && fileList.length ? fileList : null;
      if (bulkStart) bulkStart.disabled = !pendingBulkFiles;
      renderBulkPicked(root, pendingBulkFiles);
      state.bulkLast = null;
      setBulkStatus(root, "", false);
      setBulkLog(root, []);
    }

    if (bulkInput && bulkStart) {
      bulkInput.addEventListener("change", function () {
        applyBulkFiles(bulkInput.files);
      });
      bulkStart.addEventListener("click", function () {
        if (!pendingBulkFiles || !pendingBulkFiles.length) return;
        if (
          !confirm(
            "Unggah " +
              pendingBulkFiles.length +
              " foto? Nama file harus NIS. Foto yang sudah ada akan diganti."
          )
        ) {
          return;
        }
        runBulkUpload(root, pendingBulkFiles);
      });
    }

    if (bulkDrop && bulkInput) {
      ["dragenter", "dragover"].forEach(function (ev) {
        bulkDrop.addEventListener(ev, function (e) {
          e.preventDefault();
          e.stopPropagation();
          bulkDrop.classList.add("rekam-bulk__drop--active");
        });
      });
      ["dragleave", "drop"].forEach(function (ev) {
        bulkDrop.addEventListener(ev, function (e) {
          e.preventDefault();
          e.stopPropagation();
          bulkDrop.classList.remove("rekam-bulk__drop--active");
        });
      });
      bulkDrop.addEventListener("drop", function (e) {
        var dt = e.dataTransfer;
        if (!dt || !dt.files || !dt.files.length) return;
        try {
          var transfer = new DataTransfer();
          Array.prototype.forEach.call(dt.files, function (f) {
            if (String(f.type || "").indexOf("image/") === 0) transfer.items.add(f);
          });
          if (!transfer.files.length) return;
          bulkInput.files = transfer.files;
          applyBulkFiles(bulkInput.files);
        } catch (err) {
          applyBulkFiles(dt.files);
        }
      });
    }

    if (state.bulkLast) {
      setBulkStatus(root, state.bulkLast.status, state.bulkLast.isError);
      setBulkLog(root, state.bulkLast.lines);
    }

    if (!sel) return;

    if (siswaHasFoto(sel) && !getFotoDisplay(sel) && !state.detailErrors[sel.id]) {
      fetchDetailFromDb(sel, root);
    }

    var previewImg = root.querySelector("#rekam-foto-preview");
    if (previewImg) {
      var fotoSrc = getFotoDisplay(sel);
      if (fotoSrc) {
        previewImg.onerror = function () {
          previewImg.onerror = null;
          state.detailErrors[sel.id] = "Pratinjau foto tidak dapat ditampilkan.";
          render(root);
        };
        previewImg.src = fotoSrc;
      }
    }

    var retryFoto = root.querySelector("#rekam-foto-retry");
    if (retryFoto) {
      retryFoto.addEventListener("click", function () {
        delete state.detailErrors[sel.id];
        delete state.detailById[sel.id];
        fetchDetailFromDb(sel, root);
      });
    }

    var selectedIdCapture = sel.id;

    function setWebcamStatus(msg) {
      var el = root.querySelector("#rekam-webcam-status");
      if (el) el.textContent = msg || "";
    }

    var wVid = root.querySelector("#rekam-webcam-video");
    var wOver = root.querySelector("#rekam-webcam-overlay");
    var wStart = root.querySelector("#rekam-webcam-start");
    var wStop = root.querySelector("#rekam-webcam-stop");
    var wFlip = root.querySelector("#rekam-webcam-flip");
    var wCap = root.querySelector("#rekam-webcam-capture");
    var wCan = root.querySelector("#rekam-webcam-canvas");

    function syncRekamFlipLabel() {
      if (!wFlip || typeof window.presensiGetFacingMode !== "function") return;
      var back = window.presensiGetFacingMode() === "environment";
      wFlip.textContent = back ? "Pakai kamera depan" : "Pakai kamera belakang";
      wFlip.title = "Saat ini: " + (back ? "kamera belakang" : "kamera depan");
    }

    if (wStart && wVid && typeof window.presensiOpenWebcam === "function") {
      wStart.addEventListener("click", function () {
        window.presensiOpenWebcam(wVid).then(function (stream) {
          rekamWebcamStream = stream;
          if (wOver) wOver.hidden = true;
          wStart.hidden = true;
          if (wStop) wStop.hidden = false;
          if (wFlip) wFlip.hidden = false;
          if (wCap) wCap.hidden = false;
          syncRekamFlipLabel();
          setWebcamStatus("Kamera aktif — tekan Rekam foto.");
        }).catch(function (err) {
          setWebcamStatus("Kamera gagal: " + (err.message || err));
        });
      });
    } else if (wStart) {
      wStart.disabled = true;
    }

    if (wFlip && wVid && typeof window.presensiSwitchCamera === "function") {
      wFlip.addEventListener("click", function () {
        if (!rekamWebcamStream) return;
        setWebcamStatus("Mengganti kamera…");
        window
          .presensiSwitchCamera(wVid)
          .then(function (stream) {
            rekamWebcamStream = stream;
            syncRekamFlipLabel();
            var label =
              typeof window.presensiFacingLabel === "function"
                ? window.presensiFacingLabel()
                : "kamera";
            setWebcamStatus("Kamera aktif (" + label + ") — tekan Rekam foto.");
          })
          .catch(function (err) {
            setWebcamStatus("Gagal ganti kamera: " + (err.message || err));
          });
      });
    }

    if (wStop && wVid && wStart && wCap) {
      wStop.addEventListener("click", function () {
        stopRekamWebcam();
        if (typeof window.presensiStopWebcam === "function") {
          window.presensiStopWebcam(wVid);
        } else {
          wVid.srcObject = null;
        }
        if (wOver) wOver.hidden = false;
        wStart.hidden = false;
        wStop.hidden = true;
        if (wFlip) wFlip.hidden = true;
        wCap.hidden = true;
      });
    }

    if (wCap && wVid && wCan) {
      wCap.addEventListener("click", function () {
        var vw = wVid.videoWidth;
        var vh = wVid.videoHeight;
        if (!vw || !vh) {
          alert("Video belum siap.");
          return;
        }
        wCan.width = vw;
        wCan.height = vh;
        wCan.getContext("2d").drawImage(wVid, 0, 0, vw, vh);
        var dataUrl = wCan.toDataURL("image/jpeg", 0.88);
        if (fotoSizeApprox(dataUrl) > MAX_FOTO_BYTES) {
          alert("Foto terlalu besar (maks. ±750 KB).");
          return;
        }
        wCap.disabled = true;
        saveFotoToDb(selectedIdCapture, dataUrl, root)
          .then(function () {
            stopRekamWebcam();
            wVid.srcObject = null;
            alert("Foto tersimpan ke database.");
          })
          .catch(function (e) {
            alert("Gagal simpan: " + (e.message || e));
          })
          .finally(function () {
            wCap.disabled = false;
          });
      });
    }

    var hapusFoto = root.querySelector("#rekam-foto-hapus");
    if (hapusFoto) {
      hapusFoto.addEventListener("click", function () {
        if (!confirm("Hapus foto wajah " + sel.nama + " dari database?")) return;
        hapusFotoFromDb(sel.nis, sel.id, root).catch(function (e) {
          alert("Gagal hapus: " + (e.message || e));
        });
      });
    }
  }

  function bindRekamEvents(root) {
    if (root.dataset.rekamEventsBound) return;
    root.dataset.rekamEventsBound = "1";

    root.addEventListener("input", function (e) {
      if (e.target.id === "rekam-search") {
        root.dataset.searchQ = e.target.value;
        root.dataset.searchFocus = "1";
        state.page = 1;
        render(root);
      }
    });

    root.addEventListener("change", function (e) {
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleChange(root, e.target, "rekam")) {
        state.page = 1;
        render(root);
        return;
      }
      if (e.target.id === "rekam-page-size") {
        state.pageSize = parseInt(e.target.value, 10) || 8;
        state.page = 1;
        render(root);
      }
      if (e.target.id === "rekam-filter") {
        setRekamFilterKey(root, e.target.value || "all");
        state.page = 1;
        render(root);
      }
    });

    root.addEventListener("click", function (e) {
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleClick(root, e.target, "rekam")) {
        state.page = 1;
        render(root);
        return;
      }
      var filterBtn = e.target.closest("button[data-rekam-filter]");
      if (filterBtn && root.contains(filterBtn)) {
        setRekamFilterKey(root, filterBtn.getAttribute("data-rekam-filter") || "all");
        state.page = 1;
        render(root);
        return;
      }
      if (e.target.closest("#rekam-prev")) {
        state.page = Math.max(1, state.page - 1);
        render(root);
        return;
      }
      if (e.target.closest("#rekam-next")) {
        var filtered = getFilteredList(getRows(), root);
        state.page = Math.min(Math.max(1, Math.ceil(filtered.length / state.pageSize)), state.page + 1);
        render(root);
      }
    });
  }

  function init() {
    var root = document.getElementById("rekam-root");
    if (!root) return;
    if (!root.dataset.rekamBound) {
      root.dataset.rekamBound = "1";
      bindRekamEvents(root);
      window.addEventListener("beforeunload", stopRekamWebcam);
    }

    var hasCache = hydrateFromCache();
    if (hasCache) {
      render(root);
    } else if (window.PresensiLoading) {
      window.PresensiLoading.show(root, "Memuat dari database…");
    }

    loadListFromDb(root)
      .then(function () {
        render(root);
      })
      .finally(function () {
        if (window.PresensiLoading) window.PresensiLoading.hide(root);
      });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
