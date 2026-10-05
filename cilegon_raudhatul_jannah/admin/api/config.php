<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'cilegon_raudhatul_jannah',
        'user' => 'cilegon_raudhatul_jannah',
        'pass' => 'cilegon_raudhatul_jannah',
        'charset' => 'utf8mb4',
        /** true = buat tabel otomatis saat koneksi pertama (set false setelah production stabil) */
        'auto_migrate' => true,
    ],
    'jwt_secret' => '4ecfd4c24aee85b4b485f9d828aa1b7d',
    'api_url' => 'http://103.23.103.43/MobileMerchant/Cilegon_Raudhatul_Jannah_ForVPS/index.php',
    'timeout' => 60,
    'allowed_methods' => [
        'LoginRequest',
        'InquirySALDO',
        'PaymentBELANJAKantin',
        'LogTransaksiRequest',
        'StudentRequest',
        'UnitRequest',
        'StudentRequestUnit',
        'RequestNewPassword',
    ],
    'log_username_default' => 'WS_TESTING',
    'cors_origin' => '*',
];
