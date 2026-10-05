<?php

declare(strict_types=1);

final class Schema
{
    /** Buat tabel inti bila belum ada (aman dijalankan berulang). */
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS siswa (
              id VARCHAR(64) NOT NULL,
              nis VARCHAR(32) NOT NULL,
              nisn VARCHAR(32) NOT NULL DEFAULT "",
              nama VARCHAR(255) NOT NULL,
              nama_merchant VARCHAR(255) DEFAULT NULL,
              kelas_id VARCHAR(64) NOT NULL DEFAULT "",
              jenis_kelamin CHAR(1) NOT NULL DEFAULT "L",
              telepon VARCHAR(32) NOT NULL DEFAULT "",
              aktif TINYINT(1) NOT NULL DEFAULT 1,
              rfid_uid VARCHAR(64) NOT NULL DEFAULT "",
              foto_wajah LONGTEXT NULL,
              kode_suara VARCHAR(512) NOT NULL DEFAULT "",
              merchant_synced_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uk_siswa_nis (nis),
              KEY idx_siswa_aktif (aktif),
              KEY idx_siswa_nama (nama)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        require_once __DIR__ . '/MerchantSiswaSchema.php';
        MerchantSiswaSchema::ensure($pdo);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS presensi_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              log_uid VARCHAR(64) NOT NULL,
              siswa_id VARCHAR(64) NOT NULL,
              nis VARCHAR(32) NOT NULL DEFAULT "",
              nama VARCHAR(255) NOT NULL DEFAULT "",
              unit_label VARCHAR(255) NOT NULL DEFAULT "",
              kelas_label VARCHAR(255) NOT NULL DEFAULT "",
              kegiatan VARCHAR(255) NOT NULL DEFAULT "",
              metode VARCHAR(64) NOT NULL DEFAULT "face recognition",
              waktu_masuk DATETIME NULL,
              waktu_keluar DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uk_presensi_log_uid (log_uid),
              KEY idx_presensi_siswa (siswa_id),
              KEY idx_presensi_waktu_masuk (waktu_masuk)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS siswa_rekam_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              siswa_id VARCHAR(64) NOT NULL,
              nis VARCHAR(32) NOT NULL DEFAULT "",
              jenis ENUM("rfid","foto","suara","lengkap") NOT NULL DEFAULT "lengkap",
              rfid_uid VARCHAR(64) DEFAULT NULL,
              punya_foto TINYINT(1) NOT NULL DEFAULT 0,
              kode_suara VARCHAR(512) DEFAULT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_rekam_siswa (siswa_id),
              KEY idx_rekam_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS kantin_belanja_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              log_uid VARCHAR(64) NOT NULL,
              siswa_id VARCHAR(64) NOT NULL DEFAULT "",
              nis VARCHAR(32) NOT NULL DEFAULT "",
              nama_siswa VARCHAR(255) NOT NULL DEFAULT "",
              nominal INT NOT NULL DEFAULT 0,
              saldo_sebelum INT NULL,
              saldo_sesudah INT NULL,
              nama_kantin VARCHAR(128) NOT NULL DEFAULT "",
              display_name_kantin VARCHAR(255) NOT NULL DEFAULT "",
              metode VARCHAR(64) NOT NULL DEFAULT "facepay",
              merchant_status VARCHAR(64) NOT NULL DEFAULT "",
              merchant_raw TEXT NULL,
              trx_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uk_kantin_belanja_log_uid (log_uid),
              KEY idx_kantin_belanja_kantin_trx (nama_kantin, trx_at),
              KEY idx_kantin_belanja_nis (nis),
              KEY idx_kantin_belanja_siswa (siswa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Akun admin (login). Seed default ditanam oleh AdminAuth::seedDefault().
        if (is_readable(__DIR__ . '/AdminAuth.php')) {
            require_once __DIR__ . '/AdminAuth.php';
            AdminAuth::ensureSchema($pdo);
            AdminAuth::seedDefault($pdo);
        }
    }
}
