@extends('cashless.layouts.admin')
@section('title',$dataTitle??($mainTitle??($title??'')))
@section('content')
    <div class="row g-2 align-items-center">
        <div class="col">
            <h3 class="page-heading fw-bold my-0">
                {{($dataTitle??($mainTitle??($title??'')))}}
            </h3>
            <ul class="breadcrumb breadcrumb-style2">
                <li class="breadcrumb-item">
                    <a href="{{ route('cashless.admin.index') }}" class="text-hover-primary">Beranda</a>
                </li>

                @isset($title)
                    <li class="breadcrumb-item">
                        <a href="{{ $indexUrl??'#' }}" class="text-hover-primary">{{ $title }}</a>
                    </li>
                @endisset

                @if(isset($mainTitle) && $mainTitle !== $title)
                    <li class="breadcrumb-item">
                        <a href="{{ $indexUrl??'#' }}" class="text-hover-primary">{{ $mainTitle }}</a>
                    </li>
                @endif

                @if(isset($dataTitle) && isset($mainTitle) && $mainTitle !== $dataTitle)
                    <li class="breadcrumb-item {{$showTitle??'active'}}">
                        {{ $dataTitle }}
                    </li>

                    @isset($showTitle)
                        <li class="breadcrumb-item active">{{ $showTitle }}</li>
                    @endisset
                @endif
            </ul>
        </div>
    </div>

    <div class="row">
        <form action="#" id="form-profil">
            <div class="card">
                <div class="card-header header-elements">
                    <h5 class="mb-0 me-2">Profil Admin</h5>
                    <div class="card-header-elements ms-auto">
                        <div class="w-100">
                            <div class="row">
                                <div class="d-flex justify-content-center justify-content-md-end gap-4">
                                    <button type="button" class="btn btn-sm btn-linkedin" title="Ganti Password"
                                            data-bs-toggle="modal" data-bs-target="#modal-edit-password">
                                        <i class="ri-key-2-line me-2"></i>Ganti Password
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <fieldset class="form-fieldset">
                        <div class="mb-3">
                            <label class="form-label required" for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" autocomplete="off"
                                   placeholder="Username" required value="{{ session('cashless_user')['username'] }}">
                            <div class="invalid-feedback" role="alert">
                                <strong></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="nama">Nama Kantin</label>
                            <input type="text" class="form-control" name="nama" id="nama" autocomplete="off"
                                   placeholder="Nama" required value="{{ session('cashless_user')['kantin'] }}">
                            <div class="invalid-feedback" role="alert">
                                <strong></strong>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>
        </form>
    </div>
@endsection
@section('formattedNumber',true)
@section('errorInputHelper',true)
@section('datatable',true)
@section('bootstrap-datepicker',true)
@section('select2',true)
@section('script')
    <form id="form-edit-password" class="mainForm">
        <div class="modal modal-blur fade" id="modal-edit-password" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Ganti Password
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label" for="password-username">Username</label>
                                <input type="text" class="form-control" id="password-username" name="username"
                                       autocomplete="off"
                                       placeholder="Username" readonly value="{{ session('cashless_user')['username'] }}">
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password-nama">Nama</label>
                                <input type="text" class="form-control" name="nama" id="password-nama"
                                       autocomplete="off"
                                       placeholder="Nama" readonly value="{{ session('cashless_user')['kantin'] }}">
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password-old_password">Password Lama</label>
                                <div class="input-group">
                                    <input type="password" placeholder="Masukkan Password Anda" name="old_password"
                                           id="password-old_password"
                                           autocomplete="off"
                                           class="form-control " required/>
                                    <span class="input-group-text cursor-pointer showPassword"
                                          data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-dismiss-="click"
                                          data-bs-placement="bottom"
                                          title="Lihat Password">
                                            <i class="ri ri-eye-off-line"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password-password">Password</label>
                                <div class="input-group">
                                    <input type="password" placeholder="Masukkan Password Anda" name="password"
                                           id="password-password"
                                           autocomplete="off"
                                           class="form-control " required/>
                                    <span class="input-group-text cursor-pointer showPassword"
                                          data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-dismiss-="click"
                                          data-bs-placement="bottom"
                                          title="Lihat Password">
                                            <i class="ri ri-eye-off-line"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password-password_confirmation">Konfirmasi
                                    password</label>
                                <div class="input-group">
                                    <input type="password" placeholder="Konfirmasi Password Anda"
                                           name="password_confirmation"
                                           id="password-password_confirmation"
                                           autocomplete="off"
                                           class="form-control " required/>
                                    <span class="input-group-text cursor-pointer showPassword"
                                          data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-dismiss-="click"
                                          data-bs-placement="bottom"
                                          title="Lihat Password">
                                            <i class="ri ri-eye-off-line"></i>
                                    </span>
                                </div>
                            </div>
                        </fieldset>
                        <input type="hidden" name="item_id" autocomplete="off" placeholder="kode"
                               required="required">
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" value="Batal" class="btn btn-outline-secondary w-100"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Simpan Data" class="btn btn-primary w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script type="text/javascript" defer>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const adminId = '{{$admin_id}}';

        document.getElementById('form-edit-password').addEventListener('submit', async function (e) {
            e.preventDefault();
            let request, url, formInput, formBody, formUpdate, delete_id;
            clearErrorMessages('form-edit-password');
            loadingAlert('Mengubah Password ...');
            url = "{{route('cashless.admin.profil-admin.update-password',':id')}}"
            url = url.replace(':id', adminId)
            formInput = document.getElementById('form-edit-password');
            formUpdate = new FormData(formInput);
            formUpdate.append('_method', 'PUT');
            request = new Request(
                url, {
                    method: "POST",
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formUpdate
                });

            if (request) {
                let processForm = await submitForm(request);
            }
        })

        async function submitForm(request) {
            try {
                return await fetch(request)
                    .then(async response => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw {
                                status: response.status,
                                message: data.message || response.statusText,
                                errors: data.errors || data.error
                            };
                        }
                        return data;
                    })
                    .then(data => {
                        successAlert(data.message);
                        return {
                            success: true,
                        };
                    });
            } catch (error) {
                if (error.status === 422) {
                    const errors = error.errors || error.error;
                    errorAlert(error.message);
                    return {
                        success: 422,
                        errors: errors,
                    };
                } else {
                    const errorMessages = {
                        401: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman untuk melanjutkan! <br> jika masalah masih terjadi silahkan login kembali!',
                        403: 'Anda tidak memiliki izin untuk mengakses halaman ini 😖',
                        404: 'Halaman yang dituju tidak ditemukan 🧐',
                        405: 'Metode tidak valid 🧐 <br>silahkan muat ulang halaman dan coba lagi!',
                        419: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman untuk melanjutkan! <br> jika masalah masih terjadi silahkan login kembali!',
                        429: 'Terlalu banyak permintaan akses <br>silahkan tunggu beberapa saat 🙏',
                    };
                    errorAlert(errorMessages[error.status] || "Terjadi kesalahan saat memproses permintaan<br> Silahkan coba memuat ulang halaman");

                    return {
                        success: false,
                    };
                }
            }
        }


        document.querySelectorAll('.showPassword').forEach(function (el) {
            el.addEventListener('click', function (e) {
                const siblings = el.parentElement.children;
                for (let input of siblings) {
                    if (input.tagName === 'INPUT') {
                        input.type = input.type === 'password' ? 'text' : 'password';
                        let icon = el.children[0];
                        if (icon.tagName === 'i' || icon.tagName === 'I') {
                            if (icon.classList.contains('ri-eye-off-line')) {
                                icon.classList.toggle('ri-eye-off-line');
                                icon.classList.toggle('ri-eye-line');
                            } else {
                                icon.classList.toggle('ri-eye-line');
                                icon.classList.toggle('ri-eye-off-line');
                            }
                        }
                    }
                }
            });
        });


        document.getElementById('modal-edit-password').addEventListener('hidden.bs.modal', function () {
            const formId = 'form-edit-password';
            const form = document.getElementById(formId);
            form?.reset();
            clearErrorMessages(formId);

            document.querySelectorAll('.showPassword').forEach(function (el) {
                const siblings = el.parentElement.children;
                for (let input of siblings) {
                    if (input.tagName === 'INPUT' && input.type === 'text') {
                        input.type = 'password';

                        let icon = el.children[0];
                        if (icon && (icon.tagName === 'i' || icon.tagName === 'I')) {
                            icon.classList.remove('ri-eye-line');
                            icon.classList.add('ri-eye-off-line');
                        }
                    }
                }
            });
        });


    </script>
@endsection

