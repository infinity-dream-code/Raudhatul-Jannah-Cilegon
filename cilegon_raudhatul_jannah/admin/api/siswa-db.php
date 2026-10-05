<?php

declare(strict_types=1);

/**
 * REST siswa ↔ MySQL (untuk PresensiApiSiswa / data-siswa.html)
 *
 * GET    /api/siswa-db.php           → { data: [...] }
 * GET    /api/siswa-db.php?id=…      → { data: {...} }
 * POST   /api/siswa-db.php           → tambah satu
 * PUT    /api/siswa-db.php           → ganti semua { data: [...] }
 * PUT    /api/siswa-db.php?id=…      → ubah satu
 * DELETE /api/siswa-db.php?id=…      → hapus
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
    // Pakai ?id= (dan PATH_INFO jika ada) — kompatibel Apache/cPanel tanpa rewrite
    $pathId = api_request_resource_id();
    $body = api_read_json_body();

    if ($method === 'GET') {
        if ($pathId !== '') {
            $row = $repo->findByIdOrNis($pathId);
            if (!$row) {
                api_json(['ok' => false, 'error' => 'Siswa tidak ditemukan'], 404);
            }
            api_json(['data' => $row]);
        }
        api_json(['data' => $repo->listAllLight()]);
    }

    if ($method === 'POST') {
        $row = $repo->apiToDb($body);
        if ($row['nis'] === '' || $row['nama'] === '') {
            api_json(['ok' => false, 'error' => 'nis dan nama wajib'], 400);
        }
        if ($repo->findByNis($row['nis'])) {
            api_json(['ok' => false, 'error' => 'NIS sudah terdaftar'], 409);
        }
        $saved = $repo->insert($row);
        api_json(['data' => $saved], 201);
    }

    if ($method === 'PUT') {
        if ($pathId === '') {
            if (!isset($body['data']) || !is_array($body['data'])) {
                api_json(['ok' => false, 'error' => 'Body harus { data: [...] } untuk replaceAll'], 400);
            }
            $normalized = [];
            foreach ($body['data'] as $item) {
                if (is_array($item)) {
                    $normalized[] = $repo->apiToDb($item);
                }
            }
            api_json(['data' => $repo->replaceAll($normalized)]);
        }

        $existing = $repo->findByIdOrNis($pathId);
        if (!$existing) {
            api_json(['ok' => false, 'error' => 'Siswa tidak ditemukan'], 404);
        }
        $realId = (string) $existing['id'];

        // Toggle aktif saja → UPDATE langsung kolom aktif (0/1)
        $keys = array_keys($body);
        $extra = array_diff($keys, ['id', 'aktif', 'siswaId', 'siswa_id']);
        if (array_key_exists('aktif', $body) && $extra === []) {
            $saved = $repo->setAktif($realId, $body['aktif']);
            api_json(['ok' => true, 'data' => $saved]);
        }

        $merged = array_merge($existing, $body);
        $merged['id'] = $realId;
        $saved = $repo->update($realId, $repo->apiToDb($merged));
        api_json(['ok' => true, 'data' => $saved]);
    }

    if ($method === 'DELETE') {
        if ($pathId === '') {
            api_json(['ok' => false, 'error' => 'ID wajib'], 400);
        }
        $existing = $repo->findByIdOrNis($pathId);
        if (!$existing) {
            api_json(['ok' => false, 'error' => 'Siswa tidak ditemukan'], 404);
        }
        $repo->delete((string) $existing['id']);
        api_json(['ok' => true]);
    }

    api_json(['ok' => false, 'error' => 'Method tidak didukung'], 405);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
