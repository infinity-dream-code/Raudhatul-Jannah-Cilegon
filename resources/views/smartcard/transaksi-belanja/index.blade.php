@extends('layouts.admin_new')

@section('content')
    <div class="sc-page">
        <div class="page-heading sc-page-heading">
            <h2>Transaksi Belanja</h2>
            <p>Smartcard / Data transaksi belanja siswa (FIDBANK = BUY)</p>
        </div>

        <div class="card sc-card">
            <div class="sc-card-body">
                <form method="GET" action="{{ route('smartcard.transaksi_belanja') }}">
                    <input type="hidden" name="search" value="1">

                    <div class="sc-form-grid sc-tb-form">
                        <div class="sc-field">
                            <label for="thnAkademik">Tahun Pelajaran</label>
                            <div class="sc-control-wrap sc-control-select">
                                <select id="thnAkademik" name="thn_akademik">
                                    <option value="">Semua</option>
                                    @foreach ($thnAka as $row)
                                        @php $val = trim((string) ($row->thn_aka ?? '')); @endphp
                                        @if ($val !== '')
                                            <option value="{{ $val }}" @selected(($filters['thn_akademik'] ?? '') === $val)>{{ $val }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label for="kelasId">Kelas</label>
                            <div class="sc-control-wrap sc-control-select">
                                <select id="kelasId" name="kelas_id">
                                    <option value="">Semua</option>
                                    @foreach ($kelasOptions as $k)
                                        @php
                                            $id = (string) ($k->id ?? '');
                                            $parts = array_values(array_filter([
                                                trim((string) ($k->unit ?? '')),
                                                trim((string) ($k->jenjang ?? '')),
                                                trim((string) ($k->kelas ?? '')),
                                            ], static fn ($v) => $v !== ''));
                                            $lbl = implode(' - ', $parts);
                                        @endphp
                                        @if ($id !== '' && $lbl !== '')
                                            <option value="{{ $id }}" @selected(($filters['kelas_id'] ?? '') === $id)>{{ $lbl }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label for="nis">NIS</label>
                            <div class="sc-control-wrap">
                                <input type="text" id="nis" name="nis" value="{{ $filters['nis'] ?? '' }}" placeholder="Nomor induk siswa">
                            </div>
                        </div>
                        <div class="sc-field">
                            <label for="thnAngkatan">Tahun Angkatan</label>
                            <div class="sc-control-wrap sc-control-select">
                                <select id="thnAngkatan" name="thn_angkatan">
                                    <option value="">Semua</option>
                                    @foreach ($thnAka as $row)
                                        @php $val = trim((string) ($row->thn_aka ?? '')); @endphp
                                        @if ($val !== '')
                                            <option value="{{ $val }}" @selected(($filters['thn_angkatan'] ?? '') === $val)>{{ $val }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="sc-field">
                            <label for="nama">Nama</label>
                            <div class="sc-control-wrap">
                                <input type="text" id="nama" name="nama" value="{{ $filters['nama'] ?? '' }}" placeholder="Nama siswa">
                            </div>
                        </div>
                        <div class="sc-field sc-field-dates">
                            <label>Dari Tanggal</label>
                            <div class="sc-control-wrap">
                                <input type="date" name="dari_tanggal" value="{{ $filters['dari_tanggal'] ?? '' }}">
                            </div>
                        </div>
                        <div class="sc-field sc-field-dates">
                            <label>Sampai Tanggal</label>
                            <div class="sc-control-wrap">
                                <input type="date" name="sampai_tanggal" value="{{ $filters['sampai_tanggal'] ?? '' }}">
                            </div>
                        </div>
                    </div>

                    <div class="sc-actions">
                        <a href="{{ route('smartcard.transaksi_belanja') }}" class="sc-btn">Reset</a>
                        <button type="submit" class="sc-btn sc-btn-primary">Cari</button>
                    </div>
                </form>

                <div class="sc-table-section">
                    <div class="sc-table-title">
                        Data Transaksi Belanja
                        @if ($isSearch ?? false)
                            <span class="sc-table-subtitle">— hasil pencarian</span>
                        @endif
                    </div>

                    @if ($isSearch ?? false)
                        <div class="sc-tb-summary">
                            Total debet: <strong>Rp {{ number_format((float) ($totalDebet ?? 0), 0, ',', '.') }}</strong>
                        </div>
                    @endif

                    <div class="sc-table-wrap">
                        <table class="sc-table">
                            <thead>
                                <tr>
                                    <th style="width:56px;">No</th>
                                    <th>Nama</th>
                                    <th>Tgl Transaksi</th>
                                    <th style="text-align:right;">Debet</th>
                                    <th>Kantin</th>
                                    <th>Kelas</th>
                                    <th>Kelompok</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($rows ?? []) as $index => $row)
                                    <tr>
                                        <td>{{ ($rows->firstItem() ?? 0) + $index }}</td>
                                        <td>{{ $row->nama ?? '—' }}</td>
                                        <td>
                                            @if (!empty($row->tgl_transaksi))
                                                {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('d-m-Y H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td style="text-align:right;">{{ number_format((float) ($row->debet ?? 0), 0, ',', '.') }}</td>
                                        <td>{{ $row->kantin ?? '—' }}</td>
                                        <td>{{ $row->kelas ?? '—' }}</td>
                                        <td>{{ $row->kelompok ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="sc-empty">
                                            @if ($isSearch ?? false)
                                                Tidak ada transaksi belanja yang sesuai kriteria.
                                            @else
                                                Gunakan filter lalu klik Cari untuk menampilkan data.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (isset($rows) && method_exists($rows, 'hasPages') && $rows->hasPages())
                        <div class="sc-pagination-wrap">
                            <div class="sc-pagination-info">
                                Menampilkan {{ $rows->firstItem() ?? 0 }} sampai {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() ?? 0 }} entri
                            </div>
                            <div class="sc-pagination">
                                @php
                                    $current = $rows->currentPage();
                                    $last = $rows->lastPage();
                                    $start = max(1, $current - 2);
                                    $end = min($last, $current + 2);
                                @endphp
                                @if ($rows->onFirstPage())
                                    <span class="sc-page-link disabled">Sebelumnya</span>
                                @else
                                    <a class="sc-page-link" href="{{ $rows->previousPageUrl() }}">Sebelumnya</a>
                                @endif

                                @for ($page = $start; $page <= $end; $page++)
                                    @if ($page === $current)
                                        <span class="sc-page-link active">{{ $page }}</span>
                                    @else
                                        <a class="sc-page-link" href="{{ $rows->url($page) }}">{{ $page }}</a>
                                    @endif
                                @endfor

                                @if ($rows->hasMorePages())
                                    <a class="sc-page-link" href="{{ $rows->nextPageUrl() }}">Selanjutnya</a>
                                @else
                                    <span class="sc-page-link disabled">Selanjutnya</span>
                                @endif
                            </div>
                        </div>
                    @elseif (($isSearch ?? false) && ($rows->total() ?? 0) > 0)
                        <div class="sc-pagination-wrap">
                            <div class="sc-pagination-info">
                                Menampilkan {{ $rows->firstItem() ?? 0 }} sampai {{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() ?? 0 }} entri
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('smartcard.partials.styles')

    <style>
        .sc-tb-form {
            grid-template-columns: repeat(2, minmax(260px, 1fr));
        }
        @media (max-width: 768px) {
            .sc-tb-form { grid-template-columns: 1fr; }
        }
        .sc-control-select select,
        .sc-field-dates input[type="date"] {
            width: 100%;
            height: 44px;
            border: 0;
            padding: 0 14px;
            font-size: 14px;
            background: #fff;
            color: #374151;
            cursor: pointer;
        }
        .sc-field-dates input[type="date"] { cursor: text; }
        .sc-tb-summary {
            font-size: 13px;
            color: #4b5563;
            margin-bottom: 12px;
        }
        .sc-tb-summary strong { color: #7c3aed; }
        a.sc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
    </style>
@endsection
