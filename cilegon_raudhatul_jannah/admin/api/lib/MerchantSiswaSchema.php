<?php

declare(strict_types=1);

/**
 * Kolom tambahan siswa dari respons MobileMerchant (StudentRequest / SIE).
 * Kolom dibuat otomatis (ALTER TABLE) saat sinkron jika belum ada.
 */
final class MerchantSiswaSchema
{
    /** @var array<string, array{sql: string, api: list<string>}> */
    private const FIELDS = [
        'num2nd' => [
            'sql' => 'VARCHAR(64) NULL COMMENT \'NUM2ND SIE\'',
            'api' => ['NUM2ND', 'num2nd'],
        ],
        'stcust' => [
            'sql' => 'VARCHAR(32) NULL COMMENT \'STCUST SIE\'',
            'api' => ['STCUST', 'stcust'],
        ],
        'code01' => [
            'sql' => 'VARCHAR(64) NULL COMMENT \'CODE01 SIE\'',
            'api' => ['CODE01', 'code01'],
        ],
        'desc01' => [
            'sql' => 'VARCHAR(255) NULL COMMENT \'DESC01 SIE\'',
            'api' => ['DESC01', 'desc01'],
        ],
        'code02' => [
            'sql' => 'VARCHAR(128) NULL COMMENT \'CODE02 SIE\'',
            'api' => ['CODE02', 'code02'],
        ],
        'desc02' => [
            'sql' => 'VARCHAR(128) NULL COMMENT \'DESC02 SIE\'',
            'api' => ['DESC02', 'desc02'],
        ],
        'code03' => [
            'sql' => 'VARCHAR(64) NULL COMMENT \'CODE03 SIE\'',
            'api' => ['CODE03', 'code03'],
        ],
        'desc03' => [
            'sql' => 'VARCHAR(128) NULL COMMENT \'DESC03 SIE\'',
            'api' => ['DESC03', 'desc03'],
        ],
        'code04' => [
            'sql' => 'VARCHAR(64) NULL COMMENT \'CODE04 SIE\'',
            'api' => ['CODE04', 'code04'],
        ],
        'desc04' => [
            'sql' => 'VARCHAR(128) NULL COMMENT \'DESC04 SIE\'',
            'api' => ['DESC04', 'desc04'],
        ],
        'code05' => [
            'sql' => 'VARCHAR(64) NULL COMMENT \'CODE05 SIE\'',
            'api' => ['CODE05', 'code05'],
        ],
        'desc05' => [
            'sql' => 'TEXT NULL COMMENT \'DESC05 SIE (alamat)\'',
            'api' => ['DESC05', 'desc05'],
        ],
        'totpay' => [
            'sql' => 'VARCHAR(64) NULL COMMENT \'TOTPAY SIE\'',
            'api' => ['TOTPAY', 'totpay'],
        ],
        'genus' => [
            'sql' => 'VARCHAR(32) NULL COMMENT \'GENUS SIE\'',
            'api' => ['GENUS', 'genus'],
        ],
    ];

    /** @return list<string> */
    public static function columnNames(): array
    {
        return array_keys(self::FIELDS);
    }

    /** @return array<string, array{sql: string, api: list<string>}> */
    public static function fields(): array
    {
        return self::FIELDS;
    }

    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $stmt = $pdo->query('SHOW COLUMNS FROM siswa');
        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $existing[strtolower((string) ($col['Field'] ?? ''))] = true;
        }

        foreach (self::FIELDS as $name => $def) {
            if (isset($existing[$name])) {
                continue;
            }
            $pdo->exec(sprintf(
                'ALTER TABLE siswa ADD COLUMN `%s` %s',
                $name,
                $def['sql']
            ));
        }

        $done = true;
    }

    /**
     * Ambil nilai kolom merchant dari satu baris respons API SIE.
     *
     * @param array<string, mixed> $item
     * @return array<string, string|null>
     */
    public static function extractFromMerchantRow(array $item): array
    {
        $out = [];
        $upper = [];
        foreach ($item as $k => $v) {
            if (is_string($k)) {
                $upper[strtoupper($k)] = $v;
            }
        }

        foreach (self::FIELDS as $dbCol => $def) {
            $value = null;
            foreach ($def['api'] as $apiKey) {
                if (array_key_exists($apiKey, $item)) {
                    $value = $item[$apiKey];
                    break;
                }
                $u = strtoupper($apiKey);
                if (array_key_exists($u, $upper)) {
                    $value = $upper[$u];
                    break;
                }
            }
            if ($value === null) {
                continue;
            }
            $text = trim((string) $value);
            $out[$dbCol] = $text === '' || $text === '-' ? null : $text;
        }

        return $out;
    }
}
