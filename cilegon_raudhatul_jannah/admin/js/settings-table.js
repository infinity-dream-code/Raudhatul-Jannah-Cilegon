(function () {
  var UNIT_KEY = "presensi_setting_unit";
  var JENJANG_KEY = "presensi_setting_jenjang";
  var KELAS_KEY = "presensi_setting_kelas";

  var CONFIG = {
    unit: {
      title: "Unit",
      description:
        "Unit sekolah dari SIE (kolom CODE02 / code02 siswa), mis. «SMP PUTRI 1». Gunakan «Sinkron dari data SIE» setelah impor siswa, atau tambah manual.",
      listTitle: "Daftar unit",
      storageKey: UNIT_KEY,
      fields: [
        { name: "kode", label: "Kode", placeholder: "SMP-P1", required: true },
        { name: "nama", label: "Nama unit (SIE CODE02)", placeholder: "SMP PUTRI 1", required: true },
        { name: "alamat", label: "Alamat", type: "textarea", placeholder: "Jl. …", rows: 2 },
      ],
      columns: [
        { key: "kode", label: "Kode" },
        { key: "nama", label: "Nama unit" },
        { key: "sieCode02", label: "SIE CODE02" },
        { key: "alamat", label: "Alamat" },
      ],
      seed: [
        {
          id: "seed-u1",
          kode: "PUTRI",
          nama: "SMP Putri Cilegon Raudhatul Jannah",
          alamat: "Cilegon Raudhatul Jannah",
        },
        {
          id: "seed-u2",
          kode: "PUTRA",
          nama: "SMP Putra Cilegon Raudhatul Jannah",
          alamat: "Cilegon Raudhatul Jannah",
        },
      ],
    },
    jenjang: {
      title: "Jenjang",
      description:
        "Tingkat/jenjang dari SIE (kolom DESC02 / desc02 siswa), mis. «9». Terhubung ke unit induk (CODE02).",
      listTitle: "Daftar jenjang",
      storageKey: JENJANG_KEY,
      columns: [
        { key: "_unit", label: "Unit" },
        { key: "kode", label: "Kode" },
        { key: "nama", label: "Tingkat" },
        { key: "sieDesc02", label: "SIE DESC02" },
        { key: "namaPanjang", label: "Nama lengkap" },
      ],
      fields: [
        { name: "unitId", label: "Unit", type: "select", optionsKey: "units", required: true },
        { name: "kode", label: "Kode", placeholder: "JKT-SMA", required: true },
        { name: "nama", label: "Nama singkat", placeholder: "SMA", required: true },
        {
          name: "namaPanjang",
          label: "Nama lengkap",
          placeholder: "Sekolah Menengah Atas",
          required: true,
        },
        { name: "keterangan", label: "Keterangan", type: "textarea", placeholder: "Kelas 10–12", rows: 2 },
      ],
      seed: [
        {
          id: "seed-j1",
          unitId: "seed-u1",
          kode: "VII",
          nama: "VII",
          namaPanjang: "Kelas VII",
          keterangan: "Tingkat 7",
        },
        {
          id: "seed-j2",
          unitId: "seed-u1",
          kode: "VIII",
          nama: "VIII",
          namaPanjang: "Kelas VIII",
          keterangan: "Tingkat 8",
        },
        {
          id: "seed-j3",
          unitId: "seed-u1",
          kode: "IX",
          nama: "IX",
          namaPanjang: "Kelas IX",
          keterangan: "Tingkat 9",
        },
        {
          id: "seed-j4",
          unitId: "seed-u2",
          kode: "VII",
          nama: "VII",
          namaPanjang: "Kelas VII",
          keterangan: "Tingkat 7",
        },
        {
          id: "seed-j5",
          unitId: "seed-u2",
          kode: "VIII",
          nama: "VIII",
          namaPanjang: "Kelas VIII",
          keterangan: "Tingkat 8",
        },
        {
          id: "seed-j6",
          unitId: "seed-u2",
          kode: "IX",
          nama: "IX",
          namaPanjang: "Kelas IX",
          keterangan: "Tingkat 9",
        },
      ],
    },
    kelas: {
      title: "Kelas",
      description:
        "Rombel/kelas dari SIE (kolom DESC03 / desc03 siswa), mis. «9-A1». Terhubung ke jenjang (DESC02) dan unit (CODE02).",
      listTitle: "Daftar kelas",
      storageKey: KELAS_KEY,
      columns: [
        { key: "_unit", label: "Unit" },
        { key: "_jenjang", label: "Jenjang" },
        { key: "nama", label: "Kelas (DESC03)" },
        { key: "sieDesc03", label: "SIE DESC03" },
        { key: "tingkat", label: "Tingkat" },
        { key: "wali", label: "Wali kelas" },
      ],
      fields: [
        {
          name: "jenjangId",
          label: "Jenjang (per unit)",
          type: "select",
          optionsKey: "jenjang",
          required: true,
        },
        { name: "nama", label: "Nama kelas", placeholder: "X IPA 3", required: true },
        { name: "tingkat", label: "Tingkat", placeholder: "10", required: true },
        { name: "wali", label: "Wali kelas", placeholder: "Nama guru", required: false },
      ],
      seed: [
        { id: "seed-k1", jenjangId: "seed-j1", nama: "A", tingkat: "7", wali: "" },
        { id: "seed-k2", jenjangId: "seed-j1", nama: "B", tingkat: "7", wali: "" },
        { id: "seed-k3", jenjangId: "seed-j1", nama: "C", tingkat: "7", wali: "" },
        { id: "seed-k4", jenjangId: "seed-j2", nama: "A", tingkat: "8", wali: "" },
        { id: "seed-k5", jenjangId: "seed-j2", nama: "B", tingkat: "8", wali: "" },
        { id: "seed-k6", jenjangId: "seed-j2", nama: "C", tingkat: "8", wali: "" },
        { id: "seed-k7", jenjangId: "seed-j3", nama: "A", tingkat: "9", wali: "" },
        { id: "seed-k8", jenjangId: "seed-j3", nama: "B", tingkat: "9", wali: "" },
        { id: "seed-k9", jenjangId: "seed-j3", nama: "C", tingkat: "9", wali: "" },
        { id: "seed-k10", jenjangId: "seed-j4", nama: "A", tingkat: "7", wali: "" },
        { id: "seed-k11", jenjangId: "seed-j4", nama: "B", tingkat: "7", wali: "" },
        { id: "seed-k12", jenjangId: "seed-j4", nama: "C", tingkat: "7", wali: "" },
        { id: "seed-k13", jenjangId: "seed-j5", nama: "A", tingkat: "8", wali: "" },
        { id: "seed-k14", jenjangId: "seed-j5", nama: "B", tingkat: "8", wali: "" },
        { id: "seed-k15", jenjangId: "seed-j5", nama: "C", tingkat: "8", wali: "" },
        { id: "seed-k16", jenjangId: "seed-j6", nama: "A", tingkat: "9", wali: "" },
        { id: "seed-k17", jenjangId: "seed-j6", nama: "B", tingkat: "9", wali: "" },
        { id: "seed-k18", jenjangId: "seed-j6", nama: "C", tingkat: "9", wali: "" },
      ],
    },
  };

  function uid() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) return crypto.randomUUID();
    return "id-" + Date.now() + "-" + Math.random().toString(16).slice(2);
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
    localStorage.setItem(key, JSON.stringify(rows));
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function indexById(rows) {
    var m = {};
    (rows || []).forEach(function (r) {
      m[r.id] = r;
    });
    return m;
  }

  function migrateJenjang(rows) {
    if (!rows || !rows.length) return rows;
    var units = loadRows(UNIT_KEY);
    if (!units || !units.length) return rows;
    var def = units[0].id;
    var changed = false;
    rows.forEach(function (r) {
      if (!r.unitId) {
        r.unitId = def;
        changed = true;
      }
    });
    if (changed) saveRows(JENJANG_KEY, rows);
    return rows;
  }

  function migrateKelas(rows) {
    if (!rows || !rows.length) return rows;
    var jrows = loadRows(JENJANG_KEY);
    if (!jrows || !jrows.length) return rows;
    var changed = false;
    rows.forEach(function (r) {
      if (!r.jenjangId && r.jenjang) {
        var j = jrows.find(function (x) {
          return x.nama === r.jenjang;
        });
        if (j) {
          r.jenjangId = j.id;
          delete r.jenjang;
          changed = true;
        }
      }
    });
    if (changed) saveRows(KELAS_KEY, rows);
    return rows;
  }

  function ensureSeeded(cfg) {
    var rows = loadRows(cfg.storageKey);
    var missing = rows === null || !Array.isArray(rows) || rows.length === 0;
    if (missing) {
      rows = cfg.seed.map(function (r) {
        return Object.assign({}, r);
      });
      saveRows(cfg.storageKey, rows);
    }
    if (cfg.storageKey === JENJANG_KEY) migrateJenjang(rows);
    if (cfg.storageKey === KELAS_KEY) migrateKelas(rows);
    return loadRows(cfg.storageKey) || [];
  }

  function ensureUnits() {
    var units = loadRows(UNIT_KEY);
    var missing = units === null || !Array.isArray(units) || units.length === 0;
    if (missing) {
      units = CONFIG.unit.seed.map(function (r) {
        return Object.assign({}, r);
      });
      saveRows(UNIT_KEY, units);
    }
    return loadRows(UNIT_KEY) || [];
  }

  function isLegacyDemoSettings(units) {
    return (units || []).some(function (u) {
      return (
        u.nama === "Unit Pusat Jakarta" ||
        u.nama === "Cabang Bandung" ||
        u.nama === "Cabang Surabaya" ||
        u.id === "seed-u3"
      );
    });
  }

  function applyCilegonSettings() {
    saveRows(
      UNIT_KEY,
      CONFIG.unit.seed.map(function (r) {
        return Object.assign({}, r);
      })
    );
    saveRows(
      JENJANG_KEY,
      CONFIG.jenjang.seed.map(function (r) {
        return Object.assign({}, r);
      })
    );
    saveRows(
      KELAS_KEY,
      CONFIG.kelas.seed.map(function (r) {
        return Object.assign({}, r);
      })
    );
    try {
      localStorage.removeItem("presensi_modul_context");
    } catch (e) {}
  }

  function unitLabel(u) {
    if (!u) return "—";
    if (window.PresensiSieSettings && window.PresensiSieSettings.unitDisplay) {
      return window.PresensiSieSettings.unitDisplay(u);
    }
    return u.kode + " — " + u.nama;
  }

  function jenjangOptionLabel(j, unitsById) {
    var u = unitsById[j.unitId];
    var uPart = u ? unitLabel(u) : "(unit?)";
    var jPart =
      window.PresensiSieSettings && window.PresensiSieSettings.jenjangDisplay
        ? window.PresensiSieSettings.jenjangDisplay(j)
        : j.nama;
    return uPart + " · " + jPart;
  }

  function syncSettingsFromSie(root) {
    if (!window.PresensiSieSettings || !window.PresensiSieSettings.syncFromApi) {
      alert("Muat ulang halaman dengan koneksi API aktif.");
      return;
    }
    var btn = root.querySelector("#settings-sync-sie");
    if (btn) btn.disabled = true;
    window.PresensiSieSettings.syncFromApi()
      .then(function (data) {
        alert(
          "Sinkron selesai: " +
            (data.units.length || 0) +
            " unit, " +
            (data.jenjang.length || 0) +
            " jenjang, " +
            (data.kelas.length || 0) +
            " kelas."
        );
        paint(root);
      })
      .catch(function (e) {
        alert("Gagal sinkron: " + (e && e.message ? e.message : e));
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function deleteUnitCascade(unitId) {
    var units = loadRows(UNIT_KEY) || [];
    saveRows(
      UNIT_KEY,
      units.filter(function (r) {
        return r.id !== unitId;
      })
    );
    var jrows = loadRows(JENJANG_KEY) || [];
    var removedJ = jrows.filter(function (j) {
      return j.unitId === unitId;
    }).map(function (j) {
      return j.id;
    });
    saveRows(
      JENJANG_KEY,
      jrows.filter(function (j) {
        return j.unitId !== unitId;
      })
    );
    var krows = loadRows(KELAS_KEY) || [];
    saveRows(
      KELAS_KEY,
      krows.filter(function (k) {
        return removedJ.indexOf(k.jenjangId) === -1;
      })
    );
  }

  function deleteJenjangCascade(jenjangId) {
    var jrows = loadRows(JENJANG_KEY) || [];
    saveRows(
      JENJANG_KEY,
      jrows.filter(function (r) {
        return r.id !== jenjangId;
      })
    );
    var krows = loadRows(KELAS_KEY) || [];
    saveRows(
      KELAS_KEY,
      krows.filter(function (k) {
        return k.jenjangId !== jenjangId;
      })
    );
  }

  function buildSelectField(f, options) {
    var req = f.required ? " required" : "";
    var id = "fld-" + f.name;
    var opts = (options || [])
      .map(function (o) {
        return (
          '<option value="' +
          escapeHtml(o.value) +
          '">' +
          escapeHtml(o.label) +
          "</option>"
        );
      })
      .join("");
    return (
      '<label class="settings-form__label" for="' +
      id +
      '">' +
      escapeHtml(f.label) +
      '</label><select class="settings-form__input" id="' +
      id +
      '" name="' +
      f.name +
      '"' +
      req +
      '><option value="">Pilih…</option>' +
      opts +
      "</select>"
    );
  }

  function buildTextField(f) {
    var req = f.required ? " required" : "";
    var id = "fld-" + f.name;
    if (f.type === "textarea") {
      return (
        '<label class="settings-form__label" for="' +
        id +
        '">' +
        escapeHtml(f.label) +
        '</label><textarea class="settings-form__input settings-form__input--textarea" id="' +
        id +
        '" name="' +
        f.name +
        '" rows="' +
        (f.rows || 3) +
        '" placeholder="' +
        escapeHtml(f.placeholder || "") +
        '"' +
        req +
        "></textarea>"
      );
    }
    return (
      '<label class="settings-form__label" for="' +
      id +
      '">' +
      escapeHtml(f.label) +
      '</label><input class="settings-form__input" id="' +
      id +
      '" name="' +
      f.name +
      '" type="text" autocomplete="off" placeholder="' +
      escapeHtml(f.placeholder || "") +
      '"' +
      req +
      " />"
    );
  }

  function buildFormGrid(type, units, jenjangRows) {
    var cfg = CONFIG[type];
    var unitsById = indexById(units);
    return cfg.fields
      .map(function (f) {
        if (f.type === "select" && f.optionsKey === "units") {
          var opts = (units || []).map(function (u) {
            return { value: u.id, label: unitLabel(u) };
          });
          return buildSelectField(f, opts);
        }
        if (f.type === "select" && f.optionsKey === "jenjang") {
          var opts = (jenjangRows || []).map(function (j) {
            return { value: j.id, label: jenjangOptionLabel(j, unitsById) };
          });
          return buildSelectField(f, opts);
        }
        return buildTextField(f);
      })
      .join("");
  }

  function cellHtml(type, col, row, unitsById, jenjangById) {
    if (col.key === "_unit") {
      if (type === "jenjang") {
        return escapeHtml(unitLabel(unitsById[row.unitId]));
      }
      if (type === "kelas") {
        var j = jenjangById[row.jenjangId];
        return escapeHtml(j ? unitLabel(unitsById[j.unitId]) : "—");
      }
    }
    if (col.key === "_jenjang") {
      var jj = jenjangById[row.jenjangId];
      if (window.PresensiSieSettings && window.PresensiSieSettings.jenjangDisplay) {
        return escapeHtml(window.PresensiSieSettings.jenjangDisplay(jj));
      }
      return escapeHtml(jj ? jj.nama + " (" + jj.kode + ")" : "—");
    }
    var val = row[col.key];
    if (val == null || val === "") {
      if (col.key === "sieCode02") val = row.sieCode02 || row.nama || "";
      if (col.key === "sieDesc02") val = row.sieDesc02 || row.nama || "";
      if (col.key === "sieDesc03") val = row.sieDesc03 || row.nama || "";
    }
    return escapeHtml(val == null || val === "" ? "—" : val);
  }

  var modalOpen = false;

  function syncModalScrollLock() {
    document.body.classList.toggle(
      "modal-scroll-lock",
      Boolean(document.querySelector(".modal.modal--open"))
    );
  }

  function buildSettingsModal(cfg, formGrid, emptyFormNote, disableSubmit) {
    var title = "Tambah " + cfg.title;
    return (
      '<div id="settings-form-modal" class="modal' +
      (modalOpen ? " modal--open" : "") +
      '" aria-hidden="' +
      (modalOpen ? "false" : "true") +
      '">' +
      '<div class="modal__backdrop" data-close-settings-modal tabindex="-1"></div>' +
      '<div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="settings-modal-title">' +
      '<div class="modal__header">' +
      '<h3 id="settings-modal-title" class="modal__title">' +
      escapeHtml(title) +
      "</h3>" +
      '<button type="button" class="modal__close btn btn--ghost" data-close-settings-modal aria-label="Tutup">×</button>' +
      "</div>" +
      '<div class="modal__body">' +
      emptyFormNote +
      '<form id="settings-add-form" class="settings-form">' +
      '<div class="settings-form__grid">' +
      formGrid +
      "</div>" +
      '<div class="settings-form__actions">' +
      '<button type="button" class="btn btn--ghost" data-close-settings-modal>Batal</button>' +
      '<button type="submit" class="btn btn--primary"' +
      (disableSubmit ? " disabled" : "") +
      ">Simpan</button>" +
      "</div></form></div></div></div>"
    );
  }

  function renderTable(type, cfg, tbody, thead) {
    thead.innerHTML =
      "<tr>" +
      cfg.columns
        .map(function (c) {
          return "<th scope='col'>" + escapeHtml(c.label) + "</th>";
        })
        .join("") +
      "<th scope='col' class='data-table__actions'>Aksi</th></tr>";

    var rows = ensureSeeded(cfg);
    var units = loadRows(UNIT_KEY) || [];
    var jrows = loadRows(JENJANG_KEY) || [];
    var unitsById = indexById(units);
    var jenjangById = indexById(jrows);

    if (type === "jenjang" && !units.length) {
      tbody.innerHTML =
        '<tr><td colspan="' +
        (cfg.columns.length + 1) +
        '" class="data-table__empty">Buat minimal satu unit terlebih dahulu di menu Unit.</td></tr>';
      return;
    }
    if (type === "kelas" && !jrows.length) {
      tbody.innerHTML =
        '<tr><td colspan="' +
        (cfg.columns.length + 1) +
        '" class="data-table__empty">Buat jenjang terlebih dahulu (terhubung ke unit).</td></tr>';
      return;
    }

    if (!rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="' +
        (cfg.columns.length + 1) +
        '" class="data-table__empty">Belum ada data. Klik tombol Tambah untuk menambah.</td></tr>';
      return;
    }

    tbody.innerHTML = rows
      .map(function (row) {
        var cells = cfg.columns
          .map(function (c) {
            return "<td>" + cellHtml(type, c, row, unitsById, jenjangById) + "</td>";
          })
          .join("");
        return (
          "<tr data-id='" +
          escapeHtml(row.id) +
          "'>" +
          cells +
          "<td class='data-table__actions'><button type='button' class='btn btn--ghost btn--small' data-delete='" +
          escapeHtml(row.id) +
          "'>Hapus</button></td></tr>"
        );
      })
      .join("");
  }

  function paint(root) {
    var type = root.getAttribute("data-settings-type");
    var cfg = CONFIG[type];
    if (!cfg) return;

    ensureUnits();
    var units = loadRows(UNIT_KEY) || [];
    var jrows = loadRows(JENJANG_KEY);
    if (jrows !== null) migrateJenjang(jrows);

    var formGrid;
    var emptyFormNote = "";
    var disableSubmit = false;

    var disableOpen = false;

    if (type === "unit") {
      formGrid = CONFIG.unit.fields.map(buildTextField).join("");
    } else if (type === "jenjang") {
      if (!units.length) {
        emptyFormNote =
          '<p class="settings-form__hint">Tambah data unit dulu agar bisa memilih unit untuk jenjang.</p>';
        formGrid = "";
        disableSubmit = true;
        disableOpen = true;
      } else {
        formGrid = buildFormGrid("jenjang", units, loadRows(JENJANG_KEY) || []);
      }
    } else {
      var jr = loadRows(JENJANG_KEY) || [];
      if (!jr.length) {
        emptyFormNote =
          '<p class="settings-form__hint">Lengkapi unit dan jenjang dulu, lalu kembali ke halaman ini.</p>';
        formGrid = "";
        disableSubmit = true;
        disableOpen = true;
      } else {
        formGrid = buildFormGrid("kelas", units, jr);
      }
    }

    root.innerHTML =
      '<div class="module-shell data-settings__intro">' +
      "<h2>" +
      escapeHtml(cfg.title) +
      "</h2><p>" +
      escapeHtml(cfg.description) +
      "</p></div>" +
      '<div class="module-shell data-settings__table-shell">' +
      '<div class="data-settings__table-head">' +
      '<h3 class="data-settings__h3">' +
      escapeHtml(cfg.listTitle) +
      "</h3>" +
      '<div class="data-settings__table-actions">' +
      (window.PresensiSieSettings
        ? '<button type="button" class="btn btn--ghost" id="settings-sync-sie">Sinkron dari data SIE</button>'
        : "") +
      '<button type="button" class="btn btn--primary" id="settings-open-modal"' +
      (disableOpen ? " disabled" : "") +
      ">Tambah</button></div></div>" +
      '<div class="table-wrap"><table class="data-table">' +
      "<thead id='settings-thead'></thead>" +
      "<tbody id='settings-table-body'></tbody>" +
      "</table></div></div>" +
      buildSettingsModal(cfg, formGrid, emptyFormNote, disableSubmit);

    var tbody = root.querySelector("#settings-table-body");
    var thead = root.querySelector("#settings-thead");
    renderTable(type, cfg, tbody, thead);
    syncModalScrollLock();
  }

  function handleSubmit(e, root) {
    var form = e.target;
    if (form.id !== "settings-add-form") return;
    e.preventDefault();
    if (form.querySelector('button[type="submit"][disabled]')) return;

    var type = root.getAttribute("data-settings-type");
    var cfg = CONFIG[type];
    var fd = new FormData(form);
    var row = { id: uid() };

    if (type === "unit") {
      row.kode = (fd.get("kode") || "").toString().trim();
      row.nama = (fd.get("nama") || "").toString().trim();
      row.alamat = (fd.get("alamat") || "").toString().trim();
      if (!row.kode || !row.nama) return;
    } else if (type === "jenjang") {
      row.unitId = (fd.get("unitId") || "").toString().trim();
      row.kode = (fd.get("kode") || "").toString().trim();
      row.nama = (fd.get("nama") || "").toString().trim();
      row.namaPanjang = (fd.get("namaPanjang") || "").toString().trim();
      row.keterangan = (fd.get("keterangan") || "").toString().trim();
      if (!row.unitId || !row.kode || !row.nama || !row.namaPanjang) return;
    } else if (type === "kelas") {
      row.jenjangId = (fd.get("jenjangId") || "").toString().trim();
      row.nama = (fd.get("nama") || "").toString().trim();
      row.tingkat = (fd.get("tingkat") || "").toString().trim();
      row.wali = (fd.get("wali") || "").toString().trim();
      if (!row.jenjangId || !row.nama || !row.tingkat) return;
    }

    var rows = loadRows(cfg.storageKey) || [];
    rows.push(row);
    saveRows(cfg.storageKey, rows);
    modalOpen = false;
    paint(root);
  }

  function handleDeleteClick(e, root) {
    var btn = e.target.closest("[data-delete]");
    if (!btn || !root.contains(btn)) return;

    var type = root.getAttribute("data-settings-type");
    var cfg = CONFIG[type];
    var id = btn.getAttribute("data-delete");

    if (type === "unit") {
      deleteUnitCascade(id);
    } else if (type === "jenjang") {
      deleteJenjangCascade(id);
    } else {
      var rows = loadRows(cfg.storageKey) || [];
      saveRows(
        cfg.storageKey,
        rows.filter(function (r) {
          return r.id !== id;
        })
      );
    }
    paint(root);
  }

  function init() {
    var root = document.getElementById("settings-root");
    if (!root) return;

    if (!root.dataset.settingsBound) {
      root.dataset.settingsBound = "1";
      root.addEventListener("submit", function (e) {
        handleSubmit(e, root);
      });
      root.addEventListener("click", function (e) {
        if (e.target.id === "settings-open-modal") {
          modalOpen = true;
          paint(root);
          var fm = root.querySelector("#settings-add-form");
          if (fm) {
            var inp = fm.querySelector("input, select, textarea");
            if (inp) inp.focus();
          }
          return;
        }
        if (e.target.id === "settings-sync-sie") {
          syncSettingsFromSie(root);
          return;
        }
        if (e.target.closest("[data-close-settings-modal]")) {
          modalOpen = false;
          paint(root);
          return;
        }
        handleDeleteClick(e, root);
      });
    }

    if (!document.documentElement.dataset.presensiSettingsModalEsc) {
      document.documentElement.dataset.presensiSettingsModalEsc = "1";
      document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;
        var r = document.getElementById("settings-root");
        if (!r || !r.querySelector("#settings-form-modal.modal--open")) return;
        modalOpen = false;
        paint(r);
      });
    }

    paint(root);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.presensiEnsureSettingsSeeds = function () {
    var units = loadRows(UNIT_KEY) || [];
    if (isLegacyDemoSettings(units)) {
      if (window.PresensiSieSettings && window.PresensiData && window.PresensiData.isRemote()) {
        window.PresensiSieSettings.syncIfNeeded();
        return;
      }
      applyCilegonSettings();
      return;
    }
    ensureUnits();
    ensureSeeded(CONFIG.jenjang);
    ensureSeeded(CONFIG.kelas);
    if (window.PresensiSieSettings && window.PresensiData && window.PresensiData.isRemote()) {
      if (window.PresensiSieSettings.isDemoOrEmpty()) {
        window.PresensiSieSettings.syncIfNeeded();
      }
    }
  };
  try {
    window.presensiEnsureSettingsSeeds();
  } catch (eSeed) {}
})();
