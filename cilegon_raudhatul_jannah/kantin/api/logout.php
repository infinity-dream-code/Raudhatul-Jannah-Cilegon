<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

kantin_send_headers();
kantin_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

KantinAuth::logout();
kantin_api_json(['ok' => true]);
