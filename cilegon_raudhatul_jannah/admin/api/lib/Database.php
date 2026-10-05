<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = api_config()['db'] ?? [];
        $host = $cfg['host'] ?? 'localhost';
        $name = $cfg['name'] ?? '';
        $user = $cfg['user'] ?? '';
        $pass = $cfg['pass'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        if ($name === '') {
            throw new RuntimeException('Nama database kosong. Periksa api/config.php');
        }

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Koneksi database gagal (' . $host . '/' . $name . '): ' . $e->getMessage(),
                0,
                $e
            );
        }

        if (!empty($cfg['auto_migrate'])) {
            require_once __DIR__ . '/Schema.php';
            Schema::ensure(self::$pdo);
        }

        return self::$pdo;
    }
}
