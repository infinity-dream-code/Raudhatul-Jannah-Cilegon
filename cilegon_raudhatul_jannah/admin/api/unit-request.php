<?php

declare(strict_types=1);

/**
 * Daftar unit dari MobileMerchant (UnitRequest).
 * GET atau POST
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/MobileMerchantClient.php';

api_send_cors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $client = MobileMerchantClient::create();
    $raw = $client->unitRequest();

    $rows = [];
    if (is_array($raw)) {
        if (isset($raw['datas']) && is_array($raw['datas'])) {
            $rows = $raw['datas'];
        } elseif (isset($raw[0])) {
            $rows = $raw;
        }
    }

    $units = [];
    foreach ($rows as $item) {
        if (!is_array($item)) {
            continue;
        }
        $kode = trim((string) ($item['KODEUNIT'] ?? $item['kodeunit'] ?? $item['kode'] ?? ''));
        $nama = trim((string) ($item['UNIT'] ?? $item['unit'] ?? $item['nama'] ?? ''));
        if ($kode === '') {
            continue;
        }
        $units[] = [
            'kodeunit' => $kode,
            'unit' => $nama !== '' ? $nama : $kode,
        ];
    }

    api_json([
        'ok' => true,
        'data' => $units,
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
