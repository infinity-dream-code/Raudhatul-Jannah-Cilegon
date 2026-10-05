<?php

declare(strict_types=1);

/**
 * Log belanja FacePay kantin (saldo sebelum/sesudah) di MySQL lokal.
 */
final class KantinBelanjaRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::pdo();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, logUid: string}
     */
    public function saveLog(array $payload): array
    {
        $logUid = (string) ($payload['id'] ?? $payload['logUid'] ?? uniqid('kb-', true));
        $sql = 'INSERT INTO kantin_belanja_log (
            log_uid, siswa_id, nis, nama_siswa,
            nominal, saldo_sebelum, saldo_sesudah,
            nama_kantin, display_name_kantin, metode,
            merchant_status, merchant_raw, trx_at
        ) VALUES (
            :log_uid, :siswa_id, :nis, :nama_siswa,
            :nominal, :saldo_sebelum, :saldo_sesudah,
            :nama_kantin, :display_name_kantin, :metode,
            :merchant_status, :merchant_raw, :trx_at
        )';

        $merchantRaw = $payload['merchantRaw'] ?? $payload['merchant_raw'] ?? null;
        if (is_array($merchantRaw) || is_object($merchantRaw)) {
            $merchantRaw = json_encode($merchantRaw, JSON_UNESCAPED_UNICODE);
        }

        $this->pdo->prepare($sql)->execute([
            ':log_uid' => $logUid,
            ':siswa_id' => (string) ($payload['siswaId'] ?? $payload['siswa_id'] ?? ''),
            ':nis' => (string) ($payload['nis'] ?? $payload['nokartu'] ?? ''),
            ':nama_siswa' => (string) ($payload['namaSiswa'] ?? $payload['nama_siswa'] ?? $payload['nama'] ?? ''),
            ':nominal' => (int) ($payload['nominal'] ?? 0),
            ':saldo_sebelum' => $this->nullableInt($payload['saldoSebelum'] ?? $payload['saldo_sebelum'] ?? null),
            ':saldo_sesudah' => $this->nullableInt($payload['saldoSesudah'] ?? $payload['saldo_sesudah'] ?? null),
            ':nama_kantin' => (string) ($payload['namaKantin'] ?? $payload['nama_kantin'] ?? ''),
            ':display_name_kantin' => (string) ($payload['displayNameKantin'] ?? $payload['display_name_kantin'] ?? ''),
            ':metode' => (string) ($payload['metode'] ?? 'facepay'),
            ':merchant_status' => (string) ($payload['merchantStatus'] ?? $payload['merchant_status'] ?? ''),
            ':merchant_raw' => $merchantRaw !== null && $merchantRaw !== '' ? (string) $merchantRaw : null,
            ':trx_at' => $this->normalizeDateTime($payload['trxAt'] ?? $payload['trx_at'] ?? null) ?? date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'logUid' => $logUid];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(?string $namaKantin = null, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT
            log_uid AS id,
            siswa_id AS siswaId,
            nis,
            nama_siswa AS namaCust,
            nama_siswa AS namaSiswa,
            nominal,
            saldo_sebelum AS saldoSebelum,
            saldo_sesudah AS saldoSesudah,
            nama_kantin AS kantin,
            nama_kantin AS namaKantin,
            display_name_kantin AS displayNameKantin,
            metode,
            merchant_status AS merchantStatus,
            trx_at AS trxDate,
            created_at AS createdAt
          FROM kantin_belanja_log
          WHERE 1=1';
        $params = [];
        if ($namaKantin !== null && $namaKantin !== '') {
            $sql .= ' AND nama_kantin = :nama_kantin';
            $params[':nama_kantin'] = $namaKantin;
        }
        $sql .= ' ORDER BY trx_at DESC, id DESC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            $row['nominal'] = (int) ($row['nominal'] ?? 0);
            $row['saldoSebelum'] = $row['saldoSebelum'] !== null ? (int) $row['saldoSebelum'] : null;
            $row['saldoSesudah'] = $row['saldoSesudah'] !== null ? (int) $row['saldoSesudah'] : null;
            $row['trxDate'] = (string) ($row['trxDate'] ?? '');
            $row['kantin'] = (string) ($row['kantin'] ?? '');
            $row['namaCust'] = (string) ($row['namaCust'] ?? '');
            return $row;
        }, $rows ?: []);
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
