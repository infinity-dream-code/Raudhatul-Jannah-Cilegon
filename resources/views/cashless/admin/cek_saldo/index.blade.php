@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'Dashboard')
@section('style')
    <style>
        #riwayat-table tbody tr td {
            vertical-align: middle;
        }
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
            <form id="form-data" autocomplete="off">
                <div class="card mb-4">
                    <div class="card-body">
                        <fieldset class="form-fieldset pb-0">
                            <div class="col mb-4">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 140px;">TAP ID</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="Siap scan — tap kartu RFID"
                                           name="tap_id" id="tap_id" aria-describedby="tap_id"
                                           enterkeyhint="done" inputmode="numeric"
                                           autocomplete="off" autofocus>
                                </div>
                                <small class="text-muted mt-1 d-block">Kartu berikutnya langsung tap saja, data sebelumnya akan diganti otomatis.</small>
                            </div>
                            <div class="col mb-4">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 140px;">NIS</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="NIS"
                                           name="nis" id="nis" aria-describedby="nis" readonly tabindex="-1">
                                </div>
                            </div>
                            <div class="col mb-4">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 140px;">Nama</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="Nama"
                                           name="nama" id="nama" aria-describedby="nama" readonly tabindex="-1">
                                </div>
                            </div>
                            <div class="col mb-4">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 140px;">Saldo</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="Saldo"
                                           name="saldo" id="saldo" aria-describedby="saldo" readonly tabindex="-1">
                                </div>
                            </div>
                            <div class="col mb-2">
                                <div class="input-group">
                                    <span class="input-group-text" style="width: 140px;">Limit Jajan</span>
                                    <input type="text" class="form-control form-control-lg" placeholder="Limit Jajan"
                                           name="limit_jajan" id="limit_jajan" aria-describedby="limit_jajan" readonly tabindex="-1">
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="reset" class="btn btn-outline-secondary" id="btn-reset" tabindex="-1">Reset</button>
                        <button type="submit" class="btn btn-primary" tabindex="-1">Proses</button>
                    </div>
                </div>
            </form>

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
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        document.addEventListener("DOMContentLoaded", function () {
            const tapIdInput = document.getElementById('tap_id');
            const form = document.getElementById('form-data');
            let isProcessing = false;
            let hasDisplayedData = false;

            function formatRupiah(amount) {
                if (amount === null || amount === undefined || amount === '') return 'Rp 0';
                const num = Math.round(Number(amount));
                return 'Rp. ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
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

            function clearFormResult() {
                document.getElementById('nis').value = '';
                document.getElementById('nama').value = '';
                document.getElementById('saldo').value = '';
                document.getElementById('limit_jajan').value = '';
                document.getElementById('riwayat-body').innerHTML =
                    '<tr id="riwayat-empty"><td colspan="4" class="text-center text-muted py-4">Tap kartu untuk menampilkan riwayat</td></tr>';
                hasDisplayedData = false;
            }

            function focusTapInput(clearValue = false) {
                if (clearValue) {
                    tapIdInput.value = '';
                }
                // Jangan curi fokus saat user sedang klik Reset/Proses
                if (document.activeElement === tapIdInput) {
                    return;
                }
                tapIdInput.focus({ preventScroll: true });
            }

            function readyForNextTap() {
                tapIdInput.value = '';
                tapIdInput.focus({ preventScroll: true });
            }

            // Fokus otomatis saat halaman dibuka
            readyForNextTap();

            // Jaga fokus tetap di TAP ID agar RFID selalu bisa mengetik
            document.addEventListener('click', function (e) {
                if (e.target.closest('button, a, .swal2-container')) return;
                setTimeout(() => focusTapInput(false), 0);
            });

            window.addEventListener('focus', function () {
                setTimeout(() => focusTapInput(false), 0);
            });

            // Saat kartu baru mulai diketik, hapus data siswa sebelumnya agar tidak menimpa/nyampur
            tapIdInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') return;

                if (hasDisplayedData) {
                    clearFormResult();
                    // Mulai tap baru dari input kosong (hindari ID lama menempel)
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
                // Hilangkan data anak sebelumnya dulu sebelum tampilkan data baru
                clearFormResult();

                const formData = new FormData();
                formData.append('tap_id', tap_id);

                const request = new Request('{{route('cashless.admin.cek-saldo.get-data')}}', {
                    method: "POST",
                    headers: {'X-CSRF-TOKEN': csrfToken},
                    body: formData
                });

                let processForm = await submitForm(request);
                if (processForm.success === true) {
                    const hasil = processForm.data;
                    document.getElementById('nis').value = hasil.nis ?? '';
                    document.getElementById('nama').value = hasil.nama ?? '';
                    document.getElementById('saldo').value = formatRupiah(hasil.saldo ?? 0);
                    document.getElementById('limit_jajan').value = formatRupiah(hasil.limit_jajan ?? 0);
                    renderRiwayat(hasil.transaksi || []);
                    hasDisplayedData = true;
                } else {
                    clearFormResult();
                    processErrors(processForm.errors, 'tap_id');
                }

                isProcessing = false;
                if (typeof Swal !== 'undefined') Swal.close();
                // Kosongkan TAP ID + fokus lagi → siap untuk anak berikutnya
                readyForNextTap();
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

