<?php

declare(strict_types=1);

require_once __DIR__ . '/MerchantSiswaSchema.php';

final class SiswaRepository
{
    private const CORE_COLUMNS = [
        'id',
        'nis',
        'nisn',
        'nama',
        'nama_merchant',
        'kelas_id',
        'jenis_kelamin',
        'telepon',
        'aktif',
        'rfid_uid',
        'foto_wajah',
        'kode_suara',
        'merchant_synced_at',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::pdo();
    }

    private function ensureMerchantColumns(): void
    {
        MerchantSiswaSchema::ensure($this->pdo);
    }

    /** @return list<string> */
    private function writableColumns(): array
    {
        return array_merge(self::CORE_COLUMNS, MerchantSiswaSchema::columnNames());
    }

    /** @return array<string, mixed> */
    private function normalizeRow(array $data): array
    {
        $out = [
            'id' => (string) ($data['id'] ?? ''),
            'nis' => trim((string) ($data['nis'] ?? '')),
            'nisn' => (string) ($data['nisn'] ?? ''),
            'nama' => trim((string) ($data['nama'] ?? '')),
            'nama_merchant' => $data['nama_merchant'] ?? null,
            'kelas_id' => (string) ($data['kelas_id'] ?? ''),
            'jenis_kelamin' => in_array($data['jenis_kelamin'] ?? 'L', ['L', 'P'], true)
                ? $data['jenis_kelamin']
                : 'L',
            'telepon' => (string) ($data['telepon'] ?? ''),
            'aktif' => (int) ($data['aktif'] ?? 1),
            'rfid_uid' => (string) ($data['rfid_uid'] ?? ''),
            'foto_wajah' => $data['foto_wajah'] ?? null,
            'kode_suara' => (string) ($data['kode_suara'] ?? ''),
            'merchant_synced_at' => $data['merchant_synced_at'] ?? null,
        ];

        foreach (MerchantSiswaSchema::columnNames() as $col) {
            if (array_key_exists($col, $data)) {
                $out[$col] = $data[$col];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bindParams(array $data): array
    {
        $params = [];
        foreach ($this->writableColumns() as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $params[':' . $col] = $data[$col];
        }
        return $params;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertRow(array $data): void
    {
        $this->ensureMerchantColumns();
        $data = $this->normalizeRow($data);
        $cols = [];
        $placeholders = [];
        foreach ($this->writableColumns() as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $cols[] = '`' . $col . '`';
            $placeholders[] = ':' . $col;
        }
        $sql = 'INSERT INTO siswa (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->pdo->prepare($sql)->execute($this->bindParams($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateRow(string $id, array $data): void
    {
        $this->ensureMerchantColumns();
        $data = $this->normalizeRow(array_merge($data, ['id' => $id]));
        $sets = [];
        foreach ($this->writableColumns() as $col) {
            if ($col === 'id' || !array_key_exists($col, $data)) {
                continue;
            }
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $sql = 'UPDATE siswa SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($this->bindParams($data));
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM siswa ORDER BY nama ASC, nis ASC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = $this->rowToApi($row);
        }
        return $rows;
    }

    /**
     * Daftar siswa untuk tabel admin TANPA kolom foto_wajah (base64).
     * Ini mengecilkan payload secara drastis (foto diambil terpisah saat dibutuhkan).
     *
     * @return list<array<string, mixed>>
     */
    public function listAllLight(): array
    {
        $sie = MerchantSiswaSchema::columnNames();
        $base = [
            'id', 'nis', 'nisn', 'nama', 'nama_merchant', 'kelas_id', 'jenis_kelamin',
            'telepon', 'aktif', 'rfid_uid', 'kode_suara', 'merchant_synced_at',
        ];
        $cols = array_merge($base, $sie);
        $select = implode(', ', array_map(static function ($c) {
            return '`' . $c . '`';
        }, $cols));

        $stmt = $this->pdo->query(
            'SELECT ' . $select . ',
                    CASE WHEN foto_wajah IS NOT NULL AND CHAR_LENGTH(foto_wajah) > 30 THEN 1 ELSE 0 END AS has_foto
             FROM siswa ORDER BY nama ASC, nis ASC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $api = $this->rowToApi($row); // foto_wajah tidak ada → fotoWajah ''
            $hasFoto = (bool) (int) ($row['has_foto'] ?? 0);
            $api['fotoWajah'] = '';
            $api['hasFoto'] = $hasFoto;
            $api['_hasFoto'] = $hasFoto;
            $rows[] = $api;
        }
        return $rows;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn();
    }

    /** Daftar ringkas untuk halaman rekam (tanpa base64 foto — hemat bandwidth). */
    public function listRekamSummary(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, nis, nisn, nama, kelas_id, jenis_kelamin, aktif, rfid_uid, kode_suara,
                    code02, desc02, desc03, desc04,
                    CASE WHEN foto_wajah IS NOT NULL AND CHAR_LENGTH(foto_wajah) > 30 THEN 1 ELSE 0 END AS has_foto
             FROM siswa ORDER BY nama ASC, nis ASC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'nis' => (string) $row['nis'],
                'nisn' => (string) ($row['nisn'] ?? ''),
                'nama' => (string) $row['nama'],
                'kelasId' => (string) ($row['kelas_id'] ?? ''),
                'jenisKelamin' => (string) ($row['jenis_kelamin'] ?? 'L'),
                'aktif' => (bool) (int) ($row['aktif'] ?? 1),
                'rfidUid' => (string) ($row['rfid_uid'] ?? ''),
                'kodeSuara' => (string) ($row['kode_suara'] ?? ''),
                'code02' => (string) ($row['code02'] ?? ''),
                'desc02' => (string) ($row['desc02'] ?? ''),
                'desc03' => (string) ($row['desc03'] ?? ''),
                'desc04' => (string) ($row['desc04'] ?? ''),
                'fotoWajah' => '',
                'hasFoto' => (bool) (int) ($row['has_foto'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Daftar siswa aktif yang punya foto wajah, lengkap dengan base64.
     * Dipakai modul Face Detector untuk membangun referensi pengenalan wajah.
     */
    public function listWithFoto(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, nis, nisn, nama, kelas_id, jenis_kelamin, aktif, foto_wajah
             FROM siswa
             WHERE foto_wajah IS NOT NULL AND CHAR_LENGTH(foto_wajah) > 30 AND aktif = 1
             ORDER BY nama ASC, nis ASC'
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'nis' => (string) $row['nis'],
                'nisn' => (string) ($row['nisn'] ?? ''),
                'nama' => (string) $row['nama'],
                'kelasId' => (string) ($row['kelas_id'] ?? ''),
                'jenisKelamin' => (string) ($row['jenis_kelamin'] ?? 'L'),
                'aktif' => (bool) (int) ($row['aktif'] ?? 1),
                'fotoWajah' => (string) ($row['foto_wajah'] ?? ''),
                'hasFoto' => true,
            ];
        }

        return $rows;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM siswa WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->rowToApi($row) : null;
    }

    /** Cari siswa by id kolom, prefix mm-{nis}, atau NIS numerik. */
    public function findByIdOrNis(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        $row = $this->findById($key);
        if ($row) {
            return $row;
        }
        if (preg_match('/^mm-(.+)$/i', $key, $m)) {
            $row = $this->findByNis($m[1]);
            if ($row) {
                return $row;
            }
        }
        if (preg_match('/^\d+$/', $key)) {
            return $this->findByNis($key);
        }

        return null;
    }

    public function findByNis(string $nis): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM siswa WHERE nis = ? LIMIT 1');
        $stmt->execute([trim($nis)]);
        $row = $stmt->fetch();
        return $row ? $this->rowToApi($row) : null;
    }

    public function findByNisn(string $nisn): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM siswa WHERE nisn = ? LIMIT 1');
        $stmt->execute([trim($nisn)]);
        $row = $stmt->fetch();
        return $row ? $this->rowToApi($row) : null;
    }

    public function findByNimOrNisn(string $nim): ?array
    {
        $nim = preg_replace('/\s+/', '', trim($nim));
        if ($nim === '') {
            return null;
        }

        $row = $this->findByNis($nim);
        if ($row) {
            return $row;
        }

        $row = $this->findByNisn($nim);
        if ($row) {
            return $row;
        }

        $row = $this->findById('mm-' . $nim);
        if ($row) {
            return $row;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM siswa
             WHERE REPLACE(TRIM(nis), " ", "") = ?
                OR REPLACE(TRIM(nisn), " ", "") = ?
             LIMIT 1'
        );
        $stmt->execute([$nim, $nim]);
        $found = $stmt->fetch();
        return $found ? $this->rowToApi($found) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): array
    {
        $this->insertRow($data);
        return $this->findById((string) $data['id']) ?? $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): array
    {
        $this->updateRow($id, $data);
        return $this->findById($id) ?? $data;
    }

    /**
     * Set kolom aktif saja (0/1). Dipakai toggle di Data siswa.
     *
     * @param mixed $aktif
     */
    public function setAktif(string $id, $aktif): array
    {
        $val = self::normalizeAktifFlag($aktif);
        $stmt = $this->pdo->prepare('UPDATE siswa SET aktif = ? WHERE id = ? LIMIT 1');
        $stmt->execute([$val, $id]);
        $row = $this->findById($id);
        if (!$row) {
            throw new RuntimeException('Siswa tidak ditemukan setelah update aktif');
        }
        return $row;
    }

    /** @param mixed $v */
    public static function normalizeAktifFlag($v): int
    {
        if ($v === false || $v === 0 || $v === '0' || $v === 'false' || $v === 'tidak' || $v === 'Tidak') {
            return 0;
        }
        if ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'ya' || $v === 'Ya') {
            return 1;
        }
        return !empty($v) ? 1 : 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM siswa WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function clearFotoWajahByNis(string $nis): ?array
    {
        $nis = trim($nis);
        if ($nis === '') {
            return null;
        }

        $existing = $this->findByNis($nis);
        if (!$existing) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE siswa SET foto_wajah = NULL WHERE nis = ? LIMIT 1'
        );
        $stmt->execute([$nis]);

        $this->pdo->prepare(
            'INSERT INTO siswa_rekam_log (siswa_id, nis, jenis, rfid_uid, punya_foto, kode_suara)
             VALUES (:siswa_id, :nis, :jenis, :rfid_uid, 0, :kode_suara)'
        )->execute([
            ':siswa_id' => (string) $existing['id'],
            ':nis' => $nis,
            ':jenis' => 'foto',
            ':rfid_uid' => $existing['rfidUid'] ?? null,
            ':kode_suara' => $existing['kodeSuara'] ?? null,
        ]);

        return $this->findByNis($nis);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function replaceAll(array $rows): array
    {
        // Simpan foto lama (indeks per NIS) supaya tidak hilang saat payload
        // tidak menyertakan base64 foto (daftar ringan tanpa foto_wajah).
        $existingFoto = [];
        $stmt = $this->pdo->query(
            'SELECT nis, foto_wajah FROM siswa
             WHERE foto_wajah IS NOT NULL AND CHAR_LENGTH(foto_wajah) > 30'
        );
        foreach ($stmt->fetchAll() as $r) {
            $existingFoto[(string) ($r['nis'] ?? '')] = (string) ($r['foto_wajah'] ?? '');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM siswa');
            foreach ($rows as $row) {
                $db = $this->apiToDb($row);
                $foto = (string) ($db['foto_wajah'] ?? '');
                if (strlen($foto) <= 30) {
                    $nis = (string) ($db['nis'] ?? '');
                    if ($nis !== '' && isset($existingFoto[$nis])) {
                        $db['foto_wajah'] = $existingFoto[$nis];
                    }
                }
                $this->insert($db);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $this->listAllLight();
    }

    /**
     * @param list<array<string, mixed>> $merchantRows
     * @return array{inserted: int, updated: int, total: int, columns: list<string>}
     */
    public function syncFromMerchant(array $merchantRows): array
    {
        $this->ensureMerchantColumns();

        $inserted = 0;
        $updated = 0;
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            foreach ($merchantRows as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nis = trim((string) ($item['NIS'] ?? $item['nis'] ?? ''));
                $namaMerchant = trim((string) ($item['NamaCust'] ?? $item['nama'] ?? ''));
                if ($nis === '' || $namaMerchant === '') {
                    continue;
                }

                $merchantExtra = MerchantSiswaSchema::extractFromMerchantRow($item);
                $kelasLabel = (string) ($merchantExtra['desc03'] ?? $merchantExtra['desc02'] ?? '');

                $existing = $this->findByNis($nis);
                if ($existing) {
                    $dbRow = $this->apiToDb($existing);
                    $dbRow['nama'] = $namaMerchant;
                    $dbRow['nama_merchant'] = $namaMerchant;
                    $dbRow['merchant_synced_at'] = $now;
                    if ($kelasLabel !== '') {
                        $dbRow['kelas_id'] = $kelasLabel;
                    }
                    foreach ($merchantExtra as $col => $val) {
                        $dbRow[$col] = $val;
                    }
                    $this->update((string) $existing['id'], $dbRow);
                    $updated++;
                } else {
                    $dbRow = array_merge([
                        'id' => 'mm-' . $nis,
                        'nis' => $nis,
                        'nisn' => '',
                        'nama' => $namaMerchant,
                        'nama_merchant' => $namaMerchant,
                        'kelas_id' => $kelasLabel,
                        'jenis_kelamin' => 'L',
                        'telepon' => '',
                        'aktif' => 1,
                        'rfid_uid' => '',
                        'foto_wajah' => null,
                        'kode_suara' => '',
                        'merchant_synced_at' => $now,
                    ], $merchantExtra);
                    $this->insert($dbRow);
                    $inserted++;
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'total' => $this->countAll(),
            'columns' => MerchantSiswaSchema::columnNames(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function rowToApi(array $row): array
    {
        $api = [
            'id' => (string) $row['id'],
            'nis' => (string) $row['nis'],
            'nisn' => (string) ($row['nisn'] ?? ''),
            'nama' => (string) $row['nama'],
            'namaMerchant' => (string) ($row['nama_merchant'] ?? ''),
            'kelasId' => (string) ($row['kelas_id'] ?? ''),
            'jenisKelamin' => (string) ($row['jenis_kelamin'] ?? 'L'),
            'telepon' => (string) ($row['telepon'] ?? ''),
            'aktif' => (bool) (int) ($row['aktif'] ?? 1),
            'rfidUid' => (string) ($row['rfid_uid'] ?? ''),
            'fotoWajah' => (string) ($row['foto_wajah'] ?? ''),
            'kodeSuara' => (string) ($row['kode_suara'] ?? ''),
            'merchantSyncedAt' => $row['merchant_synced_at'] ?? null,
        ];

        foreach (MerchantSiswaSchema::columnNames() as $col) {
            $api[$col] = array_key_exists($col, $row) && $row[$col] !== null
                ? (string) $row[$col]
                : '';
        }

        if ($api['desc03'] === '' && $api['kelasId'] !== '') {
            $api['desc03'] = $api['kelasId'];
        }

        return $api;
    }

    /**
     * @param array<string, mixed> $api
     * @return array<string, mixed>
     */
    public function apiToDb(array $api): array
    {
        $id = (string) ($api['id'] ?? '');
        if ($id === '') {
            $nis = trim((string) ($api['nis'] ?? ''));
            $id = $nis !== '' ? 'mm-' . $nis : uniqid('s-', true);
        }

        $db = [
            'id' => $id,
            'nis' => trim((string) ($api['nis'] ?? '')),
            'nisn' => (string) ($api['nisn'] ?? ''),
            'nama' => trim((string) ($api['nama'] ?? '')),
            'nama_merchant' => (string) ($api['namaMerchant'] ?? $api['nama_merchant'] ?? $api['nama'] ?? ''),
            'kelas_id' => (string) ($api['kelasId'] ?? $api['kelas_id'] ?? ''),
            'jenis_kelamin' => in_array($api['jenisKelamin'] ?? 'L', ['L', 'P'], true) ? $api['jenisKelamin'] : 'L',
            'telepon' => (string) ($api['telepon'] ?? ''),
            'aktif' => self::normalizeAktifFlag($api['aktif'] ?? 1),
            'rfid_uid' => (string) ($api['rfidUid'] ?? $api['rfid_uid'] ?? ''),
            'foto_wajah' => $api['fotoWajah'] ?? $api['foto_wajah'] ?? null,
            'kode_suara' => (string) ($api['kodeSuara'] ?? $api['kode_suara'] ?? ''),
            'merchant_synced_at' => $api['merchantSyncedAt'] ?? $api['merchant_synced_at'] ?? null,
        ];

        foreach (MerchantSiswaSchema::columnNames() as $col) {
            if (array_key_exists($col, $api)) {
                $db[$col] = $api[$col] === '' ? null : $api[$col];
            }
        }

        return $db;
    }
}
