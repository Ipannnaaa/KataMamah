<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Debug menyala: tampilkan jejak. Debug mati: catat ke storage/logs dan
 * tampilkan halaman 500 yang netral.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', config('app.debug', false) ? '1' : '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(Throwable $e): void
    {
        self::log($e);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (config('app.debug', false)) {
            echo '<pre style="padding:24px;font:14px/1.6 ui-monospace,monospace;white-space:pre-wrap">';
            echo e(get_class($e) . ': ' . $e->getMessage()) . "\n\n";
            echo e($e->getFile() . ':' . $e->getLine()) . "\n\n";
            echo e($e->getTraceAsString());
            echo '</pre>';

            return;
        }

        // Halaman 500 yang sama rupanya dengan error lain. Dibungkus try
        // karena penyebab aslinya bisa saja ada di lapisan view atau sesi:
        // kalau merender pun gagal, jangan sampai pengunjung menerima
        // halaman kosong.
        try {
            echo View::render('errors/status', [
                'status' => 500,
                'message' => '',
                'title' => error_info(500)['judul'],
            ], 'public');

            return;
        } catch (Throwable $inner) {
            self::log($inner);
        }

        echo '<!doctype html><meta charset="utf-8"><title>Terjadi kesalahan</title>';
        echo '<div style="font:16px/1.6 system-ui;padding:48px;max-width:40em;margin:0 auto">';
        echo '<h1>Terjadi kesalahan di server</h1>';
        echo '<p>Permintaan Anda tidak dapat diproses. Silakan coba beberapa saat lagi.</p>';
        echo '</div>';
    }

    public static function log(Throwable $e): void
    {
        $dir = BASE_PATH . '/storage/logs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    }
}
