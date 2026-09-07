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
                                    <span class="input-group-text" style="width: 120px;">LIMIT</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="LIMIT"
                                           name="limit"
                                           id="limit" aria-describedby="limit" readonly>
                                </div>
                            </div>
                            <div class="col mb-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 120px;">NIS</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="NIS"
                                           name="nis"
                                           id="nis" aria-describedby="nis" readonly>
                                </div>
                            </div>
                            <div class="col mb-5">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 120px;">Nama</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="Nama"
                                           name="nama"
                                           id="nama" aria-describedby="nama" readonly>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        document.addEventListener("DOMContentLoaded", function () {
            function formatRupiah(amount) {
                if (!amount) return 'Rp 0';
                return 'Rp. ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            // document.getElementById('pin').addEventListener('keypress', function (e) {
            //     if (e.key === 'Enter') {
            //         document.getElementById('form-data').requestSubmit();
            //     }
            // })

            let currentIDStatus = false;
            // document.getElementById('tap_id').focus({focusVisible: true});
            const tapIdInput = document.getElementById('tap_id');
            tapIdInput.focus()
            document.getElementById('form-data').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearErrorMessages('form-data');
                const form = e.target;
                let request = false;
                const formData = new FormData(this);
                let tap_id = formData.get('tap_id');
                // let pin = formData.get('pin');

                if (!tap_id) {
                    warningAlert('Silahkan tap kartu terlebih dahulu', 'tap_id');
                    return;
                }

                request = new Request('{{route('cashless.admin.cek-limit.get-limit')}}', {
                    method: "POST",
                    headers: {'X-CSRF-TOKEN': csrfToken},
                    body: formData
                });

                if (request) {
                    let processForm = await submitForm(request);
                    if (processForm.success === true) {
                        const hasil = processForm.data;
                        if(hasil.data === 'error' || hasil.data === '' || !hasil.data){
                            warningAlert('Kartu diblokir atau tidak ditemukan', 'tap_id');
                        }else{
                            document.getElementById('limit').value = formatRupiah(hasil.data ?? 0);
                            document.getElementById('nis').value = hasil.nis;
                            document.getElementById('nama').value = hasil.nama;
                        }
                    } else {
                        processErrors(processForm.errors, 'tap_id');
                    }
                }
            });
        });

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
                        return {success: true, data: data};
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
                    return {success: false};
                }
            }
        }
    </script>
@endsection

