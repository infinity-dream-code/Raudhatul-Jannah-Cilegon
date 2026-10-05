<?php

declare(strict_types=1);

/**
 * InquirySALDO — wajib session kantin.
 * POST: { "nokartu": "..." } atau { "siswaId": "..." }
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/admin/api/lib/Database.php';
require_once dirname(__DIR__, 2) . '/admin/api/lib/SiswaRepository.php';

kantin_send_headers();
kantin_handle_options();
KantinAuth::requireUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
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
        kantin_api_json(['ok' => false, 'error' => 'NIS (nokartu) atau siswaId wajib'], 400);
    }

    $noKartu = preg_replace('/\D/', '', (string) $noKartu);
    if ($noKartu === '') {
        kantin_api_json(['ok' => false, 'error' => 'NIS tidak valid'], 400);
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
        kantin_api_json(['ok' => false, 'error' => 'Respons saldo tidak dikenali', 'raw' => $raw], 502);
    }

    $status = (string) ($row['STATUS'] ?? '');
    $nama = (string) ($row['NAMA'] ?? '');
    $saldo = (int) preg_replace('/\D/', '', (string) ($row['SALDO'] ?? '0'));

    if (strtoupper($status) !== 'OK' && $status !== '') {
        kantin_api_json([
            'ok' => false,
            'error' => 'Inquiry saldo gagal: ' . $status,
            'data' => [
                'status' => $status,
                'nama' => $nama,
                'saldo' => $saldo,
                'nokartu' => (string) $noKartuNum,
            ],
        ], 502);
    }

    kantin_api_json([
        'ok' => true,
        'data' => [
            'status' => $status !== '' ? $status : 'OK',
            'nama' => $nama,
            'saldo' => $saldo,
            'nokartu' => (string) $noKartuNum,
        ],
    ]);
} catch (Throwable $e) {
    kantin_api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
