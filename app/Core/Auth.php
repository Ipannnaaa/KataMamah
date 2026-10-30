<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autentikasi berbasis sesi. Peran dan location_id SELALU dibaca dari sesi,
 * bukan dari input request, supaya tidak bisa dipalsukan lewat form.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $cached = null;

    /**
     * @return array<string, mixed>|null  Data user bila sandi cocok.
     */
    public static function attempt(string $email, string $password): ?array
    {
        $user = DB::first(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [strtolower($email)]
        );

        if ($user === null) {
            // Tetap jalankan hash dummy agar waktu respons tidak membocorkan
            // apakah email terdaftar.
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1OVyZ5i1u');

            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        Session::regenerate();

        Session::put('user_id', (int) $user['id']);
        Session::put('user_role', (string) $user['role']);
        Session::put('user_location', $user['location_id'] === null ? null : (int) $user['location_id']);
        Session::put('user_name', (string) $user['name']);

        self::$cached = $user;

        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
    }

    public static function logout(): void
    {
        self::$cached = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');

        return $id === null ? null : (int) $id;
    }

    public static function role(): ?string
    {
        $role = Session::get('user_role');

        return is_string($role) ? $role : null;
    }

    /**
     * Lokasi kerja petugas/admin. Dipakai sebagai filter di setiap query panel.
     */
    public static function locationId(): ?int
    {
        $id = Session::get('user_location');

        return $id === null ? null : (int) $id;
    }

    public static function name(): string
    {
        return (string) Session::get('user_name', '');
    }

    public static function is(string ...$roles): bool
    {
        $role = self::role();

        return $role !== null && in_array($role, $roles, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $id = self::id();

        if ($id === null) {
            return null;
        }

        $user = DB::first('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);

        if ($user === null) {
            self::logout();

            return null;
        }

        self::$cached = $user;

        return $user;
    }

    public static function recordAttempt(string $email, string $ip, bool $success): void
    {
        DB::insert('login_attempts', [
            'email' => substr(strtolower($email), 0, 160),
            'ip' => $ip,
            'success' => $success ? 1 : 0,
        ]);
    }
}
