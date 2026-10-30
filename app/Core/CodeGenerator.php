<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Kode pemesanan: awalan (misalnya TCM) + 5 karakter Crockford base32
 * (tanpa I, L, O, U) agar tidak tertukar saat dibaca atau didikte.
 * Tidak berurutan, sehingga kode orang lain tidak bisa ditebak.
 */
final class CodeGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const LENGTH = 5;

    public static function prefix(): string
    {
        return strtoupper((string) config('app.queue.code_prefix', 'TCM'));
    }

    public static function make(?string $prefix = null): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return ($prefix === null ? self::prefix() : strtoupper($prefix)) . '-' . $chars;
    }

    /**
     * Kode unik di tabel queues. Mencoba beberapa kali sebelum menyerah.
     */
    public static function uniqueQueueCode(int $attempts = 8): string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $code = self::make();

            if (DB::column('SELECT 1 FROM queues WHERE code = ? LIMIT 1', [$code]) === null) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode pemesanan yang unik.');
    }

    /**
     * Normalisasi masukan pengguna: "tcm 7kq4m" -> "TCM-7KQ4M".
     *
     * Awalan lama tetap dikenali supaya kode yang sudah telanjur dibagikan
     * masih bisa dilacak setelah penggantian nama aplikasi.
     */
    public static function normalize(string $input): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
        $prefix = self::prefix();

        foreach (array_unique([$prefix, 'WIA', 'TCM']) as $known) {
            if (strpos($clean, $known) === 0) {
                return $known . '-' . substr(substr($clean, strlen($known)), 0, self::LENGTH);
            }
        }

        return $prefix . '-' . substr($clean, 0, self::LENGTH);
    }
}
