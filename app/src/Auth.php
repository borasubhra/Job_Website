<?php
declare(strict_types=1);

namespace App;

/** Single admin account from .env (ADMIN_USER / ADMIN_PASSWORD), session login, CSRF tokens. */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('jp_admin');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
            'path'     => '/',
        ]);
        session_start();
    }

    /** Null if login is allowed, otherwise the reason it is switched off. */
    public static function setupProblem(): ?string
    {
        $p = (string) env('ADMIN_PASSWORD', '');
        if ($p === '') return 'Set ADMIN_PASSWORD in the .env file, then run: docker compose up -d';
        if (strlen($p) < 10 || in_array(strtolower($p), ['changeme_admin123', 'change_me', 'admin123', 'password123'], true))
            return 'ADMIN_PASSWORD in .env is too weak. Use at least 10 characters (not the example value), then run: docker compose up -d';
        return null;
    }

    public static function attempt(string $user, string $pass): bool
    {
        self::start();
        if (self::setupProblem()) return false;
        $ok = hash_equals((string) env('ADMIN_USER', 'admin'), $user) & hash_equals((string) env('ADMIN_PASSWORD'), $pass);
        if (!$ok) { sleep(2); return false; }                       // slow down password guessing
        session_regenerate_id(true);
        $_SESSION['admin'] = $user;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function check(): bool
    {
        self::start();
        return isset($_SESSION['admin']);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function csrf(): string
    {
        self::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(): bool
    {
        return hash_equals(self::csrf(), (string) ($_POST['csrf'] ?? ''));
    }
}
