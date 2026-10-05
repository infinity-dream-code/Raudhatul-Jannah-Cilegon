<?php

declare(strict_types=1);

/**
 * Proxy transaksi kantin / uang saku — Smart Payment.
 * METHOD utama: TransaksiRequestSaku (fallback: TransaksiSakuRequest).
 */
require __DIR__ . '/bootstrap.php';

ortu_handle_list_request_methods(
    ['TransaksiRequestSaku', 'TransaksiSakuRequest'],
    'Gagal mengambil data transaksi kantin.'
);
