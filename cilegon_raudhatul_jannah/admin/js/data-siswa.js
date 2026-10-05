(function () {
  var SISWA_KEY = "presensi_data_siswa";
  var KELAS_KEY = "presensi_setting_kelas";
  var JENJANG_KEY = "presensi_setting_jenjang";
  var UNIT_KEY = "presensi_setting_unit";

  var state = {
    page: 1,
    pageSize: 8,
    editingId: null,
    modalOpen: false,
    dbLoaded: false,
    /** Baris siswa terakhir dari API (utama untuk tampilan, hindari cache localStorage stale) */
    siswaRows: null,
    /** Unit dari MobileMerchant UnitRequest untuk sinkron */
    syncUnits: [],
    syncUnitsLoaded: false,
    syncUnitsLoading: false,
    syncUnitsError: "",
    syncKodeUnit: "",
  };

  function syncModalScrollLock() {
    document.body.classList.toggle(
      "modal-scroll-lock",
      Boolean(document.querySelector(".modal.modal--open"))
    );
  }

  function closeSiswaModal() {
    state.modalOpen = false;
    state.editingId = null;
  }

  var SEED = [
    {
      nis: "20241001",
      nisn: "0012345678",
      nama: "Ahmad Fauzi Rahman",
      kelasId: "seed-k1",
      jenisKelamin: "L",
      telepon: "081234560001",
      aktif: true,
      rfidUid: "E004D21A890C",
      fotoWajah: "",
      kodeSuara: "hadir ahmad fauzi",
    },
    {
      nis: "20241002",
      nisn: "0012345679",
      nama: "Siti Nurhaliza",
      kelasId: "seed-k1",
      jenisKelamin: "P",
      telepon: "081234560002",
      aktif: true,
      rfidUid: "04B127CF91DD",
      fotoWajah: "",
      kodeSuara: "absensi siti",
    },
    {
      nis: "20241003",
      nisn: "0012345680",
      nama: "Bima Sakti Pratama",
      kelasId: "seed-k2",
      jenisKelamin: "L",
      telepon: "081234560003",
      aktif: true,
      rfidUid: "",
      fotoWajah: "",
      kodeSuara: "",
    },
    {
      nis: "20241004",
      nisn: "0012345681",
      nama: "Dewi Lestari Sari",
      kelasId: "seed-k2",
      jenisKelamin: "P",
      telepon: "",
      aktif: true,
    },
    {
      nis: "20241005",
      nisn: "0012345682",
      nama: "Eko Wijaya",
      kelasId: "seed-k3",
      jenisKelamin: "L",
      telepon: "081234560005",
      aktif: false,
    },
    {
      nis: "20241006",
      nisn: "0012345683",
      nama: "Fitri Handayani",
      kelasId: "seed-k3",
      jenisKelamin: "P",
      telepon: "081234560006",
      aktif: true,
    },
    {
      nis: "20241007",
      nisn: "0012345684",
      nama: "Gilang Ramadhan",
      kelasId: "seed-k4",
      jenisKelamin: "L",
      telepon: "081234560007",
      aktif: true,
    },
    {
      nis: "20241008",
      nisn: "0012345685",
      nama: "Hana Permatasari",
      kelasId: "seed-k4",
      jenisKelamin: "P",
      telepon: "081234560008",
      aktif: true,
    },
    {
      nis: "20241009",
      nisn: "0012345686",
      nama: "Irfan Maulana",
      kelasId: "seed-k5",
      jenisKelamin: "L",
      telepon: "",
      aktif: true,
    },
    {
      nis: "20241010",
      nisn: "0012345687",
      nama: "Jessica Putri",
      kelasId: "seed-k5",
      jenisKelamin: "P",
      telepon: "081234560010",
      aktif: true,
    },
    {
      nis: "20241011",
      nisn: "0012345688",
      nama: "Kevin Aditya",
      kelasId: "seed-k6",
      jenisKelamin: "L",
      telepon: "081234560011",
      aktif: false,
    },
    {
      nis: "20241012",
      nisn: "0012345689",
      nama: "Lina Marlina",
      kelasId: "seed-k6",
      jenisKelamin: "P",
      telepon: "081234560012",
      aktif: true,
    },
    {
      nis: "20241013",
      nisn: "0012345690",
      nama: "Muhammad Rizki",
      kelasId: "seed-k1",
      jenisKelamin: "L",
      telepon: "081234560013",
      aktif: true,
    },
    {
      nis: "20241014",
      nisn: "0012345691",
      nama: "Nadia Safitri",
      kelasId: "seed-k2",
      jenisKelamin: "P",
      telepon: "081234560014",
      aktif: true,
    },
    {
      nis: "20241015",
      nisn: "0012345692",
      nama: "Omar Hakim",
      kelasId: "seed-k3",
      jenisKelamin: "L",
      telepon: "",
      aktif: true,
    },
    {
      nis: "20241016",
      nisn: "0012345693",
      nama: "Putri Ayu",
      kelasId: "seed-k4",
      jenisKelamin: "P",
      telepon: "081234560016",
      aktif: true,
    },
    {
      nis: "20241017",
      nisn: "0012345694",
      nama: "Qori Sandria",
      kelasId: "seed-k5",
      jenisKelamin: "P",
      telepon: "081234560017",
      aktif: true,
    },
    {
      nis: "20241018",
      nisn: "0012345695",
      nama: "Raka Pradana",
      kelasId: "seed-k6",
      jenisKelamin: "L",
      telepon: "081234560018",
      aktif: true,
    },
    {
      nis: "20241019",
      nisn: "0012345696",
      nama: "Salsa Bilqis",
      kelasId: "seed-k1",
      jenisKelamin: "P",
      telepon: "081234560019",
      aktif: false,
    },
    {
      nis: "20241020",
      nisn: "0012345697",
      nama: "Taufik Hidayat",
      kelasId: "seed-k2",
      jenisKelamin: "L",
      telepon: "081234560020",
      aktif: true,
    },
    {
      nis: "20241021",
      nisn: "0012345698",
      nama: "Umi Kalsum",
      kelasId: "seed-k3",
      jenisKelamin: "P",
      telepon: "",
      aktif: true,
    },
    {
      nis: "20241022",
      nisn: "0012345699",
      nama: "Vino Bastian",
      kelasId: "seed-k4",
      jenisKelamin: "L",
      telepon: "081234560022",
      aktif: true,
    },
    {
      nis: "20241023",
      nisn: "0012345700",
      nama: "Winda Sari",
      kelasId: "seed-k5",
      jenisKelamin: "P",
      telepon: "081234560023",
      aktif: true,
    },
    {
      nis: "20241024",
      nisn: "0012345701",
      nama: "Yoga Pratama",
      kelasId: "seed-k6",
      jenisKelamin: "L",
      telepon: "081234560024",
      aktif: true,
    },
  ];

  function uid() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) return crypto.randomUUID();
    return "id-" + Date.now() + "-" + Math.random().toString(16).slice(2);
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function normSearch(s) {
    return String(s || "")
      .trim()
      .toLowerCase()
      .replace(/\s+/g, " ");
  }

  function filterSiswa(rows, q) {
    var t = normSearch(q);
    if (!t) return rows.slice();
    return rows.filter(function (s) {
      var nama = normSearch(s.nama);
      var nis = String(s.nis || "")
        .trim()
        .toLowerCase();
      return nama.indexOf(t) !== -1 || nis.indexOf(t) !== -1;
    });
  }

  function getFilteredSiswa(root) {
    var all = getDisplaySiswaRows();
    var searchQ = (root && root.dataset.searchQ) || "";
    if (window.PresensiSiswaFilter) {
      var sel = window.PresensiSiswaFilter.read(root);
      var gateOpen =
        !window.PresensiSiswaFilter.needsGate(all.length) ||
        window.PresensiSiswaFilter.isOpen(sel, searchQ);
      if (!gateOpen) return [];
      all = window.PresensiSiswaFilter.apply(all, sel);
    }
    return filterSiswa(all, searchQ);
  }

  function bindSiswaSearchFocus(root) {
    if (!root || root.dataset.searchFocus !== "1") return;
    var inp = root.querySelector("#siswa-search");
    if (!inp) return;
    var q = root.dataset.searchQ || "";
    inp.value = q;
    var len = inp.value.length;
    var restore = function () {
      try {
        inp.focus();
        inp.setSelectionRange(len, len);
      } catch (eFocus) {
        /* IE */
      }
    };
    if (typeof requestAnimationFrame === "function") {
      requestAnimationFrame(restore);
    } else {
      restore();
    }
  }

  function loadRows(key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return null;
      var data = JSON.parse(raw);
      return Array.isArray(data) ? data : null;
    } catch (e) {
      return null;
    }
  }

  function saveRows(key, rows) {
    try {
      localStorage.setItem(key, JSON.stringify(rows));
    } catch (e) {
      if (key === SISWA_KEY && window.PresensiData && window.PresensiData.writeSiswaCache) {
        window.PresensiData.writeSiswaCache(rows);
      }
    }
  }

  /** Baris siswa untuk tabel: utamakan data API in-memory, bukan cache localStorage lama. */
  function getDisplaySiswaRows() {
    if (window.PresensiData && window.PresensiData.isRemote()) {
      if (state.siswaRows && state.siswaRows.length) {
        return state.siswaRows;
      }
      if (!state.dbLoaded) {
        return [];
      }
    }
    return loadSiswa();
  }

  function indexById(rows) {
    var m = {};
    (rows || []).forEach(function (r) {
      m[r.id] = r;
    });
    return m;
  }

  function ensureKelasSeeded() {
    return loadRows(KELAS_KEY) || [];
  }

  function migrateSiswaFields(rows) {
    var changed = false;
    rows.forEach(function (r) {
      if (r.rfidUid === undefined) {
        r.rfidUid = "";
        changed = true;
      }
      if (r.fotoWajah === undefined) {
        r.fotoWajah = "";
        changed = true;
      }
      if (r.hasFoto == null && r._hasFoto == null && r.fotoWajah && String(r.fotoWajah).length > 30) {
        r.hasFoto = true;
        r._hasFoto = true;
        changed = true;
      } else if (r.hasFoto != null || r._hasFoto != null) {
        var hf = Boolean(r.hasFoto || r._hasFoto);
        if (r.hasFoto !== hf || r._hasFoto !== hf) {
          r.hasFoto = hf;
          r._hasFoto = hf;
          changed = true;
        }
      }
      if (r.kodeSuara === undefined) {
        r.kodeSuara = "";
        changed = true;
      }
      var before = JSON.stringify(r);
      enrichSieFields(r);
      if (JSON.stringify(r) !== before) changed = true;
    });
    return changed;
  }

  /** Isi field SIE dari cache lama (kelasId → desc03, dll.) */
  function enrichSieFields(r) {
    if (!r || typeof r !== "object") return r;
    var merchantKeys = [
      "num2nd", "stcust", "code01", "desc01", "code02", "desc02",
      "code03", "desc03", "code04", "desc04", "code05", "desc05",
      "totpay", "genus",
    ];
    merchantKeys.forEach(function (k) {
      if (r[k] === undefined) r[k] = "";
    });
    var kelasId = pickSiswaField(r, ["kelasId", "kelas_id"]);
    if (!pickSiswaField(r, ["desc03"]) && kelasId && kelasId.indexOf("seed-") !== 0) {
      r.desc03 = kelasId;
    }
    return r;
  }

  /** Data contoh lama (localStorage) — bukan dari database/API */
  function isLegacyDemoSiswa(rows) {
    return (rows || []).some(function (r) {
      return (
        String(r.kelasId || "").indexOf("seed-") === 0 ||
        String(r.nis || "").trim() === "20241001"
      );
    });
  }

  /** Cache localStorage rusak (mis. baris boolean setelah bug map) */
  function isCorruptSiswaCache(rows) {
    return (rows || []).some(function (r) {
      return r === true || r === false || (r && typeof r === "object" && !r.nis && !r.id);
    });
  }

  function purgeDemoSiswaCache() {
    var rows = loadRows(SISWA_KEY);
    if (isLegacyDemoSiswa(rows) || isCorruptSiswaCache(rows)) {
      localStorage.removeItem(SISWA_KEY);
    }
  }

  function loadSiswa() {
    var rows = loadRows(SISWA_KEY);
    if (window.PresensiData && window.PresensiData.isRemote()) {
      if (!state.dbLoaded) {
        return [];
      }
      if (isLegacyDemoSiswa(rows) || isCorruptSiswaCache(rows)) {
        localStorage.removeItem(SISWA_KEY);
        return [];
      }
      if (rows && rows.length) {
        rows = rows.map(enrichSieFields);
        if (migrateSiswaFields(rows)) saveRows(SISWA_KEY, rows);
        return rows;
      }
      return [];
    }
    var missing = rows === null || !Array.isArray(rows) || rows.length === 0;
    if (missing) {
      rows = SEED.map(function (r) {
        return Object.assign({ id: uid() }, r);
      });
      saveRows(SISWA_KEY, rows);
    } else if (migrateSiswaFields(rows)) {
      saveRows(SISWA_KEY, rows);
    }
    return rows;
  }

  function setSyncStatus(msg, isError) {
    var el = document.getElementById("siswa-sync-status");
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = "";
      return;
    }
    el.hidden = false;
    el.textContent = msg;
    el.classList.toggle("siswa-sync-status--error", Boolean(isError));
  }

  function formatApiError(e) {
    var msg = e && e.message ? String(e.message) : String(e || "Terjadi kesalahan");
    if (/access denied|koneksi database gagal/i.test(msg)) {
      return (
        "Koneksi database gagal. Di server hosting: buat database MySQL lewat cPanel, " +
        "lalu sesuaikan user & password di admin/api/config.php. " +
        "Setelah itu buka /admin/api/health.php?setup=1 untuk cek."
      );
    }
    return msg;
  }

  function saveSiswa(rows) {
    if (window.PresensiData && window.PresensiData.isRemote()) {
      state.siswaRows = rows;
    }
    if (window.PresensiData && window.PresensiData.writeSiswaCache) {
      window.PresensiData.writeSiswaCache(rows);
    } else {
      saveRows(SISWA_KEY, rows);
    }
    if (window.PresensiData && window.PresensiData.isRemote()) {
      window.PresensiData.pushSiswaAll(rows).catch(function (e) {
        console.warn("[data-siswa] simpan DB:", e && e.message ? e.message : e);
        setSyncStatus("Gagal simpan ke database: " + formatApiError(e), true);
      });
    }
  }

  /** Simpan cache lokal tanpa full replaceAll (untuk update/hapus per baris). */
  function writeSiswaLocal(rows) {
    if (window.PresensiData && window.PresensiData.isRemote()) {
      state.siswaRows = rows;
    }
    if (window.PresensiData && window.PresensiData.writeSiswaCache) {
      window.PresensiData.writeSiswaCache(rows);
    } else {
      saveRows(SISWA_KEY, rows);
    }
  }

  function persistAktifToggle(row, checked) {
    writeSiswaLocal(getDisplaySiswaRows());
    if (!window.PresensiData || !window.PresensiData.isRemote()) {
      return Promise.resolve(row);
    }
    setSyncStatus("Menyimpan status aktif…");
    // Kirim 0/1 eksplisit agar kolom DB `aktif` pasti ter-update
    return window.PresensiData.updateSiswaOnServer({
      id: row.id,
      aktif: checked ? 1 : 0,
    }).then(function (saved) {
      if (saved && typeof saved.aktif !== "undefined") {
        row.aktif = saved.aktif !== false && saved.aktif !== 0 && saved.aktif !== "0";
      } else {
        row.aktif = checked;
      }
      writeSiswaLocal(getDisplaySiswaRows());
      setSyncStatus(checked ? "Status aktif tersimpan (1)." : "Status nonaktif tersimpan (0).");
      return row;
    });
  }

  function persistDeleteSiswa(id, nextRows, prevRows) {
    writeSiswaLocal(nextRows);
    if (!window.PresensiData || !window.PresensiData.isRemote()) {
      return Promise.resolve();
    }
    setSyncStatus("Menghapus siswa…");
    return window.PresensiData.deleteSiswaOnServer(id).then(function () {
      setSyncStatus("Siswa dihapus dari database.");
    }).catch(function (e) {
      writeSiswaLocal(prevRows);
      throw e;
    });
  }

  /** Muat daftar siswa dari tabel MySQL (api/siswa-db.php) */
  function pullFromDb(root, opts) {
    opts = opts || {};
    if (!window.PresensiData || !window.PresensiData.isRemote()) {
      state.dbLoaded = true;
      return Promise.resolve(loadSiswa());
    }
    if (root && !opts.skipOverlay && window.PresensiLoading) {
      window.PresensiLoading.show(root, opts.message || "Memuat data siswa…");
    }
    setSyncStatus("Memuat data dari database…");
    var fetchRows =
      window.PresensiApi && window.PresensiApi.siswa && window.PresensiApi.siswa.list
        ? window.PresensiApi.siswa.list()
        : window.PresensiData.pullSiswa();
    return fetchRows
      .then(function (rows) {
        state.dbLoaded = true;
        var n = (rows && rows.length) || 0;
        setSyncStatus(
          n > 0
            ? "Menampilkan " + n + " siswa dari tabel database."
            : "Database kosong. Sinkron dari SIE jika perlu."
        );
        if (rows && rows.length) {
          state.siswaRows = rows.map(enrichSieFields);
          if (window.PresensiData && window.PresensiData.writeSiswaCache) {
            window.PresensiData.writeSiswaCache(state.siswaRows);
          } else {
            saveRows(SISWA_KEY, state.siswaRows);
          }
        } else {
          state.siswaRows = [];
        }
        if (root) render(root);
        return state.siswaRows || [];
      })
      .catch(function (e) {
        state.dbLoaded = true;
        setSyncStatus("Gagal memuat database: " + formatApiError(e), true);
        purgeDemoSiswaCache();
        if (root) render(root);
        return [];
      })
      .finally(function () {
        if (root && !opts.skipOverlay && window.PresensiLoading) {
          window.PresensiLoading.hide(root);
        }
      });
  }

  function loadSyncUnits(root) {
    if (!window.PresensiApiExtras || !window.PresensiApiExtras.fetchMerchantUnits) {
      state.syncUnitsLoaded = true;
      state.syncUnitsError = "API unit tidak tersedia";
      if (root) render(root);
      return Promise.resolve([]);
    }
    if (state.syncUnitsLoading) {
      return Promise.resolve(state.syncUnits);
    }
    state.syncUnitsLoading = true;
    state.syncUnitsError = "";
    if (root) render(root);
    return window.PresensiApiExtras
      .fetchMerchantUnits()
      .then(function (units) {
        state.syncUnits = Array.isArray(units) ? units : [];
        state.syncUnitsLoaded = true;
        state.syncUnitsLoading = false;
        if (
          state.syncKodeUnit &&
          !state.syncUnits.some(function (u) {
            return String(u.kodeunit) === String(state.syncKodeUnit);
          })
        ) {
          state.syncKodeUnit = "";
        }
        if (root) render(root);
        return state.syncUnits;
      })
      .catch(function (e) {
        state.syncUnits = [];
        state.syncUnitsLoaded = true;
        state.syncUnitsLoading = false;
        state.syncUnitsError = formatApiError(e);
        if (root) render(root);
        return [];
      });
  }

  function syncUnitSelectHtml() {
    var opts =
      '<option value="">' +
      (state.syncUnitsLoading
        ? "Memuat unit…"
        : state.syncUnitsError
        ? "Gagal memuat unit"
        : "Pilih unit…") +
      "</option>";
    state.syncUnits.forEach(function (u) {
      var kode = String(u.kodeunit || "");
      var label = String(u.unit || kode);
      opts +=
        '<option value="' +
        escapeHtml(kode) +
        '"' +
        (String(state.syncKodeUnit) === kode ? " selected" : "") +
        ">" +
        escapeHtml(kode + " — " + label) +
        "</option>";
    });
    return (
      '<label class="siswa-toolbar__per siswa-toolbar__unit">' +
      "Unit sinkron" +
      '<select id="siswa-sync-unit" class="settings-form__input siswa-toolbar__select siswa-toolbar__select--unit"' +
      (state.syncUnitsLoading || !state.syncUnits.length ? " disabled" : "") +
      ">" +
      opts +
      "</select></label>"
    );
  }

  /** Tarik dari MobileMerchant (per unit) → upsert DB → tampilkan ulang dari tabel */
  function syncMerchantThenPull(root) {
    if (!window.PresensiApiExtras || !window.PresensiApiExtras.syncSiswaFromMerchant) {
      return pullFromDb(root);
    }
    var kodeUnit = String(state.syncKodeUnit || "").trim();
    if (!kodeUnit) {
      setSyncStatus("Pilih unit terlebih dahulu sebelum sinkron.", true);
      return Promise.resolve();
    }
    if (root && window.PresensiLoading) {
      window.PresensiLoading.show(root, "Menarik data siswa unit " + kodeUnit + "…");
    }
    setSyncStatus("Menarik data siswa unit " + kodeUnit + " dari MobileMerchant…");
    return window.PresensiApiExtras
      .syncSiswaFromMerchant(kodeUnit)
      .then(function (body) {
        var stats = body && body.sync ? body.sync : {};
        setSyncStatus(
          "Sinkron unit " +
            kodeUnit +
            " selesai. Baru: " +
            (stats.inserted || 0) +
            ", diperbarui: " +
            (stats.updated || 0) +
            ". Memuat ulang…"
        );
        if (root && window.PresensiLoading) {
          window.PresensiLoading.setMessage(root, "Memuat ulang daftar siswa…");
        }
        return pullFromDb(root, { skipOverlay: true }).then(function () {
          if (window.PresensiSieSettings && state.siswaRows && state.siswaRows.length) {
            try {
              window.PresensiSieSettings.syncFromSiswaRows(state.siswaRows);
            } catch (eSie) {
              console.warn("[data-siswa] sinkron unit/kelas:", eSie.message || eSie);
            }
          }
        });
      })
      .catch(function (e) {
        setSyncStatus("Sinkron gagal: " + formatApiError(e), true);
        if (root && window.PresensiLoading) {
          window.PresensiLoading.setMessage(root, "Memuat data siswa…");
        }
        return pullFromDb(root, { skipOverlay: true });
      })
      .finally(function () {
        if (root && window.PresensiLoading) {
          window.PresensiLoading.hide(root);
        }
      });
  }

  function kelasLabel(kelasById, jenjangById, unitsById, kelasId) {
    var k = kelasById[kelasId];
    if (!k) return "—";
    if (window.PresensiSieSettings && window.PresensiSieSettings.kelasOptionLabel) {
      return window.PresensiSieSettings.kelasOptionLabel(k, jenjangById, unitsById);
    }
    var j = jenjangById[k.jenjangId];
    var u = j ? unitsById[j.unitId] : null;
    var ubit = u ? u.kode + " · " : "";
    var jbit = j ? j.nama + " · " : "";
    return ubit + jbit + k.nama;
  }

  function pickSiswaField(r, keys) {
    if (!r) return "";
    var list = typeof keys === "string" ? [keys] : keys;
    for (var i = 0; i < list.length; i++) {
      var v = r[list[i]];
      if (v != null && v !== "") {
        var s = String(v).trim();
        if (s && s !== "-") return s;
      }
    }
    return "";
  }

  /** Unit — kolom siswa.code02 (SIE CODE02) */
  function siswaUnitDisplay(r) {
    return pickSiswaField(r, ["code02", "CODE02"]) || "—";
  }

  /** Kelompok — kolom siswa.desc02 (SIE DESC02) */
  function siswaKelompokDisplay(r) {
    return pickSiswaField(r, ["desc02", "DESC02"]) || "—";
  }

  /** Kelas — kolom siswa.desc03 (SIE DESC03), fallback kelas_id lama */
  function siswaKelasDisplay(r, kelasById, jenjangById, unitsById) {
    var kelas = pickSiswaField(r, ["desc03", "DESC03", "kelasId", "kelas_id"]);
    if (kelas) return kelas;
    var kl = kelasLabel(kelasById, jenjangById, unitsById, r.kelasId);
    return kl !== "—" ? kl : "—";
  }

  /** Tahun ajaran — kolom siswa.desc04 (SIE DESC04) */
  function siswaTahunAjaranDisplay(r) {
    return pickSiswaField(r, ["desc04", "DESC04", "code04", "CODE04"]) || "—";
  }

  function buildKelasOptions(kelasRows, jenjangById, unitsById) {
    return (kelasRows || [])
      .map(function (k) {
        if (window.PresensiSieSettings && window.PresensiSieSettings.kelasOptionLabel) {
          return {
            value: k.id,
            label: window.PresensiSieSettings.kelasOptionLabel(k, jenjangById, unitsById),
          };
        }
        var j = jenjangById[k.jenjangId];
        var u = j ? unitsById[j.unitId] : null;
        var label = (u ? u.nama + " · " : "") + (j ? j.nama + " · " : "") + (k.sieDesc03 || k.nama);
        return { value: k.id, label: label };
      })
      .sort(function (a, b) {
        return a.label.localeCompare(b.label, "id");
      });
  }


  function render(root) {
    var kelasRows = ensureKelasSeeded();
    var jrows = loadRows(JENJANG_KEY) || [];
    var units = loadRows(UNIT_KEY) || [];
    var jenjangById = indexById(jrows);
    var unitsById = indexById(units);
    var kelasById = indexById(kelasRows);

    var allRows = getDisplaySiswaRows();
    var searchQ = root.dataset.searchQ || "";
    var hasFilterMod = Boolean(window.PresensiSiswaFilter);
    var filterSel = hasFilterMod
      ? window.PresensiSiswaFilter.read(root)
      : { unit: "", kelompok: "", kelas: "", tahun: "" };
    var gateOpen =
      !hasFilterMod ||
      !window.PresensiSiswaFilter.needsGate(allRows.length) ||
      window.PresensiSiswaFilter.isOpen(filterSel, searchQ);
    var dimRows = hasFilterMod ? window.PresensiSiswaFilter.apply(allRows, filterSel) : allRows;
    var rows = gateOpen ? filterSiswa(dimRows, searchQ) : [];
    var total = rows.length;
    var totalAll = allRows.length;
    var aktifCount = allRows.filter(function (r) {
      return r.aktif;
    }).length;
    var nonAktif = totalAll - aktifCount;

    var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
    if (state.page > totalPages) state.page = totalPages;
    var start = (state.page - 1) * state.pageSize;
    var pageRows = rows.slice(start, start + state.pageSize);

    var opts = buildKelasOptions(kelasRows, jenjangById, unitsById);
    var selectOpts =
      '<option value="">Pilih kelas…</option>' +
      opts
        .map(function (o) {
          return '<option value="' + escapeHtml(o.value) + '">' + escapeHtml(o.label) + "</option>";
        })
        .join("");

    var editRow = state.editingId ? rows.find(function (r) { return r.id === state.editingId; }) : null;

    var formTitle = state.editingId ? "Edit data siswa" : "Tambah siswa";
    var submitLabel = state.editingId ? "Simpan perubahan" : "Simpan";
    var nisAttrs = state.editingId ? " readonly class=\"settings-form__input settings-form__input--readonly\"" : ' class="settings-form__input"';

    var emptyKelas =
      !kelasRows.length &&
      '<p class="settings-form__hint">Sinkron data siswa agar kelas tersedia.</p>';

    var isRemote = window.PresensiData && window.PresensiData.isRemote();
    var tableBody;
    if (!gateOpen) {
      tableBody = window.PresensiSiswaFilter.gateRowHtml(7);
    } else if (!pageRows.length) {
      var emptyMsg;
      if (searchQ && totalAll > 0) {
        emptyMsg = "Tidak ada siswa yang cocok dengan pencarian «" + escapeHtml(searchQ) + "».";
      } else if (isRemote) {
        emptyMsg = "Belum ada siswa. Tekan <strong>Sinkron</strong> untuk menarik data.";
      } else {
        emptyMsg = "Belum ada siswa. Sinkron dari SIE jika perlu.";
      }
      tableBody = '<tr><td colspan="7" class="data-table__empty">' + emptyMsg + "</td></tr>";
    } else if (!kelasRows.length && !isRemote) {
      tableBody =
        '<tr><td colspan="7" class="data-table__empty">Belum ada data kelas. Sinkron siswa terlebih dahulu.</td></tr>';
    } else {
      tableBody = pageRows
        .map(function (r) {
          var unit = siswaUnitDisplay(r);
          var kelompok = siswaKelompokDisplay(r);
          var kl = siswaKelasDisplay(r, kelasById, jenjangById, unitsById);
          var tahun = siswaTahunAjaranDisplay(r);
          var checked = r.aktif ? " checked" : "";
          var rfidOk = r.rfidUid && String(r.rfidUid).trim();
          var fotoOk =
            (r.fotoWajah && String(r.fotoWajah).length > 30) || r._hasFoto || r.hasFoto;
          var speechOk = r.kodeSuara && String(r.kodeSuara).trim();
          var mini =
            '<span class="siswa-presensi-mini" title="RFID · Foto · Kode suara">' +
            '<span class="' +
            (rfidOk ? "siswa-dot siswa-dot--ok" : "siswa-dot") +
            '">R</span>' +
            '<span class="' +
            (fotoOk ? "siswa-dot siswa-dot--ok" : "siswa-dot") +
            '">F</span>' +
            '<span class="' +
            (speechOk ? "siswa-dot siswa-dot--ok" : "siswa-dot") +
            '">S</span>' +
            "</span>";
          return (
            "<tr data-id='" +
            escapeHtml(r.id) +
            "'>" +
            "<td>" +
            escapeHtml(r.nis) +
            "</td>" +
            "<td>" +
            escapeHtml(r.nama) +
            "</td>" +
            "<td class=\"siswa-cell-unit\">" +
            escapeHtml(unit) +
            "</td>" +
            "<td class=\"siswa-cell-kelompok\">" +
            escapeHtml(kelompok) +
            "</td>" +
            "<td class=\"siswa-cell-kelas\">" +
            escapeHtml(kl) +
            "</td>" +
            "<td><label class=\"toggle\"><input type=\"checkbox\" class=\"toggle__input\" data-siswa-toggle='" +
            escapeHtml(r.id) +
            "'" +
            checked +
            ' aria-label="Aktif" /><span class="toggle__slider" aria-hidden="true"></span></label></td>' +
            "<td class='siswa-rekam-cell'>" +
            mini +
            ' <a class="btn btn--ghost btn--small" href="rekam-data.html?siswa=' +
            encodeURIComponent(r.id) +
            '">Rekam</a></td></tr>'
          );
        })
        .join("");
    }

    var pageInfo =
      !gateOpen
        ? "Pilih filter untuk menampilkan siswa (total " + totalAll + ")"
        : total === 0
        ? searchQ && totalAll > 0
          ? "0 hasil pencarian (total " + totalAll + " siswa)"
          : "0 data"
        : start +
          1 +
          "–" +
          Math.min(start + state.pageSize, total) +
          " dari " +
          total +
          (searchQ && totalAll !== total ? " (filter, total " + totalAll + ")" : "");

    root.innerHTML =
      '<header class="page-head">' +
      "<h1>Data siswa</h1>" +
      "<p>Kelola daftar siswa. Pilih unit lalu sinkron untuk memperbarui dari sistem.</p>" +
      '<p id="siswa-sync-status" class="siswa-sync-status" hidden></p>' +
      "</header>" +
      '<div class="siswa-summary">' +
      '<div class="siswa-summary__card"><span class="siswa-summary__label">Total</span><span class="siswa-summary__value">' +
      totalAll +
      "</span></div>" +
      '<div class="siswa-summary__card siswa-summary__card--ok"><span class="siswa-summary__label">Aktif</span><span class="siswa-summary__value">' +
      aktifCount +
      "</span></div>" +
      '<div class="siswa-summary__card siswa-summary__card--muted"><span class="siswa-summary__label">Nonaktif</span><span class="siswa-summary__value">' +
      nonAktif +
      "</span></div>" +
      "</div>" +
      '<div class="module-shell siswa-toolbar">' +
      '<div class="siswa-toolbar__row">' +
      '<div class="siswa-toolbar__filters">' +
      '<label class="siswa-toolbar__per">Baris<select id="siswa-page-size" class="settings-form__input siswa-toolbar__select">' +
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
      syncUnitSelectHtml() +
      "</div>" +
      '<div class="siswa-toolbar__actions">' +
      '<button type="button" class="btn btn--ghost" id="siswa-sync-merchant"' +
      (!state.syncKodeUnit ? " disabled title=\"Pilih unit terlebih dahulu\"" : "") +
      ">Sinkron</button>" +
      "</div></div></div>" +
      (hasFilterMod ? window.PresensiSiswaFilter.controlsHtml(allRows, filterSel, "siswa") : "") +
      '<div class="module-shell siswa-search-panel">' +
      '<label class="settings-form__label" for="siswa-search">Cari</label>' +
      '<input type="search" id="siswa-search" class="settings-form__input siswa-search-input" placeholder="Nama atau NIS…" autocomplete="off" value="' +
      escapeHtml(searchQ) +
      '" />' +
      '<p class="siswa-search-meta">' +
      (!gateOpen
        ? "Pilih filter untuk menampilkan daftar."
        : searchQ
        ? "<strong>" + total + "</strong> hasil dari <strong>" + totalAll + "</strong>"
        : "<strong>" + total + "</strong> siswa") +
      "</p></div>" +
      '<div class="module-shell data-settings__table-shell">' +
      '<div class="table-wrap"><table class="data-table">' +
      "<thead><tr><th>NIS</th><th>Nama</th><th>Unit</th><th>Kelompok</th><th>Kelas</th><th>Aktif</th><th>Foto</th></tr></thead>" +
      "<tbody>" +
      tableBody +
      "</tbody></table></div>" +
      '<div class="siswa-pagination">' +
      '<span class="siswa-pagination__info">' +
      escapeHtml(pageInfo) +
      "</span>" +
      '<div class="siswa-pagination__nav">' +
      '<button type="button" class="btn btn--ghost btn--small" id="siswa-prev"' +
      (state.page <= 1 ? " disabled" : "") +
      ">Sebelumnya</button>" +
      '<span class="siswa-pagination__page">' +
      state.page +
      " / " +
      totalPages +
      "</span>" +
      '<button type="button" class="btn btn--ghost btn--small" id="siswa-next"' +
      (state.page >= totalPages ? " disabled" : "") +
      ">Berikutnya</button></div></div></div>" +
      '<div id="siswa-form-modal" class="modal' +
      (state.modalOpen ? " modal--open" : "") +
      '" aria-hidden="' +
      (state.modalOpen ? "false" : "true") +
      '">' +
      '<div class="modal__backdrop" data-close-siswa-modal tabindex="-1"></div>' +
      '<div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="siswa-modal-title">' +
      '<div class="modal__header">' +
      '<h3 id="siswa-modal-title" class="modal__title">' +
      escapeHtml(formTitle) +
      "</h3>" +
      '<button type="button" class="modal__close btn btn--ghost" data-close-siswa-modal aria-label="Tutup">×</button>' +
      "</div>" +
      '<div class="modal__body">' +
      emptyKelas +
      '<form id="siswa-form" class="settings-form">' +
      '<div class="settings-form__grid">' +
      '<label class="settings-form__label" for="siswa-nis">NIS</label><input id="siswa-nis" name="nis" type="text" autocomplete="off" required' +
      nisAttrs +
      ' value="' +
      escapeHtml(editRow ? editRow.nis : "") +
      '" />' +
      '<label class="settings-form__label" for="siswa-nisn">NISN</label><input class="settings-form__input" id="siswa-nisn" name="nisn" type="text" value="' +
      escapeHtml(editRow ? editRow.nisn || "" : "") +
      '" />' +
      '<label class="settings-form__label" for="siswa-nama">Nama lengkap</label><input class="settings-form__input" id="siswa-nama" name="nama" type="text" required value="' +
      escapeHtml(editRow ? editRow.nama : "") +
      '" />' +
      '<label class="settings-form__label" for="siswa-kelas">Kelas</label><select class="settings-form__input" id="siswa-kelas" name="kelasId"' +
      (window.PresensiData && window.PresensiData.isRemote() ? "" : " required") +
      (!kelasRows.length ? " disabled" : "") +
      ">" +
      selectOpts +
      "</select>" +
      '<label class="settings-form__label" for="siswa-jk">Jenis kelamin</label><select class="settings-form__input" id="siswa-jk" name="jenisKelamin">' +
      '<option value="L"' +
      (editRow && editRow.jenisKelamin === "P" ? "" : " selected") +
      ">Laki-laki</option>" +
      '<option value="P"' +
      (editRow && editRow.jenisKelamin === "P" ? " selected" : "") +
      ">Perempuan</option></select>" +
      '<label class="settings-form__label" for="siswa-telp">Telepon / WA</label><input class="settings-form__input" id="siswa-telp" name="telepon" type="text" value="' +
      escapeHtml(editRow ? editRow.telepon || "" : "") +
      '" />' +
      '<div class="siswa-form-aktif"><label class="settings-form__label" for="siswa-aktif">Status aktif</label><label class="toggle toggle--form"><input type="checkbox" id="siswa-aktif" name="aktif" class="toggle__input"' +
      (!editRow || editRow.aktif ? " checked" : "") +
      ' /><span class="toggle__slider" aria-hidden="true"></span><span class="siswa-form-aktif__hint">Nonaktifkan jika siswa tidak lagi terdaftar</span></label></div>' +
      "</div>" +
      '<div class="settings-form__actions siswa-form-actions">' +
      '<button type="button" class="btn btn--ghost" data-close-siswa-modal>Batal</button>' +
      '<button type="submit" class="btn btn--primary"' +
      (!kelasRows.length ? " disabled" : "") +
      ">" +
      escapeHtml(submitLabel) +
      "</button></div></form></div></div></div>";

    if (editRow && editRow.kelasId) {
      var sel = root.querySelector("#siswa-kelas");
      if (sel) sel.value = editRow.kelasId;
    }

    if (state.modalOpen) {
      delete root.dataset.searchFocus;
      var focusEl = root.querySelector(
        state.editingId ? "#siswa-nama" : "#siswa-nis:not([readonly])"
      );
      if (!focusEl && state.editingId) focusEl = root.querySelector("#siswa-nis");
      if (focusEl) focusEl.focus();
    } else {
      bindSiswaSearchFocus(root);
    }
    syncModalScrollLock();
  }

  function bind(root) {
    root.addEventListener("input", function (e) {
      if (e.target.id === "siswa-search") {
        root.dataset.searchQ = e.target.value;
        root.dataset.searchFocus = "1";
        state.page = 1;
        render(root);
      }
    });

    root.addEventListener("change", function (e) {
      var t = e.target;
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleChange(root, t, "siswa")) {
        state.page = 1;
        render(root);
        return;
      }
      if (t.id === "siswa-page-size") {
        state.pageSize = parseInt(t.value, 10) || 8;
        state.page = 1;
        render(root);
        return;
      }
      if (t.id === "siswa-sync-unit") {
        state.syncKodeUnit = String(t.value || "").trim();
        render(root);
        return;
      }
      if (t.matches("[data-siswa-toggle]")) {
        var id = t.getAttribute("data-siswa-toggle");
        var rows = getDisplaySiswaRows();
        var row = rows.find(function (r) {
          return r.id === id;
        });
        if (row) {
          var prevAktif = row.aktif !== false;
          row.aktif = t.checked;
          render(root);
          persistAktifToggle(row, t.checked).catch(function (e) {
            row.aktif = prevAktif;
            writeSiswaLocal(getDisplaySiswaRows());
            setSyncStatus("Gagal simpan status aktif: " + formatApiError(e), true);
            render(root);
          });
        }
      }
    });

    root.addEventListener("click", function (e) {
      if (window.PresensiSiswaFilter && window.PresensiSiswaFilter.handleClick(root, e.target, "siswa")) {
        state.page = 1;
        render(root);
        return;
      }
      if (e.target.id === "siswa-prev") {
        state.page = Math.max(1, state.page - 1);
        render(root);
        return;
      }
      if (e.target.id === "siswa-next") {
        var filtered = getFilteredSiswa(root);
        var totalPages = Math.max(1, Math.ceil(filtered.length / state.pageSize));
        state.page = Math.min(totalPages, state.page + 1);
        render(root);
        return;
      }
      if (e.target.id === "siswa-sync-merchant") {
        syncMerchantThenPull(root);
        return;
      }
      if (e.target.closest("[data-close-siswa-modal]")) {
        closeSiswaModal();
        render(root);
        return;
      }
      var delBtn = e.target.closest("[data-siswa-del]");
      if (delBtn && root.contains(delBtn)) {
        var did = delBtn.getAttribute("data-siswa-del");
        if (!confirm("Hapus siswa ini?")) return;
        var prevRows = getDisplaySiswaRows().slice();
        var nextRows = prevRows.filter(function (r) {
          return r.id !== did;
        });
        if (state.editingId === did) closeSiswaModal();
        writeSiswaLocal(nextRows);
        render(root);
        persistDeleteSiswa(did, nextRows, prevRows).catch(function (e) {
          setSyncStatus("Gagal hapus di database: " + formatApiError(e), true);
          render(root);
        });
        return;
      }
      var editBtn = e.target.closest("[data-siswa-edit]");
      if (editBtn && root.contains(editBtn)) {
        state.editingId = editBtn.getAttribute("data-siswa-edit");
        state.modalOpen = true;
        var ix = getDisplaySiswaRows().findIndex(function (r) {
          return r.id === state.editingId;
        });
        state.page = ix < 0 ? 1 : Math.floor(ix / state.pageSize) + 1;
        render(root);
        return;
      }
    });

    root.addEventListener("submit", function (e) {
      var form = e.target;
      if (form.id !== "siswa-form") return;
      e.preventDefault();
      if (form.querySelector('button[type="submit"][disabled]')) return;

      var fd = new FormData(form);
      var nis = (fd.get("nis") || "").toString().trim();
      var nama = (fd.get("nama") || "").toString().trim();
      var kelasId = (fd.get("kelasId") || "").toString().trim();
      var jk = (fd.get("jenisKelamin") || "L").toString().trim();
      if (!nis || !nama) return;
      if (!kelasId && !(window.PresensiData && window.PresensiData.isRemote())) return;

      var rows = getDisplaySiswaRows();
      var wasEditing = Boolean(state.editingId);
      if (state.editingId) {
        var cur = rows.find(function (r) {
          return r.id === state.editingId;
        });
        if (!cur) return;
        cur.nisn = (fd.get("nisn") || "").toString().trim();
        cur.nama = nama;
        cur.kelasId = kelasId;
        cur.jenisKelamin = jk === "P" ? "P" : "L";
        cur.telepon = (fd.get("telepon") || "").toString().trim();
        cur.aktif = fd.get("aktif") === "on";
        saveSiswa(rows);
        state.editingId = null;
      } else {
        var clash = rows.some(function (r) {
          return String(r.nis).trim().toLowerCase() === nis.toLowerCase();
        });
        if (clash) {
          alert("NIS sudah dipakai. Gunakan NIS lain.");
          return;
        }
        rows.push({
          id: uid(),
          nis: nis,
          nisn: (fd.get("nisn") || "").toString().trim(),
          nama: nama,
          kelasId: kelasId,
          jenisKelamin: jk === "P" ? "P" : "L",
          telepon: (fd.get("telepon") || "").toString().trim(),
          aktif: fd.get("aktif") === "on",
          rfidUid: "",
          fotoWajah: "",
          kodeSuara: "",
        });
        saveSiswa(rows);
      }
      state.editingId = null;
      state.modalOpen = false;
      if (!wasEditing) {
        state.page = Math.max(1, Math.ceil(rows.length / state.pageSize));
      }
      render(root);
    });
  }

  function init() {
    var root = document.getElementById("siswa-root");
    if (!root) return;
    if (!root.dataset.siswaBound) {
      root.dataset.siswaBound = "1";
      bind(root);
    }
    if (!document.documentElement.dataset.presensiSiswaModalEsc) {
      document.documentElement.dataset.presensiSiswaModalEsc = "1";
      document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        var r = document.getElementById("siswa-root");
        if (!r || !r.querySelector("#siswa-form-modal.modal--open")) return;
        closeSiswaModal();
        render(r);
      });
    }
    function afterLoad() {
      if (!window.PresensiData || !window.PresensiData.isRemote()) {
        loadSiswa();
      }
      render(root);
      if (!state.syncUnitsLoaded && !state.syncUnitsLoading) {
        loadSyncUnits(root);
      }
    }
    if (window.PresensiData && window.PresensiData.isRemote()) {
      purgeDemoSiswaCache();
      state.dbLoaded = false;
      pullFromDb(root).then(afterLoad).catch(afterLoad);
    } else {
      afterLoad();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.presensiEnsureSiswaSeed = function () {
    if (window.PresensiData && window.PresensiData.isRemote()) {
      return window.PresensiData.pullSiswa();
    }
    return Promise.resolve(loadSiswa());
  };
  try {
    if (!window.PresensiData || !window.PresensiData.isRemote()) loadSiswa();
  } catch (eSeed) {}
})();
