(function (global) {
  "use strict";

  var http = global.PresensiApiHttp;
  var siswaApi = global.PresensiApiSiswa;
  var saldoApi = global.PresensiApiSaldo;
  var extras = global.PresensiApiExtras;

  global.PresensiApi = {
    isEnabled: function () {
      return http && http.isEnabled();
    },
    getConfig: function () {
      return http.getConfig();
    },
    siswa: siswaApi,
    saldo: saldoApi,
    extras: extras,
    /** Tarik siswa + saldo sekaligus */
    pullAll: function () {
      if (!http.isEnabled()) {
        return Promise.reject(new Error("API tidak diaktifkan"));
      }
      return Promise.all([siswaApi.list(), saldoApi.listMap()]).then(
        function (pair) {
          return { siswa: pair[0], saldoMap: pair[1] };
        },
      );
    },
  };
})(typeof window !== "undefined" ? window : globalThis);
