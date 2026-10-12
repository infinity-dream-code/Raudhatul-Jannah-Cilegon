@extends('layouts.admin_new')
@section('title', $dataTitle ?? $mainTitle ?? $title ?? '')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $mainTitle ?? $title ?? '' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">{{ $title }}</li>
        <li class="breadcrumb-item active">{{ $mainTitle }}</li>
    </ul>

    <div class="card">
        <div class="card-header header-elements">
            <h5 class="mb-0 me-2">{{ $dataTitle ?? $mainTitle }}</h5>
        </div>

        <div class="card-body">
            @unless($tableReady)
                <div class="alert alert-warning mb-3">
                    Tabel <code>mst_angkatan_unit</code> belum dibuat. Jalankan SQL di
                    <code>database/sql/mst_angkatan_unit.sql</code> terlebih dahulu.
                </div>
            @endunless
            <p class="text-muted mb-0">
                Tentukan tahun akademik Angkatan 1 untuk tiap unit. Unit yang belum diisi memakai tahun akademik
                paling awal{{ $defaultStart ? ' (' . $defaultStart . '/' . ($defaultStart + 1) . ')' : '' }} sebagai Angkatan 1.
            </p>
        </div>

        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th class="text-center" style="width:60px;">No</th>
                    <th>Kode</th>
                    <th>Unit</th>
                    <th>Tahun Akademik Angkatan 1</th>
                    <th>Diubah Oleh</th>
                    <th class="text-center" style="width:120px;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $item)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>{{ $item->CODE01 }}</td>
                        <td>{{ $item->DESC01 ?: '-' }}</td>
                        <td>
                            @if($item->thn_aka_mulai)
                                {{ $item->thn_aka_mulai }}
                            @else
                                <span class="text-muted">
                                    Default{{ $defaultStart ? ': ' . $defaultStart . '/' . ($defaultStart + 1) : '' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            {{ $item->updated_by ?: '-' }}
                            @if($item->updated_at)
                                <small class="text-muted d-block">{{ $item->updated_at }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-primary btn-edit"
                                    data-code="{{ $item->CODE01 }}"
                                    data-nama="{{ $item->DESC01 }}"
                                    data-thn="{{ $item->thn_aka_mulai }}"
                                    @disabled(!$tableReady)>
                                <i class="ri-pencil-line me-1"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada data unit.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form id="form-angkatan-unit">
        @csrf
        <div class="modal fade" id="modal-angkatan-unit" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-angkatan-unit-title">Edit Setting Angkatan Unit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="form_code01" value="">
                        <div class="mb-3">
                            <label class="form-label" for="form_thn_aka_mulai">Tahun Akademik Angkatan 1</label>
                            <select class="form-select" id="form_thn_aka_mulai" name="thn_aka_mulai" data-angkatan-ignore>
                                <option value="">Default (tahun akademik paling awal)</option>
                                @foreach($thnAka as $thn)
                                    <option value="{{ $thn }}">{{ $thn }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Siswa dengan tahun akademik ini menjadi Angkatan 1 di unit tersebut.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit-angkatan-unit">Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('script')
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const modal = new bootstrap.Modal(document.getElementById('modal-angkatan-unit'));
            const form = document.getElementById('form-angkatan-unit');
            const titleEl = document.getElementById('modal-angkatan-unit-title');
            const codeEl = document.getElementById('form_code01');
            const thnEl = document.getElementById('form_thn_aka_mulai');

            function notify(icon, html) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({icon, html, confirmButtonText: 'OK'});
                } else {
                    alert(html.replace(/<[^>]+>/g, ' '));
                }
            }

            document.querySelectorAll('.btn-edit').forEach((btn) => {
                btn.addEventListener('click', () => {
                    codeEl.value = btn.dataset.code || '';
                    thnEl.value = btn.dataset.thn || '';
                    titleEl.textContent = 'Edit Angkatan Unit ' + (btn.dataset.nama || btn.dataset.code || '');
                    modal.show();
                });
            });

            form?.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = document.getElementById('btn-submit-angkatan-unit');
                btn.disabled = true;

                try {
                    const res = await fetch('{{ url('admin/master-data/setting-angkatan-unit') }}/' + encodeURIComponent(codeEl.value), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({thn_aka_mulai: thnEl.value}),
                    });
                    const data = await res.json().catch(() => ({}));
                    btn.disabled = false;

                    if (res.ok) {
                        modal.hide();
                        notify('success', data.message || 'Berhasil');
                        setTimeout(() => location.reload(), 700);
                    } else {
                        notify('error', data.message || 'Gagal menyimpan data');
                    }
                } catch (err) {
                    btn.disabled = false;
                    notify('error', 'Terjadi kesalahan jaringan.');
                }
            });
        })();
    </script>
@endsection
