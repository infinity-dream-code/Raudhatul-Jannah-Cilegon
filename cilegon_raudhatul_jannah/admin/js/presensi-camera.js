/**
 * Webcam helper — depan (user) / belakang (environment).
 * Dipakai admin, kantin & ortu lewat /admin/js/presensi-camera.js
 */
(function (g) {
  "use strict";

  var currentStream = null;
  var currentFacing = "user";
  var currentDeviceId = null;

  function stopTracks(stream) {
    if (!stream) return;
    try {
      stream.getTracks().forEach(function (t) {
        t.stop();
      });
    } catch (e) {}
  }

  function stopCurrent() {
    stopTracks(currentStream);
    currentStream = null;
  }

  function attachStream(videoEl, stream) {
    currentStream = stream;
    videoEl.srcObject = stream;
    videoEl.setAttribute("playsinline", "true");
    videoEl.setAttribute("webkit-playsinline", "true");
    videoEl.muted = true;
    var p = videoEl.play();
    if (p && typeof p.catch === "function") {
      p.catch(function () {});
    }
    try {
      var track = stream.getVideoTracks()[0];
      var settings = track && track.getSettings ? track.getSettings() : null;
      if (settings && settings.deviceId) {
        currentDeviceId = settings.deviceId;
      }
      if (settings && settings.facingMode) {
        currentFacing = settings.facingMode === "environment" ? "environment" : "user";
      }
    } catch (e) {}
    return stream;
  }

  function isBackLabel(label) {
    var s = String(label || "").toLowerCase();
    return (
      /back|rear|environment|belakang|traseira|atras|后|背面/.test(s) &&
      !/front|user|face|depan/.test(s)
    );
  }

  function isFrontLabel(label) {
    var s = String(label || "").toLowerCase();
    return /front|user|face|depan|facing/.test(s) && !isBackLabel(s);
  }

  function listVideoInputs() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
      return Promise.resolve([]);
    }
    return navigator.mediaDevices.enumerateDevices().then(function (devices) {
      return (devices || []).filter(function (d) {
        return d.kind === "videoinput";
      });
    });
  }

  function pickDeviceId(devices, facing, excludeId) {
    var wantBack = facing === "environment";
    var i;
    var d;

    for (i = 0; i < devices.length; i++) {
      d = devices[i];
      if (excludeId && d.deviceId === excludeId) continue;
      if (wantBack && isBackLabel(d.label)) return d.deviceId;
      if (!wantBack && isFrontLabel(d.label)) return d.deviceId;
    }

    // Tanpa label jelas: biasanya index 0 = depan, 1+ = belakang di HP
    if (devices.length >= 2) {
      if (wantBack) {
        for (i = 0; i < devices.length; i++) {
          if (excludeId && devices[i].deviceId === excludeId) continue;
          if (!isFrontLabel(devices[i].label)) return devices[i].deviceId;
        }
        return devices[devices.length - 1].deviceId;
      }
      return devices[0].deviceId;
    }

    return devices.length ? devices[0].deviceId : null;
  }

  function tryGetUserMedia(constraints) {
    return navigator.mediaDevices.getUserMedia(constraints);
  }

  function openWithFacing(facing) {
    var mode = facing === "environment" ? "environment" : "user";
    var attempts = [
      {
        audio: false,
        video: {
          facingMode: { exact: mode },
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
      },
      {
        audio: false,
        video: {
          facingMode: mode,
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
      },
      {
        audio: false,
        video: {
          facingMode: { ideal: mode },
          width: { ideal: 1280 },
          height: { ideal: 720 },
        },
      },
    ];

    var chain = Promise.reject(new Error("start"));
    attempts.forEach(function (c) {
      chain = chain.catch(function () {
        return tryGetUserMedia(c);
      });
    });
    return chain;
  }

  function openWithDeviceId(deviceId) {
    return tryGetUserMedia({
      audio: false,
      video: {
        deviceId: { exact: deviceId },
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
    }).catch(function () {
      return tryGetUserMedia({
        audio: false,
        video: { deviceId: deviceId },
      });
    });
  }

  /**
   * @param {HTMLVideoElement} videoEl
   * @param {{ facingMode?: 'user'|'environment' }} [opts]
   * @returns {Promise<MediaStream>}
   */
  g.presensiOpenWebcam = function (videoEl, opts) {
    opts = opts || {};
    if (opts.facingMode === "environment" || opts.facingMode === "user") {
      currentFacing = opts.facingMode;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      return Promise.reject(new Error("Kamera tidak didukung di browser ini."));
    }
    if (!videoEl) {
      return Promise.reject(new Error("Elemen video tidak ditemukan."));
    }

    stopCurrent();

    return openWithFacing(currentFacing)
      .then(function (stream) {
        return attachStream(videoEl, stream);
      })
      .catch(function (errFacing) {
        return listVideoInputs()
          .then(function (devices) {
            var id = pickDeviceId(devices, currentFacing, null);
            if (!id) throw errFacing;
            return openWithDeviceId(id).then(function (stream) {
              return attachStream(videoEl, stream);
            });
          })
          .catch(function () {
            return tryGetUserMedia({ audio: false, video: true }).then(function (stream) {
              return attachStream(videoEl, stream);
            });
          })
          .catch(function () {
            throw errFacing;
          });
      });
  };

  /** Tutup stream aktif. */
  g.presensiStopWebcam = function (videoEl) {
    stopCurrent();
    currentDeviceId = null;
    if (videoEl) {
      try {
        videoEl.srcObject = null;
      } catch (e) {}
    }
  };

  /** Mode saat ini: 'user' | 'environment' */
  g.presensiGetFacingMode = function () {
    return currentFacing;
  };

  /**
   * Tukar kamera depan ↔ belakang, stream ulang ke video yang sama.
   * @param {HTMLVideoElement} videoEl
   * @returns {Promise<MediaStream>}
   */
  g.presensiSwitchCamera = function (videoEl) {
    var nextFacing = currentFacing === "user" ? "environment" : "user";
    var prevFacing = currentFacing;
    var excludeId = currentDeviceId;

    currentFacing = nextFacing;
    stopCurrent();

    return listVideoInputs()
      .then(function (devices) {
        var id = pickDeviceId(devices, nextFacing, excludeId);
        if (id && id !== excludeId) {
          return openWithDeviceId(id).then(function (stream) {
            return attachStream(videoEl, stream);
          });
        }
        return openWithFacing(nextFacing).then(function (stream) {
          return attachStream(videoEl, stream);
        });
      })
      .catch(function () {
        return openWithFacing(nextFacing).then(function (stream) {
          return attachStream(videoEl, stream);
        });
      })
      .catch(function (err) {
        currentFacing = prevFacing;
        return g.presensiOpenWebcam(videoEl, { facingMode: prevFacing }).then(function () {
          throw err || new Error("Tidak bisa ganti kamera.");
        });
      });
  };

  /**
   * Set mode lalu buka ulang.
   * @param {HTMLVideoElement} videoEl
   * @param {'user'|'environment'} facing
   */
  g.presensiSetFacingMode = function (videoEl, facing) {
    currentFacing = facing === "environment" ? "environment" : "user";
    return g.presensiOpenWebcam(videoEl, { facingMode: currentFacing });
  };

  /** Label tombol berdasarkan mode aktif. */
  g.presensiFacingLabel = function () {
    return currentFacing === "environment" ? "Kamera belakang" : "Kamera depan";
  };
})(typeof window !== "undefined" ? window : this);
