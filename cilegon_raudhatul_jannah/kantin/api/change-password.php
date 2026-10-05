<?php

declare(strict_types=1);

/**
 * Ganti kata sandi kantin via MobileMerchant RequestNewPassword.
 * POST: { "password": "...", "newPassword": "...", "newPassword2": "..." }
 * USERNAME diambil dari session login.
 */

require_once __DIR__ . '/bootstrap.php';

kantin_send_headers();
kantin_handle_options();
$user = KantinAuth::requireUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

/**
 * @param mixed $raw
 */
function kantin_password_value($raw)
{
    if ($raw === null || $raw === '') {
        return '';
    }
    $s = trim((string) $raw);
    if ($s !== '' && preg_match('/^-?\d+$/', $s)) {
        return (int) $s;
    }
    return $s;
}

/**
 * @param mixed $raw
 * @return array<string, mixed>
 */
function kantin_unwrap_row($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    if (isset($raw[0]) && is_array($raw[0])) {
        return $raw[0];
    }
    if (isset($raw['datas'][0]) && is_array($raw['datas'][0])) {
        return $raw['datas'][0];
    }
    return $raw;
}

try {
    $body = api_read_json_body();
    $password = $body['password'] ?? $body['PASSWORD'] ?? '';
    $newPassword = $body['newPassword'] ?? $body['NEWPASSWORD'] ?? '';
    $newPassword2 = $body['newPassword2'] ?? $body['NEWPASSWORD2'] ?? '';

    if ($password === '' || $password === null) {
        kantin_api_json(['ok' => false, 'error' => 'Kata sandi lama wajib diisi'], 400);
    }
    if ($newPassword === '' || $newPassword === null) {
        kantin_api_json(['ok' => false, 'error' => 'Kata sandi baru wajib diisi'], 400);
    }
    if ((string) $newPassword !== (string) $newPassword2) {
        kantin_api_json(['ok' => false, 'error' => 'Konfirmasi kata sandi baru tidak cocok'], 400);
    }
    if ((string) $password === (string) $newPassword) {
        kantin_api_json(['ok' => false, 'error' => 'Kata sandi baru harus berbeda dari yang lama'], 400);
    }

    $username = (string) ($user['username'] ?? '');
    if ($username === '') {
        kantin_api_json(['ok' => false, 'error' => 'Session kantin tidak valid'], 401);
    }

    $client = MobileMerchantClient::create();
    $raw = $client->call([
        'METHOD' => 'RequestNewPassword',
        'USERNAME' => $username,
        'PASSWORD' => kantin_password_value($password),
        'NEWPASSWORD' => kantin_password_value($newPassword),
        'NEWPASSWORD2' => kantin_password_value($newPassword2),
    ]);

    $row = kantin_unwrap_row($raw);
    $kode = isset($row['KodeRespon']) ? (int) $row['KodeRespon'] : null;
    $status = strtoupper(trim((string) ($row['STATUS'] ?? $row['Status'] ?? $row['status'] ?? '')));
    $result = trim((string) ($row['RESULT'] ?? $row['Result'] ?? $row['MESSAGE'] ?? $row['Message'] ?? ''));

    $ok = false;
    if ($kode !== null) {
        $ok = $kode === 1;
    } elseif ($status !== '') {
        $ok = in_array($status, ['OK', 'SUCCESS', 'SUKSES', '1', '00', 'TRUE'], true);
    } else {
        // Beberapa gateway mengembalikan datas kosong tanpa error = sukses
        $ok = is_array($raw) && (!isset($raw['error']));
    }

    if (!$ok) {
        $msg = 'Gagal mengganti kata sandi';
        if ($result !== '') {
            $msg .= ': ' . $result;
        } elseif ($status !== '' && $status !== 'OK') {
            $msg .= ' (' . $status . ')';
        } elseif ($kode !== null) {
            $msg .= ' (KodeRespon ' . $kode . ')';
        }
        kantin_api_json([
            'ok' => false,
            'error' => $msg,
            'kodeRespon' => $kode,
            'data' => ['raw' => $raw],
        ], 422);
    }

    kantin_api_json([
        'ok' => true,
        'message' => 'Kata sandi berhasil diubah',
        'data' => [
            'username' => $username,
            'raw' => $raw,
        ],
    ]);
} catch (Throwable $e) {
    kantin_api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
