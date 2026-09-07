<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckCashlessSession
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('cashless_user')) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Sesi cashless berakhir. Silahkan login kembali.',
                    'errors' => ['session' => ['Sesi cashless berakhir']],
                ], 401);
            }

            return redirect()->route('cashless.login');
        }

        if (strtolower((string) session('cashless_user.role')) === 'siswa') {
            $allowed = $request->routeIs([
                'cashless.admin.index',
                'cashless.admin.cek-saldo.*',
            ]);

            if (!$allowed) {
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'message' => 'Akses ditolak untuk role ini.',
                    ], 403);
                }

                return redirect()->route('cashless.admin.cek-saldo.index');
            }
        }

        return $next($request);
    }
}
