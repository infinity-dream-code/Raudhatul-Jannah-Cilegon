-- Migrasi: tabel akun admin (login panel admin)
-- Jalankan di database malang_artri_face.
USE malang_artri_face;

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

-- Akun default: username = admin, password = admin123 (ganti setelah login pertama)
INSERT INTO admin_user (username, nama, password_hash, role, aktif)
SELECT 'admin', 'Administrator',
       '$2y$10$N1O/jV5dZNHjnKBdEbD5h.DpfBR7nvUYLQOl6RR.3oJQGYNF60Cm2', 'admin', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_user WHERE username = 'admin');
