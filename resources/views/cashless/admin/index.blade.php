@extends('cashless.layouts.admin')
@section('title',$dataTitle??$mainTitle??$title??'Dashboard')
@section('style')
    <link rel="stylesheet" href="{{asset('main/libs/apex-charts/apex-charts.css')}}"/>

@endsection

@section('content')
    @php
        $isCekSaldoOnly = strtolower((string) session('cashless_user.role')) === 'siswa';
    @endphp
    <div class="row">
        <div class="col-12">
            <div class="row row-cols-2 row-cols-lg-3">
                @unless($isCekSaldoOnly)
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-primary bg-label-primary p-5 h-100">
                        <a href="{{route('cashless.admin.tap-belanja.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-primary">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path
                                        d="M3 8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -8"/>
                                    <path d="M3 10l18 0"/>
                                    <path d="M7 15l.01 0"/>
                                    <path d="M11 15l2 0"/>
                                </svg>
                            </span>
                            <h6 class="mt-1 mb-0">Tap Belanja</h6>
                        </a>
                    </div>
                </div>
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-success bg-label-success p-5 h-100">
                        <a href="{{route('cashless.admin.cek-limit.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-primary">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="icon icon-tabler icons-tabler-outline icon-tabler-cash-off"><path
                                        stroke="none" d="M0 0h24v24H0z" fill="none"/><path
                                        d="M13 9h6a2 2 0 0 1 2 2v6m-2 2h-10a2 2 0 0 1 -2 -2v-6a2 2 0 0 1 2 -2"/><path
                                        d="M12.582 12.59a2 2 0 0 0 2.83 2.826"/><path
                                        d="M17 9v-2a2 2 0 0 0 -2 -2h-6m-4 0a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2"/><path
                                        d="M3 3l18 18"/></svg>
                            </span>
                            <h6 class="mt-1 mb-0">Cek Limit</h6>
                        </a>
                    </div>
                </div>
                @endunless
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-primary bg-label-primary p-5 h-100">
                        <a href="{{route('cashless.admin.cek-saldo.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-primary">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="icon icon-tabler icons-tabler-outline icon-tabler-wallet">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/>
                                    <path d="M20 12v4h-4a2 2 0 0 1 0 -4h4"/>
                                </svg>
                            </span>
                            <h6 class="mt-1 mb-0">Cek Saldo</h6>
                        </a>
                    </div>
                </div>
                @unless($isCekSaldoOnly)
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-warning bg-label-warning p-5 h-100">
                        <a href="{{route('cashless.admin.data-transaksi-belanja.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-primary">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-list">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M9 6l11 0"/>
                                    <path d="M9 12l11 0"/>
                                    <path d="M9 18l11 0"/>
                                    <path d="M5 6l0 .01"/>
                                    <path d="M5 12l0 .01"/>
                                    <path d="M5 18l0 .01"/>
                                </svg>
                            </span>
                            <h6 class="mt-1 mb-0">Data Transaksi Belanja</h6>
                        </a>
                    </div>
                </div>
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-info bg-label-info p-5 h-100">
                        <a href="{{route('cashless.admin.rekap-penerimaan-harian.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-info">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="icon icon-tabler icons-tabler-outline icon-tabler-calendar">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12"/>
                                    <path d="M16 3v4"/>
                                    <path d="M8 3v4"/>
                                    <path d="M4 11h16"/>
                                    <path d="M11 15h1"/>
                                    <path d="M12 15v3"/>
                                </svg>
                            </span>
                            <h6 class="mt-1 mb-0">Rekap Harian</h6>
                        </a>
                    </div>
                </div>
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-success bg-label-success p-5 h-100">
                        <a href="{{route('cashless.admin.riwayat-pencairan.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-info">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="icon icon-tabler icons-tabler-outline icon-tabler-list-check">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M3.5 5.5l1.5 1.5l2.5 -2.5"/>
                                    <path d="M3.5 11.5l1.5 1.5l2.5 -2.5"/>
                                    <path d="M3.5 17.5l1.5 1.5l2.5 -2.5"/>
                                    <path d="M11 6l9 0"/>
                                    <path d="M11 12l9 0"/>
                                    <path d="M11 18l9 0"/>
                                </svg>
                            </span>
                            <h6 class="mt-1 mb-0">Riwayat Pencairan</h6>
                        </a>
                    </div>
                </div>
                <div class="col mb-6 text-center">
                    <div class="card card-border-shadow-light bg-label-light p-5 h-100">
                        <a href="{{route('cashless.admin.profil-admin.index')}}"
                           class="nav-link btn d-flex flex-column align-items-center justify-content-center border-info">
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round"
                                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-user">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                                    <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                                </svg>
                            </span>
                            <h6 class="mt-1 mb-0">Profil Admin</h6>
                        </a>
                    </div>
                </div>
                @endunless
            </div>
        </div>
    </div>
@endsection

