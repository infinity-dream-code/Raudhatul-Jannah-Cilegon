<div class="cashless-face-wrap kantin-app">
    @unless($faceDbReady ?? false)
        <div class="alert alert-warning">
            Layanan FacePay sementara tidak tersedia. Hubungi admin ICT.
        </div>
    @endunless

    <div class="k-toolbar mb-3">
        <div class="k-actions d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-success btn-sm" id="kantin-cam-start">Aktifkan kamera</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="kantin-cam-stop" hidden>Hentikan</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="kantin-cam-flip" hidden>Ganti kamera</button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="kantin-reload-faces">Muat ulang wajah</button>
            <button type="button" class="btn btn-primary btn-sm" id="cek-saldo-face-next" hidden>Cek siswa berikutnya</button>
        </div>
    </div>

    <div id="kantin-status" class="k-status mb-2" role="status">Menyiapkan…</div>

    <div class="k-stage mb-3" id="kantin-video-wrap" style="max-width:720px;margin:0 auto;">
        <video id="kantin-video" class="k-stage__video" playsinline autoplay muted style="width:100%;border-radius:12px;background:#000;min-height:280px;"></video>
        <div class="k-stage__overlay" aria-hidden="true">
            <div id="kantin-target-box" class="k-target" hidden>
                <span id="kantin-target-label" class="k-target__label" hidden></span>
            </div>
        </div>
    </div>
    <p class="text-muted small" id="kantin-hint">
        Aktifkan kamera dan hadapkan wajah siswa. Setelah wajah cocok, saldo siswa tampil di bawah.
    </p>
</div>
