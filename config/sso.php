<?php

return [

    'modules' => [
        'sikeu' => [
            'enabled' => true,
            'label' => 'SIKEU',
            'url' => env('SSO_MODULE_SIKEU_URL', ''),
        ],
        'facepay_admin' => [
            'enabled' => (bool) env('SSO_MODULE_FACEPAY_ADMIN_ENABLED', true),
            'label' => 'Admin Facepay',
            'url' => env(
                'SSO_MODULE_FACEPAY_ADMIN_URL',
                'https://face-raudhatuljannah.smartpayment.co.id/admin/index.html'
            ),
        ],
        'facepay_siswa' => [
            'enabled' => (bool) env('SSO_MODULE_FACEPAY_SISWA_ENABLED', true),
            'label' => 'Siswa',
            'url' => env(
                'SSO_MODULE_FACEPAY_SISWA_URL',
                'https://face-raudhatuljannah.smartpayment.co.id/ortu/'
            ),
        ],
        'facepay_kantin' => [
            'enabled' => (bool) env('SSO_MODULE_FACEPAY_KANTIN_ENABLED', true),
            'label' => 'Kantin',
            'url' => env(
                'SSO_MODULE_FACEPAY_KANTIN_URL',
                'https://face-raudhatuljannah.smartpayment.co.id/kantin/login.html'
            ),
        ],
        'presensi' => [
            'enabled' => (bool) env('SSO_MODULE_PRESENSI_ENABLED', false),
            'url' => env('SSO_MODULE_PRESENSI_URL', ''),
            'label' => 'Presensi',
            'use_signed_token' => (bool) env('SSO_MODULE_PRESENSI_USE_SIGNED_TOKEN', true),
        ],
        'cashless' => [
            'enabled' => (bool) env('SSO_MODULE_CASHLESS_ENABLED', false),
            'url' => env('SSO_MODULE_CASHLESS_URL', ''),
            'label' => 'Cashless',
            'use_signed_token' => (bool) env('SSO_MODULE_CASHLESS_USE_SIGNED_TOKEN', false),
        ],
    ],

];
