<?php

declare(strict_types=1);

/** Koneksi PDO untuk API dashboard ortu. */
function ortu_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $localConfig = __DIR__ . '/database.php';
    if (is_file($localConfig)) {
        $config = require $localConfig;
    } else {
        // Fallback: shared api/config.php di root proyek (jika ada)
        $rootConfig = dirname(__DIR__, 2) . '/api/config.php';
        if (!is_file($rootConfig)) {
            throw new RuntimeException(
                'Konfigurasi database tidak ditemukan. Salin api/database.php.example ke api/database.php.'
            );
        }
        $config = require $rootConfig;
    }

    $db = $config['db'] ?? null;
    if (!is_array($db)) {
        throw new RuntimeException('Konfigurasi db belum diatur (api/database.php).');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) ($db['port'] ?? 3306),
        $db['database'],
        $db['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function ortu_encode_password(string $password): string
{
    return base64_encode($password);
}

function ortu_decode_password(string $encoded): string
{
    $decoded = base64_decode($encoded, true);
    return $decoded === false ? '' : $decoded;
}

function ortu_build_class_info(array $profile): string
{
    $kelas = trim((string) ($profile['kelas'] ?? ''));
    $kelompok = trim((string) ($profile['kelompok'] ?? $profile['jenjang'] ?? ''));
    if ($kelas !== '' && $kelompok !== '') {
        return $kelas . ' - ' . $kelompok;
    }
    return $kelas !== '' ? $kelas : ($kelompok !== '' ? $kelompok : '—');
}
