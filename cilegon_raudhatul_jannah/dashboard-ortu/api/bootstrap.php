<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function ortu_api_config(): array
{
    /** @var array<string, mixed> $config */
    $config = require __DIR__ . '/config.php';
    return $config;
}

/** @param array<string, mixed> $payload */
function ortu_sign_jwt(array $payload, string $secret): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $segments = [
        ortu_b64url(json_encode($header, JSON_UNESCAPED_UNICODE)),
        ortu_b64url(json_encode($payload, JSON_UNESCAPED_UNICODE)),
    ];
    $input = implode('.', $segments);
    $segments[] = ortu_b64url(hash_hmac('sha256', $input, $secret, true));
    return implode('.', $segments);
}

function ortu_b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function ortu_http_get(string $url, int $timeout): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('php_curl belum diaktifkan');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
        CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*'],
        CURLOPT_USERAGENT => 'CilegonRaudhatulJannahOrtuPortal/1.0',
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno !== 0) {
        throw new RuntimeException('cURL: ' . $error);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ': ' . (string) $body);
    }
    return (string) $body;
}

/** @param array<string, mixed> $payload
 *  @return array<string, mixed>
 */
function ortu_smartpayment_request(array $payload, ?array $config = null): array
{
    $config = $config ?? ortu_api_config();
    $token = ortu_sign_jwt($payload, (string) $config['jwt_secret']);
    $base = rtrim((string) $config['api_base'], '/') . '/';
    $body = ortu_http_get($base . $token, (int) ($config['timeout'] ?? 30));
    if (trim($body) === '') {
        return ['datas' => []];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respon server tidak valid');
    }
    return $decoded;
}

/** @return array<string, mixed> */
function ortu_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '', true);
    return is_array($input) ? $input : [];
}

function ortu_norm_username(string $raw): string
{
    return preg_replace('/\D+/', '', $raw) ?? '';
}

/** @return int|string */
function ortu_cast_password(string $password)
{
    return is_numeric($password) ? (int) $password : $password;
}

/** Respon merchant yang artinya data kosong (bukan error teknis). */
function ortu_is_no_data_response(array $decoded): bool
{
    $kode = (int) ($decoded['KodeRespon'] ?? 0);
    // Kode 20 umum dipakai Smart Payment untuk "Tidak Ada …"
    if ($kode === 20) {
        return true;
    }
    $pesan = strtolower(trim((string) (
        $decoded['PesanRespon']
        ?? $decoded['Keterangan']
        ?? $decoded['RESULT']
        ?? $decoded['message']
        ?? ''
    )));
    if ($pesan === '') {
        return false;
    }
    return str_contains($pesan, 'tidak ada')
        || str_contains($pesan, 'no data')
        || str_contains($pesan, 'kosong')
        || str_contains($pesan, 'not found');
}

function ortu_response_message(array $decoded, string $defaultMessage): string
{
    foreach (['PesanRespon', 'Keterangan', 'RESULT', 'message'] as $key) {
        $msg = trim((string) ($decoded[$key] ?? ''));
        if ($msg !== '' && strtolower($msg) !== 'null') {
            return $msg;
        }
    }
    return $defaultMessage;
}

/** @param array<string, mixed> $decoded */
function ortu_fail_if_not_ok(array $decoded, string $defaultMessage, int $httpCode = 401): void
{
    $kode = (int) ($decoded['KodeRespon'] ?? 0);
    if ($kode === 1) {
        return;
    }
    if (isset($decoded['datas']) && is_array($decoded['datas'])) {
        return;
    }
    // "Tidak Ada Tagihan/Transaksi" → sukses dengan daftar kosong
    if (ortu_is_no_data_response($decoded)) {
        return;
    }
    $msg = ortu_response_message($decoded, $defaultMessage);
    http_response_code($httpCode);
    echo json_encode([
        'ok' => false,
        'message' => $msg,
        'data' => $decoded,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @param array<string, mixed> $decoded
 *  @return list<array<string, mixed>>
 */
function ortu_extract_datas(array $decoded): array
{
    $rows = $decoded['datas'] ?? $decoded['Datas'] ?? [];
    return is_array($rows) ? array_values($rows) : [];
}

/** @return array{username: string, payload: array<string, mixed>}|null */
function ortu_read_username_payload(string $method): ?array
{
    $input = ortu_read_json_body();
    $username = ortu_norm_username((string) ($input['username'] ?? $input['USERNAME'] ?? ''));
    if ($username === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Username wajib diisi'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return [
        'username' => $username,
        'payload' => [
            'METHOD' => $method,
            'USERNAME' => (int) $username,
        ],
    ];
}

function ortu_handle_list_request(string $method, string $errorMessage): void
{
    ortu_api_bootstrap();
    ortu_require_post();

    $req = ortu_read_username_payload($method);
    try {
        $decoded = ortu_smartpayment_request($req['payload']);
    } catch (Throwable $e) {
        ortu_handle_exception($e);
        exit;
    }

    ortu_fail_if_not_ok($decoded, $errorMessage);
    $datas = ortu_extract_datas($decoded);

    ortu_send_json_ok([
        'data' => $decoded,
        'datas' => $datas,
        'username' => $req['username'],
    ]);
}

/** Coba beberapa METHOD Smart Payment (fallback jika method utama error). */
function ortu_handle_list_request_methods(array $methods, string $errorMessage): void
{
    ortu_api_bootstrap();
    ortu_require_post();

    $input = ortu_read_json_body();
    $username = ortu_norm_username((string) ($input['username'] ?? $input['USERNAME'] ?? ''));
    if ($username === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Username wajib diisi'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $methods = array_values(array_filter(array_map('strval', $methods)));
    if (!$methods) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Konfigurasi method API kosong'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lastError = null;
    $usedMethod = $methods[0];

    foreach ($methods as $method) {
        $payload = [
            'METHOD' => $method,
            'USERNAME' => (int) $username,
        ];
        try {
            $decoded = ortu_smartpayment_request($payload);
            $kode = (int) ($decoded['KodeRespon'] ?? 0);
            $hasDatas = isset($decoded['datas']) && is_array($decoded['datas']);
            if ($kode === 1 || $hasDatas || ortu_is_no_data_response($decoded)) {
                $usedMethod = $method;
                $datas = ortu_extract_datas($decoded);
                ortu_send_json_ok([
                    'data' => $decoded,
                    'datas' => $datas,
                    'username' => $username,
                    'method' => $usedMethod,
                ]);
                exit;
            }
            $msg = ortu_response_message($decoded, $errorMessage);
            $lastError = new RuntimeException($msg);
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    if ($lastError instanceof Throwable) {
        ortu_handle_exception($lastError);
        exit;
    }

    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => $errorMessage], JSON_UNESCAPED_UNICODE);
}

function ortu_send_json_ok(array $payload): void
{
    echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_UNICODE);
}

function ortu_api_bootstrap(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function ortu_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function ortu_handle_exception(Throwable $e): void
{
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'message' => 'Gagal menghubungi server pembayaran. Coba lagi.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
