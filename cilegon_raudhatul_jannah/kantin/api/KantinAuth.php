<?php

declare(strict_types=1);

/**
 * Sesi petugas kantin (terpisah dari admin MAF_ADMIN).
 */
final class KantinAuth
{
    private const SESSION_NAME = 'CILEGON_RAUDHATUL_JANNAH_KANTIN';
    private const SESSION_KEY = 'kantin_user';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() !== self::SESSION_NAME) {
                // Sesi lain sudah aktif (mis. admin) — tetap pakai data di $_SESSION
                // dengan key sendiri agar tidak bentrok.
            }
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
        session_start();
    }

    /** @param array{username: string, displayName?: string} $user */
    public static function login(array $user): void
    {
        self::startSession();
        $_SESSION[self::SESSION_KEY] = [
            'username' => (string) ($user['username'] ?? ''),
            'displayName' => (string) ($user['displayName'] ?? $user['username'] ?? ''),
            'loggedAt' => date('c'),
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    /** @return array{username: string, displayName: string, loggedAt: string}|null */
    public static function user(): ?array
    {
        self::startSession();
        $u = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($u) || empty($u['username'])) {
            return null;
        }
        return [
            'username' => (string) $u['username'],
            'displayName' => (string) ($u['displayName'] ?? $u['username']),
            'loggedAt' => (string) ($u['loggedAt'] ?? ''),
        ];
    }

    public static function requireUser(): array
    {
        $u = self::user();
        if (!$u) {
            kantin_api_json(['ok' => false, 'error' => 'Belum login kantin'], 401);
        }
        return $u;
    }

    public static function username(): string
    {
        $u = self::user();
        return $u ? (string) $u['username'] : '';
    }
}
