<?php

declare(strict_types=1);

/**
 * Proxy ganti password — RequestNewPassword via JWT.
 */
require __DIR__ . '/bootstrap.php';

ortu_api_bootstrap();
ortu_require_post();

$input = ortu_read_json_body();
$username = ortu_norm_username((string) ($input['username'] ?? $input['USERNAME'] ?? ''));
$password = trim((string) ($input['password'] ?? $input['PASSWORD'] ?? ''));
$newPassword = trim((string) ($input['newPassword'] ?? $input['NEWPASSWORD'] ?? ''));
$newPassword2 = trim((string) ($input['newPassword2'] ?? $input['NEWPASSWORD2'] ?? ''));

if ($username === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Username wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Password lama wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($newPassword === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Password baru wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($newPassword2 === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Konfirmasi password baru wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($newPassword !== $newPassword2) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Konfirmasi password tidak cocok'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'METHOD' => 'RequestNewPassword',
    'USERNAME' => (int) $username,
    'PASSWORD' => ortu_cast_password($password),
    'NEWPASSWORD' => ortu_cast_password($newPassword),
    'NEWPASSWORD2' => ortu_cast_password($newPassword2),
];

try {
    $decoded = ortu_smartpayment_request($payload);
} catch (Throwable $e) {
    ortu_handle_exception($e);
    exit;
}

ortu_fail_if_not_ok($decoded, 'Gagal mengubah password.');

$msg = trim((string) ($decoded['Keterangan'] ?? ''));
if ($msg === '' || strtolower($msg) === 'null') {
    $msg = 'Password berhasil diubah.';
}

ortu_send_json_ok([
    'data' => $decoded,
    'datas' => ortu_extract_datas($decoded),
    'username' => $username,
    'message' => $msg,
]);
