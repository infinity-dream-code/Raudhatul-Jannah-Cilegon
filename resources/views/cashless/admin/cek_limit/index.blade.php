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
        document.addEventListener("DOMContentLoaded", function () {
            function formatRupiah(amount) {
                if (amount === null || amount === undefined || amount === '') return 'Rp 0';
                const num = Math.round(Number(amount));
                return 'Rp. ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            const tapIdInput = document.getElementById('tap_id');
            tapIdInput.focus();

            document.getElementById('form-data').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearErrorMessages('form-data');
                const formData = new FormData(this);
                const tap_id = (formData.get('tap_id') || '').toString().trim();

                if (!tap_id) {
                    warningAlert('Silahkan tap kartu terlebih dahulu', 'tap_id');
                    return;
                }

                const request = cashlessRequest('/cashless/admin/cek-limit/get-limit', {
                    method: 'POST',
                    body: formData,
                });

                const processForm = await cashlessSubmit(request);
                if (processForm.success === true) {
                    const hasil = processForm.data;
                    if (hasil.data === 'error' || hasil.data === '' || hasil.data === null || hasil.data === undefined) {
                        warningAlert('Kartu diblokir atau tidak ditemukan', 'tap_id');
                        document.getElementById('limit').value = '';
                        document.getElementById('nis').value = '';
                        document.getElementById('nama').value = '';
                    } else {
                        document.getElementById('limit').value = formatRupiah(hasil.data ?? 0);
                        document.getElementById('nis').value = hasil.nis || '';
                        document.getElementById('nama').value = hasil.nama || '';
                    }
                } else {
                    document.getElementById('limit').value = '';
                    document.getElementById('nis').value = '';
                    document.getElementById('nama').value = '';
                    processErrors(processForm.errors, 'tap_id');
                }

                tapIdInput.value = '';
                tapIdInput.focus();
            });
        });
    </script>
@endsection

