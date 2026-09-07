@php
    $isCekSaldoOnly = strtolower((string) session('cashless_user.role')) === 'siswa';
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ $isCekSaldoOnly ? route('cashless.admin.cek-saldo.index') : route('cashless.admin.index') }}" class="app-brand-link">
              <span class="app-brand-logo demo">
                <span style="color: var(--bs-primary)">
                  <img width="50" height="50" src="{{asset('logo.png')}}" alt="logo">
                </span>
              </span>
            <span class="app-brand-text demo menu-text fw-bold ms-2">{{Auth::user()->name??'CASHLESS'}}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M11.4854 4.88844C11.0081 4.41121 10.2344 4.41121 9.75715 4.88844L4.51028 10.1353C4.03297 10.6126 4.03297 11.3865 4.51028 11.8638L9.75715 17.1107C10.2344 17.5879 11.0081 17.5879 11.4854 17.1107C11.9626 16.6334 11.9626 15.8597 11.4854 15.3824L7.96672 11.8638C7.48942 11.3865 7.48942 10.6126 7.96672 10.1353L11.4854 6.61667C11.9626 6.13943 11.9626 5.36568 11.4854 4.88844Z"
                    fill="currentColor"
                    fill-opacity="0.6"/>
                <path
                    d="M15.8683 4.88844L10.6214 10.1353C10.1441 10.6126 10.1441 11.3865 10.6214 11.8638L15.8683 17.1107C16.3455 17.5879 17.1192 17.5879 17.5965 17.1107C18.0737 16.6334 18.0737 15.8597 17.5965 15.3824L14.0778 11.8638C13.6005 11.3865 13.6005 10.6126 14.0778 10.1353L17.5965 6.61667C18.0737 6.13943 18.0737 5.36568 17.5965 4.88844C17.1192 4.41121 16.3455 4.41121 15.8683 4.88844Z"
                    fill="currentColor"
                    fill-opacity="0.38"/>
            </svg>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        @unless($isCekSaldoOnly)
        <li class="menu-item  {{ Request::is(['admin'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.index')}}" class="menu-link">
                <i class="menu-icon ri ri-home-3-line"></i>
                <div data-i18n="Beranda">Beranda</div>
            </a>
        </li>
        <li class="menu-item  {{ Request::is(['admin/tap-belanja*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.tap-belanja.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-credit-card">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M3 8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -8"/>
                    <path d="M3 10l18 0"/>
                    <path d="M7 15l.01 0"/>
                    <path d="M11 15l2 0"/>
                </svg>
                <div data-i18n="Tap Belanja">Tap Belanja</div>
            </a>
        </li>
        <li class="menu-item  {{ Request::is(['admin/cek-limit*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.cek-limit.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-cash-off">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M13 9h6a2 2 0 0 1 2 2v6m-2 2h-10a2 2 0 0 1 -2 -2v-6a2 2 0 0 1 2 -2"/>
                    <path d="M12.582 12.59a2 2 0 0 0 2.83 2.826"/>
                    <path d="M17 9v-2a2 2 0 0 0 -2 -2h-6m-4 0a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2"/>
                    <path d="M3 3l18 18"/>
                </svg>
                <div data-i18n="Cek Limit">Cek Limit</div>
            </a>
        </li>
        @endunless
        <li class="menu-item  {{ Request::is(['admin/cek-saldo*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.cek-saldo.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-wallet">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/>
                    <path d="M20 12v4h-4a2 2 0 0 1 0 -4h4"/>
                </svg>
                <div data-i18n="Cek Saldo">Cek Saldo</div>
            </a>
        </li>
        @unless($isCekSaldoOnly)
        <li class="menu-item  {{ Request::is(['admin/data-transaksi-belanja*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.data-transaksi-belanja.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-list">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M9 6l11 0"/>
                    <path d="M9 12l11 0"/>
                    <path d="M9 18l11 0"/>
                    <path d="M5 6l0 .01"/>
                    <path d="M5 12l0 .01"/>
                    <path d="M5 18l0 .01"/>
                </svg>
                <div data-i18n="Data Transaksi Belanja">Data Transaksi Belanja</div>
            </a>
        </li>
        <li class="menu-item  {{ Request::is(['admin/rekap-penerimaan-harian*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.rekap-penerimaan-harian.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-calendar">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12"/>
                    <path d="M16 3v4"/>
                    <path d="M8 3v4"/>
                    <path d="M4 11h16"/>
                    <path d="M11 15h1"/>
                    <path d="M12 15v3"/>
                </svg>
                <div data-i18n="Rekap Harian">Rekap Harian</div>
            </a>
        </li>
        <li class="menu-item  {{ Request::is(['admin/riwayat-pencairan*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.riwayat-pencairan.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-list-check">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M3.5 5.5l1.5 1.5l2.5 -2.5"/>
                    <path d="M3.5 11.5l1.5 1.5l2.5 -2.5"/>
                    <path d="M3.5 17.5l1.5 1.5l2.5 -2.5"/>
                    <path d="M11 6l9 0"/>
                    <path d="M11 12l9 0"/>
                    <path d="M11 18l9 0"/>
                </svg>
                <div data-i18n="Riwayat Pencairan">Riwayat Pencairan</div>
            </a>
        </li>
        <li class="menu-item  {{ Request::is(['admin/profil-admin*'])  ? 'active' : '' }}">
            <a href="{{route('cashless.admin.profil-admin.index')}}" class="menu-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="menu-icon icon icon-tabler icons-tabler-outline icon-tabler-user">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                    <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                </svg>
                <div data-i18n="Profil Admin">Profil Admin</div>
            </a>
        </li>
        @endunless
        <div class="mt-auto w-100">
            <li class="menu-item pb-2">
                <a href="{{route('cashless.logout')}}" class="menu-link btn-danger text-white" onclick="event.preventDefault();
                              document.getElementById('logout-form').submit();">
                    <i class="menu-icon ri ri-logout-box-r-line"></i>
                    <div data-i18n="Logout">
                        Logout
                    </div>
                </a>
            </li>
        </div>
    </ul>
</aside>

