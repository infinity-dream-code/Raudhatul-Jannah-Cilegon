<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'malang_alizzah_face',
        'user' => 'malang_alizzah_face',
        'pass' => 'malang_alizzah_face',
        'charset' => 'utf8mb4',
        /** true = buat tabel otomatis saat koneksi pertama */
        'auto_migrate' => true,
    ],
    'jwt_secret' => '4ecfd4c24aee85b4b485f9d828aa1b7d',
    'api_url' => 'http://103.23.103.43/MobileMerchant/Malang_Alizzah_ForVPS/index.php',
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
