/**
 * Konfigurasi API — aktifkan untuk MySQL + proxy PHP (localhost).
 */
window.PresensiApiConfig = {
  enabled: true,
  /** Kosong = deteksi otomatis dari URL + data-app-root; atau set mis. "/Malang_Artri_Face/admin" */
  appPath: "",
  baseUrl: "",
  paths: {
    siswa: "api/siswa-db.php",
    siswaSync: "api/siswa-sync.php",
    unitRequest: "api/unit-request.php",
    presensiLog: "api/presensi-log.php",
    rekamSimpan: "api/rekam-simpan.php",
    rekamData: "api/rekam-data.php",
    rekamHapusFoto: "api/rekam-hapus-foto.php",
    saldo: "api/saldo.php",
    saldoInquiry: "api/saldo-inquiry.php",
    saldoDebit: "api/saldo.php",
  },
  headers: {},
  timeoutMs: 60000,
};
