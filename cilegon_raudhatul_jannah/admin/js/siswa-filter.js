/**
 * Filter siswa bersama (Unit / Kelompok / Kelas / Tahun ajaran) untuk halaman admin
 * yang menampilkan data siswa. Karena data bisa mencapai puluhan ribu baris,
 * daftar TIDAK ditampilkan sekaligus — pengguna wajib memilih minimal satu
 * filter (atau mengetik pencarian) lebih dulu agar halaman tetap ringan.
 *
 * Dipakai oleh: data-siswa.js, rekam-data.js, saldo-siswa.js.
 */
(function (global) {
  "use strict";

  var FIELDS = {
    unit: ["code02", "CODE02"],
    kelompok: ["desc02", "DESC02"],
    kelas: ["desc03", "DESC03", "kelasId", "kelas_id"],
    tahun: ["desc04", "DESC04", "code04", "CODE04"],
  };

  /** Mulai sembunyikan daftar (wajib filter) bila baris melampaui ambang ini. */
  var GATE_THRESHOLD = 200;

  function needsGate(count) {
    return Number(count) > GATE_THRESHOLD;
  }

  function pick(row, aliases) {
    if (!row) return "";
    for (var i = 0; i < aliases.length; i++) {
      var v = row[aliases[i]];
      if (v != null && String(v).trim() !== "") {
        var s = String(v).trim();
        if (s.indexOf("seed-") === 0) continue;
        return s;
      }
    }
    return "";
  }

  function distinct(rows, aliases) {
    var seen = {};
    var out = [];
    (rows || []).forEach(function (r) {
      var v = pick(r, aliases);
      if (v && !seen[v]) {
        seen[v] = true;
        out.push(v);
      }
    });
    out.sort(function (a, b) {
      return a.localeCompare(b, "id", { numeric: true });
    });
    return out;
  }

  function emptySel() {
    return { unit: "", kelompok: "", kelas: "", tahun: "" };
  }

  function read(root) {
    var d = (root && root.dataset) || {};
    return {
      unit: d.fltUnit || "",
      kelompok: d.fltKelompok || "",
      kelas: d.fltKelas || "",
      tahun: d.fltTahun || "",
    };
  }

  function write(root, sel) {
    if (!root) return;
    root.dataset.fltUnit = (sel && sel.unit) || "";
    root.dataset.fltKelompok = (sel && sel.kelompok) || "";
    root.dataset.fltKelas = (sel && sel.kelas) || "";
    root.dataset.fltTahun = (sel && sel.tahun) || "";
  }

  function hasActive(sel) {
    return Boolean(sel && (sel.unit || sel.kelompok || sel.kelas || sel.tahun));
  }

  function isOpen(sel, searchQ) {
    return hasActive(sel) || (searchQ != null && String(searchQ).trim() !== "");
  }

  function apply(rows, sel) {
    if (!hasActive(sel)) return (rows || []).slice();
    return (rows || []).filter(function (r) {
      if (sel.unit && pick(r, FIELDS.unit) !== sel.unit) return false;
      if (sel.kelompok && pick(r, FIELDS.kelompok) !== sel.kelompok) return false;
      if (sel.kelas && pick(r, FIELDS.kelas) !== sel.kelas) return false;
      if (sel.tahun && pick(r, FIELDS.tahun) !== sel.tahun) return false;
      return true;
    });
  }

  function esc(s) {
    var d = document.createElement("div");
    d.textContent = s == null ? "" : s;
    return d.innerHTML;
  }

  function selectHtml(id, label, values, current, placeholder) {
    var opts = '<option value="">' + esc(placeholder) + "</option>";
    values.forEach(function (v) {
      opts +=
        '<option value="' +
        esc(v) +
        '"' +
        (current === v ? " selected" : "") +
        ">" +
        esc(v) +
        "</option>";
    });
    return (
      '<div class="siswa-filter__field">' +
      '<label class="settings-form__label" for="' +
      id +
      '">' +
      esc(label) +
      "</label>" +
      '<select id="' +
      id +
      '" class="settings-form__input siswa-filter__select">' +
      opts +
      "</select></div>"
    );
  }

  /**
   * HTML panel filter. `prefix` membedakan id antar halaman (siswa/rekam/saldo).
   */
  function controlsHtml(rows, sel, prefix) {
    var p = prefix || "siswa";
    sel = sel || emptySel();
    var units = distinct(rows, FIELDS.unit);
    var kelompokSource = sel.unit ? apply(rows, { unit: sel.unit }) : rows;
    var kelompokVals = distinct(kelompokSource, FIELDS.kelompok);
    var kelasSource = apply(rows, {
      unit: sel.unit || "",
      kelompok: sel.kelompok || "",
    });
    var kelasVals = distinct(kelasSource, FIELDS.kelas);
    var tahunVals = distinct(rows, FIELDS.tahun);
    var active = hasActive(sel);
    return (
      '<div class="module-shell siswa-filter" data-siswa-filter="' +
      esc(p) +
      '">' +
      '<div class="siswa-filter__head"><h3 class="data-settings__h3">Filter siswa</h3>' +
      '<p class="siswa-filter__hint">Pilih minimal satu filter (atau ketik pencarian) untuk menampilkan siswa. Daftar tidak dimuat sekaligus agar ringan.</p></div>' +
      '<div class="siswa-filter__grid">' +
      selectHtml(p + "-flt-unit", "Unit", units, sel.unit, "Semua unit") +
      selectHtml(p + "-flt-kelompok", "Kelompok", kelompokVals, sel.kelompok, "Semua kelompok") +
      selectHtml(p + "-flt-kelas", "Kelas", kelasVals, sel.kelas, "Semua kelas") +
      selectHtml(p + "-flt-tahun", "Tahun ajaran", tahunVals, sel.tahun, "Semua tahun") +
      "</div>" +
      (active
        ? '<div class="siswa-filter__foot"><button type="button" class="btn btn--ghost btn--small" id="' +
          p +
          '-flt-clear">Hapus filter</button></div>'
        : "") +
      "</div>"
    );
  }

  function gateRowHtml(colspan) {
    return (
      '<tr><td class="data-table__empty siswa-filter__gate" colspan="' +
      (colspan || 6) +
      '">Pilih <strong>filter</strong> (Unit / Kelompok / Kelas / Tahun ajaran) di atas, atau ketik <strong>pencarian</strong>, untuk menampilkan siswa.</td></tr>'
    );
  }

  /** Tangani perubahan select filter. Return true bila ditangani. */
  function handleChange(root, target, prefix) {
    var p = prefix || "siswa";
    if (!target || !target.id) return false;
    var sel = read(root);
    if (target.id === p + "-flt-unit") {
      sel.unit = target.value || "";
      sel.kelompok = "";
      sel.kelas = "";
      write(root, sel);
      return true;
    }
    if (target.id === p + "-flt-kelompok") {
      sel.kelompok = target.value || "";
      sel.kelas = "";
      write(root, sel);
      return true;
    }
    if (target.id === p + "-flt-kelas") {
      sel.kelas = target.value || "";
      write(root, sel);
      return true;
    }
    if (target.id === p + "-flt-tahun") {
      sel.tahun = target.value || "";
      write(root, sel);
      return true;
    }
    return false;
  }

  /** Tangani klik tombol "Hapus filter". Return true bila ditangani. */
  function handleClick(root, target, prefix) {
    var p = prefix || "siswa";
    if (!target) return false;
    var hit = target.id === p + "-flt-clear" || (target.closest && target.closest("#" + p + "-flt-clear"));
    if (hit) {
      write(root, emptySel());
      return true;
    }
    return false;
  }

  global.PresensiSiswaFilter = {
    FIELDS: FIELDS,
    GATE_THRESHOLD: GATE_THRESHOLD,
    needsGate: needsGate,
    pick: pick,
    distinct: distinct,
    read: read,
    write: write,
    hasActive: hasActive,
    isOpen: isOpen,
    apply: apply,
    controlsHtml: controlsHtml,
    gateRowHtml: gateRowHtml,
    handleChange: handleChange,
    handleClick: handleClick,
  };
})(typeof window !== "undefined" ? window : globalThis);
