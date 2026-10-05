-- Jalankan jika tabel siswa sudah ada tanpa kolom no_kartu
USE malang_artri_face;

ALTER TABLE siswa
  ADD COLUMN no_kartu VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'NOKARTU InquirySALDO' AFTER rfid_uid;
