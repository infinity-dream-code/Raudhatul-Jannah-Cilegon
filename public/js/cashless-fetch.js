/**
 * Shared fetch helper for cashless admin AJAX.
 * Uses relative URL + fresh CSRF to avoid false 419 "session expired".
 */
(function (window) {
    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function csrfHeaders() {
        const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const xsrf = readCookie('XSRF-TOKEN');
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        };
        if (meta) headers['X-CSRF-TOKEN'] = meta;
        if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
        return headers;
    }

    window.cashlessRequest = function (url, options) {
        options = options || {};
        const headers = Object.assign({}, csrfHeaders(), options.headers || {});
        return new Request(url, Object.assign({}, options, { headers, credentials: 'same-origin' }));
    };

    window.cashlessSubmit = async function (request) {
        try {
            const response = await fetch(request);
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw {
                    status: response.status,
                    message: data.message || response.statusText,
                    errors: data.errors || data.error,
                };
            }

            return { success: true, data: data };
        } catch (error) {
            if (error.status === 422) {
                if (typeof errorAlert === 'function') errorAlert(error.message);
                return { success: 422, errors: error.errors || error.error };
            }

            const errorMessages = {
                401: 'Sesi cashless berakhir 🙏 <br>Silahkan login cashless kembali!',
                403: 'Anda tidak memiliki izin untuk mengakses halaman ini 😖',
                404: 'Halaman yang dituju tidak ditemukan 🧐',
                405: 'Metode tidak valid 🧐 <br>silahkan muat ulang halaman dan coba lagi!',
                419: 'Token keamanan kedaluwarsa 🙏 <br>Silahkan muat ulang halaman lalu coba lagi!',
                429: 'Terlalu banyak permintaan akses <br>silahkan tunggu beberapa saat 🙏',
            };

            if (typeof errorAlert === 'function') {
                errorAlert(errorMessages[error.status] || 'Terjadi kesalahan saat memproses permintaan<br> Silahkan coba memuat ulang halaman');
            }

            if (error.status === 401 || error.status === 419) {
                setTimeout(function () {
                    window.location.href = '/cashless/login';
                }, 1500);
            }

            return { success: false };
        }
    };
})(window);
