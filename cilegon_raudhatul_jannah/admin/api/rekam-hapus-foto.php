<?php

declare(strict_types=1);

/**
 * Hapus rekap foto wajah siswa berdasarkan NIS.
 * POST: { "nis": "220007" }
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/SiswaRepository.php';

api_send_cors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

try {
    $body = api_read_json_body();
    $nis = trim((string) ($body['nis'] ?? $body['NIS'] ?? ''));
    if ($nis === '') {
        api_json(['ok' => false, 'error' => 'nis wajib'], 400);
    }

    $repo = new SiswaRepository();
    $saved = $repo->clearFotoWajahByNis($nis);
    if (!$saved) {
        api_json(['ok' => false, 'error' => 'Siswa dengan NIS tersebut tidak ditemukan'], 404);
    }

    api_json(['ok' => true, 'data' => $saved]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
