# Database `malang_artri_face`

## Instalasi

1. Buat database & user MySQL (atau lewat cPanel), lalu jalankan `schema.sql`.
2. Salin `api/config.example.php` → `api/config.php`, sesuaikan host/user/password database.
3. Upload folder `admin/` ke server.
4. Buka **`/admin/api/health.php?setup=1`** di browser — pastikan `"ok": true`.
5. (Opsional) Tes MobileMerchant: `health.php?setup=1&merchant=1`

Jika halaman Data siswa error 500, biasanya penyebabnya:
- `config.php` belum ada / kredensial DB salah
- database belum dibuat
- tabel belum di-import (gunakan `health.php?setup=1`)

Jika Sinkron dari SIE error 502:
- `api_url` atau `jwt_secret` di `config.php` tidak cocok dengan server MobileMerchant

## Tabel

| Tabel | Fungsi |
|-------|--------|
| `siswa` | Master siswa — **halaman Data siswa & Rekam data membaca dari sini** |
| `presensi_log` | Log masuk/keluar modul presensi wajah |
| `siswa_rekam_log` | Riwayat setiap kali simpan rekam (RFID/foto/suara) |

### Kolom `siswa` yang diisi lewat Rekam data

| Kolom DB | Isi |
|----------|-----|
| `rfid_uid` | UID kartu RFID |
| `foto_wajah` | Foto wajah (base64 JPEG) |
| `kode_suara` | Frasa pengenalan suara |

Endpoint: `POST api/rekam-simpan.php` → update `siswa` + insert `siswa_rekam_log`.

### Kolom detail SIE (MobileMerchant)

Saat **Sinkron dari SIE**, field berikut dari respons API disimpan ke tabel `siswa`:

| Kolom DB | Field API | Contoh |
|----------|-----------|--------|
| `num2nd` | NUM2ND | - |
| `stcust` | STCUST | 0 |
| `code01` / `desc01` | CODE01 / DESC01 | 101 |
| `code02` / `desc02` | CODE02 / DESC02 | SMP PUTRI 1 / 9 |
| `code03` / `desc03` | CODE03 / DESC03 | 4 / 9-A1 |
| `code04` / `desc04` | CODE04 / DESC04 | tahun ajaran |
| `code05` / `desc05` | CODE05 / DESC05 | alamat lengkap |
| `totpay` | TOTPAY | |
| `genus` | GENUS | |

Kolom **otomatis ditambahkan** (`ALTER TABLE`) jika belum ada. Manual: `migrate-sie-columns.sql`.

Endpoint: `POST api/siswa-sync.php` → upsert `siswa` + isi kolom di atas.

### Saldo (FacePay / monitoring)

| Field | Untuk API `InquirySALDO` |
|-------|-------------------------|
| `nis` | `NOKARTU` (NOKARTU = NIS siswa, contoh: 220007) |

Endpoint: `POST api/saldo-inquiry.php` → proxy ke MobileMerchant, respons `STATUS`, `NAMA`, `SALDO`.

## Endpoint PHP

- `api/siswa-sync.php` — tarik `StudentRequest` dari MobileMerchant → upsert `siswa`
- `api/siswa-db.php` — CRUD siswa untuk halaman Data siswa
- `api/rekam-simpan.php` — simpan mapping rekam ke `siswa` + log
- `api/presensi-log.php` — simpan log presensi wajah
