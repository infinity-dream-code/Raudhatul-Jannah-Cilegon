# Dokumentasi Lengkap Sistem Facepay Alizzah (Malang Alizzah Face)

> **Versi Sistem:** 2.0.0-redesign  
> **Pemilik / Developer:** ICT Alizzah  
> **Tanggal Pembaruan:** Juli 2026  
> **Teknologi:** HTML5, CSS3 (Alizzah Design Tokens), JavaScript (ES6+), PHP Proxy API, MySQL, Face Recognition / WebCam Camera Engine, Merchant API Integration.

---

## 📋 1. Pendahuluan & Ringkasan Eksekutif

**Facepay Alizzah** adalah platform sistem pengenalan wajah (*Face Recognition*) terpadu yang dirancang khusus untuk lingkungan sekolah **Alizzah**. Sistem ini mengintegrasikan presensi absensi siswa, transaksi pembayaran non-tunai di kantin (*FacePay*), serta layanan perekaman foto referensi wajah secara mandiri oleh orang tua siswa.

### 🌟 Fitur Utama Sistem
1. **FacePay Kantin (Transaksi Cashless Wajah)**: Petugas kantin dapat memproses belanjaan siswa secara instan hanya dengan pemindaian wajah. Sistem otomatis melakukan *Inquiry Saldo* dan *Payment Belanja* ke Merchant Server.
2. **Dashboard Panel Admin**: Pengelolaan data siswa, presensi harian berbasis wajah/RFID, perekaman data referensi, pemantauan saldo, serta konfigurasi sistem.
3. **Portal Orang Tua Mandiri (`/ortu/`)**: Memungkinkan orang tua siswa mendaftarkan atau memperbarui foto referensi wajah anak secara mandiri dari HP/laptop dengan panduan posisi wajah (*Oval Face Guide*) dan opsi unggah dari galeri.
4. **Portal Pintu Akses Utama (`/`)**: Navigasi terpusat untuk memisahkan jalur penguna (Kantin, Admin, dan Orang Tua) secara aman.

---

## 🏛️ 2. Arsitektur Informasi (Information Architecture)

Sistem membagi jalur akses ke dalam 4 modul utama untuk menjamin keamanan, pemisahan peran, dan kenyamanan pengguna:

```
[ Portal Utama: / ]
       │
       ├──► [ Modul Kantin: /kantin/ ]
       │       ├── /kantin/login.html   (Login Petugas Kantin - JWT Merchant)
       │       └── /kantin/index.html   (FacePay Terminal & Log Transaksi)
       │
       ├──► [ Modul Admin: /admin/ ]
       │       ├── /admin/login.html    (Login Admin Sekolah - DB Local)
       │       └── /admin/index.html    (Dashboard Admin & Presensi Absensi)
       │
       └──► [ Portal Ortu: /ortu/ ]
               ├── /ortu/index.html     (Login SIS Ortu)
               └── /ortu/dashboard.html (Perekaman Foto Wajah Mandiri Siswa)
```

---

## 🔒 3. Sistem Autentikasi & Matriks Hak Akses

| Peran (Role) | Sumber Autentikasi | Masa Sesi (Session) | Kredensial / Akses |
| :--- | :--- | :--- | :--- |
| **Guest / Publik** | Tanpa Login | — | Halaman Pintu Portal (`/`) |
| **Petugas Kantin** | Merchant API (`LoginRequest`) | `sessionStorage` (`alizzah_kantin_session`) | Terminal FacePay, Inquiry Saldo, Payment Belanja, Log Pembelian |
| **Administrator** | Database Lokal (`admin_user`) | PHP Session (`MAF_ADMIN`) | Manajemen Data Siswa, Absensi Wajah, Rekam Referensi, Cek/Topup Saldo, Settings |
| **Orang Tua Siswa** | Nomor Induk Siswa (`ortu-siswa.php`) | `sessionStorage` (`ortu_siswa_session`) | Rekam & Unggah Foto Wajah Siswa Mandiri |

---

## 🔄 4. Spesifikasi Modul & Alur Kerja (Workflows)

### 4.1. Modul Kantin (FacePay Terminal)
1. **Login Petugas**: Petugas memasukkan `username` & `password` kantin yang diverifikasi langsung ke Merchant API server (`http://103.23.103.43/MobileMerchant/...`).
2. **Pemindaian Wajah Siswa**:
   - Kamera mendeteksi wajah siswa saat mendekati kasir kantin.
   - Sistem mencocokkan vektor wajah dengan database referensi.
3. **Modal Konfirmasi & Inquiry Saldo**:
   - Setelah wajah teridentifikasi, modal menampilkan foto & nama siswa.
   - Sistem melakukan panggilan API `InquirySALDO` untuk mengecek ketersediaan saldo siswa.
4. **Eksekusi Payment Belanja**:
   - Kasir menginput nominal belanja dan menekan tombol **Bayar (Payment)**.
   - Panggilan API `PaymentBELANJAKantin` mengeksekusi pemotongan saldo.
   - Riwayat transaksi tercetak secara otomatis di **Log Pembelian Kantin**.

### 4.2. Modul Admin (Management & Presensi)
1. **Login Admin**: Menggunakan akun terdaftar di tabel `admin_user` database lokal.
2. **Absensi Presensi Wajah**: Fitur deteksi wajah untuk pencatatan kehadiran harian siswa.
3. **Rekam Referensi Wajah**: Kasie ICT/Admin dapat memfoto siswa langsung dari web camera sekolah untuk disimpan sebagai sampel referensi.
4. **Cek & Monitor Saldo**: Melihat sisa saldo siswa dan riwayat transaksi.

### 4.3. Modul Portal Orang Tua (`/ortu/`)
1. **Login NIS Siswa**: Orang tua memasukkan NIS anak untuk masuk portal.
2. **Visual Kartu Identitas Siswa**: Menampilkan status foto referensi saat ini (`✓ Foto wajah sudah ada` / `○ Belum ada foto`).
3. **Rekam Wajah Mandiri**:
   - Menggunakan panduan visual **Oval Face Guide** pada viewport webcam.
   - Fitur ganti kamera (depan/belakang) untuk perangkat smartphone.
   - **Alternatif Upload File**: Jika webcam tidak didukung, ortu dapat mengunggah foto dari galeri HP/laptop.

---

## 🎨 5. Panduan Sistem Desain (Alizzah Design System)

Sistem menggunakan **Design Tokens resmi Alizzah** untuk menciptakan visual yang konsisten, bersih, dan profesional:

| Token Desain | Nilai / Kode Warna | Penggunaan |
| :--- | :--- | :--- |
| **Brand Primary** | `#1f6b4a` (Hijau Alizzah) | Header, Tombol Utama, Badge FA, Focus Halo |
| **Brand Deep** | `#134833` | Hover Tombol Primary, Gradient Hero |
| **Brand Soft** | `#e6f2ec` | Background Chip Aktif, Highlight |
| **Accent / CTA** | `#c45c26` (Terracotta) | Tombol Eksekusi Bayar / Kantin |
| **Paper Background**| `#f7f5f1` (Krem Warm) | Latar Belakang Halaman |
| **Surface Card** | `#ffffff` (Clean White) | Kartu Konten, Form, Modal Box |
| **Text Ink** | `#14201a` (Dark Charcoal) | Teks Utama, Judul |
| **Text Muted** | `#5c6b63` | Teks Bantuan / Subtitle |
| **Font Display** | `"Fraunces", Georgia, serif` | Judul Utama / Header |
| **Font Body** | `"DM Sans", sans-serif` | Teks Isi, Form Input, Tombol |

---

## 🌐 6. Integrasi Endpoints API (PHP Proxy & Merchant API)

### Merchant API (External Service)
- `LoginRequest`: Verifikasi autentikasi petugas kantin (Menghasilkan JWT Token).
- `InquirySALDO`: Memeriksa jumlah sisa saldo siswa berdasarkan ID/NIM.
- `PaymentBELANJAKantin`: Eksekusi transaksi pembelian kantin.
- `LogTransaksiRequest`: Mengambil riwayat log transaksi belanja.

### PHP Proxy API (Local Backend)
- `admin/api/ortu-siswa.php`: Proxy verifikasi NIS siswa untuk login ortu.
- `admin/api/rekam-data.php`: Proxy penyimpanan foto referensi wajah (Base64 JPEG).
- `admin/api/rekam-hapus-foto.php`: Proxy penghapusan foto referensi siswa.
- `admin/api/kantin-login.php`: Proxy enkapsulasi JWT login kantin.

---

## 🛠️ 7. Panduan Instalasi & Persyaratan Server

### Persyaratan Minimal:
- **Web Server**: Apache 2.4+ / Nginx 1.18+ (Wajib mendukung SSL/HTTPS agar kamera webcam dapat diakses di browser smartphone/laptop).
- **PHP**: PHP 7.4 / PHP 8.x dengan ekstensi `curl`, `json`, `pdo_mysql`.
- **Database**: MySQL 5.7+ / MariaDB 10.3+.

### Langkah Pengoperasian:
1. Copy seluruh folder proyek `malang_alizzah_face` ke `htdocs` / `wwwroot`.
2. Konfigurasi `admin/api/config.php` untuk mengatur kredensial database & URL Merchant API.
3. Pastikan direktori tempat penyimpanan foto memiliki hak akses *Write* (755/777).
4. Buka browser dan akses alamat domain dengan protokol `https://`.
