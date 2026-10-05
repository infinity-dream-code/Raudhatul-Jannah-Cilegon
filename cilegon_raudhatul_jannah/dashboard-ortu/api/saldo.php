<?php

declare(strict_types=1);

/**
 * Proxy saldo SPP Smart Payment — SaldoRequest via JWT.
 */
require __DIR__ . '/bootstrap.php';

ortu_api_bootstrap();
ortu_require_post();

$input = ortu_read_json_body();
$username = ortu_norm_username((string) ($input['username'] ?? $input['USERNAME'] ?? ''));

if ($username === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Username wajib diisi'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'METHOD' => 'SaldoRequest',
    'USERNAME' => (int) $username,
];

try {
    $decoded = ortu_smartpayment_request($payload);
} catch (Throwable $e) {
    ortu_handle_exception($e);
    exit;
}

ortu_fail_if_not_ok($decoded, 'Gagal mengambil saldo SPP.');

$saldoRaw = $decoded['SALDO'] ?? $decoded['Saldo'] ?? 0;
$saldo = is_numeric($saldoRaw) ? (int) $saldoRaw : (int) preg_replace('/\D+/', '', (string) $saldoRaw);

ortu_send_json_ok([
    'data' => $decoded,
    'username' => $username,
    'saldo' => $saldo,
]);
