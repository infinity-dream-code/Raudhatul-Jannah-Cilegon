(function () {
  var UNIT_KEY = "presensi_setting_unit";
  var JENJANG_KEY = "presensi_setting_jenjang";
  var KELAS_KEY = "presensi_setting_kelas";
  var CONTEXT_KEY = "presensi_modul_context";

  var root;
  var state = {
    units: [],
    jenjang: [],
    kelas: [],
  };

  function unitLabel(u) {
    if (window.PresensiSieSettings && window.PresensiSieSettings.unitDisplay) {
      return window.PresensiSieSettings.unitDisplay(u);
    }
    return u ? u.nama : "";
  }

  function jenjangLabel(j) {
    if (window.PresensiSieSettings && window.PresensiSieSettings.jenjangDisplay) {
      return window.PresensiSieSettings.jenjangDisplay(j);
    }
    return j ? j.nama : "";
  }

  function kelasLabel(k) {
    if (window.PresensiSieSettings && window.PresensiSieSettings.kelasDisplay) {
      return window.PresensiSieSettings.kelasDisplay(k);
    }
    return k ? k.nama : "";
  }

  function $(id) {
    return root ? root.querySelector("#" + id) : null;
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

  function saveContext(payload) {
    try {
      localStorage.setItem(CONTEXT_KEY, JSON.stringify(payload || {}));
    } catch (e) {}
  }

  function loadContext() {
    try {
      var raw = localStorage.getItem(CONTEXT_KEY);
      if (!raw) return null;
      var data = JSON.parse(raw);
      return data && typeof data === "object" ? data : null;
    } catch (e) {
      return null;
    }
  }

  function setOptions(selectEl, rows, placeholder, labelBuilder) {
    if (!selectEl) return;
    var html = ['<option value="">' + placeholder + "</option>"];
    (rows || []).forEach(function (row) {
      html.push('<option value="' + row.id + '">' + labelBuilder(row) + "</option>");
    });
    selectEl.innerHTML = html.join("");
  }

  function showStatus(text, kind) {
    var el = $("modul-context-status");
    if (!el) return;
    el.className = "modul-context__status" + (kind ? " modul-context__status--" + kind : "");
    el.textContent = text || "";
  }

  function getNamedMap(rows) {
    var map = {};
    (rows || []).forEach(function (r) {
      map[r.id] = r;
    });
    return map;
  }

  function getSelectedContext() {
    var unitId = $("modul-unit").value;
    var jenjangId = $("modul-jenjang").value;
    var kelasId = $("modul-kelas").value;
    var note = $("modul-catatan").value || "";
    var unitById = getNamedMap(state.units);
    var jenjangById = getNamedMap(state.jenjang);
    var kelasById = getNamedMap(state.kelas);
    var unit = unitById[unitId] || null;
    var jenjang = jenjangById[jenjangId] || null;
    var kelas = kelasById[kelasId] || null;
    return {
      unitId: unitId,
      unitNama: unit ? unitLabel(unit) : "",
      jenjangId: jenjangId,
      jenjangNama: jenjang ? jenjangLabel(jenjang) : "",
      kelasId: kelasId,
      kelasNama: kelas ? kelasLabel(kelas) : "",
      catatan: note.trim(),
      updatedAt: new Date().toISOString(),
    };
  }

  function persistContextIfValid() {
    var data = getSelectedContext();
    if (!data.unitId || !data.jenjangId || !data.kelasId) {
      return false;
    }
    saveContext(data);
    showStatus(
      "Konteks presensi tersimpan: " +
        data.unitNama +
        " / " +
        data.jenjangNama +
        " / " +
        data.kelasNama +
        ".",
      "ok"
    );
    return true;
  }

  function rebuildJenjang() {
    var unitId = $("modul-unit").value;
    var filtered = state.jenjang.filter(function (j) {
      return j.unitId === unitId;
    });
    setOptions($("modul-jenjang"), filtered, "Pilih jenjang", function (j) {
      return jenjangLabel(j);
    });
    setOptions($("modul-kelas"), [], "Pilih kelas", function (k) {
      return kelasLabel(k);
    });
  }

  function rebuildKelas() {
    var jenjangId = $("modul-jenjang").value;
    var filtered = state.kelas.filter(function (k) {
      return k.jenjangId === jenjangId;
    });
    setOptions($("modul-kelas"), filtered, "Pilih kelas", function (k) {
      return kelasLabel(k);
    });
  }

  function hydrateSavedContext() {
    var saved = loadContext();
    if (!saved) return;
    if (saved.unitId) {
      $("modul-unit").value = saved.unitId;
      rebuildJenjang();
    }
    if (saved.jenjangId) {
      $("modul-jenjang").value = saved.jenjangId;
      rebuildKelas();
    }
    if (saved.kelasId) {
      $("modul-kelas").value = saved.kelasId;
    }
    if (saved.catatan) {
      $("modul-catatan").value = saved.catatan;
    }
    persistContextIfValid();
  }

  function onChange(e) {
    var id = e.target && e.target.id;
    if (id === "modul-unit") {
      rebuildJenjang();
      showStatus("Pilih jenjang dan kelas untuk melanjutkan.", "");
      return;
    }
    if (id === "modul-jenjang") {
      rebuildKelas();
      showStatus("Pilih kelas untuk melanjutkan.", "");
      return;
    }
    persistContextIfValid();
  }

  function onModuleClick(e) {
    var card = e.target.closest(".module-card");
    if (!card) return;
    if (persistContextIfValid()) return;
    e.preventDefault();
    showStatus("Lengkapi pilihan unit, jenjang, dan kelas sebelum masuk modul.", "warn");
    var unitEl = $("modul-unit");
    if (unitEl) unitEl.focus();
  }

  function reloadSettingsState() {
    state.units = loadRows(UNIT_KEY);
    state.jenjang = loadRows(JENJANG_KEY);
    state.kelas = loadRows(KELAS_KEY);
  }

  function bindForm() {
    setOptions($("modul-unit"), state.units, "Pilih unit", function (u) {
      return unitLabel(u);
    });
    setOptions($("modul-jenjang"), [], "Pilih jenjang", function (j) {
      return jenjangLabel(j);
    });
    setOptions($("modul-kelas"), [], "Pilih kelas", function (k) {
      return kelasLabel(k);
    });

    if (!state.units.length || !state.jenjang.length || !state.kelas.length) {
      showStatus("Data unit/jenjang/kelas belum lengkap. Sinkron dari SIE di Pengaturan → Unit.", "warn");
    } else {
      showStatus("Isi pilihan lokasi presensi sebelum membuka modul.", "");
    }

    hydrateSavedContext();
  }

  function init() {
    root = document;
    var form = $("modul-context-form");
    if (!form || form.dataset.bound) return;
    form.dataset.bound = "1";

    reloadSettingsState();

    if (window.PresensiSieSettings && window.PresensiSieSettings.syncIfNeeded) {
      window.PresensiSieSettings.syncIfNeeded().then(function () {
        reloadSettingsState();
        bindForm();
      });
    } else {
      bindForm();
    }

    form.addEventListener("change", onChange);
    form.addEventListener("input", function () {
      persistContextIfValid();
    });
    document.addEventListener("click", onModuleClick);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
