<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

kantin_send_headers();
kantin_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan GET'], 405);
}

$user = KantinAuth::user();
if (!$user) {
    kantin_api_json(['ok' => false, 'error' => 'Belum login kantin'], 401);
}

kantin_api_json(['ok' => true, 'data' => $user]);
