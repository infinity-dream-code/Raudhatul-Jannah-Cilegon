<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

final class OrtuMultiAccountService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? ortu_db();
    }

    /** @param array<string, mixed> $profile */
    public function upsertAkun(string $username, string $password, array $profile): int
    {
        $user = ortu_norm_username($username);
        if ($user === '') {
            throw new InvalidArgumentException('Username tidak valid.');
        }

        $existing = $this->findAkunByUsername($user);
        $classInfo = ortu_build_class_info($profile);
        $params = [
            'student_username' => $user,
            'student_nis' => $user,
            'student_name' => trim((string) ($profile['nama'] ?? 'Siswa')),
            'class_info' => $classInfo,
            'unit_name' => trim((string) ($profile['unit'] ?? '')),
            'kelompok' => trim((string) ($profile['kelompok'] ?? $profile['jenjang'] ?? '')),
            'va_spp' => trim((string) ($profile['vaSpp'] ?? '')),
            'va_du' => trim((string) ($profile['vaDu'] ?? '')),
            'password_enc' => ortu_encode_password($password),
            'last_login_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $sql = 'UPDATE ortu_akun_anak SET
                student_nis = :student_nis,
                student_name = :student_name,
                class_info = :class_info,
                unit_name = :unit_name,
                kelompok = :kelompok,
                va_spp = :va_spp,
                va_du = :va_du,
                password_enc = :password_enc,
                last_login_at = :last_login_at
                WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $params['id'] = (int) $existing['id'];
            $stmt->execute($params);
            return (int) $existing['id'];
        }

        $sql = 'INSERT INTO ortu_akun_anak
            (student_username, student_nis, student_name, class_info, unit_name, kelompok, va_spp, va_du, password_enc, last_login_at)
            VALUES
            (:student_username, :student_nis, :student_name, :class_info, :unit_name, :kelompok, :va_spp, :va_du, :password_enc, :last_login_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findAkunByUsername(string $username): ?array
    {
        $user = ortu_norm_username($username);
        $stmt = $this->pdo->prepare('SELECT * FROM ortu_akun_anak WHERE student_username = ? LIMIT 1');
        $stmt->execute([$user]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findKelompokByAkunId(int $akunId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ortu_akun_kelompok
             WHERE akun_1_id = ? OR akun_2_id = ? OR akun_3_id = ?
             LIMIT 1'
        );
        $stmt->execute([$akunId, $akunId, $akunId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<int> */
    public function collectAkunIdsFromKelompok(array $kelompok): array
    {
        $ids = [];
        foreach (['akun_1_id', 'akun_2_id', 'akun_3_id'] as $col) {
            if (!empty($kelompok[$col])) {
                $ids[] = (int) $kelompok[$col];
            }
        }
        return array_values(array_unique($ids));
    }

    /** @return list<array<string, mixed>> */
    public function getAkunByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM ortu_akun_anak WHERE id IN ($placeholders) ORDER BY id ASC");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function getLinkedAccounts(string $username): array
    {
        $akun = $this->findAkunByUsername($username);
        if (!$akun) {
            return [];
        }

        $kelompok = $this->findKelompokByAkunId((int) $akun['id']);
        if (!$kelompok) {
            return [];
        }

        $ids = $this->collectAkunIdsFromKelompok($kelompok);
        $rows = $this->getAkunByIds($ids);
        $activeUser = ortu_norm_username($username);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->formatAkunRow(
                $row,
                ortu_norm_username((string) $row['student_username']) === $activeUser
            );
        }
        return $result;
    }

    /** @param array<string, mixed> $profile */
    public function saveAndLink(string $username, string $password, array $profile, ?string $linkWithUsername = null): array
    {
        $akunId = $this->upsertAkun($username, $password, $profile);
        $linkWith = $linkWithUsername ? ortu_norm_username($linkWithUsername) : '';
        $currentUser = ortu_norm_username($username);

        if ($linkWith !== '' && $linkWith !== $currentUser) {
            $this->attachToKelompok($linkWith, $currentUser);
        } elseif (!$this->findKelompokByAkunId($akunId)) {
            $this->ensureSoloKelompok($akunId);
        }

        return $this->getLinkedAccounts($currentUser);
    }

    /** Hubungkan akun baru ke kelompok akun anchor (isi akun_2_id / akun_3_id). */
    public function attachToKelompok(string $anchorUsername, string $newUsername): void
    {
        $akunAnchor = $this->findAkunByUsername($anchorUsername);
        $akunNew = $this->findAkunByUsername($newUsername);
        if (!$akunAnchor || !$akunNew) {
            throw new RuntimeException('Akun tidak ditemukan untuk dihubungkan.');
        }

        $idAnchor = (int) $akunAnchor['id'];
        $idNew = (int) $akunNew['id'];
        if ($idAnchor === $idNew) {
            return;
        }

        $groupAnchor = $this->findKelompokByAkunId($idAnchor);
        $groupNew = $this->findKelompokByAkunId($idNew);

        if ($groupAnchor && $groupNew && (int) $groupAnchor['id'] === (int) $groupNew['id']) {
            return;
        }

        if ($groupNew && (!$groupAnchor || (int) $groupNew['id'] !== (int) $groupAnchor['id'])) {
            $idsNew = $this->collectAkunIdsFromKelompok($groupNew);
            if (count($idsNew) === 1 && $idsNew[0] === $idNew) {
                $this->pdo->prepare('DELETE FROM ortu_akun_kelompok WHERE id = ?')
                    ->execute([(int) $groupNew['id']]);
                $groupNew = null;
            } elseif ($groupAnchor) {
                $this->mergeKelompok($groupAnchor, $groupNew);

                return;
            }
        }

        if (!$groupAnchor) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ortu_akun_kelompok (akun_1_id, akun_2_id) VALUES (?, ?)'
            );
            $stmt->execute([$idAnchor, $idNew]);

            return;
        }

        $ids = $this->collectAkunIdsFromKelompok($groupAnchor);
        if (in_array($idNew, $ids, true)) {
            return;
        }

        if ($this->isKelompokSlotEmpty($groupAnchor, 'akun_2_id')) {
            $stmt = $this->pdo->prepare('UPDATE ortu_akun_kelompok SET akun_2_id = ? WHERE id = ?');
            $stmt->execute([$idNew, (int) $groupAnchor['id']]);

            return;
        }
        if ($this->isKelompokSlotEmpty($groupAnchor, 'akun_3_id')) {
            $stmt = $this->pdo->prepare('UPDATE ortu_akun_kelompok SET akun_3_id = ? WHERE id = ?');
            $stmt->execute([$idNew, (int) $groupAnchor['id']]);

            return;
        }

        throw new RuntimeException('Kelompok multi akun sudah penuh (maksimal 3 akun).');
    }

    /** @param array<string, mixed> $kelompok */
    private function isKelompokSlotEmpty(array $kelompok, string $column): bool
    {
        if (!array_key_exists($column, $kelompok)) {
            return true;
        }
        $value = $kelompok[$column];

        return $value === null || $value === '' || (int) $value === 0;
    }

    /** Upsert profil akun tanpa mengubah relasi kelompok. */
    /** @param array<string, mixed> $profile */
    public function syncAccount(string $username, string $password, array $profile): array
    {
        $currentUser = ortu_norm_username($username);
        $this->upsertAkun($username, $password, $profile);

        return $this->getLinkedAccounts($currentUser);
    }

    private function ensureSoloKelompok(int $akunId): void
    {
        if ($this->findKelompokByAkunId($akunId)) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO ortu_akun_kelompok (akun_1_id) VALUES (?)');
        $stmt->execute([$akunId]);
    }

    private function linkTwoAccounts(string $usernameA, string $usernameB): void
    {
        $akunA = $this->findAkunByUsername($usernameA);
        $akunB = $this->findAkunByUsername($usernameB);
        if (!$akunA || !$akunB) {
            throw new RuntimeException('Akun tidak ditemukan untuk dihubungkan.');
        }

        $idA = (int) $akunA['id'];
        $idB = (int) $akunB['id'];
        if ($idA === $idB) {
            return;
        }

        $groupA = $this->findKelompokByAkunId($idA);
        $groupB = $this->findKelompokByAkunId($idB);

        if ($groupA && $groupB && (int) $groupA['id'] !== (int) $groupB['id']) {
            $this->mergeKelompok($groupA, $groupB);
            return;
        }

        $group = $groupA ?: $groupB;
        if (!$group) {
            $stmt = $this->pdo->prepare('INSERT INTO ortu_akun_kelompok (akun_1_id, akun_2_id) VALUES (?, ?)');
            $stmt->execute([$idA, $idB]);
            return;
        }

        $ids = $this->collectAkunIdsFromKelompok($group);
        if (in_array($idA, $ids, true) && in_array($idB, $ids, true)) {
            return;
        }

        $toAdd = in_array($idA, $ids, true) ? $idB : $idA;
        if (in_array($toAdd, $ids, true)) {
            return;
        }

        if (empty($group['akun_2_id'])) {
            $stmt = $this->pdo->prepare('UPDATE ortu_akun_kelompok SET akun_2_id = ? WHERE id = ?');
            $stmt->execute([$toAdd, (int) $group['id']]);
            return;
        }
        if (empty($group['akun_3_id'])) {
            $stmt = $this->pdo->prepare('UPDATE ortu_akun_kelompok SET akun_3_id = ? WHERE id = ?');
            $stmt->execute([$toAdd, (int) $group['id']]);
            return;
        }

        throw new RuntimeException('Kelompok multi akun sudah penuh (maksimal 3 akun).');
    }

    /** @param array<string, mixed> $primary @param array<string, mixed> $secondary */
    private function mergeKelompok(array $primary, array $secondary): void
    {
        $ids = array_unique(array_merge(
            $this->collectAkunIdsFromKelompok($primary),
            $this->collectAkunIdsFromKelompok($secondary)
        ));
        if (count($ids) > 3) {
            throw new RuntimeException('Gabungan akun melebihi batas 3 akun per kelompok.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM ortu_akun_kelompok WHERE id = ?');
        $stmt->execute([(int) $secondary['id']]);

        $stmt = $this->pdo->prepare(
            'UPDATE ortu_akun_kelompok SET akun_1_id = ?, akun_2_id = ?, akun_3_id = ? WHERE id = ?'
        );
        $stmt->execute([
            $ids[0] ?? null,
            $ids[1] ?? null,
            $ids[2] ?? null,
            (int) $primary['id'],
        ]);
    }

    public function deleteSavedAccount(string $targetUsername): void
    {
        $target = $this->findAkunByUsername($targetUsername);
        if (!$target) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM ortu_akun_anak WHERE id = ?');
        $stmt->execute([(int) $target['id']]);
    }

    /** @deprecated use deleteSavedAccount */
    public function removeFromGroup(string $currentUsername, string $targetUsername): array
    {
        $this->deleteSavedAccount($targetUsername);
        return $this->getLinkedAccounts($currentUsername);
    }

    private function cleanupEmptyKelompok(int $kelompokId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ortu_akun_kelompok WHERE id = ?');
        $stmt->execute([$kelompokId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $filled = 0;
        foreach (['akun_1_id', 'akun_2_id', 'akun_3_id'] as $col) {
            if (!empty($row[$col])) {
                $filled++;
            }
        }
        if ($filled === 0) {
            $this->pdo->prepare('DELETE FROM ortu_akun_kelompok WHERE id = ?')->execute([$kelompokId]);
        }
    }

    /** @param array<string, mixed> $row */
    private function formatAkunRow(array $row, bool $isActive): array
    {
        return [
            'id' => (int) $row['id'],
            'studentUsername' => (string) $row['student_username'],
            'studentNis' => (string) $row['student_nis'],
            'studentName' => (string) $row['student_name'],
            'classInfo' => (string) ($row['class_info'] ?? '—'),
            'unitName' => (string) ($row['unit_name'] ?? ''),
            'kelompok' => (string) ($row['kelompok'] ?? ''),
            'vaSpp' => (string) ($row['va_spp'] ?? ''),
            'vaDu' => (string) ($row['va_du'] ?? ''),
            'password' => ortu_decode_password((string) $row['password_enc']),
            'isActive' => $isActive,
            'lastLoginAt' => $row['last_login_at'] ?? null,
        ];
    }
}
