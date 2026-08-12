<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (session('auth_module') === 'sikeu') {
            return redirect('/admin');
        }

        $user = Auth::user();
        $userName = trim((string) ($user->name ?? $user->users ?? 'Pengguna'));

        return view('auth.portal', [
            'modules' => config('sso.modules', []),
            'userName' => $userName !== '' ? $userName : 'Pengguna',
        ]);
    }

    public function sikeu(Request $request): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->session()->put('auth_module', 'sikeu');
        $request->session()->save();

        $targetUrl = trim((string) (config('sso.modules.sikeu.url') ?? ''));
        if (preg_match('/^https?:\/\//i', $targetUrl) === 1) {
            return redirect()->away($targetUrl);
        }

        // Relative — tidak tergantung APP_URL di .env
        return redirect('/admin');
    }

    public function switchModule(Request $request): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->session()->forget(['auth_module']);

        return redirect('/portal');
    }
}
