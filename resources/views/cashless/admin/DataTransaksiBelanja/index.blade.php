@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'')
@section('style')
    <style>
        table.dataTable tr.selected {
            border-top: 2px solid var(--bs-primary);
            border-bottom: 2px solid var(--bs-primary);
            border-left: none;
            border-right: none;
        }

        .dataTables_wrapper .dataTables_info {
            padding-top: 0.85em;
        }

        .total-footer-row {
            background-color: #e9ecef !important;
            font-weight: bold;
        }

        .total-footer-row td {
            background-color: #e9ecef !important;
            border-top: 2px solid #dee2e6;
        }

        .btn-export {
            background-color: #28a745;
            border-color: #28a745;
            margin-bottom: 15px;
        }

        .btn-export:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .export-button-container {
            margin-bottom: 15px;
            text-align: right;
        }

        .table-total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .table-total-row td {
            border-top: 2px solid #dee2e6;
        }

        #main_table tfoot th,
        #main_table tfoot td {
            background-color: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
        }

        #main_table tfoot td:first-child {
            text-align: right;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .spinning {
            animation: spin 1s linear infinite;
            display: inline-block;
        }
    </style>
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/select2/select2-bootstrap.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-bs5/datatables.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
    <link rel="stylesheet" href="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css')}}">
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
        <div class="card-header">
            <div class="row mb-3">
                <h5 class="mb-0 me-2">{{($dataTitle??$mainTitle??$title)}}</h5>
            </div>
        </div>
        <div class="card-body">
            <form id="filter-form">
                <fieldset class="form-fieldset">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="mb-3 row">
                                <label for="filter[tahun_akademik]" class="col-sm-4 col-form-label form-label">Tahun Akademik</label>
                                <div class="col">
                                    <select class="form-select select2" id="filter[tahun_akademik]"
                                            name="filter[tahun_akademik]"
                                            data-control="select2"
                                            data-placeholder="Pilih Tahun Akademik">
                                        <option value="all">Semua</option>
                                        @isset($thn_aka)
                                            @foreach($thn_aka as $item)
                                                <option value="{{$item->thn_aka}}">{{$item->thn_aka}}</option>
                                            @endforeach
                                        @else
                                            <option>data kosong</option>
                                        @endisset
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter[nis]" class="col-sm-4 col-form-label form-label">Nis</label>
                                <div class="col">
                                    <input type="text" class="form-control"
                                           placeholder="Masukkan nis siswa" id="filter[nis]" name="filter[nis]">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter[nama]" class="col-sm-4 col-form-label text-capitalize form-label">nama</label>
                                <div class="col">
                                    <input type="text" class="form-control"
                                           placeholder="Masukkan nama siswa" id="filter[nama]" name="filter[nama]">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter_dari_tanggal" class="col-sm-4 col-form-label text-capitalize form-label">dari tanggal</label>
                                <div class="col">
                                    <input type="text" class="form-control"
                                           placeholder="dari tanggal" id="filter_dari_tanggal"
                                           name="filter[dari_tanggal]">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="filter_sampai_tanggal" class="col-sm-4 col-form-label text-capitalize form-label">sampai tanggal</label>
                                <div class="col">
                                    <input type="text" class="form-control"
                                           placeholder="sampai tanggal" id="filter_sampai_tanggal"
                                           name="filter[sampai_tanggal]">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label form-label" for="filter[angkatan]">Angkatan Siswa</label>
                                <div class="col">
                                    <select class="form-select" id="filter[angkatan]"
                                            name="filter[angkatan]"
                                            data-control="select2"
                                            data-placeholder="Pilih Angkatan Siswa">
                                        <option value="all">Semua</option>
                                        @isset($thn_aka)
                                            @foreach($thn_aka as $item)
                                                <option value="{{$item->thn_aka}}">{{$item->thn_aka}}</option>
                                            @endforeach
                                        @else
                                            <option>data kosong</option>
                                        @endisset
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label class="col-sm-4 col-form-label form-label" for="filter[kelas]">Kelas</label>
                                <div class="col">
                                    <select class="form-select" id="filter[kelas]" name="filter[kelas]"
                                            data-control="select2" data-placeholder="Pilih Kelas">
                                        <option value="all">Semua</option>
                                        @isset($kelas)
                                            @foreach($kelas as $item)
                                                <option value="{{$item->jenjang}}">{{$item->unit}} - {{$item->kelas}} {{$item->jenjang}}</option>
                                            @endforeach
                                        @else
                                            <option>data kosong</option>
                                        @endisset
                                    </select>
                                </div>
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
            <div class="export-button-container">
                <button type="button" id="btn-export" class="btn btn-success btn-export">
                    <i class="ri-file-excel-line me-2"></i>
                    Export ke CSV
                </button>
            </div>
            <table class="table table-sm table-bordered table-hover" id="main_table">
                <thead class="table-light"></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/pdfmake.min.js"
            integrity="sha512-axXaF5grZBaYl7qiM6OMHgsgVXdSLxqq0w7F4CQxuFyrcPmn0JfnqsOtYHUun80g6mRRdvJDrTCyL8LQqBOt/Q=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/vfs_fonts.min.js"
            integrity="sha512-EFlschXPq/G5zunGPRSYqazR1CMKj0cQc8v6eMrQwybxgIbhsfoO5NAMQX3xFDQIbFlViv53o7Hy+yCWw6iZxA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{asset('main/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
    <script src="{{asset('js/datatableCustom/Datatable-0-4.min.js')}}"></script>
    <script src="{{asset('main/libs/moment/moment.js')}}"></script>
    <script src="{{asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.js')}}"></script>
    <script type="text/javascript">
        const select2 = $(`[data-control='select2']`);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let totalPayment = 0;

        let dtOptions = {
            tableId: 'main_table',
            formId: 'filter-form',
            columnUrl: '{{($columnsUrl??null)}}',
            dataUrl: '{{($datasUrl??null)}}',
            dataColumns: [],
            thead: true,
            tfoot: true,
            paging: true,
            searching: true,
            fixedHeader: false,
            select: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 75, 100],
            footerCallback: function(row, data, start, end, display) {
                var api = this.api();
                var pageTotal = 0;

                api.rows({ page: 'current' }).every(function() {
                    var rowData = this.data();
                    var debet = parseFloat(rowData.BILLAM) || 0;
                    pageTotal += debet;
                });

                $(api.column(3).footer()).html(
                    'Rp ' + pageTotal.toLocaleString('id-ID')
                );
            },
            initComplete: function() {
                var api = this.api();

                if ($(api.table().footer()).length === 0) {
                    $(api.table().container()).append('<tfoot><tr><th colspan="8" style="text-align:right">TOTAL:</th><th></th></tr></tfoot>');
                }

                $(api.column(3).footer()).html('Rp 0');
            },
            drawCallback: function() {
                updateTotalPayment();
            }
        };

        function getFilterData() {
            const formData = new FormData(document.getElementById('filter-form'));
            const filters = {};

            for (let [key, value] of formData.entries()) {
                if (value && value !== 'all') {
                    if (key === 'filter[dari_tanggal]') filters['dari_tanggal'] = value;
                    else if (key === 'filter[sampai_tanggal]') filters['sampai_tanggal'] = value;
                    else if (key === 'filter[kelas]') filters['kelas'] = value;
                    else if (key === 'filter[nis]') filters['nis'] = value;
                    else if (key === 'filter[nama]') filters['nama'] = value;
                    else if (key === 'filter[angkatan]') filters['angkatan'] = value;
                }
            }

            return filters;
        }

        function updateTotalPayment() {
            const filters = getFilterData();

            $.ajax({
                url: '{{($totalUrl??null)}}',
                type: 'POST',
                data: JSON.stringify({filter: filters}),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function(response) {
                    totalPayment = response.total;

                    var table = $('#main_table').DataTable();
                    if (table && table.column(3).footer()) {
                        $(table.column(3).footer()).html(
                            '<strong>Total: Rp ' + response.total.toLocaleString('id-ID') + '</strong>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching total:', error);
                }
            });
        }

        $('#btn-export').on('click', function() {
            const filters = getFilterData();

            const exportForm = $('<form>', {
                'method': 'POST',
                'action': '{{($exportUrl??null)}}'
            });

            exportForm.append($('<input>', {
                'type': 'hidden',
                'name': '_token',
                'value': csrfToken
            }));

            exportForm.append($('<input>', {
                'type': 'hidden',
                'name': 'filter',
                'value': JSON.stringify(filters)
            }));

            $('body').append(exportForm);
            exportForm.submit();
            exportForm.remove();

            const exportBtn = $('#btn-export');
            const originalText = exportBtn.html();
            exportBtn.html('<i class="ri-loader-4-line me-2 spinning"></i> Loading...');
            exportBtn.prop('disabled', true);

            setTimeout(function() {
                exportBtn.html(originalText);
                exportBtn.prop('disabled', false);
            }, 3000);
        });

        document.addEventListener("DOMContentLoaded", function () {
            if (dtOptions.dataUrl && dtOptions.columnUrl) {
                getDT(dtOptions);
                if (dtOptions.formId) {
                    let filterForm = $(`#${dtOptions.formId}`);
                    filterForm.on('submit', function (e) {
                        e.preventDefault();
                        dataReFilter(dtOptions.tableId);
                        setTimeout(function() { updateTotalPayment(); }, 500);
                    });
                    filterForm.on('reset', function (e) {
                        setTimeout(function () {
                            dataReFilter(dtOptions.tableId);
                            const select2InForm = select2.filter(`#${dtOptions.formId} [data-control='select2']`);
                            if (select2InForm.length) {
                                select2InForm.each(function () {
                                    $(this).trigger('change');
                                });
                            }
                            setTimeout(function() { updateTotalPayment(); }, 500);
                        }, 0);
                    });
                }
                setTimeout(function() { updateTotalPayment(); }, 1000);
            }

            if (select2.length) {
                select2.each(function () {
                    let $this = $(this);
                    $this.wrap('<div class="position-relative"></div>').select2({
                        placeholder: 'Select value',
                        dropdownParent: $this.parent(),
                    });
                });
            }

            const dariTanggal = $('#filter_dari_tanggal');
            const sampaiTanggal = $('#filter_sampai_tanggal');

            dariTanggal.datepicker({
                format: "dd-mm-yyyy",
                autoclose: true
            }).on('changeDate', function (e) {
                sampaiTanggal.datepicker('setStartDate', e.date);
            });

            sampaiTanggal.datepicker({
                format: "dd-mm-yyyy",
                autoclose: true
            }).on('changeDate', function (e) {
                dariTanggal.datepicker('setEndDate', e.date);
            });
        });
    </script>
@endsection
