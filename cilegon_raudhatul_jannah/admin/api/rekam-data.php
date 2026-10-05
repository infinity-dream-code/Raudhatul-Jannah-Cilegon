<?php

declare(strict_types=1);

/**
 * API rekam wajah — sumber tunggal admin & portal ortu.
 *
 * GET  /api/rekam-data.php             → daftar ringkas (tanpa foto base64)
 * GET  /api/rekam-data.php?id={id}     → satu siswa lengkap + foto_wajah
 * GET  /api/rekam-data.php/{id}        → (fallback jika PATH_INFO didukung)
 * GET  /api/rekam-data.php?withFoto=1  → semua siswa aktif berfoto + base64 (Face Detector)
 * POST /api/rekam-data.php             → simpan rfid/foto/suara ke tabel siswa
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

try {
    $repo = new SiswaRepository();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $resourceId = api_request_resource_id();

    if ($method === 'GET') {
        if (!empty($_GET['withFoto'])) {
            api_json(['ok' => true, 'data' => $repo->listWithFoto()]);
        }
        if ($resourceId !== '') {
            $row = $repo->findByIdOrNis($resourceId);
            if (!$row) {
                api_json(['ok' => false, 'error' => 'Siswa tidak ditemukan'], 404);
            }
            $foto = (string) ($row['fotoWajah'] ?? '');
            $row['hasFoto'] = strlen($foto) > 30;
            api_json(['ok' => true, 'data' => $row]);
        }
        api_json(['ok' => true, 'data' => $repo->listRekamSummary()]);
    }

    if ($method === 'POST') {
        $body = api_read_json_body();
        $siswaId = (string) ($body['siswaId'] ?? $body['id'] ?? '');
        if ($siswaId === '') {
            api_json(['ok' => false, 'error' => 'siswaId wajib'], 400);
        }

        $existing = $repo->findByIdOrNis($siswaId);
        if (!$existing) {
            api_json(['ok' => false, 'error' => 'Siswa tidak ditemukan'], 404);
        }
        $siswaId = (string) ($existing['id'] ?? $siswaId);

        if (array_key_exists('rfidUid', $body)) {
            $existing['rfidUid'] = (string) $body['rfidUid'];
        }
        if (array_key_exists('fotoWajah', $body)) {
            $existing['fotoWajah'] = (string) $body['fotoWajah'];
        }
        if (array_key_exists('kodeSuara', $body)) {
            $existing['kodeSuara'] = (string) $body['kodeSuara'];
        }

        $saved = $repo->update($siswaId, $repo->apiToDb($existing));

        $jenis = 'lengkap';
        $hasRfid = !empty($saved['rfidUid']);
        $hasFoto = !empty($saved['fotoWajah']) && strlen((string) $saved['fotoWajah']) > 30;
        $hasSuara = !empty($saved['kodeSuara']);
        if ($hasRfid && !$hasFoto && !$hasSuara) {
            $jenis = 'rfid';
        } elseif ($hasFoto && !$hasRfid && !$hasSuara) {
            $jenis = 'foto';
        } elseif ($hasSuara && !$hasRfid && !$hasFoto) {
            $jenis = 'suara';
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO siswa_rekam_log (siswa_id, nis, jenis, rfid_uid, punya_foto, kode_suara)
             VALUES (:siswa_id, :nis, :jenis, :rfid_uid, :punya_foto, :kode_suara)'
        )->execute([
            ':siswa_id' => $siswaId,
            ':nis' => $saved['nis'] ?? '',
            ':jenis' => $jenis,
            ':rfid_uid' => $saved['rfidUid'] ?? null,
            ':punya_foto' => $hasFoto ? 1 : 0,
            ':kode_suara' => $saved['kodeSuara'] ?? null,
        ]);

        $saved['hasFoto'] = $hasFoto;
        api_json(['ok' => true, 'data' => $saved]);
    }

    api_json(['ok' => false, 'error' => 'Method tidak didukung'], 405);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
