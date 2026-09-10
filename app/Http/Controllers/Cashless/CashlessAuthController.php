<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\SmKantin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CashlessAuthController extends Controller
{
    public function showLogin(): View|\Illuminate\Http\RedirectResponse
    {
        if (session()->has('cashless_user')) {
            return redirect()->route('cashless.admin.index');
        }

        return view('cashless.auth.login');
    }

    public function login(Request $request)
    {
        $rules = [
            'username' => 'required|string',
            'password' => 'required|string',
        ];
        $messages = [
            'username.required' => 'Silahkan isi username terlebih dahulu.',
            'password.required' => 'Silahkan isi password terlebih dahulu.',
        ];

        $turnstileSecret = trim((string) config('services.turnstile.secret_key'));
        if ($turnstileSecret !== '') {
            $rules['cf-turnstile-response'] = 'required|string';
            $messages['cf-turnstile-response.required'] = 'Captcha wajib diisi.';
        }

        $request->validate($rules, $messages);

        if ($turnstileSecret !== '') {
            $response = Http::asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret' => $turnstileSecret,
                    'response' => $request->input('cf-turnstile-response'),
                    'remoteip' => $request->ip(),
                ],
            );

            if (!($response->json('success') ?? false)) {
                return back()->withErrors([
                    'captcha' => 'Gagal verifikasi captcha, silahkan coba lagi.',
                ])->withInput();
            }
        }

        $user = SmKantin::query()->where('username', $request->username)->first();
        if (!$user || !hash_equals((string) $user->password, md5($request->password))) {
            return back()->withErrors([
                'username' => 'Username atau password salah!',
                'password' => 'Username atau password salah!',
            ])->withInput();
        }

        session([
            'cashless_user' => [
                'id' => $user->urut,
                'username' => $user->username,
                'kantin' => $user->NamaKantin,
                'kode_merchan' => $user->KDMERCAN,
                'role' => $user->role ?? null,
            ],
        ]);
        $request->session()->regenerate();

        if ($request->filled('remember')) {
            $token = Str::random(60);
            $user->remember_token = $token;
            $user->save();
            Cookie::queue('remember_cashless_login', $token, 60 * 24 * 30);
        }

        if (strtolower((string) ($user->role ?? '')) === 'siswa') {
            return redirect()->route('cashless.admin.cek-saldo.index');
        }

        return redirect()->route('cashless.admin.index');
    }

    public function logout(Request $request)
    {
        $token = Cookie::get('remember_cashless_login');
        if ($token) {
            SmKantin::query()->where('remember_token', $token)->update([
                'remember_token' => null,
            ]);
        }
        Cookie::queue(Cookie::forget('remember_cashless_login'));
        Session::forget('cashless_user');

        return redirect()->route('cashless.login');
    }
}
