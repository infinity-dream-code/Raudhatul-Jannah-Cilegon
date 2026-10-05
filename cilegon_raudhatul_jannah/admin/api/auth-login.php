<?php

declare(strict_types=1);

/**
 * Login admin. POST { username, password } → set sesi, balas data admin.
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
    $body = api_read_json_body();
    $username = (string) ($body['username'] ?? $body['user'] ?? '');
    $password = (string) ($body['password'] ?? $body['pass'] ?? '');

    if (trim($username) === '' || $password === '') {
        api_json(['ok' => false, 'error' => 'Username dan kata sandi wajib diisi.'], 400);
    }

    $user = AdminAuth::attempt($username, $password);
    if (!$user) {
        api_json(['ok' => false, 'error' => 'Username atau kata sandi salah.'], 401);
    }

    api_json(['ok' => true, 'data' => $user]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
