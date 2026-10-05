-- Database: malang_artri_face
-- User: malang_artri_face / malang_artri_face @ localhost
-- Jalankan sebagai root MySQL/MariaDB, lalu import lewat phpMyAdmin jika perlu.

CREATE DATABASE IF NOT EXISTS malang_artri_face
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'malang_artri_face'@'localhost' IDENTIFIED BY 'malang_artri_face';
GRANT ALL PRIVILEGES ON malang_artri_face.* TO 'malang_artri_face'@'localhost';
FLUSH PRIVILEGES;

USE malang_artri_face;

-- Master siswa (sinkron dari MobileMerchant + data lokal rekam/presensi)
CREATE TABLE IF NOT EXISTS siswa (
  id VARCHAR(64) NOT NULL,
  nis VARCHAR(32) NOT NULL,
  nisn VARCHAR(32) NOT NULL DEFAULT '',
  nama VARCHAR(255) NOT NULL,
  nama_merchant VARCHAR(255) DEFAULT NULL COMMENT 'NamaCust dari API',
  kelas_id VARCHAR(64) NOT NULL DEFAULT '',
  jenis_kelamin CHAR(1) NOT NULL DEFAULT 'L',
  telepon VARCHAR(32) NOT NULL DEFAULT '',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  rfid_uid VARCHAR(64) NOT NULL DEFAULT '',
  foto_wajah LONGTEXT NULL,
  kode_suara VARCHAR(512) NOT NULL DEFAULT '',
  merchant_synced_at DATETIME NULL,
  num2nd VARCHAR(64) NULL COMMENT 'NUM2ND SIE',
  stcust VARCHAR(32) NULL COMMENT 'STCUST SIE',
  code01 VARCHAR(64) NULL COMMENT 'CODE01 SIE',
  desc01 VARCHAR(255) NULL COMMENT 'DESC01 SIE',
  code02 VARCHAR(128) NULL COMMENT 'CODE02 SIE',
  desc02 VARCHAR(128) NULL COMMENT 'DESC02 SIE',
  code03 VARCHAR(64) NULL COMMENT 'CODE03 SIE',
  desc03 VARCHAR(128) NULL COMMENT 'DESC03 SIE',
  code04 VARCHAR(64) NULL COMMENT 'CODE04 SIE',
  desc04 VARCHAR(128) NULL COMMENT 'DESC04 SIE',
  code05 VARCHAR(64) NULL COMMENT 'CODE05 SIE',
  desc05 TEXT NULL COMMENT 'DESC05 SIE',
  totpay VARCHAR(64) NULL COMMENT 'TOTPAY SIE',
  genus VARCHAR(32) NULL COMMENT 'GENUS SIE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_siswa_nis (nis),
  KEY idx_siswa_aktif (aktif),
  KEY idx_siswa_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log presensi masuk/keluar (modul wajah)
CREATE TABLE IF NOT EXISTS presensi_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  log_uid VARCHAR(64) NOT NULL,
  siswa_id VARCHAR(64) NOT NULL,
  nis VARCHAR(32) NOT NULL DEFAULT '',
  nama VARCHAR(255) NOT NULL DEFAULT '',
  unit_label VARCHAR(255) NOT NULL DEFAULT '',
  kelas_label VARCHAR(255) NOT NULL DEFAULT '',
  kegiatan VARCHAR(255) NOT NULL DEFAULT '',
  metode VARCHAR(64) NOT NULL DEFAULT 'face recognition',
  waktu_masuk DATETIME NULL,
  waktu_keluar DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_presensi_log_uid (log_uid),
  KEY idx_presensi_siswa (siswa_id),
  KEY idx_presensi_waktu_masuk (waktu_masuk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riwayat penyimpanan rekam RFID / foto / kode suara
CREATE TABLE IF NOT EXISTS siswa_rekam_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id VARCHAR(64) NOT NULL,
  nis VARCHAR(32) NOT NULL DEFAULT '',
  jenis ENUM('rfid','foto','suara','lengkap') NOT NULL DEFAULT 'lengkap',
  rfid_uid VARCHAR(64) DEFAULT NULL,
  punya_foto TINYINT(1) NOT NULL DEFAULT 0,
  kode_suara VARCHAR(512) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rekam_siswa (siswa_id),
  KEY idx_rekam_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log belanja FacePay kantin (saldo sebelum / sesudah)
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

-- Akun admin (login panel admin)
CREATE TABLE IF NOT EXISTS admin_user (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  nama VARCHAR(255) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'admin',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed admin default: username = admin, password = admin123
-- Hash di bawah dibuat dengan password_hash('admin123', PASSWORD_BCRYPT).
-- Backend juga otomatis menanam akun ini bila tabel kosong (AdminAuth::seedDefault()).
INSERT INTO admin_user (username, nama, password_hash, role, aktif)
SELECT 'admin', 'Administrator',
       '$2y$10$N1O/jV5dZNHjnKBdEbD5h.DpfBR7nvUYLQOl6RR.3oJQGYNF60Cm2', 'admin', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_user WHERE username = 'admin');
