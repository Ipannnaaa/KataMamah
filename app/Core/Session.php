<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (bool) config('app.https', false),
        ]);

        session_name((string) config('app.session_name', 'wia_session'));
        session_start();

        self::expireIdle();
        self::rotateFlash();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Tersedia tepat satu request berikutnya.
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    /**
     * Teruskan flash request ini ke request berikutnya.
     *
     * Dipakai halaman antara seperti /mengalihkan: tanpa ini, pesan sukses
     * dan kode antrean habis terpakai di halaman antara dan tidak pernah
     * sampai ke tujuan sebenarnya.
     *
     * @param array<int, string> $kecuali Kunci yang justru tidak boleh ikut.
     */
    public static function keepFlash(array $kecuali = []): void
    {
        foreach ((array) ($_SESSION['_flash'] ?? []) as $key => $value) {
            if (!in_array((string) $key, $kecuali, true)) {
                $_SESSION['_flash_next'][$key] = $value;
            }
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], true);
        }

        session_destroy();
    }

    private static function rotateFlash(): void
    {
        $_SESSION['_flash'] = $_SESSION['_flash_next'] ?? [];
        unset($_SESSION['_flash_next']);

        // _old dan _errors mengikuti siklus flash yang sama.
        $_SESSION['_old'] = $_SESSION['_flash']['_old'] ?? [];
        $_SESSION['_errors'] = $_SESSION['_flash']['_errors'] ?? [];
    }

    private static function expireIdle(): void
    {
        $timeout = (int) config('app.session_timeout', 3600);
        $last = (int) ($_SESSION['_last_activity'] ?? 0);

        if ($last > 0 && (time() - $last) > $timeout) {
            self::destroy();
            session_start();
        }

        $_SESSION['_last_activity'] = time();
    }
}
