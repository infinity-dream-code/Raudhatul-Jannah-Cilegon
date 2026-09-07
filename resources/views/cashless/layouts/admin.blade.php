@php use Carbon\Carbon; @endphp
<!DOCTYPE html>
<html
    lang="en"
    class="light-style layout-navbar-fixed layout-menu-fixed layout-compact"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{asset('')}}"
    data-template="vertical-menu-template">
<head>
    <meta charset="utf-8"/>
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>

    <title>@yield('title', config('app.name')) - {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <meta name="description" content="Core system ICT "/>

    <link rel="icon" type="image/x-icon" href="{{asset('favicon.ico')}}"/>

    <link rel="stylesheet" href="{{asset('main/libs/perfect-scrollbar/perfect-scrollbar.css')}}"/>
    <link rel="stylesheet" href="{{asset('main/libs/sweetalert2/sweetalert2.css')}}"/>
    <link rel="stylesheet" href="{{asset('main/libs/spinkit/spinkit.css')}}"/>

    @hasSection('filepond')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/filepond@4.32.7/dist/filepond.min.css"
              integrity="sha256-R/TKiFR8YXiqvCSFSm3ek/rIjgEoFS5PpaAMkv/brg4=" crossorigin="anonymous">
        <link rel="stylesheet" href="{{asset('libs/filepond/dist/custom.css')}}">
    @endif

    @hasSection('datatable')
        <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
        @hasSection('datatable-responsive')
            <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
        @endif
    @endif

    @hasSection('bootstrap-datepicker')
        <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
    @endif

    @hasSection('bootstrap-daterangepicker')
        <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
    @endif

    @hasSection('select2')
        <link rel="stylesheet" href="{{asset('main/libs/select2/select2.min.css')}}">
        <link rel="stylesheet" href="{{asset('main/libs/select2/select2-bootstrap.css')}}">
        <style>
            select[readonly].select2-hidden-accessible + .select2-container {
                pointer-events: none;
                touch-action: none;
            }

            select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
                box-shadow: none;
            }

            select[readonly].select2-hidden-accessible + .select2-container .select2-selection__arrow,
            select[readonly].select2-hidden-accessible + .select2-container .select2-selection__clear {
                display: none;
            }
        </style>
    @endif

    @yield('style')

    <link rel="stylesheet" href="{{asset('main/css/status.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('main/css/core.min.css')}}" class="template-customizer-core-css"/>
    <link rel="stylesheet" href="{{asset('main/css/theme-default.css')}}" class="template-customizer-theme-css"/>

    <style>
        [class^="ri-"], [class*=" ri-"] {
            font-size: 18px;
            line-height: 1;
            vertical-align: middle
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            min-height: 2.25rem;
            flex-direction: column;
            justify-content: center;
            max-width: 100%;
        }

        .form-fieldset {
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--bs-body-bg);
            border: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color);
            border-radius: 0.625rem;
        }

        .modal-blur {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }

        .transparent-swal2 .swal2-popup {
            background-color: transparent !important;
            box-shadow: none !important;
        }

        .swal2-container.transparent-swal2 {
            background-color: rgba(48, 51, 78, .3) !important;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .table_dt_no {
            width: 25px !important;
            max-width: 25px !important;
            min-width: 25px !important;
        }
        
        .navbar-info-text {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .navbar-info-text span {
            margin-right: 8px;
        }
        
        .navbar-info-text .separator {
            margin: 0 5px;
            color: #dee2e6;
        }
        
        #date-time {
            font-family: monospace;
            font-size: 0.9rem;
        }
    </style>

    <script src="{{asset('main/js/helpers.js')}}"></script>
    <script src="{{asset('main/js/template-customizer.min.js')}}"></script>
    <script src="{{asset('js/config.js')}}"></script>
</head>

<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('cashless.layouts.adminMenu_new')

        <div class="layout-page">
            <nav
                class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                        <i class="ri-menu-fill ri-xl"></i>
                    </a>
                </div>

                <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                    <div class="navbar-nav align-items-center d-none d-md-flex">
                        <div class="navbar-info-text">
                            <span><i class="ri-apps-2-line me-1"></i>{{ config('app.name') }}</span>
                            <span class="separator">|</span>
                            <span><i class="ri-user-line me-1"></i>{{ session('cashless_user.username') }}</span>
                            @if(!empty($namaKantin))
                                <span class="separator">|</span>
                                <span><i class="ri-store-line me-1"></i>{{ $namaKantin }}</span>
                            @endif
                            <span class="separator">|</span>
                            <span><i class="ri-calendar-line me-1"></i><span id="date-time"></span></span>
                        </div>
                    </div>

                    <ul class="navbar-nav flex-row align-items-center ms-auto">
                        <li class="nav-item dropdown-style-switcher dropdown me-1 me-xl-0">
                            <a class="nav-link btn btn-text-secondary rounded-pill btn-icon dropdown-toggle hide-arrow"
                               href="javascript:void(0);" data-bs-toggle="dropdown">
                                <i class='ri-xl'></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-styles">
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" data-theme="light">
                                        <span class="align-middle"><i class='ri-sun-line ri-xl me-3'></i>Terang</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" data-theme="dark">
                                        <span class="align-middle"><i class="ri-moon-clear-line ri-xl me-3"></i>Gelap</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" data-theme="system">
                                        <span class="align-middle"><i class="ri-computer-line ri-xl me-3"></i>Sistem</span>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item navbar-dropdown dropdown-user dropdown">
                            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                               data-bs-toggle="dropdown">
                                <div class="avatar avatar-online">
                                    <img src="{{asset('logo.png')}}" alt class="rounded-circle">
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-2">
                                                <div class="avatar avatar-online">
                                                    <img src="{{asset('logo.png')}}" alt class="rounded-circle">
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="fw-medium d-block small">{{config('app.name')}}</span>
                                                <small class="text-muted d-block">{{session('cashless_user.username')}}</small>
                                                @if(!empty($namaKantin))
                                                    <small class="text-muted d-block">{{$namaKantin}}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <div class="dropdown-divider"></div>
                                </li>
                                <li>
                                    <div class="d-grid px-4 pt-2 pb-1">
                                        <a class="btn btn-sm btn-danger d-flex" href="{{route('cashless.logout')}}"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <small class="align-middle">Logout</small>
                                            <i class="ri-logout-box-r-line ms-2 ri-16px"></i>
                                        </a>
                                        <form id="logout-form" action="{{ route('cashless.logout') }}" method="POST" class="d-none">
                                            @csrf
                                        </form>
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="content-wrapper">
                <div class="flex-grow-1 container-p-y container-fluid">
                    @yield('content')
                </div>

                <footer class="content-footer footer bg-footer-theme">
                    <div class="container-xxl">
                        <div
                            class="footer-container d-flex align-items-center justify-content-center py-3 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0 text-center">
                                ©
                                <script>document.write(new Date().getFullYear());</script>
                                , made with <span class="text-danger"><i class="tf-icons ri ri-heart-3-fill"></i></span>
                                by
                                <a href="{{route('cashless.admin.index')}}" target="_blank"
                                   class="footer-link fw-medium">PT. Inovasi Cipta Teknologi</a>
                            </div>
                        </div>
                    </div>
                </footer>

                <button type="button" href="#" id="backToTopBtn" class="btn btn-lg px-3 back-to-top" role="button"
                        aria-label="Ke Atas" title="Ke Atas">
                    <i class="ri ri-arrow-up-line ri-2x"></i>
                </button>

                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <div class="drag-target"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha256-y3ibfOyBqlgBd+GzwFYQEVOZdNJD06HeDXihongBXKs=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/perfect-scrollbar@1.5.6/dist/perfect-scrollbar.min.js"
        integrity="sha256-B69LaJOkADtiChnrAMKFvAyqbzM3Thpr6EyGtViOFG8=" crossorigin="anonymous"></script>
<script src="{{asset('main/js/menu.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js"
        integrity="sha256-WjwoxFTbs4JzyDmrHgK4VgR+Dz7he8HYwCbocAlNn9k=" crossorigin="anonymous"></script>
<script src="{{asset('js/main.js')}}"></script>
<script src="{{asset('js/alerts.min.js')}}"></script>
<script src="{{asset('js/cashless-fetch.js')}}"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"/>

<style>
    .back-to-top {
        display: none !important;
        color: #30334e;
        background-color: rgba(231, 231, 255, 0.8);
        -webkit-backdrop-filter: saturate(180%) blur(6px);
        backdrop-filter: saturate(180%) blur(6px);
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: auto;
        transition: opacity 0.3s ease-in-out !important;
    }

    .dark-style .back-to-top {
        color: #e7e7ff;
        background-color: rgba(48, 51, 78, .8)
    }
</style>

@if(session()->has('alert'))
    <script>
        {!! session('alert') !!}
    </script>
@endif

@if (session('error'))
    <script>
        console.log('error');
        errorAlert('{{ session('error') }}');
    </script>
@endif

<script>
    function updateDateTime() {
        let now = new Date();
        let days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        let months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        let dayName = days[now.getDay()];
        let day = now.getDate();
        let month = months[now.getMonth()];
        let year = now.getFullYear();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        
        let dateString = `${dayName}, ${day} ${month} ${year}`;
        let timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        document.getElementById('date-time').innerHTML = `${dateString} - ${timeString}`;
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);

    document.addEventListener('DOMContentLoaded', function () {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        let backToTopButton = document.getElementById('backToTopBtn');

        window.addEventListener('scroll', function () {
            if (window.scrollY > 200) {
                backToTopButton.style.setProperty('display', 'block', 'important');
            } else {
                backToTopButton.style.setProperty('display', 'none', 'important');
            }
        });

        backToTopButton.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    });
</script>

@hasSection('formattedNumber')
    <script src="{{asset('js/helper/formattedNumber.min.js')}}"></script>
@endif
@hasSection('errorInputHelper')
    <script src="{{asset('js/helper/errorInputHelper.min.js')}}"></script>
@endif
@hasSection('momentjs')
    <script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/moment.min.js"></script>
@endif

@hasSection('bootstrap-datepicker')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.0/dist/js/bootstrap-datepicker.min.js"
            integrity="sha256-iZp9dyOMJKPFdn1UMra9ZMhPZAlSGZUzdhqqEgijE+Q=" crossorigin="anonymous" defer></script>
@endif

@hasSection('bootstrap-daterangepicker')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-daterangepicker@3.1.0/daterangepicker.min.js" defer></script>
@endif

@hasSection('select2')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"
            integrity="sha256-AFAYEOkzB6iIKnTYZOdUf9FFje6lOTYdwRJKwTN5mks=" crossorigin="anonymous" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/i18n/id.js"
            integrity="sha256-fGJ++Aw70Ppzk3EgLjF1V/QvqD2q/ufXjnQIIyZqYgc=" crossorigin="anonymous" defer></script>
@endif

@hasSection('datatable')
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}" defer></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}" defer></script>

    @hasSection('datatable-select')
        <script src="https://cdn.jsdelivr.net/npm/jquery-datatables-checkboxes@1.2.14/js/dataTables.checkboxes.min.js"
                integrity="sha256-0fpVa2zI7evUwBEHoZUbn4GsMQtXIvE4TXUMPz3hA50=" crossorigin="anonymous" defer></script>
    @endif

    @hasSection('datatable-buttons')
        <link rel="stylesheet" href="{{asset('main/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
    @endif
@endif

@hasSection('filepond')
    <script
        src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js"
        integrity="sha256-iNzotay9f+s57lx/JEVaRWbOsGGXZlPPZlSQ34OHx+A=" crossorigin="anonymous"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-size@2.2.8/dist/filepond-plugin-file-validate-size.min.js"
        integrity="sha256-XaHceW4NII52xG26aOw+77JG8yEk95GAZs8h7ZAv1xo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/filepond@4.32.7/dist/filepond.min.js"
            integrity="sha256-BRICH2AsAT7Vx36hU5PcHTuKBbusAU4j6fge+/dHO1M=" crossorigin="anonymous" defer></script>
    <script type="text/javascript" defer>
        let filePondElements = [];

        function initializeFilePond(id) {
            let inputElement = document.querySelector('input#' + id);
            filePondElements[id] = FilePond.create(inputElement, {
                credits: null,
                allowFileEncode: false,
                required: false,
                storeAsFile: true,
                acceptedFileTypes: [
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/wps-office.xlsx',
                    'application/wps-office.xls'
                ],
                labelIdle: 'Klik untuk membuka file manager, atau seret file ke dalam box ini.',
                allowFileTypeValidation: true,
                allowFileSizeValidation: true,
                labelMaxFileSizeExceeded: 'File terlalu besar',
                labelMaxFileSize: 'Ukuran maksimal file: {filesize}',
                labelFileTypeNotAllowed: 'Format file salah!',
                fileValidateTypeLabelExpectedTypes: 'file harus berformat .xls atau .xlsx',
                maxFileSize: 1024000,
            });
        }

        function resetFilePond(id) {
            filePondElements[id].removeFiles();
        }

        document.addEventListener('DOMContentLoaded', function (e) {
            FilePond.registerPlugin(
                FilePondPluginFileValidateType,
                FilePondPluginFileValidateSize,
            );
        });
    </script>
@endif

@yield('script')
</body>
</html>
