<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;

/**
 * Escape untuk output HTML. Dipakai di SEMUA view, tanpa kecuali.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function env(string $key, mixed $default = null): mixed
{
    return Config::env($key, $default);
}

/**
 * URL absolut di dalam aplikasi: url('/lokasi/dukcapil-sentolo').
 */
function url(string $path = '/'): string
{
    $base = rtrim((string) config('app.url', ''), '/');

    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/**
 * Nilai input sebelumnya setelah validasi gagal.
 */
function old(string $key, string $default = ''): string
{
    $old = Session::get('_old', []);

    return (string) ($old[$key] ?? $default);
}

function errors(string $key): ?string
{
    $errors = Session::get('_errors', []);

    return $errors[$key] ?? null;
}

function rupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Tanggal panjang Indonesia: 27 Oktober 2026.
 */
function tanggal(?string $date): string
{
    if ($date === null || $date === '') {
        return '-';
    }

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $ts = strtotime($date);

    if ($ts === false) {
        return '-';
    }

    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Nama hari pendek: Sen, Sel, Rab …
 */
function hari(?string $date): string
{
    if ($date === null || $date === '') {
        return '-';
    }

    $ts = strtotime($date);

    if ($ts === false) {
        return '-';
    }

    return ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][(int) date('N', $ts) - 1];
}

function jam(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }

    $ts = strtotime($datetime);

    return $ts === false ? '-' : date('H.i', $ts);
}

function status_label(string $status): string
{
    return (string) config('roles.status.' . $status, $status);
}

/**
 * Kelas CSS badge status. Warnanya diatur di components.css, bukan di sini.
 */
function status_class(string $status): string
{
    return 'badge badge--' . preg_replace('/[^a-z]/', '', $status);
}

function pay_label(string $status): string
{
    return (string) config('roles.payment_status.' . $status, $status);
}

/**
 * Masking nama untuk halaman cek antrean publik: "Rudi Maulana" -> "Ru*** M***".
 */
function mask_nama(string $nama): string
{
    $parts = preg_split('/\s+/', trim($nama)) ?: [];

    $masked = array_map(static function (string $part): string {
        $len = mb_strlen($part);

        if ($len <= 1) {
            return $part . '***';
        }

        return mb_substr($part, 0, $len > 3 ? 2 : 1) . '***';
    }, $parts);

    return implode(' ', $masked);
}

/**
 * Teks halaman error untuk sebuah kode status.
 *
 * Satu sumber untuk <title>, judul halaman, dan keterangannya, supaya
 * ketiganya tidak pernah berbeda. Kode yang tidak terdaftar di config/errors
 * jatuh ke entri 'default'.
 *
 * @return array{judul: string, ket: string, saran: string}
 */
function error_info(int $status): array
{
    $default = (array) config('errors.default', [
        'judul' => 'Permintaan tidak dapat diproses',
        'ket' => 'Terjadi sesuatu yang tidak kami perkirakan.',
        'saran' => 'Coba ulangi, atau kembali ke peta layanan.',
    ]);

    $info = (array) config('errors.' . $status, $default);

    return [
        'judul' => (string) ($info['judul'] ?? $default['judul']),
        'ket' => (string) ($info['ket'] ?? $default['ket']),
        'saran' => (string) ($info['saran'] ?? $default['saran']),
    ];
}

/**
 * Tarif layanan sebagai teks.
 *
 * null dan 0 harus dibedakan: 0 berarti layanan itu memang gratis menurut
 * peraturan, null berarti tarifnya belum dikonfirmasi ke instansi.
 * Menampilkan keduanya sebagai "Gratis" pernah membuat halaman layanan
 * menjanjikan layanan berbayar sebagai cuma-cuma.
 */
function tarif(float|int|string|null $fee): string
{
    if ($fee === null || $fee === '') {
        return 'Perlu dikonfirmasi';
    }

    return (float) $fee > 0 ? rupiah((float) $fee) : 'Gratis';
}
