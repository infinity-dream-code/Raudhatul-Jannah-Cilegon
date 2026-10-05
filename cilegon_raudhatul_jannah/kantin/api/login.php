<?php

declare(strict_types=1);

/**
 * Login kantin via MobileMerchant LoginRequest.
 * POST: { "username": "...", "password": "..." }
 */

require_once __DIR__ . '/bootstrap.php';

kantin_send_headers();
kantin_handle_options();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

try {
    $body = api_read_json_body();
    $username = trim((string) ($body['username'] ?? $body['USERNAME'] ?? ''));
    $passwordRaw = $body['password'] ?? $body['PASSWORD'] ?? '';

    if ($username === '' || $passwordRaw === '' || $passwordRaw === null) {
        kantin_api_json(['ok' => false, 'error' => 'Username dan password wajib'], 400);
    }

    // API spek contoh memakai PASSWORD numerik; kirim int jika seluruhnya digit.
    $password = is_numeric((string) $passwordRaw) && preg_match('/^-?\d+$/', trim((string) $passwordRaw))
        ? (int) $passwordRaw
        : (string) $passwordRaw;

    $client = MobileMerchantClient::create();
    $raw = $client->call([
        'METHOD' => 'LoginRequest',
        'USERNAME' => $username,
        'PASSWORD' => $password,
    ]);

    $row = is_array($raw) ? $raw : [];
    // Beberapa gateway membungkus dalam array
    if (isset($row[0]) && is_array($row[0])) {
        $row = $row[0];
    }

    $kode = isset($row['KodeRespon']) ? (int) $row['KodeRespon'] : 0;
    $display = trim((string) ($row['Username'] ?? $row['USERNAME'] ?? $username));

    if ($kode !== 1) {
        kantin_api_json([
            'ok' => false,
            'error' => 'Username atau kata sandi salah',
            'kodeRespon' => $kode,
        ], 401);
    }

    // Session: username login (untuk NAMAKANTIN / LogTransaksi) + display dari response
    KantinAuth::login([
        'username' => $username,
        'displayName' => $display !== '' ? $display : $username,
    ]);

    $user = KantinAuth::user();
    kantin_api_json(['ok' => true, 'data' => $user]);
} catch (Throwable $e) {
    kantin_api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
