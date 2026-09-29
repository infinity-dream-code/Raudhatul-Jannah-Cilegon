@extends('layouts.admin_new')

@section('content')
    <div class="sc-page sc-page-wide">
        <div class="page-heading sc-page-heading">
            <h2>Rekap Pencairan Kantin</h2>
            <p>Smartcard / Data pencairan belanja ke kantin</p>
        </div>

        <div class="card sc-card">
            <div class="sc-card-body">
                @if (session('smartcard_success'))
                    <div class="sc-alert sc-alert-success">{{ session('smartcard_success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="sc-alert sc-alert-error">{{ $errors->first() }}</div>
                @endif

                <div class="rp-builder-title">Data Pencairan Belanja ke Kantin</div>

                <div class="rp-builder-form">
                    <div class="rp-builder-col rp-builder-col-left">
                        <form method="GET" action="{{ route('smartcard.rekap_pencairan_kantin') }}" id="rpFormGet" class="rp-inner-form">
                            <input type="hidden" name="nama_penerima" value="{{ $namaPenerima ?? '' }}">
                            <input type="hidden" name="nominal" value="{{ $nominal ?? '' }}">

                            <div class="sc-field">
                                <label for="kdMercan">Merchan</label>
                                <div class="sc-control-wrap sc-control-select">
                                    <select id="kdMercan" name="kd_mercan">
                                        <option value="">Pilih Merchan</option>
                                        @foreach ($mercanOptions as $m)
                                            <option value="{{ $m->kode }}" @selected(($kdMercan ?? '') === $m->kode)>{{ $m->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="sc-field">
                                <label for="dariTanggal">Dari Tanggal</label>
                                <div class="sc-control-wrap">
                                    <input type="date" id="dariTanggal" name="dari_tanggal" value="{{ $dariTanggal ?? '' }}">
                                </div>
                            </div>
                            <div class="sc-field">
                                <label for="sampaiTanggal">Sampai Tanggal</label>
                                <div class="sc-control-wrap">
                                    <input type="date" id="sampaiTanggal" name="sampai_tanggal" value="{{ $sampaiTanggal ?? '' }}">
                                </div>
                            </div>
                            <div class="rp-btn-row rp-btn-row-left">
                                <button type="submit" name="cari_transaksi" value="1" class="sc-btn sc-btn-block sc-btn-primary">Cari Transaksi</button>
                            </div>
                        </form>
                    </div>

                    <div class="rp-builder-col rp-builder-col-right">
                        <form method="POST" action="{{ route('smartcard.rekap_pencairan_kantin.store') }}" id="rpFormSave" class="rp-inner-form">
                            @csrf
                            <input type="hidden" name="kd_mercan" id="kdMercanSave" value="{{ $kdMercan ?? '' }}">
                            <input type="hidden" name="dari_tanggal" id="dariTanggalSave" value="{{ $dariTanggal ?? '' }}">
                            <input type="hidden" name="sampai_tanggal" id="sampaiTanggalSave" value="{{ $sampaiTanggal ?? '' }}">

                            <div class="sc-field">
                                <label for="namaPenerima">Nama Penerima</label>
                                <div class="sc-control-wrap">
                                    <input type="text" id="namaPenerima" name="nama_penerima" value="{{ old('nama_penerima', $namaPenerima ?? '') }}" placeholder="Nama penerima">
                                </div>
                            </div>
                            <div class="sc-field">
                                <label for="nominal">Nominal</label>
                                <div class="sc-control-wrap sc-control-kartu">
                                    <input type="number" id="nominal" name="nominal" value="{{ old('nominal', $nominal ?? '') }}" min="1" step="1" placeholder="0">
                                </div>
                            </div>
                            <div class="sc-field">
                                <label>No Terima</label>
                                <div class="sc-control-wrap sc-control-readonly">
                                    <input type="text" value="{{ $previewNoTerima ?? '' }}" readonly tabindex="-1">
                                </div>
                            </div>
                            <div class="rp-btn-row rp-btn-row-right">
                                <button type="button" id="btnLiatPencairan" class="sc-btn sc-btn-flex">Liat Pencairan</button>
                                <button type="submit" class="sc-btn sc-btn-flex sc-btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="rp-tables-grid">
                    <div class="rp-table-block">
                        <div class="sc-table-wrap rp-table-wrap">
                            <table class="sc-table">
                                <thead>
                                    <tr>
                                        <th>Tgl Transaksi</th>
                                        <th style="text-align:right;">Saldo</th>
                                        <th>Merchan</th>
                                        <th>Kantin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($cariTransaksi ?? false)
                                        @forelse (($transaksiRows ?? collect()) as $row)
                                            <tr>
                                                <td>
                                                    @if (!empty($row->tgl_transaksi))
                                                        {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('d-m-Y H:i') }}
                                                    @else — @endif
                                                </td>
                                                <td style="text-align:right;">{{ number_format((float) ($row->saldo ?? 0), 0, ',', '.') }}</td>
                                                <td>{{ $row->mercan ?? '—' }}</td>
                                                <td>{{ $row->kantin ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="sc-empty">Tidak ada transaksi pada periode ini.</td></tr>
                                        @endforelse
                                        @if (($transaksiRows ?? collect())->isNotEmpty())
                                            <tr class="rp-total-row">
                                                <td><strong>Total</strong></td>
                                                <td style="text-align:right;"><strong>{{ number_format((float) ($transaksiTotal ?? 0), 0, ',', '.') }}</strong></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        @endif
                                    @else
                                        <tr><td colspan="4" class="sc-empty">Pilih merchan & tanggal, lalu klik Cari Transaksi.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rp-table-block">
                        <div class="sc-table-wrap rp-table-wrap">
                            <table class="sc-table">
                                <thead>
                                    <tr>
                                        <th>Tgl Terima</th>
                                        <th>Nama Penerima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($liatPencairan ?? false)
                                        @forelse (($pencairanRows ?? collect()) as $row)
                                            <tr>
                                                <td>
                                                    @if (!empty($row->tgl_terima))
                                                        {{ \Illuminate\Support\Carbon::parse($row->tgl_terima)->format('Y-m-d H:i:s') }}
                                                    @else — @endif
                                                </td>
                                                <td>{{ $row->nama_penerima ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="sc-empty">Belum ada data pencairan.</td></tr>
                                        @endforelse
                                        @if (($pencairanRows ?? collect())->isNotEmpty())
                                            <tr class="rp-total-row">
                                                <td><strong>Total</strong></td>
                                                <td><strong>{{ number_format((float) ($pencairanTotal ?? 0), 0, ',', '.') }}</strong></td>
                                            </tr>
                                        @endif
                                    @else
                                        <tr><td colspan="2" class="sc-empty">Klik Liat Pencairan untuk menampilkan data.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('smartcard.partials.styles')

    <style>
        .sc-page-wide { max-width: 1280px; }
        .rp-builder-title {
            font-family: 'Sora', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: #6d28d9;
            text-align: center;
            margin-bottom: 18px;
            letter-spacing: 0.02em;
        }
        .rp-builder-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid #ddd6fe;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            background: #fff;
        }
        @media (max-width: 768px) {
            .rp-builder-form { grid-template-columns: 1fr; }
        }
        .rp-builder-col {
            padding: 16px 18px 18px;
        }
        .rp-builder-col-left {
            border-right: 1px solid #e9d5ff;
            background: #faf5ff;
        }
        @media (max-width: 768px) {
            .rp-builder-col-left { border-right: 0; border-bottom: 1px solid #e9d5ff; }
        }
        .rp-builder-col-right { background: #fff; }
        .rp-inner-form .sc-field { margin-bottom: 12px; }
        .rp-inner-form .sc-form-grid { margin-bottom: 0; }
        .rp-btn-row { display: flex; gap: 10px; margin-top: 6px; }
        .rp-btn-row-left { padding-top: 4px; }
        .rp-btn-row-right { padding-top: 4px; }
        .sc-btn-block { width: 100%; justify-content: center; }
        .sc-btn-flex { flex: 1; justify-content: center; display: inline-flex; align-items: center; }
        .rp-tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 992px) {
            .rp-tables-grid { grid-template-columns: 1fr; }
        }
        .rp-table-block { min-width: 0; }
        .rp-table-wrap { max-height: 380px; }
        .rp-total-row td {
            background: #f3f4f6 !important;
            border-top: 2px solid #d1d5db;
        }
        .sc-control-select select,
        .sc-field input[type="date"],
        .sc-field input[type="number"],
        .sc-field input[type="text"] {
            width: 100%;
            height: 44px;
            border: 0;
            padding: 0 14px;
            font-size: 14px;
            background: #fff;
            color: #374151;
        }
        .sc-control-kartu input[type="number"] { background: #fffbeb; }
        .sc-control-readonly input { background: #f5f3ff; color: #6b7280; }
    </style>

    <script>
        (function () {
            const formGet = document.getElementById('rpFormGet');
            const formSave = document.getElementById('rpFormSave');
            const kdMercan = document.getElementById('kdMercan');
            const dariTanggal = document.getElementById('dariTanggal');
            const sampaiTanggal = document.getElementById('sampaiTanggal');
            const namaPenerima = document.getElementById('namaPenerima');
            const nominal = document.getElementById('nominal');
            const btnLiat = document.getElementById('btnLiatPencairan');

            const syncToSave = () => {
                document.getElementById('kdMercanSave').value = kdMercan?.value || '';
                document.getElementById('dariTanggalSave').value = dariTanggal?.value || '';
                document.getElementById('sampaiTanggalSave').value = sampaiTanggal?.value || '';
            };

            const syncToGetHidden = () => {
                const hNama = formGet?.querySelector('input[name="nama_penerima"]');
                const hNom = formGet?.querySelector('input[name="nominal"]');
                if (hNama) hNama.value = namaPenerima?.value || '';
                if (hNom) hNom.value = nominal?.value || '';
            };

            formGet?.addEventListener('submit', () => syncToGetHidden());
            formSave?.addEventListener('submit', () => syncToSave());

            btnLiat?.addEventListener('click', () => {
                syncToGetHidden();
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'liat_pencairan';
                input.value = '1';
                formGet.appendChild(input);
                formGet.submit();
            });
        })();
    </script>
@endsection
