<?php

namespace App\Http\Middleware;

use Closure;

class CheckCashlessSession
{
    public function handle($request, Closure $next)
    {
        if (!session()->has('cashless_user')) {
            return redirect()->route('cashless.login');
        }

        if (strtolower((string) session('cashless_user.role')) === 'siswa') {
            $allowed = $request->routeIs([
                'cashless.admin.index',
                'cashless.admin.cek-saldo.*',
            ]);

            if (!$allowed) {
                return redirect()->route('cashless.admin.cek-saldo.index');
            }
        }

        return $next($request);
    }
}
