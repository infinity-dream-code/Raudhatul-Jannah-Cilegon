<?php

declare(strict_types=1);

/**
 * Cek saldo MobileMerchant — InquirySALDO
 * POST: { "nokartu": "220007" } (NOKARTU = NIS) atau { "siswaId": "mm-220007" }
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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

try {
    $body = api_read_json_body();
    $noKartu = $body['nokartu'] ?? $body['NOKARTU'] ?? $body['no_kartu'] ?? null;

    if (($noKartu === null || $noKartu === '') && !empty($body['siswaId'])) {
        $repo = new SiswaRepository();
        $siswa = $repo->findById((string) $body['siswaId']);
        if ($siswa) {
            $noKartu = preg_replace('/\D/', '', (string) ($siswa['nis'] ?? ''));
        }
    }

    if ($noKartu === null || $noKartu === '') {
        api_json(['ok' => false, 'error' => 'NIS (nokartu) atau siswaId wajib'], 400);
    }

    $noKartu = preg_replace('/\D/', '', (string) $noKartu);
    if ($noKartu === '') {
        api_json(['ok' => false, 'error' => 'NIS tidak valid'], 400);
    }

    $noKartuNum = is_numeric($noKartu) ? (int) $noKartu : $noKartu;

    $client = MobileMerchantClient::create();
    $raw = $client->call([
        'METHOD' => 'InquirySALDO',
        'NOKARTU' => $noKartuNum,
    ]);

    $row = null;
    if (is_array($raw)) {
        if (isset($raw[0]) && is_array($raw[0])) {
            $row = $raw[0];
        } elseif (isset($raw['STATUS']) || isset($raw['SALDO'])) {
            $row = $raw;
        }
    }

    if (!$row) {
        api_json(['ok' => false, 'error' => 'Respons saldo tidak dikenali', 'raw' => $raw], 502);
    }

    $status = (string) ($row['STATUS'] ?? '');
    $nama = (string) ($row['NAMA'] ?? '');
    $saldo = (int) preg_replace('/\D/', '', (string) ($row['SALDO'] ?? '0'));

    api_json([
        'ok' => true,
        'data' => [
            'status' => $status,
            'nama' => $nama,
            'saldo' => $saldo,
            'nokartu' => (string) $noKartuNum,
        ],
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
