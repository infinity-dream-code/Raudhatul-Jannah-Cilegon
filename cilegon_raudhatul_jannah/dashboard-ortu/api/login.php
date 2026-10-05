<?php

declare(strict_types=1);

/**
 * Proxy login Smart Payment — LoginRequest via JWT di path Token/{jwt}.
 */
require __DIR__ . '/bootstrap.php';

ortu_api_bootstrap();
ortu_require_post();

$input = ortu_read_json_body();
$username = ortu_norm_username((string) ($input['username'] ?? $input['USERNAME'] ?? ''));
$password = trim((string) ($input['password'] ?? $input['PASSWORD'] ?? ''));

if ($username === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Username wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Password wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'METHOD' => 'LoginRequest',
    'USERNAME' => (int) $username,
    'PASSWORD' => ortu_cast_password($password),
];

try {
    $decoded = ortu_smartpayment_request($payload);
} catch (Throwable $e) {
    ortu_handle_exception($e);
    exit;
}

ortu_fail_if_not_ok($decoded, 'Username atau password salah.');

ortu_send_json_ok([
    'data' => $decoded,
    'username' => $username,
]);
