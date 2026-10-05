<?php

declare(strict_types=1);

/**
 * Simpan / baca log presensi masuk-keluar.
 * POST body: objek log (id, siswaId, nis, nama, unit, kelas, kegiatan, waktuMasuk, waktuKeluar, metode)
 * GET: daftar terbaru
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/PresensiRepository.php';

api_send_cors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $repo = new PresensiRepository();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
        $date = isset($_GET['date']) ? trim((string) $_GET['date']) : null;
        $kegiatan = isset($_GET['kegiatan']) ? trim((string) $_GET['kegiatan']) : null;
        if ($date !== null && $date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            api_json(['ok' => false, 'error' => 'Format date harus YYYY-MM-DD'], 400);
        }
        api_json(['data' => $repo->listRecent($limit, $date ?: null, $kegiatan ?: null)]);
    }

    if ($method === 'POST') {
        $body = api_read_json_body();
        if ($body === []) {
            api_json(['ok' => false, 'error' => 'Body kosong'], 400);
        }
        $result = $repo->saveLog($body);
        api_json($result);
    }

    api_json(['ok' => false, 'error' => 'Method tidak didukung'], 405);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
