<div class="cashless-face-wrap kantin-app">
    @unless($faceDbReady ?? false)
        <div class="alert alert-warning">
            Database FacePay (<code>FACE_DB_*</code>) belum terhubung. Tab wajah membutuhkan DB
            <strong>cilegon_raudhatul_jannah</strong> (tabel <code>siswa.foto_wajah</code>).
        </div>
    @endunless

    <div class="k-toolbar mb-3">
        <div class="k-actions d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-success btn-sm" id="kantin-cam-start">Aktifkan kamera</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="kantin-cam-stop" hidden>Hentikan</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="kantin-cam-flip" hidden>Ganti kamera</button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="kantin-reload-faces">Muat ulang wajah</button>
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
        Aktifkan kamera dan hadapkan wajah siswa. Setelah cocok, isi nominal di modal lalu bayar.
    </p>

    <div id="kantin-pay-modal" class="k-modal" hidden role="dialog" aria-modal="true">
        <div class="k-modal__backdrop"></div>
        <div class="k-modal__dialog card shadow">
            <div class="card-body">
                <h5 class="card-title">Pengurangan saldo (FacePay)</h5>
                <dl class="row mb-3">
                    <dt class="col-sm-4">Nama</dt>
                    <dd class="col-sm-8" id="kantin-pay-nama">—</dd>
                    <dt class="col-sm-4">Saldo</dt>
                    <dd class="col-sm-8" id="kantin-pay-saldo">—</dd>
                    <dt class="col-sm-4">Sisa setelah potong</dt>
                    <dd class="col-sm-8" id="kantin-pay-sisa">—</dd>
                </dl>
                <div class="mb-3">
                    <label class="form-label" for="kantin-pay-nominal-input">Nominal (Rp)</label>
                    <input type="number" id="kantin-pay-nominal-input" class="form-control" min="100" step="100" value="1000">
                </div>
                <p id="kantin-pay-error" class="text-danger small" hidden></p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="kantin-pay-cancel">Batal</button>
                    <button type="button" class="btn btn-primary" id="kantin-pay-confirm">Potong saldo</button>
                </div>
            </div>
        </div>
    </div>
</div>
