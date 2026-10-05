@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'Dashboard')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/apex-charts/apex-charts.css')}}"/>
    <link rel="stylesheet" href="{{ asset('css/cashless-face-pay.css') }}?v=1"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css"/>
    <style>
        .cashless-face-wrap .k-modal { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; }
        .cashless-face-wrap .k-modal[hidden] { display: none !important; }
        .cashless-face-wrap .k-modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.45); }
        .cashless-face-wrap .k-modal__dialog { position: relative; z-index: 1; max-width: 480px; width: calc(100% - 2rem); }
        .cashless-face-wrap .k-stage { position: relative; }
        .cashless-face-wrap .k-stage__overlay { position: absolute; inset: 0; pointer-events: none; }
        .cashless-face-wrap .k-target { position: absolute; border: 2px solid #22c55e; border-radius: 8px; box-sizing: border-box; }
    </style>
@endsection

@section('content')
    <div class="container-xxl py-4">
        <h3 class="page-heading fw-bold mb-3">{{ $mainTitle ?? $title ?? 'Tap Belanja' }}</h3>

        <ul class="nav nav-pills mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-rfid-btn" data-bs-toggle="pill" data-bs-target="#tab-rfid" type="button" role="tab">RFID / Tap kartu</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-face-btn" data-bs-toggle="pill" data-bs-target="#tab-face" type="button" role="tab">Wajah (FacePay)</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-rfid" role="tabpanel">
    <div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
        <div class="w-100 m-auto" style="max-width: 640px;">
            <form id="form-data">
                <div class="card">
                    <div class="card-body">
                        <fieldset class="form-fieldset pb-0">
                            <div class="col mb-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 120px;">TAP ID</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="TAP ID"
                                           name="tap_id" id="tap_id" aria-describedby="tap_id" enterkeyhint="done" inputmode="numeric">
                                </div>
                            </div>
                            <div class="col mb-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 120px;">NAMA</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="NAMA"
                                           name="nama"
                                           id="nama" aria-describedby="nama" readonly>
                                </div>
                            </div>
                            <div class="col mb-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 120px;">SALDO</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="SALDO"
                                           name="saldo"
                                           id="saldo" aria-describedby="saldo" readonly>
                                </div>
                            </div>
                            <div class="col mb-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 120px;">BELANJA</span>
                                    <input type="text" class="form-control form-control-lg formattedNumber"
                                           placeholder="BELANJA" name="belanja" id="belanja" aria-describedby="belanja" enterkeyhint="done" inputmode="numeric">
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary">Proses</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
            </div>

            <div class="tab-pane fade" id="tab-face" role="tabpanel">
                @include('cashless.admin.tap_belanja._face')
            </div>
        </div>
    </div>
@endsection
@section('errorInputHelper', true)
@section('script')
    @if($faceDbReady ?? false)
        <script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
        <script src="{{ asset('js/cashless/presensi-camera.js') }}?v=1"></script>
        <script>
            window.TAP_BELANJA_FACE = {
                referencesUrl: @json(route('cashless.admin.tap-belanja.face-references')),
                saldoUrl: @json(route('cashless.admin.tap-belanja.get-saldo-nis')),
                paymentUrl: @json(route('cashless.admin.tap-belanja.payment-nis')),
                csrf: @json(csrf_token()),
            };
        </script>
        <script src="{{ asset('js/cashless/tap-belanja-face.js') }}?v=20261005"></script>
        <script defer>
            document.addEventListener('DOMContentLoaded', function () {
                var faceTab = document.getElementById('tab-face-btn');
                var started = false;
                function bootFace() {
                    if (started || !window.CashlessTapBelanjaFace) return;
                    started = true;
                    window.CashlessTapBelanjaFace.init();
                }
                if (faceTab) {
                    faceTab.addEventListener('shown.bs.tab', bootFace);
                }
            });
        </script>
    @endif
    <script type="text/javascript" defer>
        document.addEventListener("DOMContentLoaded", function () {
            function formatRupiah(amount) {
                if (amount === null || amount === undefined || amount === '') return 'Rp 0';
                const num = Math.round(Number(amount));
                return 'Rp. ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            let currentIDStatus = false;
            const tapIdInput = document.getElementById('tap_id');
            tapIdInput.focus();
            document.getElementById('form-data').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearErrorMessages('form-data');
                const form = e.target;
                let request = false;
                const formData = new FormData(this);
                let tap_id = (formData.get('tap_id') || '').toString().trim();
                let belanja = formData.get('belanja');

                if (!tap_id && !belanja) {
                    warningAlert('Silahkan tap kartu terlebih dahulu', 'tap_id');
                } else if (tap_id && !belanja) {
                    loadingAlert("Memproses Kartu...");
                    request = cashlessRequest('/cashless/admin/tap-belanja/get-saldo', {
                        method: "POST",
                        body: formData
                    });
                } else if (tap_id && belanja) {
                    loadingAlert("Memproses Belanja...");
                    request = cashlessRequest('/cashless/admin/tap-belanja/payment', {
                        method: "POST",
                        body: formData
                    });
                }

                if (request) {
                    let processForm = await cashlessSubmit(request);
                    if (processForm.success === true) {
                        const result = processForm.data.data;
                        if (tap_id && !belanja) {
                            // Terima array [id, saldo, nama] (minimal 3 elemen)
                            const ok = Array.isArray(result) && result.length >= 3 && result[2] !== undefined;
                            if (!ok) {
                                warningAlert('Data tidak ditemukan, silahkan tap kartu yang valid', 'tap_id');
                                document.getElementById('saldo').value = 0;
                                document.getElementById('nama').value = '';
                            } else {
                                document.getElementById('belanja').setAttribute('max', result[1]);
                                document.getElementById('saldo').value = result[1];
                                document.getElementById('nama').value = result[2];
                                currentIDStatus = true;
                                Swal.close();
                            }
                        } else if (tap_id && belanja) {
                            const hasil = processForm.data;
                            switch (hasil.code) {
                                case 1000:
                                    form.reset();
                                    let message = hasil.message;
                                    let nama = hasil.data.nama;
                                    let sisa = formatRupiah(hasil.data.sisa_saldo ?? 0);
                                    successAlert(`${message} <br> Nama Siswa : ${nama} <br> Sisa Saldo : ${sisa}` , 'tap_id');
                                    currentIDStatus = false;
                                    break;
                                case 2001:
                                    warningAlert(hasil.message, 'belanja');
                                    break;
                                case 2002:
                                    warningAlert(hasil.message);
                                    break;
                                case 2003:
                                    warningAlert(hasil.message);
                                    break;
                                case 9999:
                                    warningAlert(hasil.message);
                                    break;
                                default:
                                    warningAlert(hasil.message ?? "Terjadi kesalahan, silahkan hubungi admin");
                                    break;
                            }
                        }
                    } else {
                        if (tap_id && !belanja) {
                            warningAlert(
                                'Data tidak ditemukan, silahkan tap kartu yang valid',
                                'tap_id'
                            )
                            currentIDStatus = false
                        }
                        if (processForm.errors) {
                            processErrors(processForm.errors, 'tap_id');
                        }
                    }
                }
            });

            document.addEventListener('keypress', function (e) {
                const allowedClasses = ['formattedNumber', 'onlyNumber', 'onlyNumberWithPrefix'];
                if (allowedClasses.some(cls => e.target.classList.contains(cls))) {
                    const charCode = e.which || e.keyCode;
                    if (
                        charCode === 13 ||
                        charCode === 8  ||
                        charCode === 0
                    ) {
                        return;
                    }

                    if (charCode < 48 || charCode > 57) {
                        e.preventDefault();
                    }
                }
            });

            document.addEventListener('paste', function (e) {
                const allowedClasses = ['formattedNumber', 'onlyNumber'];
                if (allowedClasses.some(cls => e.target.classList.contains(cls))) {
                    const inputElement = e.target;
                    const oldElementValue = inputElement.value;
                    if (inputElement.hasAttribute('readonly') || inputElement.disabled) {
                        e.preventDefault();
                        return;
                    }
                    e.preventDefault();
                    const clipboardData = (e.clipboardData || window.clipboardData).getData('text');
                    const sanitizedValue = clipboardData.replace(/[^0-9]/g, '');
                    const sanitizedOldElementValue = oldElementValue.replace(/[^0-9]/g, '');
                    if (sanitizedValue) {
                        let parsedNumber = parseInt(sanitizedValue, 10);
                        parsedNumber = parsedNumber + parseInt(sanitizedOldElementValue,10);

                        if (inputElement.hasAttribute('max')) {
                            const maxValue = parseInt(inputElement.getAttribute('max'), 10);
                            if (!isNaN(maxValue) && parsedNumber > maxValue) {
                                parsedNumber = maxValue;
                            }
                        }
                        if (inputElement.hasAttribute('min')) {
                            const minValue = parseInt(inputElement.getAttribute('min'), 10);
                            if (!isNaN(minValue) && parsedNumber < minValue) {
                                parsedNumber = minValue;
                            }
                        }
                        if (inputElement.classList.contains('onlyNumber')){
                            inputElement.value = parsedNumber;
                        }else{
                            inputElement.value = parsedNumber.toLocaleString('id-ID');
                        }
                    }
                }
            });

            document.addEventListener('input', function (e) {
                const allowedClasses = ['formattedNumber', 'onlyNumber'];
                if (allowedClasses.some(cls => e.target.classList.contains(cls))) {
                    const inputElement = e.target;
                    const formattedValue = inputElement.value;
                    // const cursorPosition = inputElement.selectionStart;
                    let parsedNumber = parseInt(formattedValue.replace(/\./g, ''), 10);
                    if (!isNaN(parsedNumber)) {
                        if (inputElement.hasAttribute('max')) {
                            const maxValue = parseInt(inputElement.getAttribute('max'), 10);
                            if (!isNaN(maxValue) && parsedNumber > maxValue) {
                                parsedNumber = maxValue;
                            }
                        }
                        if (inputElement.hasAttribute('min')) {
                            const minValue = parseInt(inputElement.getAttribute('min'), 10);
                            if (!isNaN(minValue) && parsedNumber < minValue) {
                                parsedNumber = minValue;
                            }
                        }
                        let formattedString;
                        if (inputElement.classList.contains('formattedNumber')) {
                            formattedString = parsedNumber.toLocaleString('id-ID');
                        } else {
                            formattedString = parsedNumber;
                        }
                        inputElement.value = formattedString;
                        // const newCursorPosition = Math.max(0, cursorPosition + (formattedString.length - formattedValue.length));
                        // inputElement.setSelectionRange(newCursorPosition, newCursorPosition);
                    }
                }
            });
        });
    </script>
@endsection

