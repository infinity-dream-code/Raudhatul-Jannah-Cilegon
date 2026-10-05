<?php

declare(strict_types=1);

/**
 * Tarik daftar siswa dari MobileMerchant (StudentRequestUnit) → simpan ke MySQL.
 * POST JSON: { "kodeunit": "101" } atau { "KODEUNIT": 101 }
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/MobileMerchantClient.php';
require_once __DIR__ . '/lib/SiswaRepository.php';

api_send_cors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $input = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? array_merge($_POST, api_read_json_body())
        : $_GET;

    $kodeUnit = $input['KODEUNIT'] ?? $input['kodeunit'] ?? $input['kodeUnit'] ?? null;
    if ($kodeUnit === null || trim((string) $kodeUnit) === '') {
        api_json(['ok' => false, 'error' => 'Pilih unit terlebih dahulu (KODEUNIT wajib).'], 400);
    }

    $client = MobileMerchantClient::create();
    $raw = $client->studentRequestUnit($kodeUnit);

    $rows = [];
    if (is_array($raw)) {
        if (isset($raw['datas']) && is_array($raw['datas'])) {
            $rows = $raw['datas'];
        } elseif (isset($raw[0])) {
            $rows = $raw;
        }
    }

    $repo = new SiswaRepository();
    $stats = $repo->syncFromMerchant($rows);

    api_json([
        'ok' => true,
        'kodeunit' => is_numeric($kodeUnit) ? (int) $kodeUnit : (string) $kodeUnit,
        'sync' => $stats,
        'data' => $repo->listAllLight(),
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
