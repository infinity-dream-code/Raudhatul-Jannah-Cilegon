<?php

namespace App\Support\FacePay;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FaceKantinBelanjaLog
{
    public function save(array $payload): void
    {
        $conn = (string) config('facepay.connection', 'FACE_MYSQL');

        try {
            DB::connection($conn)->table('kantin_belanja_log')->insert([
                'log_uid' => (string) ($payload['logUid'] ?? Str::uuid()->toString()),
                'siswa_id' => (string) ($payload['siswaId'] ?? ''),
                'nis' => (string) ($payload['nis'] ?? ''),
                'nama_siswa' => (string) ($payload['namaSiswa'] ?? ''),
                'nominal' => (int) ($payload['nominal'] ?? 0),
                'saldo_sebelum' => isset($payload['saldoSebelum']) ? (int) $payload['saldoSebelum'] : null,
                'saldo_sesudah' => isset($payload['saldoSesudah']) ? (int) $payload['saldoSesudah'] : null,
                'nama_kantin' => (string) ($payload['namaKantin'] ?? ''),
                'display_name_kantin' => (string) ($payload['displayNameKantin'] ?? ''),
                'metode' => (string) ($payload['metode'] ?? 'facepay_cashless'),
                'merchant_status' => (string) ($payload['merchantStatus'] ?? 'ok'),
                'merchant_raw' => isset($payload['merchantRaw']) ? json_encode($payload['merchantRaw']) : null,
                'trx_at' => (string) ($payload['trxAt'] ?? now()->format('Y-m-d H:i:s')),
            ]);
        } catch (Throwable) {
            // Log opsional — transaksi cashless tetap sukses
        }
    }
}
