@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.min.css')}}">
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        @if(isset($dataTitle) && isset($mainTitle) && $mainTitle != $dataTitle)
            {{$mainTitle .' - '.$dataTitle}}
        @else
            {{$mainTitle??$title??''}}
        @endif
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{route('cashless.admin.index')}}" class="text-hover-primary">Beranda</a>
        </li>
        @if(isset($title))
            <li class="breadcrumb-item">
                {{$title}}
            </li>
        @endif
        @if(isset($mainTitle))
            <li class="breadcrumb-item">
                {{$mainTitle}}
            </li>
        @endif
        @if(isset($dataTitle) && isset($mainTitle) && $mainTitle != $dataTitle)
            <li class="breadcrumb-item active">
                {{$dataTitle}}
            </li>
        @endif
    </ul>

    <div class="card">
        <div class="card-header header-elements">
            <div class="card-title">
                <h5 class="mb-0 me-2">{{($dataTitle??$mainTitle)}}</h5>
            </div>
            <div class="card-header-elements ms-auto">
                <div class="d-flex justify-content-center justify-content-md-end gap-4">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#modal-create" title="Buat Data">
                        <span class="ri-add-line me-2"></span>
                        Buat Admin
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form id="filterForm">
                <fieldset class="form-fieldset">
                    <h5>Filter</h5>
                    <div class="row">
                        <div class="row mb-4">
                            <label class="col-sm-2 col-form-label" for="filter[role]">
                                Role Admin
                            </label>
                            <div class="col-sm-10">
                                <select class="form-select" id="filter[role]"
                                        name="filter[role]"
                                        data-control="select2"
                                        data-placeholder="Pilih Role">
                                    <option value="all" >Semua</option>
                                    @isset($role)
                                        @foreach($role as $item)
                                            @if($item->name !== 'siswa')
                                                <option
                                                    value="{{$item->name}}">{{$item->name}}</option>
                                            @endif
                                        @endforeach
                                    @else
                                        <option>data kosong</option>
                                    @endisset
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="d-flex justify-content-center justify-content-md-end gap-4">
                            <button type="reset" class="btn btn-secondary">
                                <span class="ri-reset-left-line me-2"></span>
                                Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <span class="ri-search-line me-2"></span>
                                Cari
                            </button>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover"
                   id="main_table">
                <thead class="table-light">

                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')

    <form id="form-delete" class="mainForm">
        <div class="modal modal-blur fade" id="modal-delete" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status bg-danger"></div>
                    <div class="modal-header ">
                        <div class="modal-title">
                            Hapus Data
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-capitalize text-center py-4">
                        <i class="ti ti-trash-x ti-3xl text-danger"></i>
                        <h3>Hapus Data User?</h3>
                        <div class="text-secondary">
                            anda yakin akan menghapus data Admin?
                        </div>
                        <input type="hidden" id="delete_id" name="delete_id" value="12">
                    </div>
                    <div class="modal-footer ">
                        <div class="w-100">
                            <div class="row">
                                <div class="col">
                                    <input type="reset" class="btn btn-outline-secondary w-100" value="Batal"
                                           data-bs-dismiss="modal">
                                </div>
                                <div class="col">
                                    <input type="submit" value="Hapus Data" class="btn btn-danger w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="form-edit" class="mainForm">
        <div class="modal modal-blur fade" id="modal-edit" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status bg-warning"></div>
                    <div class="modal-header">
                        <div class="modal-title">
                            Edit Data User
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-capitalize py-2">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label required" for="edit-username">Username</label>
                                <input type="text" class="form-control" id="edit-username" name="username"
                                       autocomplete="off"
                                       placeholder="Username" required>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="edit-nama">Nama</label>
                                <input type="text" class="form-control" name="nama" id="edit-nama" autocomplete="off"
                                       placeholder="Nama" required>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-role">
                                    Role
                                </label>
                                <select class="form-select required" id="edit-role" name="role"
                                        data-control="select2" data-placeholder="Pilih Role">
                                    @isset($role)
                                        @foreach($role as $item)
                                            @if($item->name !== 'siswa')
                                                <option
                                                    value="{{$item->name}}">{{$item->name}}</option>
                                            @endif
                                        @endforeach
                                    @else
                                        <option>data kosong</option>
                                    @endisset
                                </select>
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
                                    <input type="submit" value="Simpan Data" class="btn btn-warning w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form id="form-create" class="mainForm">
        <div class="modal modal-blur fade" id="modal-create" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Tambah Data User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label required" for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" autocomplete="off"
                                       placeholder="Username" required>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="nama">Nama</label>
                                <input type="text" class="form-control" name="nama" id="nama" autocomplete="off"
                                       placeholder="Nama" required>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group">
                                    <input type="password" placeholder="Masukkan Password Anda" name="password"
                                           id="password"
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
                                <label class="form-label" for="password_confirmation">Konfirmasi password</label>
                                <div class="input-group">
                                    <input type="password" placeholder="Konfirmasi Password Anda"
                                           name="password_confirmation"
                                           id="password_confirmation"
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
                                <label class="form-label" for="role">
                                    Role
                                </label>
                                <select class="form-select required" id="role" name="role"
                                        data-control="select2" data-placeholder="Pilih Role">
                                    @isset($role)
                                        @foreach($role as $item)
                                            @if($item->name !== 'siswa')
                                                <option
                                                    value="{{$item->name}}">{{$item->name}}</option>
                                            @endif
                                        @endforeach
                                    @else
                                        <option>data kosong</option>
                                    @endisset
                                </select>
                            </div>
                        </fieldset>
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
                                <input type="text" class="form-control" id="password-username" name="username" autocomplete="off"
                                       placeholder="Username" readonly>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password-nama">Nama</label>
                                <input type="text" class="form-control" name="nama" id="password-nama" autocomplete="off"
                                       placeholder="Nama" readonly>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password-email">Email</label>
                                <input type="email" class="form-control required" id="password-email" name="email"
                                       autocomplete="off"
                                       placeholder="Email" readonly>
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
                                <label class="form-label" for="password-password_confirmation">Konfirmasi password</label>
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

    <form id="form-reset-password" class="mainForm">
        <div class="modal modal-blur fade" id="modal-reset-password" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Reset Password
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-capitalize text-center py-4">
                        <h3>Reset Password?</h3>
                        <div class="text-secondary">
                            anda yakin akan mereset password Admin?
                        </div>
                        <input type="hidden" id="delete_id" name="delete_id" value="12">
                    </div>
                    <div class="modal-body py-4">
                        <fieldset class="form-fieldset">
                            <div class="mb-3">
                                <label class="form-label" for="reset-password-username">Username</label>
                                <input type="text" class="form-control" id="reset-password-username" name="username" autocomplete="off"
                                       placeholder="Username" readonly>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="reset-password-nama">Nama</label>
                                <input type="text" class="form-control" name="nama" id="reset-password-nama" autocomplete="off"
                                       placeholder="Nama" readonly>
                                <div class="invalid-feedback" role="alert">
                                    <strong></strong>
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
                                    <input type="submit" value="Simpan Data" class="btn btn-warning w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.min.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('main/libs/select2/select2.min.js')}}"></script>
    <script src="{{asset('js/helper/formattedNumber.min.js')}}"></script>
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>

    <script type="text/javascript">
        const select2 = $(`[data-control='select2']`);
        let filePondElements = [];
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const imagePath = '{{asset('storage/photos/tanda_tangan')}}';

        let dtOptions = {
            tableId: 'main_table',
            formId: 'filterForm',
            columnUrl: '{{($columnsUrl??null)}}',
            dataUrl: '{{($datasUrl??null)}}',
            dataColumns: [],
            thead: true,
            tfoot: true,
            paging: true,
            searching: true,
            fixedHeader: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 75, 100],
            info: false,
            scrollX: false,
            serverSide: true,
            select: false,
            scrollY: false,
        };

        function updateFilterWindowLocation(form) {
            let baseUrl = window.location.origin + window.location.pathname;
            let queryParams = $.param($(`#${form}`).serializeArray().reduce(function (acc, curr) {
                if (curr.value !== '') {
                    acc[curr.name] = curr.value;
                }
                return acc;
            }, {}));
            let newUrl = baseUrl + '?' + queryParams;
            window.history.pushState(null, '', newUrl);
        }

        const modals = [
            {modalId: 'modal-create', formId: 'form-create'},
            {modalId: 'modal-edit', formId: 'form-edit'},
            {modalId: 'modal-edit-password', formId: 'form-edit-password'},
            {modalId: 'modal-reset-password', formId: 'form-reset-password'},
        ];

        const modalInstances = {};

        modals.forEach(({modalId, formId, inputs}) => {
            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);

            modalInstances[modalId] = modal;

            modalElement.addEventListener('hide.bs.modal', () => {
                const form = document.getElementById(formId);
                form?.reset();
                clearErrorMessages(formId);
            });

            modalElement.addEventListener('show.bs.modal', function (e) {
                if (formId !== 'form-create') {
                    const button = event.relatedTarget;
                    const row = DT[`${dtOptions.tableId}`].row($(button).closest('tr'));
                    fillFormValue(formId, row);
                }
            });

            document.getElementById(formId).addEventListener('submit', async function (e) {
                e.preventDefault();
                let processForm = await submitForm(formId);
                if (processForm) {
                    modal.hide();
                }
            });
        });

        function fillFormValue(id, rowEl) {
            const rowData = DT[`${dtOptions.tableId}`].row(rowEl).data();
            Object.entries(rowData).forEach(([key, value]) => {
                let input = document.querySelector(`#${id} [name="${key.toLowerCase()}"]`);
                if (input) {
                    if (id === 'form-show') {
                        if ($(input).hasClass('select2-hidden-accessible')) {
                            $(input).val(value).trigger('change').prop('disabled', true);
                        }
                    } else if (id === "form-edit") {
                        if ($(input).hasClass('select2-hidden-accessible')) {
                            $(input).val(value).trigger('change');
                        } else if (key === 'tanda_tangan') {
                            $('#edit-show-image').attr('src', `${imagePath}/${value}`);
                        } else {
                            input.value = value;
                        }
                    } else {
                        if ($(input).hasClass('select2-hidden-accessible')) {
                            $(input).val(value).trigger('change');
                        } else {
                            input.value = value;
                        }
                    }
                }
            });
        }

        async function submitForm(form) {
            let request, url, formInput;
            clearErrorMessages(form);
            formInput = document.getElementById(form);
            let formdata = new FormData(formInput);
            switch (form) {
                case 'form-create':
                    loadingAlert('Membuat Master Data ....');
                    url = "{{route('cashless.admin.manajemen-admin.store')}}";
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: formdata
                        });
                    break;
                case 'form-edit':
                    loadingAlert('Membuat Master Data ....');
                    const update_id = document.querySelector(`#${form} [name="item_id"]`).value;
                    url = "{{route('cashless.admin.manajemen-admin.update',['id'=>':id'])}}";
                    url = url.replace(':id', update_id)
                    formdata.append('_method', 'PUT');
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: formdata
                        });
                    break;
                case 'form-edit-password':
                    loadingAlert('Merubah Password Admin ....');
                    const reset_pass_id = document.querySelector(`#${form} [name="item_id"]`).value;
                    url = "{{route('cashless.admin.manajemen-admin.update-password',['id'=>':id'])}}";
                    url = url.replace(':id', reset_pass_id)
                    formdata.append('_method', 'PUT');
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: formdata
                        });
                    break;
                case 'form-reset-password':
                    loadingAlert('Mereset Password Admin ....');
                    const update_pass_id = document.querySelector(`#${form} [name="item_id"]`).value;
                    url = "{{route('cashless.admin.manajemen-admin.reset-password',['id'=>':id'])}}";
                    url = url.replace(':id', update_pass_id)
                    formInput = document.getElementById(form);
                    const formEditPass = new FormData(formInput)
                    formEditPass.append('_method', 'PUT');
                    request = new Request(
                        url, {
                            method: "POST",
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: formEditPass
                        });
                    break;
                default:
                    errorAlert('Data tidak valid!');
                    return;
            }

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
                        dataReload(dtOptions.tableId);
                        successAlert(data.message);
                        return true;
                    });
            } catch (error) {
                if (error.status === 422) {
                    const errors = error.errors || error.error;
                    errorAlert(error.message);
                    if (errors) {
                        processErrors(errors, form);
                    }
                } else {
                    const errorMessages = {
                        401: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman untuk melanjutkan! <br> jika masalah masih terjadi silahkan login kembali!',
                        403: 'Anda tidak memiliki izin untuk mengakses halaman ini 😖',
                        404: 'Halaman yang dituju tidak ditemukan 🧐',
                        405: 'Metode tidak valid 🧐 <br>silahkan muat ulang halaman dan coba lagi!',
                        419: 'Sesi anda sudah habis 🙏 <br>Silahkan muat ulang halaman untuk melanjutkan! <br> jika masalah masih terjadi silahkan login kembali!',
                        429: 'Terlalu banyak permintaan akses <br>silahkan tunggu beberapa saat 🙏',
                    };
                    errorAlert(errorMessages[error.status] || "Terjadi kesalahan, silahkan coba memuat ulang halaman");
                }
                return false;
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);
                if (dtOptions.formId) {
                    let filterForm = $(`#${dtOptions.formId}`);
                    filterForm.on('submit', function (e) {
                        e.preventDefault();
                        dataReFilter(dtOptions.tableId);
                    });
                    filterForm.on('reset', function (e) {
                        setTimeout(function () {
                            dataReFilter(dtOptions.tableId);
                            const select2InForm = select2.filter(`#${dtOptions.formId} [data-control='select2']`);
                            if (select2InForm.length) {
                                select2InForm.each(function () {
                                    let $this = $(this);
                                    $this.trigger('change');
                                });
                            }
                            updateFilterWindowLocation(dtOptions.formId);
                            dataReFilter(dtOptions.tableId);
                        }, 0)
                    });
                }
            }
            if (select2.length) {
                select2.each(function () {
                    let $this = $(this);
                    const placeholder = $this.attr('data-placeholder') ?? 'Pilih Data';
                    $this.wrap('<div class="position-relative"></div>').select2({
                        placeholder: placeholder,
                        language: 'id',
                        dropdownParent: $this.parent()
                    });
                });
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

            function resetPasswordView(){
                document.querySelectorAll('.showPassword').forEach(function (el) {
                    const siblings = el.parentElement.children;
                    for (let input of siblings) {
                        if (input.tagName === 'INPUT' && input.type === 'text') {
                            input.type = 'password'; // reset input

                            let icon = el.children[0];
                            if (icon && (icon.tagName === 'i' || icon.tagName === 'I')) {
                                icon.classList.remove('ri-eye-line');
                                icon.classList.add('ri-eye-off-line');
                            }
                        }
                    }
                });
            }
            document.getElementById('modal-edit-password').addEventListener('hidden.bs.modal', function () {
                resetPasswordView();
            });

            document.getElementById('modal-edit').addEventListener('hidden.bs.modal', function () {
                resetPasswordView();
            });
        });

    </script>
@endsection

