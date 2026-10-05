<?php

declare(strict_types=1);

/**
 * Cek kesehatan API + database. Buka di browser:
 *   /admin/api/health.php
 * Tambah ?setup=1 untuk membuat tabel otomatis.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$result = [
    'ok' => false,
    'php' => PHP_VERSION,
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
    ],
    'config' => false,
    'database' => null,
    'tables' => [],
    'merchant' => null,
];

$configPath = __DIR__ . '/config.php';
if (!is_readable($configPath)) {
    $result['error'] = 'api/config.php tidak ditemukan. Salin dari config.example.php.';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$result['config'] = true;

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Schema.php';

$setup = isset($_GET['setup']) && $_GET['setup'] !== '0';

try {
    $cfg = api_config();
    $dbCfg = $cfg['db'] ?? [];
    $result['database'] = [
        'host' => (string) ($dbCfg['host'] ?? ''),
        'name' => (string) ($dbCfg['name'] ?? ''),
        'user' => (string) ($dbCfg['user'] ?? ''),
    ];

    $pdo = Database::pdo();
    $result['database']['connected'] = true;

    if ($setup) {
        Schema::ensure($pdo);
        $result['database']['migrated'] = true;
    }

    foreach (['siswa', 'presensi_log', 'siswa_rekam_log'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        $result['tables'][$table] = (bool) $stmt->fetchColumn();
    }

    if (isset($_GET['merchant']) && $_GET['merchant'] !== '0') {
        require_once __DIR__ . '/lib/MobileMerchantClient.php';
        $client = MobileMerchantClient::create();
        $raw = $client->studentRequest();
        $count = 0;
        if (is_array($raw)) {
            if (isset($raw['datas']) && is_array($raw['datas'])) {
                $count = count($raw['datas']);
            } elseif (isset($raw[0])) {
                $count = count($raw);
            }
        }
        $result['merchant'] = ['ok' => true, 'rows' => $count];
    }

    $missing = array_keys(array_filter($result['tables'], static function ($exists) {
        return !$exists;
    }));
    if ($missing) {
        $result['error'] = 'Tabel belum ada: ' . implode(', ', $missing) . '. Buka health.php?setup=1';
    } else {
        $result['ok'] = true;
    }
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
