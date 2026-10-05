<?php

declare(strict_types=1);

/** Stub saldo — belum terhubung MobileMerchant; hindari error pullAll. */

require_once __DIR__ . '/lib/bootstrap.php';

api_send_cors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    api_json(['data' => []]);
}

if ($method === 'PUT') {
    api_json(['ok' => true, 'map' => []]);
}

if ($method === 'POST') {
    api_json(['ok' => true, 'before' => 0, 'after' => 0]);
}

api_json(['ok' => false, 'error' => 'Method tidak didukung'], 405);
