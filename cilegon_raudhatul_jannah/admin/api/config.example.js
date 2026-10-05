/**
 * Salin file ini menjadi config.js lalu sesuaikan baseUrl server Anda.
 *
 * Server harus mengizinkan CORS dari origin aplikasi (atau gunakan proxy di domain yang sama).
 *
 * Kontrak endpoint (REST, JSON):
 *
 * GET  {baseUrl}/api/siswa          → daftar siswa (array atau { data: [...] })
 * POST {baseUrl}/api/siswa          → tambah siswa (body: objek siswa)
 * PUT  {baseUrl}/api/siswa/:id      → ubah siswa
 *
 * GET  {baseUrl}/api/saldo          → map saldo atau { data: [{ siswaId, saldo }] }
 * PUT  {baseUrl}/api/saldo          → simpan bulk { map: { "id": 50000 } }
 * POST {baseUrl}/api/saldo/debit    → debit { siswaId, amount } → { before, after }
 */
window.PresensiApiConfig = {
  /** true = data siswa & saldo dari server; false = localStorage saja */
  enabled: true,
  /** Contoh: "https://api.sekolah.sch.id" atau "http://192.168.1.10:8080" */
  /** Path absolut app di server, mis. "/Malang_Artri_Face/admin" (hindari 404 dari settings/) */
  appPath: "/Malang_Artri_Face/admin",
  baseUrl: "",
  /** Path relatif ke appPath / root */
  paths: {
    siswa: "api/siswa-db.php",
    siswaSync: "api/siswa-sync.php",
    unitRequest: "api/unit-request.php",
    presensiLog: "api/presensi-log.php",
    rekamSimpan: "api/rekam-simpan.php",
    rekamData: "api/rekam-data.php",
    rekamHapusFoto: "api/rekam-hapus-foto.php",
    siswaLegacy: "api/siswa",
    saldo: "api/saldo.php",
    saldoInquiry: "api/saldo-inquiry.php",
    saldoDebit: "api/saldo/debit",
  },
  /** Header opsional, mis. token API */
  headers: {
    // Authorization: "Bearer TOKEN_ANDA",
  },
  /** Timeout fetch (ms) */
  timeoutMs: 30000,
};
