<?php

declare(strict_types=1);

/**
 * Portal ortu: cari identitas siswa berdasarkan NIM/NIS.
 * GET  ?nim=220007
 * POST { "nim": "220007" }
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    api_json(['ok' => false, 'error' => 'Gunakan GET atau POST'], 405);
}

try {
    $body = $method === 'POST' ? api_read_json_body() : [];
    $nim = trim((string) (
        $body['nim'] ?? $body['nis'] ?? $body['NIS'] ?? $_GET['nim'] ?? $_GET['nis'] ?? ''
    ));
    $nim = preg_replace('/\s+/', '', $nim);
    if ($nim === '') {
        api_json(['ok' => false, 'error' => 'NIS/NIM wajib diisi'], 400);
    }

    $repo = new SiswaRepository();
    $siswa = $repo->findByNimOrNisn($nim);
    if (!$siswa) {
        api_json([
            'ok' => false,
            'error' => 'Siswa dengan NIS «' . $nim . '» tidak ditemukan. Pastikan sudah sinkron di admin.',
        ], 404);
    }

    $foto = (string) ($siswa['fotoWajah'] ?? '');
    api_json([
        'ok' => true,
        'data' => [
            'id' => $siswa['id'],
            'nis' => $siswa['nis'],
            'nisn' => $siswa['nisn'] ?? '',
            'nama' => $siswa['nama'],
            'kelasId' => $siswa['kelasId'] ?? '',
            'jenisKelamin' => $siswa['jenisKelamin'] ?? 'L',
            'aktif' => !empty($siswa['aktif']),
            'hasFoto' => strlen($foto) > 30,
            'fotoWajah' => $foto,
        ],
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
