<?php

declare(strict_types=1);

/**
 * Ganti kata sandi admin yang sedang login.
 * POST { oldPassword, newPassword }
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/AdminAuth.php';

AdminAuth::sendCors();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

try {
    $user = AdminAuth::currentUser();
    if (!$user) {
        api_json(['ok' => false, 'error' => 'Belum login'], 401);
    }

    $body = api_read_json_body();
    $old = (string) ($body['oldPassword'] ?? $body['old'] ?? '');
    $new = (string) ($body['newPassword'] ?? $body['new'] ?? '');

    if (strlen($new) < 6) {
        api_json(['ok' => false, 'error' => 'Kata sandi baru minimal 6 karakter.'], 400);
    }

    $ok = AdminAuth::changePassword((int) $user['id'], $old, $new);
    if (!$ok) {
        api_json(['ok' => false, 'error' => 'Kata sandi lama salah atau tidak valid.'], 400);
    }

    api_json(['ok' => true]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
