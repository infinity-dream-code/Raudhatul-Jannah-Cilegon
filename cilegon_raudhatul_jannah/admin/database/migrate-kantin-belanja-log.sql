-- Migrasi: log belanja FacePay kantin
-- Database: cilegon_raudhatul_jannah (atau sesuai config.php)

USE cilegon_raudhatul_jannah;

CREATE TABLE IF NOT EXISTS kantin_belanja_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  log_uid VARCHAR(64) NOT NULL,
  siswa_id VARCHAR(64) NOT NULL DEFAULT '',
  nis VARCHAR(32) NOT NULL DEFAULT '',
  nama_siswa VARCHAR(255) NOT NULL DEFAULT '',
  nominal INT NOT NULL DEFAULT 0,
  saldo_sebelum INT NULL,
  saldo_sesudah INT NULL,
  nama_kantin VARCHAR(128) NOT NULL DEFAULT '',
  display_name_kantin VARCHAR(255) NOT NULL DEFAULT '',
  metode VARCHAR(64) NOT NULL DEFAULT 'facepay',
  merchant_status VARCHAR(64) NOT NULL DEFAULT '',
  merchant_raw TEXT NULL,
  trx_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_kantin_belanja_log_uid (log_uid),
  KEY idx_kantin_belanja_kantin_trx (nama_kantin, trx_at),
  KEY idx_kantin_belanja_nis (nis),
  KEY idx_kantin_belanja_siswa (siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
