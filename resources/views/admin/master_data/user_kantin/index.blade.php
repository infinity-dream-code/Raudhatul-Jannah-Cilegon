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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create">
                    <span class="ri-add-line me-2"></span>
                    Tambah User Kantin
                </button>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('admin.master-data.user-kantin.index') }}" id="filterForm">
                <fieldset class="form-fieldset">
                    <h5>Filter</h5>
                    <div class="row mb-3">
                        <label class="col-sm-2 col-form-label" for="nama_kantin">Nama Kantin</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="nama_kantin" name="nama_kantin"
                                   value="{{ $filters['nama_kantin'] ?? '' }}" placeholder="Cari nama kantin">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-2 col-form-label" for="username">Username</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="username" name="username"
                                   value="{{ $filters['username'] ?? '' }}" placeholder="Cari username">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-search-line me-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('admin.master-data.user-kantin.index') }}" class="btn btn-outline-secondary">
                            Reset Filter
                        </a>
                        <button type="button" class="btn btn-warning" id="btn-reset-bulk" disabled>
                            <i class="ri-lock-password-line me-1"></i> Reset Password Jamak
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Pilih beberapa baris lalu klik <strong>Reset Password Jamak</strong>. Password akan direset ke
                        <code>123</code>.
                    </small>
                </fieldset>
            </form>
        </div>

        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th class="text-center" style="width:42px;">
                        <input type="checkbox" class="form-check-input" id="check-all">
                    </th>
                    <th class="text-center" style="width:60px;">No</th>
                    <th>Nama Kantin</th>
                    <th>Username</th>
                    <th class="text-center" style="width:140px;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $item)
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input row-check"
                                   value="{{ $item->urut }}" data-username="{{ $item->username }}">
                        </td>
                        <td class="text-center">{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{{ $item->NamaKantin ?: '-' }}</td>
                        <td>{{ $item->username ?: '-' }}</td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-warning btn-reset"
                                    data-id="{{ $item->urut }}"
                                    data-username="{{ $item->username }}"
                                    data-nama="{{ $item->NamaKantin }}">
                                <i class="ri-lock-password-line me-1"></i> Reset
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada data user kantin.</td>
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

    {{-- Modal Tambah --}}
    <form id="form-create">
        @csrf
        <div class="modal fade" id="modal-create" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah User Kantin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required" for="NamaKantin">Nama Kantin</label>
                            <input type="text" class="form-control" id="NamaKantin" name="NamaKantin" maxlength="50"
                                   required placeholder="Contoh: Kantin Utama">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="create_username">Username</label>
                            <input type="text" class="form-control" id="create_username" name="username" maxlength="50"
                                   required placeholder="Username login merchant">
                            <small class="text-muted">Password awal otomatis: <code>123</code>. Username tidak boleh
                                sudah ada di kantin / cyber_key.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit-create">Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('script')
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || '{{ csrf_token() }}';

            const checkAll = document.getElementById('check-all');
            const btnBulk = document.getElementById('btn-reset-bulk');

            function selectedIds() {
                return Array.from(document.querySelectorAll('.row-check:checked')).map((el) => el.value);
            }

            function syncBulkButton() {
                btnBulk.disabled = selectedIds().length === 0;
            }

            checkAll?.addEventListener('change', function () {
                document.querySelectorAll('.row-check').forEach((el) => {
                    el.checked = checkAll.checked;
                });
                syncBulkButton();
            });

            document.querySelectorAll('.row-check').forEach((el) => {
                el.addEventListener('change', syncBulkButton);
            });

            async function postJson(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));
                return {ok: res.ok, status: res.status, data};
            }

            function toastSuccess(html) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({icon: 'success', html: html, confirmButtonText: 'OK'});
                } else {
                    alert(html.replace(/<[^>]+>/g, ' '));
                }
            }

            function toastError(html) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({icon: 'error', html: html, confirmButtonText: 'OK'});
                } else {
                    alert(html.replace(/<[^>]+>/g, ' '));
                }
            }

            document.getElementById('form-create')?.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = document.getElementById('btn-submit-create');
                btn.disabled = true;
                const payload = {
                    NamaKantin: document.getElementById('NamaKantin').value,
                    username: document.getElementById('create_username').value,
                };
                const {ok, data} = await postJson('{{ route('admin.master-data.user-kantin.store') }}', payload);
                btn.disabled = false;
                if (ok) {
                    bootstrap.Modal.getInstance(document.getElementById('modal-create'))?.hide();
                    toastSuccess(data.message || 'Berhasil');
                    setTimeout(() => location.reload(), 800);
                } else {
                    toastError(data.message || 'Gagal menyimpan data');
                }
            });

            document.querySelectorAll('.btn-reset').forEach((btn) => {
                btn.addEventListener('click', async function () {
                    const id = this.dataset.id;
                    const username = this.dataset.username;
                    const nama = this.dataset.nama || username;
                    const confirm = typeof Swal !== 'undefined'
                        ? await Swal.fire({
                            icon: 'warning',
                            title: 'Reset Password?',
                            html: `Reset password <b>${nama}</b> (<code>${username}</code>) ke <code>123</code>?`,
                            showCancelButton: true,
                            confirmButtonText: 'Ya, Reset',
                            cancelButtonText: 'Batal',
                        })
                        : {isConfirmed: window.confirm(`Reset password ${username} ke 123?`)};

                    if (!confirm.isConfirmed) return;

                    const url = '{{ url('admin/master-data/user-kantin') }}/' + id + '/reset-password';
                    const {ok, data} = await postJson(url, {});
                    if (ok) {
                        toastSuccess(data.message || 'Berhasil reset');
                    } else {
                        toastError(data.message || 'Gagal reset password');
                    }
                });
            });

            btnBulk?.addEventListener('click', async function () {
                const ids = selectedIds();
                if (!ids.length) return;

                const confirm = typeof Swal !== 'undefined'
                    ? await Swal.fire({
                        icon: 'warning',
                        title: 'Reset Password Jamak?',
                        html: `Reset password <b>${ids.length}</b> user kantin terpilih ke <code>123</code>?`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Reset Semua',
                        cancelButtonText: 'Batal',
                    })
                    : {isConfirmed: window.confirm(`Reset ${ids.length} user ke password 123?`)};

                if (!confirm.isConfirmed) return;

                btnBulk.disabled = true;
                const {ok, data} = await postJson(
                    '{{ route('admin.master-data.user-kantin.reset-password-bulk') }}',
                    {ids}
                );
                btnBulk.disabled = false;
                syncBulkButton();

                if (ok) {
                    toastSuccess(data.message || 'Berhasil');
                    document.querySelectorAll('.row-check:checked, #check-all').forEach((el) => el.checked = false);
                    syncBulkButton();
                } else {
                    toastError(data.message || 'Gagal reset jamak');
                }
            });
        })();
    </script>
@endsection
