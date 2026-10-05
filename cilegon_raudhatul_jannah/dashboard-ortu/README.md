# Dashboard Orang Tua — Paket Salinan

Folder ini adalah **salinan mandiri** modul `dashboard-ortu` beserta dependensi minimum, siap di-copy ke server atau proyek lain.

## Isi folder

```
dashboard-ortu-copy/
├── index.html              → redirect ke ortu-login.html
├── ortu-*.html             → halaman UI
├── css/                    → ortu-style.css + alhanif-theme.css
├── js/                     → logika frontend
├── api/                    → proxy PHP ke Smart Payment + multi-akun
├── assets/logo/            → logo Cilegon Raudhatul Jannah
└── database/               → SQL migrasi multi-akun
```

## Cara deploy

1. **Upload** seluruh isi folder ini ke web server (mis. `public_html/dashboard-ortu/`).

2. **PHP** — pastikan PHP 8+ dengan ekstensi `pdo_mysql` dan `curl` aktif.

3. **Smart Payment** — edit `api/config.php`:
   - `api_base` — URL token Smart Payment
   - `jwt_secret` — secret JWT dari penyedia

4. **Database (multi-akun)** — wajib jika pakai fitur tambah/switch akun:
   ```bash
   mysql -u user -p nama_db < database/ortu_multi_account.sql
   ```
   Lalu salin dan isi kredensial MySQL:
   ```bash
   cp api/database.php.example api/database.php
   ```

5. **Akses** — buka `https://domain-anda/.../ortu-login.html`

## Tanpa database

Login, saldo, tagihan, pembayaran, dan transaksi tetap jalan (hanya lewat Smart Payment API). Fitur **multi-akun** membutuhkan tabel `ortu_akun_anak` dan `ortu_akun_kelompok`.

## Integrasi ke portal utama

- Tambahkan link dari `login.html` portal ke `dashboard-ortu-copy/ortu-login.html` (sesuaikan path).
- Jika folder ditaruh di proyek yang sudah punya `api/config.php` di root, `api/db.php` otomatis fallback ke config root bila `api/database.php` belum ada.

## Catatan API transaksi kantin

Endpoint `api/transaksi-kantin.php` mencoba method `TransaksiRequestSaku` lalu fallback `TransaksiSakuRequest`. Jika upstream Smart Payment mengembalikan kosong, hubungi tim Smart Payment.

---
Salinan dibuat dari modul `dashboard-ortu` — diadaptasi untuk Cilegon Raudhatul Jannah.
