<?php

declare(strict_types=1);

final class PresensiRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::pdo();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveLog(array $payload): array
    {
        $logUid = (string) ($payload['id'] ?? $payload['logUid'] ?? uniqid('pl-', true));
        $sql = 'INSERT INTO presensi_log (
            log_uid, siswa_id, nis, nama, unit_label, kelas_label, kegiatan, metode,
            waktu_masuk, waktu_keluar
        ) VALUES (
            :log_uid, :siswa_id, :nis, :nama, :unit_label, :kelas_label, :kegiatan, :metode,
            :waktu_masuk, :waktu_keluar
        )
        ON DUPLICATE KEY UPDATE
            nis = VALUES(nis),
            nama = VALUES(nama),
            unit_label = VALUES(unit_label),
            kelas_label = VALUES(kelas_label),
            kegiatan = VALUES(kegiatan),
            metode = VALUES(metode),
            waktu_masuk = COALESCE(VALUES(waktu_masuk), waktu_masuk),
            waktu_keluar = VALUES(waktu_keluar)';

        $this->pdo->prepare($sql)->execute([
            ':log_uid' => $logUid,
            ':siswa_id' => (string) ($payload['siswaId'] ?? $payload['siswa_id'] ?? ''),
            ':nis' => (string) ($payload['nis'] ?? ''),
            ':nama' => (string) ($payload['nama'] ?? ''),
            ':unit_label' => (string) ($payload['unit'] ?? $payload['unit_label'] ?? ''),
            ':kelas_label' => (string) ($payload['kelas'] ?? $payload['kelas_label'] ?? ''),
            ':kegiatan' => (string) ($payload['kegiatan'] ?? ''),
            ':metode' => (string) ($payload['metode'] ?? 'face recognition'),
            ':waktu_masuk' => $this->normalizeDateTime($payload['waktuMasuk'] ?? $payload['waktu_masuk'] ?? $payload['waktu'] ?? null),
            ':waktu_keluar' => $this->normalizeDateTime($payload['waktuKeluar'] ?? $payload['waktu_keluar'] ?? null),
        ]);

        return ['ok' => true, 'logUid' => $logUid];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 200, ?string $dateYmd = null, ?string $kegiatan = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT log_uid AS id, siswa_id AS siswaId, nis, nama,
                    unit_label AS unit, kelas_label AS kelas, kegiatan, metode,
                    waktu_masuk AS waktuMasuk, waktu_keluar AS waktuKeluar,
                    waktu_masuk AS waktu
             FROM presensi_log
             WHERE 1=1';
        $params = [];
        if ($dateYmd !== null && $dateYmd !== '') {
            $sql .= ' AND DATE(COALESCE(waktu_masuk, created_at)) = :tanggal';
            $params[':tanggal'] = $dateYmd;
        }
        if ($kegiatan !== null && $kegiatan !== '') {
            $sql .= ' AND kegiatan = :kegiatan';
            $params[':kegiatan'] = $kegiatan;
        }
        $sql .= ' ORDER BY COALESCE(waktu_keluar, waktu_masuk) DESC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }
        $s = trim((string) $value);
        // Waktu dinding lokal dari klien (YYYY-MM-DD HH:MM:SS) — jangan digeser zona.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $s, $m)) {
            return $m[1] . ' ' . $m[2];
        }
        $ts = strtotime($s);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
