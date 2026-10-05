<?php

declare(strict_types=1);

/**
 * Autentikasi admin berbasis sesi PHP (cookie, same-origin).
 *
 * Tabel: admin_user. Seed default: admin / admin123 (ganti setelah login pertama).
 */
final class AdminAuth
{
    private const SESSION_NAME = 'MAF_ADMIN';
    private const SESSION_KEY = 'admin_user_id';
    private const DEFAULT_USERNAME = 'admin';
    private const DEFAULT_PASSWORD = 'admin123';
    private const DEFAULT_NAMA = 'Administrator';

    /** Buat tabel admin_user bila belum ada (aman dijalankan berulang). */
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_user (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              username VARCHAR(64) NOT NULL,
              nama VARCHAR(255) NOT NULL DEFAULT "",
              password_hash VARCHAR(255) NOT NULL,
              role VARCHAR(32) NOT NULL DEFAULT "admin",
              aktif TINYINT(1) NOT NULL DEFAULT 1,
              last_login_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uk_admin_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** Tanam akun admin default bila tabel masih kosong. */
    public static function seedDefault(PDO $pdo): void
    {
        self::ensureSchema($pdo);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_user')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO admin_user (username, nama, password_hash, role, aktif)
             VALUES (:username, :nama, :hash, "admin", 1)'
        );
        $stmt->execute([
            ':username' => self::DEFAULT_USERNAME,
            ':nama' => self::DEFAULT_NAMA,
            ':hash' => password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT),
        ]);
    }

    /** Pastikan tabel ada + akun default tersedia. */
    public static function bootstrap(): PDO
    {
        $pdo = Database::pdo();
        self::seedDefault($pdo);
        return $pdo;
    }

    /** Mulai sesi PHP dengan cookie aman (idempoten). */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        $params = [
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params(0, '/', '', $secure, true);
        }
        session_name(self::SESSION_NAME);
        @session_start();
    }

    /** CORS aman untuk kredensial (refleksikan Origin, bukan '*'). */
    public static function sendCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept');
        }
    }

    /**
     * Coba login. Mengembalikan data user publik bila berhasil, atau null bila gagal.
     */
    public static function attempt(string $username, string $password): ?array
    {
        $pdo = self::bootstrap();
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM admin_user WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row || (int) ($row['aktif'] ?? 0) !== 1) {
            return null;
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            return null;
        }

        // Perbarui hash bila algoritma default berubah.
        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            $upd = $pdo->prepare('UPDATE admin_user SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }

        $pdo->prepare('UPDATE admin_user SET last_login_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);

        self::startSession();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $row['id'];

        return self::publicUser($row);
    }

    /** User yang sedang login, atau null. */
    public static function currentUser(): ?array
    {
        self::startSession();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if (!$id) {
            return null;
        }
        $pdo = Database::pdo();
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM admin_user WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        if (!$row || (int) ($row['aktif'] ?? 0) !== 1) {
            self::logout();
            return null;
        }
        return self::publicUser($row);
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'] ?? '/',
                $p['domain'] ?? '',
                $p['secure'] ?? false,
                $p['httponly'] ?? true
            );
        }
        @session_destroy();
    }

    /** @param array<string,mixed> $row */
    private static function publicUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'nama' => (string) ($row['nama'] ?? ''),
            'role' => (string) ($row['role'] ?? 'admin'),
            'lastLoginAt' => $row['last_login_at'] ?? null,
        ];
    }

    /** Ganti password untuk user yang sedang login. */
    public static function changePassword(int $id, string $oldPassword, string $newPassword): bool
    {
        $pdo = Database::pdo();
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM admin_user WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($oldPassword, (string) $row['password_hash'])) {
            return false;
        }
        if (strlen($newPassword) < 6) {
            return false;
        }
        $pdo->prepare('UPDATE admin_user SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        return true;
    }
}
