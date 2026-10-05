-- Dashboard Orang Tua — multi account (2 tabel)
-- Table 1: data lengkap akun anak (NIS + password)
-- Table 2: relasi kelompok max 3 akun (akun_1_id, akun_2_id, akun_3_id)
--
-- Contoh: login NIS 123, tambah NIS 345
--   ortu_akun_anak  → baris id=1 (123), id=2 (345)
--   ortu_akun_kelompok → akun_1_id=1, akun_2_id=2
--
-- Jalankan di Navicat (sesuaikan nama database).

SET NAMES utf8mb4;

-- USE alhanif_portal;
-- USE cilegon_alhanif_portal;

/* Hapus skema lama jika pernah dibuat */
DROP TABLE IF EXISTS ortu_multi_account;
DROP TABLE IF EXISTS ortu_akun_kelompok;
DROP TABLE IF EXISTS ortu_akun_anak;

-- =============================================================================
-- TABLE 1: Akun anak (NIS / username + profil + password)
-- =============================================================================
CREATE TABLE ortu_akun_anak (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_username VARCHAR(32)     NOT NULL COMMENT 'USERNAME login Smart Payment / NIS',
  student_nis      VARCHAR(32)     NOT NULL COMMENT 'NIS tampilan',
  student_name     VARCHAR(255)    NOT NULL,
  class_info       VARCHAR(64)     NULL COMMENT 'Mis. 1 - TESTING',
  unit_name        VARCHAR(255)    NULL,
  kelompok         VARCHAR(128)    NULL,
  va_spp           VARCHAR(32)     NULL,
  va_du            VARCHAR(32)     NULL,
  password_enc     VARCHAR(512)    NOT NULL COMMENT 'Password untuk switch akun (enkripsi di aplikasi)',
  last_login_at    DATETIME        NULL,
  created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ortu_akun_username (student_username),
  KEY idx_ortu_akun_nis (student_nis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Daftar akun anak dashboard orang tua';

-- =============================================================================
-- TABLE 2: Kelompok multi akun (maks. 3 akun per baris)
-- =============================================================================
CREATE TABLE ortu_akun_kelompok (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  akun_1_id  BIGINT UNSIGNED NOT NULL COMMENT 'Akun utama / pertama',
  akun_2_id  BIGINT UNSIGNED NULL     COMMENT 'Akun kedua (opsional)',
  akun_3_id  BIGINT UNSIGNED NULL     COMMENT 'Akun ketiga (opsional)',
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ortu_kelompok_a1 (akun_1_id),
  KEY idx_ortu_kelompok_a2 (akun_2_id),
  KEY idx_ortu_kelompok_a3 (akun_3_id),
  CONSTRAINT fk_ortu_kelompok_a1 FOREIGN KEY (akun_1_id) REFERENCES ortu_akun_anak (id) ON DELETE CASCADE,
  CONSTRAINT fk_ortu_kelompok_a2 FOREIGN KEY (akun_2_id) REFERENCES ortu_akun_anak (id) ON DELETE SET NULL,
  CONSTRAINT fk_ortu_kelompok_a3 FOREIGN KEY (akun_3_id) REFERENCES ortu_akun_anak (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Relasi multi account ortu — max 3 akun per kelompok';

-- =============================================================================
-- Contoh data
-- =============================================================================
-- INSERT INTO ortu_akun_anak (student_username, student_nis, student_name, class_info, password_enc, last_login_at)
-- VALUES ('123', '123', 'Siswa NIS 123', '7 - A', 'MTIz', NOW()),
--        ('345', '345', 'Siswa NIS 345', '8 - B', 'MzQ1', NOW());
--
-- INSERT INTO ortu_akun_kelompok (akun_1_id, akun_2_id)
-- VALUES (1, 2);
