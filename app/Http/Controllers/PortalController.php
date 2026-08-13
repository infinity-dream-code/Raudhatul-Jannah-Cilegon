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
        if ($this->isExternalUrl($targetUrl)) {
            return redirect()->away($targetUrl);
        }

        return redirect('/admin');
    }

    public function facepayAdmin(): RedirectResponse
    {
        return $this->redirectExternalModule('facepay_admin');
    }

    public function facepaySiswa(): RedirectResponse
    {
        return $this->redirectExternalModule('facepay_siswa');
    }

    public function facepayKantin(): RedirectResponse
    {
        return $this->redirectExternalModule('facepay_kantin');
    }

    public function switchModule(Request $request): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->session()->forget(['auth_module']);

        return redirect('/portal');
    }

    private function redirectExternalModule(string $key): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $enabled = (bool) config("sso.modules.{$key}.enabled", false);
        $targetUrl = trim((string) (config("sso.modules.{$key}.url") ?? ''));

        if (!$enabled || !$this->isExternalUrl($targetUrl)) {
            return redirect('/portal')->with('portal_info', 'Modul belum tersedia.');
        }

        return redirect()->away($targetUrl);
    }

    private function isExternalUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }
}
