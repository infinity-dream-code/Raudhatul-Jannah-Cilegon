@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'Dashboard')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/apex-charts/apex-charts.css')}}"/>

@endsection

@section('content')
    <div class="d-flex justify-content-center align-items-center" style="height: 80vh !important;">
        <div class="w-100 m-auto">
            <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
                @if(isset($dataTitle) && isset($mainTitle) && $mainTitle != $dataTitle)
                    {{$mainTitle .' - '.$dataTitle}}
                @else
                    {{$mainTitle??$title??''}}
                @endif
            </h3>
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
@endsection
@section('errorInputHelper', true)
@section('script')
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

