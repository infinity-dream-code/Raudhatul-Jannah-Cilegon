<?php

declare(strict_types=1);

/**
 * Simpan data rekam (RFID, foto, kode suara) ke tabel siswa + log rekam.
 * POST: { siswaId, rfidUid?, fotoWajah?, kodeSuara? }
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
    $siswaId = (string) ($body['siswaId'] ?? $body['id'] ?? '');
    if ($siswaId === '') {
        api_json(['ok' => false, 'error' => 'siswaId wajib'], 400);
    }

    $repo = new SiswaRepository();
    $existing = $repo->findById($siswaId);
    if (!$existing) {
        api_json(['ok' => false, 'error' => 'Siswa tidak ditemukan'], 404);
    }

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
    $hasFoto = !empty($saved['fotoWajah']);
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

    api_json(['ok' => true, 'data' => $saved]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
