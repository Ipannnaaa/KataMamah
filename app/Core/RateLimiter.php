<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembatas percobaan sederhana berbasis file (tanpa Redis).
 * Dipakai untuk login, cek antrean, dan pengiriman chat.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $key, int $max, int $decaySeconds): bool
    {
        $entry = self::read($key);

        if ($entry === null) {
            return false;
        }

        if ((int) $entry['expires'] <= time()) {
            self::clear($key);

            return false;
        }

        return (int) $entry['hits'] >= $max;
    }

    public static function hit(string $key, int $decaySeconds): int
    {
        $entry = self::read($key);
        $now = time();

        if ($entry === null || (int) $entry['expires'] <= $now) {
            $entry = ['hits' => 0, 'expires' => $now + $decaySeconds];
        }

        $entry['hits'] = (int) $entry['hits'] + 1;

        self::write($key, $entry);

        return (int) $entry['hits'];
    }

    public static function secondsLeft(string $key): int
    {
        $entry = self::read($key);

        if ($entry === null) {
            return 0;
        }

        return max(0, (int) $entry['expires'] - time());
    }

    public static function clear(string $key): void
    {
        $file = self::file($key);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function file(string $key): string
    {
        $dir = BASE_PATH . '/storage/cache/ratelimit';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/' . sha1($key) . '.json';
    }

    /**
     * @return array{hits: int, expires: int}|null
     */
    private static function read(string $key): ?array
    {
        $file = self::file($key);

        if (!is_file($file)) {
            return null;
        }

        $raw = (string) @file_get_contents($file);
        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['hits'], $data['expires'])) {
            return null;
        }

        return ['hits' => (int) $data['hits'], 'expires' => (int) $data['expires']];
    }

    /**
     * @param array{hits: int, expires: int} $entry
     */
    private static function write(string $key, array $entry): void
    {
        @file_put_contents(self::file($key), json_encode($entry), LOCK_EX);
    }
}
