-- Tambah kolom batasan_utang di sm_batasan (aman, default 0 = tidak boleh utang).
-- Lewati jika kolom sudah ada.
ALTER TABLE sm_batasan
    ADD COLUMN batasan_utang INT NOT NULL DEFAULT 0
    AFTER batas_cash;

-- Fungsi VPSPaymentBUY (limit harian + utang) ada di vps_payment_buy.sql.
