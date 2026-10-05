const CACHE = "malang-artri-face-admin-v2";
const CORE = [
  "./index.html",
  "./modul.html",
  "./settings/index.html",
  "./settings/unit.html",
  "./settings/jenjang.html",
  "./settings/kelas.html",
  "./settings/data-siswa.html",
  "./settings/rekam-data.html",
  "./settings/saldo-siswa.html",
  "./modules/face.html",
  "./modules/facepay.html",
  "./css/style.css",
  "./js/app.js",
  "./js/presensi-camera.js",
  "./js/presensi-masuk-modal.js",
  "./js/settings-table.js",
  "./js/presensi-sie-settings.js",
  "./js/data-siswa.js",
  "./js/rekam-service.js",
  "./js/rekam-data.js",
  "./js/saldo-siswa.js",
  "./js/face-detector.js",
  "./js/facepay.js",
  "./js/modul-context.js",
  "./js/presensi-data-service.js",
  "./api/config.js",
  "./api/http.js",
  "./api/siswa.js",
  "./api/saldo.js",
  "./api/presensi-api.js",
  "./api/index.js",
  "./api/config.example.js",
  "./manifest.json",
  "./icons/icon-192.png",
  "./icons/icon-512.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE)
      .then((cache) => cache.addAll(CORE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;
  const url = new URL(request.url);
  if (url.origin !== location.origin) return;

  event.respondWith(
    fetch(request)
      .then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(request, copy));
        }
        return res;
      })
      .catch(() =>
        caches.match(request).then((hit) => hit || caches.match("./index.html"))
      )
  );
});
