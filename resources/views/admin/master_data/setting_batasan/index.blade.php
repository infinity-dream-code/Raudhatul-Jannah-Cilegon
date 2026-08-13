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
            <div class="card-header-elements ms-auto">
                <button type="button" class="btn btn-primary" id="btn-open-create">
                    <span class="ri-add-line me-2"></span>
                    Tambah Batasan
                </button>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('admin.master-data.setting-batasan.index') }}">
                <fieldset class="form-fieldset">
                    <h5>Filter</h5>
                    <div class="row mb-3">
                        <label class="col-sm-2 col-form-label" for="periode">Periode</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="periode" name="periode"
                                   value="{{ $filters['periode'] ?? '' }}" placeholder="Cari periode">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-2 col-form-label" for="kelompok_kantin">Kelompok Kantin</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="kelompok_kantin" name="kelompok_kantin"
                                   value="{{ $filters['kelompok_kantin'] ?? '' }}" placeholder="Cari kelompok kantin">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-2 col-form-label" for="aktif">Status</label>
                        <div class="col-sm-10">
                            <select class="form-select" id="aktif" name="aktif">
                                <option value="all" @selected(($filters['aktif'] ?? 'all') === 'all')>Semua</option>
                                <option value="1" @selected(($filters['aktif'] ?? '') === '1' || ($filters['aktif'] ?? '') === 1)>Aktif</option>
                                <option value="0" @selected(($filters['aktif'] ?? '') === '0' || ($filters['aktif'] ?? '') === 0)>Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-search-line me-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('admin.master-data.setting-batasan.index') }}" class="btn btn-outline-secondary">
                            Reset Filter
                        </a>
                    </div>
                </fieldset>
            </form>
        </div>

        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th class="text-center" style="width:60px;">No</th>
                    <th>Periode</th>
                    <th class="text-end">Batas Belanja/Hari</th>
                    <th class="text-end">Batas Cash</th>
                    <th>Kelompok Kantin</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width:120px;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $item)
                    <tr>
                        <td class="text-center">{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{{ $item->periode ?: '-' }}</td>
                        <td class="text-end">{{ number_format((float) $item->batas_belanja_hari, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $item->batas_cash, 0, ',', '.') }}</td>
                        <td>{{ $item->kelompok_kantin ?: '-' }}</td>
                        <td class="text-center">
                            @if((int) $item->aktif === 1)
                                <span class="badge bg-label-success">Aktif</span>
                            @else
                                <span class="badge bg-label-secondary">Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-primary btn-edit"
                                    data-id="{{ $item->urut }}"
                                    data-periode="{{ $item->periode }}"
                                    data-batas_belanja_hari="{{ $item->batas_belanja_hari }}"
                                    data-batas_cash="{{ $item->batas_cash }}"
                                    data-aktif="{{ (int) $item->aktif }}"
                                    data-kelompok_kantin="{{ $item->kelompok_kantin }}">
                                <i class="ri-pencil-line me-1"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Belum ada data setting batasan.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
            <div class="card-footer">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    <form id="form-batasan">
        @csrf
        <div class="modal fade" id="modal-batasan" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-batasan-title">Tambah Setting Batasan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="batasan_id" value="">
                        <div class="mb-3">
                            <label class="form-label required" for="form_periode">Periode</label>
                            <input type="text" class="form-control" id="form_periode" name="periode" maxlength="50"
                                   required placeholder="Contoh: 2026 atau 2026-08">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="form_batas_belanja_hari">Batas Belanja / Hari</label>
                            <input type="number" class="form-control" id="form_batas_belanja_hari"
                                   name="batas_belanja_hari" min="0" step="1" required placeholder="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="form_batas_cash">Batas Cash</label>
                            <input type="number" class="form-control" id="form_batas_cash"
                                   name="batas_cash" min="0" step="1" required placeholder="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="form_kelompok_kantin">Kelompok Kantin</label>
                            <input type="text" class="form-control" id="form_kelompok_kantin"
                                   name="kelompok_kantin" maxlength="100" placeholder="Opsional">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="form_aktif">Status</label>
                            <select class="form-select" id="form_aktif" name="aktif" required>
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit-batasan">Simpan</button>
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
            const modalEl = document.getElementById('modal-batasan');
            const modal = new bootstrap.Modal(modalEl);
            const form = document.getElementById('form-batasan');
            const titleEl = document.getElementById('modal-batasan-title');
            const idEl = document.getElementById('batasan_id');

            function toastSuccess(html) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({icon: 'success', html, confirmButtonText: 'OK'});
                } else {
                    alert(html.replace(/<[^>]+>/g, ' '));
                }
            }

            function toastError(html) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({icon: 'error', html, confirmButtonText: 'OK'});
                } else {
                    alert(html.replace(/<[^>]+>/g, ' '));
                }
            }

            function resetForm() {
                idEl.value = '';
                form.reset();
                document.getElementById('form_aktif').value = '1';
            }

            function openCreate() {
                resetForm();
                titleEl.textContent = 'Tambah Setting Batasan';
                modal.show();
            }

            function openEdit(btn) {
                resetForm();
                titleEl.textContent = 'Edit Setting Batasan';
                idEl.value = btn.dataset.id || '';
                document.getElementById('form_periode').value = btn.dataset.periode || '';
                document.getElementById('form_batas_belanja_hari').value = btn.dataset.batas_belanja_hari || 0;
                document.getElementById('form_batas_cash').value = btn.dataset.batas_cash || 0;
                document.getElementById('form_kelompok_kantin').value = btn.dataset.kelompok_kantin || '';
                document.getElementById('form_aktif').value = String(btn.dataset.aktif ?? '1');
                modal.show();
            }

            document.getElementById('btn-open-create')?.addEventListener('click', openCreate);
            document.querySelectorAll('.btn-edit').forEach((btn) => {
                btn.addEventListener('click', () => openEdit(btn));
            });

            form?.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = document.getElementById('btn-submit-batasan');
                btn.disabled = true;

                const id = idEl.value;
                const payload = {
                    periode: document.getElementById('form_periode').value,
                    batas_belanja_hari: document.getElementById('form_batas_belanja_hari').value,
                    batas_cash: document.getElementById('form_batas_cash').value,
                    kelompok_kantin: document.getElementById('form_kelompok_kantin').value,
                    aktif: document.getElementById('form_aktif').value,
                };

                const url = id
                    ? '{{ url('admin/master-data/setting-batasan') }}/' + id
                    : '{{ route('admin.master-data.setting-batasan.store') }}';
                const method = id ? 'PUT' : 'POST';

                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    btn.disabled = false;

                    if (res.ok) {
                        modal.hide();
                        toastSuccess(data.message || 'Berhasil');
                        setTimeout(() => location.reload(), 700);
                    } else {
                        toastError(data.message || 'Gagal menyimpan data');
                    }
                } catch (err) {
                    btn.disabled = false;
                    toastError('Terjadi kesalahan jaringan.');
                }
            });
        })();
    </script>
@endsection
