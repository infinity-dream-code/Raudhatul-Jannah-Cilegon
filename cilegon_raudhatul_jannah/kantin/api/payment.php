<?php

declare(strict_types=1);

/**
 * PaymentBELANJAKantin — selalu pengurangan saldo.
 * NAMAKANTIN dari session (bukan body client).
 * POST: { "nokartu": "...", "nominal": 100, "saldoSebelum"?: number, "namaSiswa"?: string }
 * Setelah sukses, catat ke tabel kantin_belanja_log (DB lokal).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/admin/api/lib/Database.php';
require_once dirname(__DIR__, 2) . '/admin/api/lib/SiswaRepository.php';
require_once dirname(__DIR__, 2) . '/admin/api/lib/KantinBelanjaRepository.php';

kantin_send_headers();
kantin_handle_options();
$user = KantinAuth::requireUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

/**
 * Merchant kadang mengembalikan objek, kadang list [{...}].
 *
 * @param mixed $raw
 * @return array<string, mixed>|null
 */
function kantin_merchant_row($raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }
    if (isset($raw[0]) && is_array($raw[0])) {
        return $raw[0];
    }
    if (isset($raw['datas'][0]) && is_array($raw['datas'][0])) {
        return $raw['datas'][0];
    }
    // Assoc object response
    if (array_key_exists('STATUS', $raw)
        || array_key_exists('Status', $raw)
        || array_key_exists('status', $raw)
        || array_key_exists('RESULT', $raw)
        || array_key_exists('KodeRespon', $raw)
    ) {
        return $raw;
    }
    return null;
}

/**
 * @param mixed $raw
 */
function kantin_merchant_status($raw): string
{
    $row = kantin_merchant_row($raw);
    if (!$row) {
        return is_string($raw) ? trim($raw) : '';
    }
    foreach (['STATUS', 'Status', 'status', 'RESULT', 'Result', 'result', 'KodeRespon', 'MESSAGE', 'Message', 'message'] as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
            return trim((string) $row[$key]);
        }
    }
    return '';
}

/**
 * Pesan ramah untuk kode RESULT merchant.
 */
function kantin_result_message(string $result, string $status = ''): string
{
    $code = strtoupper(trim($result));
    $map = [
        'TRANSAKSI_LIMIT' => 'Transaksi melebihi limit. Kurangi nominal atau coba lagi nanti.',
        'SALDO_TIDAK_CUKUP' => 'Saldo tidak cukup untuk transaksi ini.',
        'SALDOTIDAKCUKUP' => 'Saldo tidak cukup untuk transaksi ini.',
        'INSUFFICIENT' => 'Saldo tidak cukup untuk transaksi ini.',
        'KARTU_TIDAK_DITEMUKAN' => 'Kartu / NIS tidak ditemukan.',
        'NOT_FOUND' => 'Data siswa tidak ditemukan.',
        'TIMEOUT' => 'Koneksi ke server merchant timeout. Coba lagi.',
        'UNAUTHORIZED' => 'Kantin tidak diizinkan melakukan transaksi.',
    ];
    if (isset($map[$code])) {
        return $map[$code];
    }
    if ($code !== '') {
        return 'Pembayaran ditolak: ' . $code;
    }
    if ($status !== '') {
        return 'Pembayaran gagal (' . $status . ').';
    }
    return 'Pembayaran gagal.';
}

/**
 * @param mixed $raw
 */
function kantin_merchant_failed($raw): ?string
{
    $row = kantin_merchant_row($raw);
    if ($row === null) {
        return null;
    }

    if (array_key_exists('KodeRespon', $row) || array_key_exists('kodeRespon', $row)) {
        $kode = $row['KodeRespon'] ?? $row['kodeRespon'];
        if (is_numeric($kode) && (int) $kode !== 1) {
            $result = (string) ($row['RESULT'] ?? $row['Result'] ?? $row['MESSAGE'] ?? '');
            return kantin_result_message($result, 'KodeRespon ' . (int) $kode);
        }
    }

    $status = strtoupper(trim((string) ($row['STATUS'] ?? $row['Status'] ?? $row['status'] ?? '')));
    $result = trim((string) ($row['RESULT'] ?? $row['Result'] ?? $row['result'] ?? ''));
    $message = trim((string) ($row['MESSAGE'] ?? $row['Message'] ?? $row['message'] ?? ''));

    $okStatuses = ['OK', 'SUCCESS', 'SUKSES', '1', '00', 'TRUE'];
    if ($status !== '' && !in_array($status, $okStatuses, true)) {
        return kantin_result_message($result !== '' ? $result : $message, $status);
    }

    // Beberapa respons hanya mengirim RESULT tanpa STATUS
    if ($status === '' && $result !== '') {
        $failResults = ['TRANSAKSI_LIMIT', 'SALDO_TIDAK_CUKUP', 'FAILED', 'GAGAL', 'ERROR', 'NOTOK'];
        if (in_array(strtoupper($result), $failResults, true)
            || stripos($result, 'LIMIT') !== false
            || stripos($result, 'GAGAL') !== false
        ) {
            return kantin_result_message($result);
        }
    }

    return null;
}

/**
 * @param mixed $raw
 */
function kantin_extract_saldo($raw): ?int
{
    $row = kantin_merchant_row($raw) ?? (is_array($raw) ? $raw : null);
    if (!$row) {
        return null;
    }
    foreach (['SALDO', 'Saldo', 'saldo', 'SISA', 'Sisa', 'sisa', 'SALDOAKHIR', 'SaldoAkhir'] as $key) {
        if (isset($row[$key]) && is_numeric($row[$key])) {
            return (int) $row[$key];
        }
    }
    return null;
}

try {
    $body = api_read_json_body();
    $noKartu = preg_replace('/\D/', '', (string) ($body['nokartu'] ?? $body['NOKARTU'] ?? ''));
    $nominal = $body['nominal'] ?? $body['NOMINAL'] ?? null;

    if ($noKartu === '') {
        kantin_api_json(['ok' => false, 'error' => 'nokartu wajib'], 400);
    }

    if ($nominal === null || $nominal === '' || !is_numeric($nominal)) {
        kantin_api_json(['ok' => false, 'error' => 'nominal tidak valid'], 400);
    }

    $nominalInt = (int) $nominal;
    if ($nominalInt <= 0) {
        kantin_api_json(['ok' => false, 'error' => 'nominal harus lebih dari 0'], 400);
    }
    if ($nominalInt > 999999999) {
        kantin_api_json(['ok' => false, 'error' => 'nominal terlalu besar'], 400);
    }

    $namaKantin = (string) $user['username'];
    if ($namaKantin === '') {
        kantin_api_json(['ok' => false, 'error' => 'Session kantin tidak valid'], 401);
    }

    $saldoSebelum = null;
    if (isset($body['saldoSebelum']) && is_numeric($body['saldoSebelum'])) {
        $saldoSebelum = (int) $body['saldoSebelum'];
    } elseif (isset($body['saldo_sebelum']) && is_numeric($body['saldo_sebelum'])) {
        $saldoSebelum = (int) $body['saldo_sebelum'];
    }

    $namaSiswaBody = trim((string) ($body['namaSiswa'] ?? $body['nama'] ?? ''));

    $noKartuNum = is_numeric($noKartu) ? (int) $noKartu : $noKartu;

    $client = MobileMerchantClient::create();
    $raw = $client->call([
        'METHOD' => 'PaymentBELANJAKantin',
        'NOKARTU' => $noKartuNum,
        'NOMINAL' => $nominalInt,
        'NAMAKANTIN' => $namaKantin,
    ]);

    $failMsg = kantin_merchant_failed($raw);
    if ($failMsg !== null) {
        $row = kantin_merchant_row($raw);
        kantin_api_json([
            'ok' => false,
            'error' => $failMsg,
            'code' => strtoupper(trim((string) ($row['RESULT'] ?? $row['Result'] ?? $row['STATUS'] ?? 'NOTOK'))),
            'data' => [
                'nokartu' => (string) $noKartuNum,
                'nominal' => $nominalInt,
                'namaKantin' => $namaKantin,
                'raw' => $raw,
            ],
        ], 422);
    }

    $saldoDariMerchant = kantin_extract_saldo($raw);
    $saldoSesudah = $saldoDariMerchant;
    if ($saldoSesudah === null && $saldoSebelum !== null) {
        $saldoSesudah = $saldoSebelum - $nominalInt;
    }

    $siswaId = '';
    $namaSiswa = $namaSiswaBody;
    try {
        $siswaRepo = new SiswaRepository();
        $siswa = $siswaRepo->findByNis((string) $noKartuNum);
        if ($siswa) {
            $siswaId = (string) ($siswa['id'] ?? '');
            if ($namaSiswa === '') {
                $namaSiswa = (string) ($siswa['nama'] ?? $siswa['namaMerchant'] ?? '');
            }
        }
    } catch (Throwable $eSiswa) {
        // Lookup siswa opsional — belanja tetap sukses
    }

    $logUid = null;
    try {
        $belanjaRepo = new KantinBelanjaRepository();
        $saved = $belanjaRepo->saveLog([
            'siswaId' => $siswaId,
            'nis' => (string) $noKartuNum,
            'namaSiswa' => $namaSiswa,
            'nominal' => $nominalInt,
            'saldoSebelum' => $saldoSebelum,
            'saldoSesudah' => $saldoSesudah,
            'namaKantin' => $namaKantin,
            'displayNameKantin' => (string) ($user['displayName'] ?? $namaKantin),
            'metode' => 'facepay',
            'merchantStatus' => kantin_merchant_status($raw),
            'merchantRaw' => $raw,
            'trxAt' => date('Y-m-d H:i:s'),
        ]);
        $logUid = $saved['logUid'] ?? null;
    } catch (Throwable $eLog) {
        error_log('[kantin payment] gagal simpan log belanja: ' . $eLog->getMessage());
    }

    kantin_api_json([
        'ok' => true,
        'data' => [
            'nokartu' => (string) $noKartuNum,
            'nominal' => $nominalInt,
            'namaKantin' => $namaKantin,
            'namaSiswa' => $namaSiswa,
            'saldoSebelum' => $saldoSebelum,
            'saldoSesudah' => $saldoSesudah,
            'logUid' => $logUid,
            'raw' => $raw,
        ],
    ]);
} catch (Throwable $e) {
    kantin_api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}
