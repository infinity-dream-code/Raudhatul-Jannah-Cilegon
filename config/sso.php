<?php

return [

    'modules' => [
        'sikeu' => [
            'enabled' => true,
            'label' => 'SIKEU',
            'url' => env('SSO_MODULE_SIKEU_URL', ''),
        ],
        // Modul lain dinonaktifkan untuk Raudhatul Jannah Cilegon (hanya SIKEU).
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
