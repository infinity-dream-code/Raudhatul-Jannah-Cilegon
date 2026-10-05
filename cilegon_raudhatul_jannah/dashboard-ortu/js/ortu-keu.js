/**
 * Helper & render keuangan dashboard orang tua.
 */
(function (global) {
  var ICON_CARD = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>';
  var ICON_CAL = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
  var ICON_WALLET = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>';
  var ICON_CHEV = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>';

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseAmount(value) {
    if (value == null || value === '') return 0;
    if (typeof value === 'number' && !isNaN(value)) return value;

    var s = String(value).trim().replace(/[^\d.,-]/g, '');
    if (!s) return 0;

    var lastComma = s.lastIndexOf(',');
    var lastDot = s.lastIndexOf('.');

    if (lastComma > -1 && lastDot > -1) {
      if (lastComma > lastDot) {
        s = s.replace(/\./g, '').replace(',', '.');
      } else {
        s = s.replace(/,/g, '');
      }
    } else if (lastDot > -1) {
      // Format ID: 400.000 atau 1.000.000 — titik sebagai pemisah ribuan
      if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
        s = s.replace(/\./g, '');
      }
    } else if (lastComma > -1) {
      var parts = s.split(',');
      if (parts.length === 2 && parts[1].length === 3 && /^\d+$/.test(parts[1])) {
        s = parts.join('');
      } else {
        s = s.replace(',', '.');
      }
    }

    var num = Number(s);
    return isNaN(num) ? 0 : num;
  }

  function formatRupiah(n, opts) {
    opts = opts || {};
    var num = parseAmount(n);
    if (opts.dotPrefix) return 'Rp.' + num.toLocaleString('id-ID');
    if (opts.short) return 'Rp' + num.toLocaleString('id-ID');
    return 'Rp ' + num.toLocaleString('id-ID');
  }

  function fieldRow(icon, label, value) {
    return (
      '<div class="ortu-field-row">' +
        '<div class="ortu-field-row__icon" aria-hidden="true">' + icon + '</div>' +
        '<div><div class="ortu-field-row__label">' + escapeHtml(label) + '</div>' +
        '<div class="ortu-field-row__value">' + escapeHtml(value) + '</div></div>' +
      '</div>'
    );
  }

  function isBlank(value) {
    var s = String(value == null ? '' : value).trim();
    return s === '' || s === 'null';
  }

  function normalizeDetList(det) {
    if (!Array.isArray(det)) return [];
    return det.filter(function (row) {
      return row && (
        !isBlank(row.KodePost) ||
        !isBlank(row.NamaPost) ||
        !isBlank(row.DetailNominal)
      );
    });
  }

  function renderDetAccordion(panelId, title, det, extraRows) {
    extraRows = extraRows || [];
    var rows = normalizeDetList(det).concat(extraRows);
    if (!rows.length) return '';

    var body = rows.map(function (row) {
      var metaParts = [row.KodePost, formatMetaText(row.NamaPost)].filter(function (v) { return !isBlank(v); });
      var meta = metaParts.map(escapeHtml).join('<br>');
      var amount = row.DetailNominal != null ? formatRupiah(parseAmount(row.DetailNominal), { dotPrefix: true }) : '';
      return (
        '<div class="ortu-riwayat-item">' +
          '<div class="ortu-riwayat-item__meta">' + meta + '</div>' +
          (amount ? '<div class="ortu-riwayat-item__amount">' + escapeHtml(amount) + '</div>' : '') +
        '</div>'
      );
    }).join('');

    return (
      '<div class="ortu-bill-card__footer">' +
        '<button type="button" class="ortu-accordion-btn" data-ortu-accordion aria-expanded="false" aria-controls="' + panelId + '">' +
          escapeHtml(title) + ICON_CHEV +
        '</button>' +
        '<div class="ortu-accordion-panel" id="' + panelId + '" hidden>' + body + '</div>' +
      '</div>'
    );
  }

  function sumTagihan(items) {
    return (items || []).reduce(function (sum, item) {
      return sum + parseAmount(item.TotalNominal);
    }, 0);
  }

  function renderTagihanList(items) {
    if (!items || !items.length) {
      return '<div class="ortu-empty-state"><h2>Belum ada tagihan</h2><p>Tidak ada data tagihan aktif.</p></div>';
    }

    return items.map(function (item, idx) {
      var panelId = 'tagihan-det-' + idx;
      var extra = [];
      if (!isBlank(item.ALLOW)) extra.push({ KodePost: item.ALLOW, NamaPost: item.CICIL === '1' ? 'Cicilan' : 'Status', DetailNominal: '' });

      return (
        '<article class="ortu-bill-card">' +
          '<div class="ortu-bill-card__body">' +
            fieldRow(ICON_CARD, 'Nama Tagihan', item.NamaTagihan || '—') +
            fieldRow(ICON_CAL, 'Periode', item.TahunAkademik || '—') +
            fieldRow(ICON_WALLET, 'Jumlah Tagihan', formatRupiah(item.TotalNominal, { dotPrefix: true })) +
          '</div>' +
          renderDetAccordion(panelId, 'Detail Tagihan', item.det, extra) +
        '</article>'
      );
    }).join('');
  }

  function renderPaymentList(items) {
    if (!items || !items.length) {
      return '<div class="ortu-empty-state"><h2>Belum ada pembayaran</h2><p>Riwayat pembayaran kosong.</p></div>';
    }

    return items.map(function (item, idx) {
      var panelId = 'pay-det-' + idx;
      var rawTanggal = item.TanggalBayar || (item.det && item.det[0] && item.det[0].NamaPost) || '';
      var tanggal = rawTanggal ? formatTxDate(rawTanggal, { withTime: true }) : '—';

      return (
        '<article class="ortu-bill-card">' +
          '<div class="ortu-bill-card__body">' +
            fieldRow(ICON_CARD, 'Nama Tagihan', item.NamaTagihan || '—') +
            fieldRow(ICON_CAL, 'Tahun Akademik', item.TahunAkademik || '—') +
            fieldRow(ICON_CAL, 'Tanggal Bayar', tanggal) +
            fieldRow(ICON_WALLET, 'Jumlah Tagihan', formatRupiah(item.TotalNominal, { dotPrefix: true })) +
          '</div>' +
          '<div class="ortu-status-badge ortu-status-badge--lunas">Lunas</div>' +
          renderDetAccordion(panelId, 'Riwayat Pembayaran', item.det) +
          '<button type="button" class="ortu-btn--receipt" data-ortu-receipt="' + idx + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' +
            'Cetak Kuitansi' +
          '</button>' +
        '</article>'
      );
    }).join('');
  }

  function parseApiDate(value) {
    if (value instanceof Date) {
      return isNaN(value.getTime()) ? null : value;
    }
    if (isBlank(value)) return null;
    var s = String(value).trim();
    var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
    if (m) {
      return new Date(
        Number(m[1]),
        Number(m[2]) - 1,
        Number(m[3]),
        Number(m[4] || 0),
        Number(m[5] || 0),
        Number(m[6] || 0)
      );
    }
    var d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
  }

  function formatTxDate(value, opts) {
    opts = opts || {};
    var d = parseApiDate(value);
    if (!d) return isBlank(value) ? '—' : String(value).trim();
    if (opts.withTime) {
      return d.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      });
    }
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  }

  function formatTxTime(value) {
    var d = parseApiDate(value);
    if (!d) return '';
    return d.toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
  }

  function formatMetaText(value) {
    if (isBlank(value)) return '';
    if (/^\d{4}-\d{2}-\d{2}/.test(String(value).trim())) {
      return formatTxDate(value, { withTime: true });
    }
    return String(value).trim();
  }

  function txDateValue(tx) {
    if (!tx) return '';
    return tx.TGL || tx.Tanggal || tx.tanggal || tx.WAKTU || tx.Waktu || '';
  }

  function txLabel(tx, fallback) {
    return tx.KETERANGAN || tx.Keterangan || tx.KET || tx.NAMAMERCHANT || tx.Merchant || fallback || 'TRANSAKSI';
  }

  function txDebetValue(tx) {
    if (tx.DEBET != null && String(tx.DEBET).trim() !== '') return tx.DEBET;
    if (tx.Debet != null && String(tx.Debet).trim() !== '') return tx.Debet;
    return 0;
  }

  function txKreditValue(tx) {
    if (tx.KREDIT != null && String(tx.KREDIT).trim() !== '') return tx.KREDIT;
    if (tx.Kredit != null && String(tx.Kredit).trim() !== '') return tx.Kredit;
    if (tx.NOMINAL != null && String(tx.NOMINAL).trim() !== '') return tx.NOMINAL;
    if (tx.Nominal != null && String(tx.Nominal).trim() !== '') return tx.Nominal;
    return 0;
  }

  function filterTransaksiByDate(items, dateFrom, dateTo) {
    items = items || [];
    var from = parseDateInput(dateFrom);
    var to = parseDateInput(dateTo);
    if (!from && !to) return items.slice();

    if (from && to && from > to) {
      var tmp = from;
      from = to;
      to = tmp;
    }

    return items.filter(function (tx) {
      var parsed = parseApiDate(txDateValue(tx));
      if (!parsed) return false;
      var day = startOfDay(parsed);
      if (from && day < from) return false;
      if (to && day > to) return false;
      return true;
    });
  }

  function parseDateInput(value) {
    if (isBlank(value)) return null;
    var parts = String(value).trim().split('-');
    if (parts.length !== 3) return null;
    var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    return isNaN(d.getTime()) ? null : startOfDay(d);
  }

  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function renderTxCard(tx, fallbackLabel) {
    return (
      '<article class="ortu-tx-card">' +
        '<div class="ortu-tx-card__head">' + escapeHtml(txLabel(tx, fallbackLabel)) + '</div>' +
        '<div class="ortu-tx-card__date">' + escapeHtml(formatTxDate(txDateValue(tx), { withTime: true })) + '</div>' +
        '<div class="ortu-tx-card__amounts">' +
          '<div class="ortu-tx-card__col">' +
            '<div class="ortu-tx-card__col-label">Debet</div>' +
            '<div class="ortu-tx-card__col-value">' + parseAmount(txDebetValue(tx)).toLocaleString('id-ID') + '</div>' +
          '</div>' +
          '<div class="ortu-tx-card__col">' +
            '<div class="ortu-tx-card__col-label">Kredit</div>' +
            '<div class="ortu-tx-card__col-value">' + parseAmount(txKreditValue(tx)).toLocaleString('id-ID') + '</div>' +
          '</div>' +
        '</div>' +
      '</article>'
    );
  }

  function renderTransaksiList(items, opts) {
    opts = opts || {};
    if (!items || !items.length) {
      var msg = opts.filtered
        ? 'Tidak ada transaksi pada rentang tanggal ini.'
        : 'Riwayat transaksi kosong.';
      return '<div class="ortu-empty-state"><h2>Belum ada transaksi</h2><p>' + escapeHtml(msg) + '</p></div>';
    }

    return items.map(function (tx) {
      return renderTxCard(tx, 'TRANSAKSI');
    }).join('');
  }

  function renderTransaksiKantinList(items, opts) {
    opts = opts || {};
    if (!items || !items.length) {
      var msg = opts.filtered
        ? 'Tidak ada transaksi kantin pada rentang tanggal ini.'
        : 'Riwayat transaksi kantin kosong.';
      return '<div class="ortu-empty-state"><h2>Belum ada transaksi</h2><p>' + escapeHtml(msg) + '</p></div>';
    }

    return items.map(function (tx) {
      return renderTxCard(tx, 'TRANSAKSI KANTIN');
    }).join('');
  }

  function formatDateRange(dates) {
    if (!dates || !dates.length) return '—';
    var first = dates[0];
    var last = dates[dates.length - 1];
    if (first.toDateString() === last.toDateString()) {
      return formatTxDate(first) + ' · ' + formatTxTime(first) + ' – ' + formatTxTime(last);
    }
    return formatTxDate(first, { withTime: true }) + ' s/d ' + formatTxDate(last, { withTime: true });
  }

  function transaksiSummary(items) {
    items = items || [];
    var totalDebet = 0;
    var totalKredit = 0;
    var dates = [];
    items.forEach(function (tx) {
      totalDebet += parseAmount(txDebetValue(tx));
      totalKredit += parseAmount(txKreditValue(tx));
      var parsed = parseApiDate(txDateValue(tx));
      if (parsed) dates.push(parsed);
    });
    dates.sort(function (a, b) { return a - b; });
    return {
      count: items.length,
      totalDebet: totalDebet,
      totalKredit: totalKredit,
      range: formatDateRange(dates),
    };
  }

  function loadingHtml(message) {
    return '<div class="ortu-empty-state"><p>' + escapeHtml(message || 'Memuat data…') + '</p></div>';
  }

  function errorHtml(message) {
    return '<div class="ortu-empty-state"><h2>Gagal memuat</h2><p>' + escapeHtml(message || 'Terjadi kesalahan.') + '</p></div>';
  }

  function getUsername() {
    if (!global.OrtuAuth) return '';
    var s = OrtuAuth.getSession();
    return s ? String(s.username || s.nis || '').replace(/\D/g, '') : '';
  }

  global.OrtuKeu = {
    escapeHtml: escapeHtml,
    parseAmount: parseAmount,
    formatRupiah: formatRupiah,
    sumTagihan: sumTagihan,
    renderTagihanList: renderTagihanList,
    renderPaymentList: renderPaymentList,
    renderTransaksiList: renderTransaksiList,
    renderTransaksiKantinList: renderTransaksiKantinList,
    filterTransaksiByDate: filterTransaksiByDate,
    transaksiSummary: transaksiSummary,
    loadingHtml: loadingHtml,
    errorHtml: errorHtml,
    getUsername: getUsername,
  };
})(typeof window !== 'undefined' ? window : globalThis);
