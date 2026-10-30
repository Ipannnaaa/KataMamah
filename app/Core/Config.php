<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Memuat .env dan seluruh file di config/ ke satu array bertitik:
 * config('database.host'), config('app.debug').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /** @var array<string, string> */
    private static array $env = [];

    private static bool $booted = false;

    public static function boot(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::loadEnv($basePath . '/.env');

        // scandir, bukan glob(): path proyek bisa mengandung karakter yang
        // ditafsirkan glob sebagai pola — misalnya folder bernama "[WIA]",
        // yang membuat glob() mengembalikan array kosong tanpa error.
        $dir = $basePath . '/config';
        $entries = is_dir($dir) ? (scandir($dir) ?: []) : [];

        foreach ($entries as $entry) {
            if (substr($entry, -4) !== '.php') {
                continue;
            }

            $values = require $dir . '/' . $entry;

            if (is_array($values)) {
                self::$items[basename($entry, '.php')] = $values;
            }
        }

        self::$booted = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$env)) {
            return $default;
        }

        $value = self::$env[$key];

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            '' => $default,
            default => $value,
        };
    }

    private static function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }

            self::$env[$key] = $value;
        }
    }
}
