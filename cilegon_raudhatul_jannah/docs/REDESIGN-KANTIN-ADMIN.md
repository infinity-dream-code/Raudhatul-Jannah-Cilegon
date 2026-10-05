# Dokumentasi Redesign — Portal, Admin & Kantin FacePay

Dokumentasi teknis untuk implementasi redesign yang dijelaskan di [`design.md`](../design.md).

---

## 1. Ringkasan perubahan

| Area | Sebelum | Sesudah |
|------|---------|---------|
| Pintu masuk | `/` redirect ke ortu; admin terpisah | Portal: pilih **Kantin** atau **Admin** |
| Login kantin | Tidak ada | API `LoginRequest` (JWT MobileMerchant) |
| Login admin | Tabel `admin_user` | **Tetap** tabel `admin_user` |
| FacePay | Di dalam admin, debit lokal/inquiry | Modul **kantin** terpisah + `PaymentBELANJAKantin` |
| Log beli | Log lokal FacePay | API `LogTransaksiRequest` per username kantin |
| Merchant URL | bervariasi per sekolah | `Malang_Alizzah_ForVPS` |

---

## 2. Endpoint MobileMerchant

**Base URL**

```
http://103.23.103.43/MobileMerchant/Malang_Alizzah_ForVPS/index.php
```

**Pola panggilan**

```
GET {base}?token={JWT_HS256}
```

JWT dibuat di server PHP (`MobileMerchantClient`) dengan `jwt_secret` dari `admin/api/config.php`.

### 2.1 LoginRequest — login kantin

**Payload**

```json
{
  "METHOD": "LoginRequest",
  "USERNAME": "farrelep",
  "PASSWORD": 123
}
```

**Response sukses**

```json
{
  "Username": "ict test",
  "KodeRespon": 1
}
```

- `KodeRespon === 1` → autentikasi berhasil.
- Simpan `Username` ke session kantin; nilai ini dipakai sebagai `NAMAKANTIN` dan `USERNAME` pada log transaksi.

**Proxy yang diusulkan**

```
POST /kantin/api/login.php
Body: { "username": "...", "password": "..." }
```

---

### 2.2 InquirySALDO — cek saldo sebelum bayar

**Payload**

```json
{
  "METHOD": "InquirySALDO",
  "NOKARTU": 1301154344
}
```

**Response**

```json
[
  {
    "STATUS": "OK",
    "NAMA": "FARREL GANTENG",
    "SALDO": "27900"
  }
]
```

Dipakai di modal FacePay setelah wajah dikenali, **sebelum** konfirmasi pembayaran.

**Proxy**

```
POST /kantin/api/saldo-inquiry.php
Body: { "nokartu": "1301154344" }
```

---

### 2.3 PaymentBELANJAKantin — debit belanja

**Payload**

```json
{
  "METHOD": "PaymentBELANJAKantin",
  "NOKARTU": 1301154344,
  "NOMINAL": 100,
  "NAMAKANTIN": "WS_TESTING"
}
```

| Field | Sumber aman |
|-------|-------------|
| `NOKARTU` | NIS siswa dari hasil face match |
| `NOMINAL` | Input petugas (validasi server: > 0) |
| `NAMAKANTIN` | **Hanya dari session login kantin** (bukan dari body client) |

**Proxy**

```
POST /kantin/api/payment.php
Body: { "nokartu": "...", "nominal": 100 }
```

---

### 2.4 LogTransaksiRequest — riwayat pembelian

**Payload**

```json
{
  "METHOD": "LogTransaksiRequest",
  "USERNAME": "WS_TESTING"
}
```

`USERNAME` = username session kantin.

**Response**

```json
{
  "datas": [
    {
      "NamaCust": "FARREL GANTENG",
      "TRXDATE": "2026-06-04 17:28:40",
      "KANTIN": "WS_TESTING",
      "Nominal": "100"
    }
  ]
}
```

**Proxy**

```
GET /kantin/api/log-transaksi.php
```

---

## 3. Alur FacePay (produk)

```
Set nominal
    → Aktifkan kamera
    → Face match (foto referensi dari DB siswa)
    → Modal: tampilkan nama
    → GET InquirySALDO (NOKARTU)
    → Modal: tampilkan SALDO + NOMINAL
    → Petugas konfirmasi
    → PaymentBELANJAKantin
    → Refresh LogTransaksiRequest
```

Face recognition tetap memakai pola yang sama dengan absensi (`face-api.js` + `rekam-data.php?withFoto=1`). Lihat [`FACE-DETECTOR-ABSEN.md`](./FACE-DETECTOR-ABSEN.md) dan [`REKAM-WAJAH.md`](./REKAM-WAJAH.md).

---

## 4. Auth: Admin vs Kantin

| | Admin | Kantin |
|--|-------|--------|
| UI login | `/admin/login.html` | `/kantin/login.html` |
| Sumber | Tabel MySQL `admin_user` | API `LoginRequest` |
| Session | PHP `MAF_ADMIN` (existing) | Session kantin terpisah |
| Setelah login | Dashboard admin | FacePay + log |

Kedua jalur dapat diakses dari portal `/index.html`.

---

## 5. Perubahan konfigurasi PHP

File: `admin/api/config.php` (dan `config.example.php`)

```php
'api_url' => 'http://103.23.103.43/MobileMerchant/Malang_Alizzah_ForVPS/index.php',
'allowed_methods' => [
    'LoginRequest',
    'InquirySALDO',
    'PaymentBELANJAKantin',
    'LogTransaksiRequest',
    'StudentRequest',
],
```

### Redesign panel Admin (v2)

Admin memakai tema terang Alizzah yang sama dengan portal/kantin:

- Token CSS di `admin/css/style.css` (`--brand`, `--bg` paper, Fraunces + DM Sans)
- Beranda baru dengan tile: Presensi, Rekam, Data siswa, Portal Kantin
- Modul absensi → Face Detector; FacePay produksi → `/kantin`
- Login admin tetap dari tabel `admin_user`
- Top bar: mark **FA** + nama brand + chip user/logout

`MobileMerchantClient::call()` sudah memvalidasi `METHOD` terhadap `allowed_methods` — method baru harus didaftarkan di sini.

---

## 6. Struktur folder target

```
/
  index.html                 Portal (Kantin | Admin)
  design.md
  docs/REDESIGN-KANTIN-ADMIN.md

  kantin/
    login.html
    index.html
    css/kantin.css
    js/kantin-auth.js
    js/kantin-facepay.js
    js/kantin-log.js
    api/login.php
    api/logout.php
    api/me.php
    api/saldo-inquiry.php
    api/payment.php
    api/log-transaksi.php

  admin/                     (existing)
```

---

## 7. Responsive

| Breakpoint | Layout kantin |
|------------|---------------|
| < 640px | Kolom tunggal: nominal → kamera → log |
| 640–1023px | Kamera + nominal; log collapsible |
| ≥ 1024px | Split: stage kiri ~60%, log kanan ~40% |

Detail token & komponen: lihat `design.md` §2, §4, §8.

---

## 8. Checklist implementasi

- [x] Portal `/index.html` (bukan redirect ortu saja; ortu tetap bisa punya pintu terpisah bila perlu)
- [x] `kantin/api/login.php` + session
- [x] Update `allowed_methods` + `api_url`
- [x] FacePay UI baru + modal InquirySALDO
- [x] `payment.php` dengan `NAMAKANTIN` dari session
- [x] Tabel log dari `LogTransaksiRequest`
- [x] Guard: halaman kantin redirect ke login bila belum session
- [ ] Uji di 375 / 768 / 1280 px (manual di browser)
- [ ] Uji flow: login → match → saldo → bayar → log muncul (butuh server + API merchant)

---

## 9. Keamanan singkat

1. Secret JWT hanya di server.
2. Browser tidak memanggil merchant URL langsung.
3. `NAMAKANTIN` / `USERNAME` log tidak boleh diisi dari request body client.
4. Validasi `nominal` (angka, > 0, batas wajar).
5. Session kantin HttpOnly bila memakai cookie PHP.

---

## 10. Referensi gambar spek API

| # | Isi |
|---|-----|
| 1 | JWT `LoginRequest` (USERNAME, PASSWORD) |
| 2 | Claims `PaymentBELANJAKantin` |
| 3 | Claims `InquirySALDO` |
| 4 | Response InquirySALDO: STATUS, NAMA, SALDO |
| 5 | Response login: Username, KodeRespon |
| 6 | JWT `LogTransaksiRequest` |
| 7 | Response log: `datas[]` NamaCust, TRXDATE, KANTIN, Nominal |

---

*Selaras dengan `design.md` v2.0.0-redesign.*
