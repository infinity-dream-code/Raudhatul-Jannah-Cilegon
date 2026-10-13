@extends('layouts.admin_new')

@section('content')
    <div class="sc-page">
        <div class="page-heading sc-page-heading">
            <h2>Data Kartu Siswa</h2>
            <p>Smartcard / Setting daftar kartu siswa</p>
        </div>

        <div class="card sc-card">
            <div class="sc-card-body">
                @if (session('smartcard_success'))
                    <div class="sc-alert sc-alert-success">{{ session('smartcard_success') }}</div>
                @endif
                @if (session('smartcard_error'))
                    <div class="sc-alert sc-alert-error">{{ session('smartcard_error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="sc-alert sc-alert-error">
                        @foreach ($errors->all() as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <form id="formSearch" method="GET" action="{{ route('smartcard.data_kartu') }}">
                    <input type="hidden" name="search" value="1">
                    <input type="hidden" id="custidHidden" name="custid" value="{{ (int) ($custid ?? 0) }}">

                    <div class="sc-form-grid">
                        <div class="sc-field sc-field-nis">
                            <label for="siswaSearchInput">No Induk Siswa</label>
                            <div id="siswaAutoWrap" class="sc-siswa-wrap">
                                <div class="sc-control-wrap">
                                    <input type="text" id="siswaSearchInput" name="siswa_search" autocomplete="off"
                                           value="{{ $siswaLabel ?? '' }}" placeholder="Ketik NIS / nama, pilih dari dropdown">
                                </div>
                                <div id="siswaAutoList"></div>
                            </div>
                        </div>
                        <div class="sc-field sc-field-kartu">
                            <label for="noKartuInput">No Kartu RFID</label>
                            <div class="sc-control-wrap sc-control-kartu sc-control-kartu-scan">
                                <input type="text" id="noKartuInput" name="no_kartu" value="{{ $noKartu ?? '' }}"
                                       placeholder="Tap kartu RFID / isi manual" autocomplete="off" inputmode="numeric" maxlength="16">
                                <button type="button" id="btnTapRfid" class="sc-scan-btn" title="Tap kartu RFID">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="5" width="13" height="14" rx="2"/>
                                        <path d="M19 8a6 6 0 0 1 0 8"/>
                                        <path d="M21.5 5.5a10 10 0 0 1 0 13"/>
                                    </svg>
                                    <span>Tap RFID</span>
                                </button>
                            </div>
                            <small id="rfidHint" class="sc-rfid-hint" hidden>Menunggu tap kartu RFID di reader…</small>
                        </div>
                        <div class="sc-field">
                            <label for="namaSiswa">Nama</label>
                            <div class="sc-control-wrap sc-control-readonly">
                                <input type="text" id="namaSiswa" value="{{ $nama ?? '' }}" readonly tabindex="-1" placeholder="Otomatis dari NIS">
                            </div>
                        </div>
                        <div class="sc-field">
                            <label for="pinInput">PIN</label>
                            <div class="sc-control-wrap sc-control-readonly">
                                <input type="text" id="pinInput" name="pin" value="{{ $pin ?? '123' }}" placeholder="123" maxlength="6">
                            </div>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <button type="submit" class="sc-btn">Lihat</button>
                        <button type="submit" class="sc-btn sc-btn-primary" form="formSave">Simpan</button>
                    </div>
                </form>

                <form id="formSave" method="POST" action="{{ route('smartcard.data_kartu.store') }}">
                    @csrf
                    <input type="hidden" name="custid" id="custidSave" value="{{ (int) ($custid ?? 0) }}">
                    <input type="hidden" name="no_kartu" id="noKartuSave" value="{{ $noKartu ?? '' }}">
                    <input type="hidden" name="pin" id="pinSave" value="{{ $pin ?? '123' }}">
                </form>

                <div class="sc-table-section">
                    <div class="sc-table-title">
                        Daftar Kartu Siswa
                        @if ($isSearch ?? false)
                            <span class="sc-table-subtitle">— hasil pencarian</span>
                        @endif
                    </div>
                    <div class="sc-table-wrap">
                        <table class="sc-table">
                            <thead>
                                <tr>
                                    <th style="width:64px;">No</th>
                                    <th>NIS</th>
                                    <th>Nama</th>
                                    <th>No Kartu</th>
                                    <th style="width:150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($kartuRows ?? null) as $index => $row)
                                    @php($isBlokir = trim((string) ($row->blokir ?? '0')) === '1')
                                    <tr>
                                        <td>{{ ($kartuRows->firstItem() ?? 0) + $index }}</td>
                                        <td>{{ $row->nis ?? '—' }}</td>
                                        <td>{{ $row->nama ?? '—' }}</td>
                                        <td>{{ $row->no_kartu ?? '—' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('smartcard.data_kartu.blokir') }}" class="sc-blokir-form"
                                                  data-confirm="{{ $isBlokir ? 'Buka blokir' : 'Blokir' }} kartu {{ trim((string) $row->no_kartu) }} milik {{ $row->nama }}?">
                                                @csrf
                                                <input type="hidden" name="pid" value="{{ trim((string) $row->no_kartu) }}">
                                                <input type="hidden" name="blokir" value="{{ $isBlokir ? '0' : '1' }}">
                                                <label class="sc-switch" title="{{ $isBlokir ? 'Klik untuk buka blokir' : 'Klik untuk blokir' }}">
                                                    <input type="checkbox" class="sc-blokir-toggle" @checked($isBlokir)>
                                                    <span class="sc-switch-slider"></span>
                                                    <span class="sc-switch-text {{ $isBlokir ? 'is-blokir' : '' }}">{{ $isBlokir ? 'Diblokir' : 'Aktif' }}</span>
                                                </label>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="sc-empty">Data kartu tidak ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (isset($kartuRows) && method_exists($kartuRows, 'hasPages'))
                        <div class="sc-pagination-wrap">
                            <div class="sc-pagination-info">
                                Menampilkan {{ $kartuRows->firstItem() ?? 0 }} sampai {{ $kartuRows->lastItem() ?? 0 }} dari {{ $kartuRows->total() ?? 0 }} entri
                            </div>
                            @if ($kartuRows->hasPages())
                                <div class="sc-pagination">
                                    @php
                                        $current = $kartuRows->currentPage();
                                        $last = $kartuRows->lastPage();
                                        $start = max(1, $current - 2);
                                        $end = min($last, $current + 2);
                                    @endphp
                                    @if ($kartuRows->onFirstPage())
                                        <span class="sc-page-link disabled">Sebelumnya</span>
                                    @else
                                        <a class="sc-page-link" href="{{ $kartuRows->previousPageUrl() }}">Sebelumnya</a>
                                    @endif

                                    @for ($page = $start; $page <= $end; $page++)
                                        @if ($page === $current)
                                            <span class="sc-page-link active">{{ $page }}</span>
                                        @else
                                            <a class="sc-page-link" href="{{ $kartuRows->url($page) }}">{{ $page }}</a>
                                        @endif
                                    @endfor

                                    @if ($kartuRows->hasMorePages())
                                        <a class="sc-page-link" href="{{ $kartuRows->nextPageUrl() }}">Selanjutnya</a>
                                    @else
                                        <span class="sc-page-link disabled">Selanjutnya</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .sc-blokir-form { margin: 0; }
        .sc-switch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            margin: 0;
            position: relative;
        }
        .sc-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .sc-switch-slider {
            width: 40px;
            height: 22px;
            border-radius: 999px;
            background: #d1d5db;
            position: relative;
            transition: background .2s;
            flex-shrink: 0;
        }
        .sc-switch-slider::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
            transition: transform .2s;
        }
        .sc-switch input:checked + .sc-switch-slider { background: #ef4444; }
        .sc-switch input:checked + .sc-switch-slider::after { transform: translateX(18px); }
        .sc-switch input:disabled + .sc-switch-slider { opacity: .6; }
        .sc-switch-text { font-size: 13px; font-weight: 600; color: #047857; }
        .sc-switch-text.is-blokir { color: #b91c1c; }
        .sc-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 8px 20px 32px;
        }
        @media (min-width: 1200px) {
            .sc-page { padding-left: 28px; padding-right: 28px; }
        }
        .sc-page-heading {
            margin-bottom: 20px;
            padding: 0 4px;
        }
        .sc-page-heading p {
            margin-bottom: 0;
            color: #6b7280;
        }
        .sc-card {
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: visible;
        }
        .sc-card-body {
            padding: 28px 28px 32px;
        }
        @media (max-width: 768px) {
            .sc-page { padding-left: 12px; padding-right: 12px; }
            .sc-card-body { padding: 20px 16px 24px; }
        }
        .sc-alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .sc-alert-success {
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }
        .sc-alert-error {
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }
        .sc-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(280px, 1fr));
            gap: 20px 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .sc-form-grid { grid-template-columns: 1fr; }
        }
        .sc-field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #5b21b6;
            background: linear-gradient(135deg, #ede9fe 0%, #f3e8ff 100%);
            padding: 10px 14px;
            border-radius: 10px 10px 0 0;
            margin: 0;
            border: 1px solid #ddd6fe;
            border-bottom: 0;
        }
        .sc-field .sc-control-wrap {
            border: 1px solid #ddd6fe;
            border-top: 0;
            border-radius: 0 0 10px 10px;
            background: #fff;
        }
        .sc-field-nis {
            position: relative;
            z-index: 1;
        }
        .sc-field-nis.sc-dropdown-open {
            z-index: 50;
        }
        .sc-siswa-wrap {
            position: relative;
        }
        .sc-field input[type="text"],
        .sc-field input[readonly] {
            width: 100%;
            height: 44px;
            border: 0;
            padding: 0 14px;
            font-size: 14px;
            background: transparent;
        }
        .sc-control-readonly { background: #f8fafc; }
        .sc-control-readonly input { background: #f8fafc; }
        .sc-control-kartu { background: #fffbeb; }
        .sc-control-kartu input { background: #fffbeb; }
        .sc-control-kartu-scan {
            display: flex;
            align-items: stretch;
            overflow: hidden;
        }
        .sc-control-kartu-scan input {
            flex: 1;
            min-width: 0;
        }
        .sc-scan-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            border: 0;
            border-left: 1px solid #fcd34d;
            background: #fef3c7;
            color: #92400e;
            padding: 0 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s;
        }
        .sc-scan-btn:hover {
            background: #fde68a;
        }
        .sc-scan-btn.is-active {
            background: #f59e0b;
            color: #fff;
            border-left-color: #d97706;
        }
        .sc-rfid-hint {
            display: block;
            margin-top: 8px;
            color: #a16207;
            font-size: 12px;
            font-weight: 600;
        }
        #siswaAutoList {
            display: none;
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 200;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            max-height: 240px;
            overflow: auto;
            box-shadow: 0 12px 32px rgba(0,0,0,.12);
        }
        .sc-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 28px;
            padding-top: 4px;
        }
        .sc-btn {
            min-width: 120px;
            height: 42px;
            padding: 0 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            transition: background .15s, border-color .15s;
        }
        .sc-btn:hover { background: #f9fafb; }
        .sc-btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .sc-btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        .sc-table-section {
            margin-top: 8px;
            padding-top: 24px;
            border-top: 1px solid #eef2f7;
        }
        .sc-table-title {
            font-size: 14px;
            font-weight: 800;
            color: #374151;
            margin-bottom: 14px;
            letter-spacing: 0.02em;
        }
        .sc-table-subtitle {
            font-weight: 600;
            color: #6b7280;
            font-size: 13px;
        }
        .sc-pagination-wrap {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 14px;
            padding: 4px 2px 0;
        }
        .sc-pagination-info {
            font-size: 13px;
            color: #6b7280;
        }
        .sc-pagination {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .sc-page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            background: #fff;
            text-decoration: none;
        }
        .sc-page-link:hover:not(.disabled):not(.active) {
            background: #f3f4f6;
        }
        .sc-page-link.active {
            background: #7c3aed;
            border-color: #7c3aed;
            color: #fff;
        }
        .sc-page-link.disabled {
            color: #9ca3af;
            background: #f9fafb;
            cursor: not-allowed;
        }
        .sc-table-wrap {
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            max-height: 460px;
            background: #fff;
        }
        .sc-table {
            width: 100%;
            min-width: 520px;
            border-collapse: collapse;
            font-size: 14px;
        }
        .sc-table thead th {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: #fff;
            font-weight: 700;
            padding: 12px 16px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .sc-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #eef2f7;
        }
        .sc-table tbody tr:nth-child(even) { background: #f8fafc; }
        .sc-table tbody tr:nth-child(odd) { background: #fff; }
        .sc-table tbody tr:hover { background: #f5f3ff; }
        .sc-empty {
            text-align: center;
            color: #6b7280;
            padding: 32px 16px !important;
        }
    </style>

    <script>
        (function () {
            const siswaSearchUrl = @json(route('smartcard.data_kartu.siswa_search'));
            const siswaInput = document.getElementById('siswaSearchInput');
            const custidHidden = document.getElementById('custidHidden');
            const custidSave = document.getElementById('custidSave');
            const namaSiswa = document.getElementById('namaSiswa');
            const noKartuInput = document.getElementById('noKartuInput');
            const noKartuSave = document.getElementById('noKartuSave');
            const pinInput = document.getElementById('pinInput');
            const pinSave = document.getElementById('pinSave');
            const siswaList = document.getElementById('siswaAutoList');
            const siswaWrap = document.getElementById('siswaAutoWrap');
            const nisField = document.querySelector('.sc-field-nis');
            const btnTapRfid = document.getElementById('btnTapRfid');
            const rfidHint = document.getElementById('rfidHint');
            let searchTimer = null;
            let searchSeq = 0;

            const normalizeRfid = function (raw) {
                return String(raw || '').replace(/\s+/g, '').trim();
            };

            const syncSaveFields = function () {
                if (custidSave) custidSave.value = custidHidden ? custidHidden.value : '';
                if (noKartuSave && noKartuInput) noKartuSave.value = normalizeRfid(noKartuInput.value);
                if (pinSave && pinInput) pinSave.value = pinInput.value || '123';
            };

            const setRfidWaiting = function (waiting) {
                if (rfidHint) rfidHint.hidden = !waiting;
                if (btnTapRfid) btnTapRfid.classList.toggle('is-active', waiting);
            };

            const startRfidTap = function () {
                if (!noKartuInput) return;
                noKartuInput.value = '';
                syncSaveFields();
                setRfidWaiting(true);
                noKartuInput.focus();
            };

            if (btnTapRfid) {
                btnTapRfid.addEventListener('click', startRfidTap);
            }

            if (noKartuInput) {
                noKartuInput.addEventListener('input', syncSaveFields);
                noKartuInput.addEventListener('blur', function () { setRfidWaiting(false); });
                // Reader RFID USB mengetik nomor kartu lalu Enter
                noKartuInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        noKartuInput.value = normalizeRfid(noKartuInput.value);
                        syncSaveFields();
                        setRfidWaiting(false);
                        noKartuInput.select();
                    }
                });
            }
            if (pinInput) {
                pinInput.addEventListener('input', syncSaveFields);
            }

            document.getElementById('formSave')?.addEventListener('submit', function (e) {
                syncSaveFields();
                if (!custidSave || parseInt(custidSave.value, 10) <= 0) {
                    e.preventDefault();
                    alert('Pilih siswa (NIS) terlebih dahulu dari dropdown.');
                    return;
                }
                if (!noKartuSave || noKartuSave.value.trim() === '') {
                    e.preventDefault();
                    alert('Nomor kartu wajib diisi.');
                }
            });

            if (!siswaInput || !custidHidden || !siswaList || !siswaWrap) {
                return;
            }

            const openDropdown = function () {
                if (nisField) nisField.classList.add('sc-dropdown-open');
            };

            const closeList = function () {
                siswaList.style.display = 'none';
                siswaList.innerHTML = '';
                if (nisField) nisField.classList.remove('sc-dropdown-open');
            };

            const renderRows = function (matched) {
                openDropdown();
                if (!matched.length) {
                    siswaList.innerHTML = '<div style="padding:10px 14px;color:#6b7280;font-size:13px;">Siswa tidak ditemukan.</div>';
                    siswaList.style.display = 'block';
                    return;
                }
                siswaList.innerHTML = matched.map(function (r) {
                    const label = (r.label || '').replace(/"/g, '&quot;');
                    const nmcust = (r.nmcust || '').replace(/"/g, '&quot;');
                    return '<button type="button" data-cid="' + r.cid + '" data-label="' + label + '" data-nmcust="' + nmcust + '" style="width:100%;text-align:left;padding:10px 14px;border:0;background:#fff;cursor:pointer;border-bottom:1px solid #f3f4f6;">' + (r.label || '—') + '</button>';
                }).join('');
                siswaList.style.display = 'block';
                Array.from(siswaList.querySelectorAll('button[data-cid]')).forEach(function (btn) {
                    btn.addEventListener('mouseenter', function () { btn.style.background = '#eef2ff'; });
                    btn.addEventListener('mouseleave', function () { btn.style.background = '#fff'; });
                    btn.addEventListener('click', function () {
                        siswaInput.value = btn.getAttribute('data-label') || '';
                        custidHidden.value = btn.getAttribute('data-cid') || '';
                        if (namaSiswa) {
                            namaSiswa.value = btn.getAttribute('data-nmcust') || '';
                        }
                        syncSaveFields();
                        closeList();
                    });
                });
            };

            const fetchSiswa = function (q) {
                const query = String(q || '').trim();
                if (query.length < 1) {
                    closeList();
                    return;
                }
                const seq = ++searchSeq;
                openDropdown();
                siswaList.innerHTML = '<div style="padding:10px 14px;color:#6b7280;font-size:13px;">Mencari…</div>';
                siswaList.style.display = 'block';
                const url = siswaSearchUrl + '?mode=nis&q=' + encodeURIComponent(query);
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (seq !== searchSeq) return;
                        renderRows(Array.isArray(json.rows) ? json.rows : []);
                    })
                    .catch(function () {
                        if (seq !== searchSeq) return;
                        openDropdown();
                        siswaList.innerHTML = '<div style="padding:10px 14px;color:#b91c1c;font-size:13px;">Gagal memuat data siswa.</div>';
                        siswaList.style.display = 'block';
                    });
            };

            siswaInput.addEventListener('input', function () {
                custidHidden.value = '';
                if (namaSiswa) namaSiswa.value = '';
                syncSaveFields();
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    fetchSiswa(siswaInput.value);
                }, 280);
            });

            siswaInput.addEventListener('focus', function () {
                if (String(siswaInput.value || '').trim() !== '') {
                    fetchSiswa(siswaInput.value);
                }
            });

            document.addEventListener('click', function (e) {
                if (!siswaWrap.contains(e.target)) {
                    closeList();
                }
            });

            syncSaveFields();
        })();

        document.querySelectorAll('.sc-blokir-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                const form = toggle.closest('form');
                if (!window.confirm(form.dataset.confirm)) {
                    toggle.checked = !toggle.checked;
                    return;
                }
                toggle.disabled = true;
                form.submit();
            });
        });
    </script>
@endsection
