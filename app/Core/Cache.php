<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache berkas sederhana untuk hasil perhitungan yang mahal.
 *
 * Dipakai scene peta, yang perhitungannya berat tetapi hasilnya hanya berubah
 * ketika data kecamatan atau kantor layanan berubah.
 */
final class Cache
{
    /**
     * Ambil dari cache, atau hitung lalu simpan.
     *
     * @param callable(): mixed $builder
     */
    public static function remember(string $key, callable $builder, int $seconds = 86400): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $builder();

        self::put($key, $value, $seconds);

        return $value;
    }

    public static function get(string $key): mixed
    {
        $file = self::file($key);

        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $entry = json_decode($raw, true);

        if (!is_array($entry) || !array_key_exists('expires', $entry)) {
            return null;
        }

        if ((int) $entry['expires'] < time()) {
            @unlink($file);

            return null;
        }

        return $entry['value'] ?? null;
    }

    public static function put(string $key, mixed $value, int $seconds = 86400): void
    {
        $encoded = json_encode(['expires' => time() + $seconds, 'value' => $value]);

        if ($encoded === false) {
            return;
        }

        @file_put_contents(self::file($key), $encoded, LOCK_EX);
    }

    /**
     * Mengosongkan seluruh cache. Dipakai setelah seeder berjalan.
     */
    public static function flush(): int
    {
        $dir = self::dir();
        $count = 0;

        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $entry) {
            if (substr($entry, -5) !== '.json') {
                continue;
            }

            if (@unlink($dir . '/' . $entry)) {
                $count++;
            }
        }

        return $count;
    }

    private static function dir(): string
    {
        $dir = BASE_PATH . '/storage/cache/data';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function file(string $key): string
    {
        return self::dir() . '/' . sha1($key) . '.json';
    }
}
