<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * View PHP biasa. Tidak ada template engine: setiap nilai di-escape dengan e().
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::capture(self::path($view), $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture(self::path('layouts/' . $layout), $data + ['content' => $content]);
    }

    /**
     * Potongan view tanpa layout, untuk dipakai di dalam view lain.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture(self::path('partials/' . $view), $data);
    }

    private static function path(string $view): string
    {
        $file = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('View tidak ditemukan: ' . $view);
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }
}
