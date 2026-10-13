@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'Dashboard')
@section('style')
    @unless($isSiswa ?? false)
        <link rel="stylesheet" href="{{ asset('css/cashless-face-pay.css') }}?v=1"/>
        <link rel="stylesheet" href="{{ asset('vendor/toastify/toastify.min.css') }}?v=1"/>
    @endunless
    <style>
        #riwayat-table tbody tr td {
            vertical-align: middle;
        }
        .cashless-face-wrap .k-stage { position: relative; }
        .cashless-face-wrap .k-stage__overlay { position: absolute; inset: 0; pointer-events: none; }
        .cashless-face-wrap .k-target { position: absolute; border: 2px solid #22c55e; border-radius: 8px; box-sizing: border-box; }
    </style>
@endsection

@section('content')
    <div class="d-flex justify-content-center align-items-start py-4" style="min-height: 80vh !important;">
        <div class="w-100 m-auto" style="max-width: 900px;">
            <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0 mb-3">
                @if(isset($dataTitle) && isset($mainTitle) && $mainTitle != $dataTitle)
                    {{$mainTitle .' - '.$dataTitle}}
                @else
                    {{$mainTitle??$title??''}}
                @endif
            </h3>

            @unless($isSiswa ?? false)
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="tab-rfid-btn" data-bs-toggle="pill" data-bs-target="#tab-rfid" type="button" role="tab">RFID / Tap kartu</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="tab-face-btn" data-bs-toggle="pill" data-bs-target="#tab-face" type="button" role="tab">Wajah (FacePay)</button>
                    </li>
                </ul>
            @endunless

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-rfid" role="tabpanel">
                    <form id="form-data" autocomplete="off">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 140px;">TAP ID</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="Siap scan — tap kartu RFID"
                                           name="tap_id" id="tap_id" aria-describedby="tap_id"
                                           enterkeyhint="done" inputmode="numeric"
                                           autocomplete="off" autofocus>
                                </div>
                                <small class="text-muted mt-1 d-block">Kartu berikutnya langsung tap saja, data sebelumnya akan diganti otomatis.</small>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <button type="reset" class="btn btn-outline-secondary" id="btn-reset" tabindex="-1">Reset</button>
                                <button type="submit" class="btn btn-primary" tabindex="-1">Proses</button>
                            </div>
                        </div>
                    </form>
                </div>

                @unless($isSiswa ?? false)
                    <div class="tab-pane fade" id="tab-face" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-body">
                                @include('cashless.admin.cek_saldo._face')
                            </div>
                        </div>
                    </div>
                @endunless
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <fieldset class="form-fieldset pb-0">
                        <div class="col mb-4">
                            <div class="input-group">
                                <span class="input-group-text" style="width: 200px;">NIS</span>
                                <input type="text" class="form-control form-control-lg" placeholder="NIS"
                                       id="nis" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col mb-4">
                            <div class="input-group">
                                <span class="input-group-text" style="width: 200px;">Nama</span>
                                <input type="text" class="form-control form-control-lg" placeholder="Nama"
                                       id="nama" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col mb-4">
                            <div class="input-group">
                                <span class="input-group-text" style="width: 200px;">Saldo</span>
                                <input type="text" class="form-control form-control-lg" placeholder="Saldo"
                                       id="saldo" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col mb-2">
                            <div class="input-group">
                                <span class="input-group-text" style="width: 200px;">Batasan Limit Belanja</span>
                                <input type="text" class="form-control form-control-lg" placeholder="Batasan Limit Belanja"
                                       id="batasan_limit" readonly tabindex="-1">
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">10 Riwayat Transaksi Jajan Terakhir</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0" id="riwayat-table">
                            <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Tanggal</th>
                                <th>Merchant</th>
                                <th class="text-end">Nominal</th>
                            </tr>
                            </thead>
                            <tbody id="riwayat-body">
                            <tr id="riwayat-empty">
                                <td colspan="4" class="text-center text-muted py-4">Tap kartu untuk menampilkan riwayat</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('errorInputHelper', true)
@section('script')
    <script type="text/javascript" defer>
        window.CekSaldo = (function () {
            function formatRupiah(amount) {
                if (amount === null || amount === undefined || amount === '') return 'Rp 0';
                const num = Math.round(Number(amount));
                return 'Rp. ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            function formatBatasan(amount) {
                const num = Math.abs(Math.round(Number(amount) || 0));
                return num > 0 ? '-' + formatRupiah(num) : formatRupiah(0);
            }

            function formatTanggal(value) {
                if (!value) return '-';
                const date = new Date(value);
                if (isNaN(date.getTime())) return value;
                const pad = (n) => String(n).padStart(2, '0');
                return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
            }

            function renderRiwayat(transaksi) {
                const tbody = document.getElementById('riwayat-body');
                tbody.innerHTML = '';

                if (!transaksi || transaksi.length === 0) {
                    tbody.innerHTML = '<tr id="riwayat-empty"><td colspan="4" class="text-center text-muted py-4">Belum ada riwayat transaksi jajan</td></tr>';
                    return;
                }

                transaksi.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${formatTanggal(item.tanggal)}</td>
                        <td>${item.merchant || '-'}</td>
                        <td class="text-end">${formatRupiah(item.nominal)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            function clearResult() {
                document.getElementById('nis').value = '';
                document.getElementById('nama').value = '';
                document.getElementById('saldo').value = '';
                document.getElementById('batasan_limit').value = '';
                document.getElementById('riwayat-body').innerHTML =
                    '<tr id="riwayat-empty"><td colspan="4" class="text-center text-muted py-4">Tap kartu untuk menampilkan riwayat</td></tr>';
            }

            function showResult(hasil) {
                hasil = hasil || {};
                document.getElementById('nis').value = hasil.nis ?? '';
                document.getElementById('nama').value = hasil.nama ?? '';
                document.getElementById('saldo').value = formatRupiah(hasil.saldo ?? 0);
                document.getElementById('batasan_limit').value = formatBatasan(hasil.batasan_limit ?? 0);
                renderRiwayat(hasil.transaksi || []);
            }

            return { clearResult: clearResult, showResult: showResult };
        })();

        document.addEventListener("DOMContentLoaded", function () {
            const tapIdInput = document.getElementById('tap_id');
            const form = document.getElementById('form-data');
            const rfidPane = document.getElementById('tab-rfid');
            let isProcessing = false;
            let hasDisplayedData = false;

            function rfidActive() {
                return !rfidPane || rfidPane.classList.contains('active');
            }

            function clearFormResult() {
                window.CekSaldo.clearResult();
                hasDisplayedData = false;
            }

            function focusTapInput(clearValue = false) {
                if (!rfidActive()) return;
                if (clearValue) {
                    tapIdInput.value = '';
                }
                if (document.activeElement === tapIdInput) {
                    return;
                }
                tapIdInput.focus({ preventScroll: true });
            }

            function readyForNextTap() {
                if (!rfidActive()) return;
                tapIdInput.value = '';
                tapIdInput.focus({ preventScroll: true });
            }

            readyForNextTap();

            document.addEventListener('click', function (e) {
                if (e.target.closest('button, a, .swal2-container')) return;
                setTimeout(() => focusTapInput(false), 0);
            });

            window.addEventListener('focus', function () {
                setTimeout(() => focusTapInput(false), 0);
            });

            const rfidTabBtn = document.getElementById('tab-rfid-btn');
            if (rfidTabBtn) {
                rfidTabBtn.addEventListener('shown.bs.tab', function () {
                    if (window.CashlessTapBelanjaFace && window.CashlessTapBelanjaFace.stopCamera) {
                        window.CashlessTapBelanjaFace.stopCamera();
                    }
                    clearFormResult();
                    readyForNextTap();
                });
            }

            const faceTabBtn = document.getElementById('tab-face-btn');
            if (faceTabBtn) {
                faceTabBtn.addEventListener('shown.bs.tab', clearFormResult);
            }

            tapIdInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') return;

                if (hasDisplayedData) {
                    clearFormResult();
                    tapIdInput.value = '';
                }
            });

            form.addEventListener('reset', function () {
                setTimeout(() => {
                    clearFormResult();
                    readyForNextTap();
                }, 0);
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (isProcessing) return;

                clearErrorMessages('form-data');
                const tap_id = (tapIdInput.value || '').trim();

                if (!tap_id) {
                    warningAlert('Silahkan tap kartu terlebih dahulu', 'tap_id');
                    readyForNextTap();
                    return;
                }

                isProcessing = true;
                clearFormResult();

                const formData = new FormData();
                formData.append('tap_id', tap_id);

                const request = cashlessRequest('/cashless/admin/cek-saldo/get-data', {
                    method: 'POST',
                    body: formData,
                });

                let processForm = await cashlessSubmit(request);
                if (processForm.success === true) {
                    window.CekSaldo.showResult(processForm.data || {});
                    hasDisplayedData = true;
                } else {
                    clearFormResult();
                    if (typeof processErrors === 'function') {
                        processErrors(processForm.errors, 'tap_id');
                    }
                }

                isProcessing = false;
                if (typeof Swal !== 'undefined') Swal.close();
                readyForNextTap();
            });
        });
    </script>

    @if(!($isSiswa ?? false) && ($faceDbReady ?? false))
        <script src="{{ asset('vendor/toastify/toastify.min.js') }}?v=1"></script>
        <script src="{{ asset('vendor/face-api/face-api.min.js') }}?v=1"></script>
        <script src="{{ asset('js/cashless/presensi-camera.js') }}?v=1"></script>
        <script>
            window.TAP_BELANJA_FACE = {
                referencesUrl: @json(route('cashless.admin.cek-saldo.face-references')),
                csrf: @json(csrf_token()),
                modelUrl: @json(asset('vendor/face-api/weights')),
                onMatch: function (siswa) {
                    var nis = String((siswa && siswa.nis) || '').replace(/\D/g, '');
                    var nextBtn = document.getElementById('cek-saldo-face-next');
                    window.CekSaldo.clearResult();
                    if (nextBtn) nextBtn.hidden = false;
                    if (!nis) {
                        warningAlert('NIS siswa dari FacePay tidak valid');
                        return;
                    }
                    fetch(@json(route('cashless.admin.cek-saldo.get-data-nis')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ nis: nis }),
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                if (!res.ok) throw new Error(data.message || 'Gagal mengambil saldo');
                                return data;
                            });
                        })
                        .then(function (data) {
                            window.CekSaldo.showResult(data);
                        })
                        .catch(function (err) {
                            warningAlert(err.message || 'Gagal mengambil saldo');
                        });
                },
            };
        </script>
        <script src="{{ asset('js/cashless/tap-belanja-face.js') }}?v=20261013b"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var nextBtn = document.getElementById('cek-saldo-face-next');
                if (nextBtn) {
                    nextBtn.addEventListener('click', function () {
                        nextBtn.hidden = true;
                        window.CekSaldo.clearResult();
                        if (window.CashlessTapBelanjaFace && window.CashlessTapBelanjaFace.startCamera) {
                            window.CashlessTapBelanjaFace.startCamera().catch(function () {});
                        }
                    });
                }
            });
        </script>
    @endif
@endsection
