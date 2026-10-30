<?php

declare(strict_types=1);

/**
 * Smoke test tanpa basis data.
 *
 *   php tests/smoke.php
 *
 * Memeriksa bagian yang tidak memerlukan MySQL: routing, render view,
 * escaping, generator kode, validator, CSRF, helper, dan rate limiter.
 * Alur yang menyentuh basis data (antrean, chat, rekap) belum tercakup.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\CodeGenerator;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $extra = ''): void
{
    global $pass, $fail;

    if ($ok) {
        $pass++;
        echo "  ok   $label\n";

        return;
    }

    $fail++;
    echo "  FAIL $label $extra\n";
}

Session::start();

echo "Router & view\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/jalan-yang-tidak-ada';
$_GET = [];
$_POST = [];

$router = require BASE_PATH . '/routes/web.php';
$response = $router->dispatch(Request::capture());

check('404 untuk path tak dikenal', $response->status() === 404, '(status ' . $response->status() . ')');
check('halaman 404 memakai layout publik', strpos($response->body(), '<!doctype html>') === 0);
check('halaman 404 memuat pesan', strpos($response->body(), 'tidak ditemukan') !== false);
check('aset CSS tertaut', strpos($response->body(), 'anim.css') !== false);

$_SERVER['REQUEST_URI'] = '/api/map/landmarks';
check('request API dikenali sebagai JSON', Request::capture()->wantsJson());

$_SERVER['REQUEST_METHOD'] = 'DELETE';
$_SERVER['REQUEST_URI'] = '/';
check('metode tak diizinkan menjadi 405', $router->dispatch(Request::capture())->status() === 405);

echo "\nView & escaping\n";

$html = View::render('errors/status', ['status' => 403, 'message' => 'Akses ditolak <script>'], 'public');
check('view ter-render', strpos($html, 'Kode 403') !== false);
check('output di-escape', strpos($html, '&lt;script&gt;') !== false && strpos($html, '<script>Akses') === false);

echo "\nKode pemesanan\n";

$prefix = CodeGenerator::prefix();
$code = CodeGenerator::make();
check('format ' . $prefix . '-XXXXX', preg_match('/^' . $prefix . '-[0-9A-HJKMNP-TV-Z]{5}$/', $code) === 1, "($code)");
check('tanpa huruf I, L, O, U', preg_match('/[ILOU]/', substr($code, 4)) === 0, "($code)");
check('normalisasi masukan bebas', CodeGenerator::normalize(strtolower($prefix) . ' 7kq4m') === $prefix . '-7KQ4M');
check('normalisasi tanpa prefix', CodeGenerator::normalize('7kq4m') === $prefix . '-7KQ4M');
check('awalan lama tetap dikenali', CodeGenerator::normalize('wia7kq4m') === 'WIA-7KQ4M', '(' . CodeGenerator::normalize('wia7kq4m') . ')');

$codes = [];

for ($i = 0; $i < 500; $i++) {
    $codes[CodeGenerator::make()] = true;
}

check('500 kode acak hampir seluruhnya unik', count($codes) > 490, '(' . count($codes) . ' unik)');

echo "\nValidator\n";

$bad = Validator::make([
    'email' => 'bukan-email',
    'password' => 'pendek',
    'password_confirmation' => 'lain',
    'nik' => '123',
    'phone' => '12345',
], [
    'email' => 'required|email',
    'password' => 'required|min:8',
    'password_confirmation' => 'required|same:password',
    'nik' => 'digits:16',
    'phone' => 'phone',
    'nama' => 'required',
])->errors();

check('email tidak sah ditolak', isset($bad['email']));
check('sandi pendek ditolak', isset($bad['password']));
check('konfirmasi sandi tidak cocok ditolak', isset($bad['password_confirmation']));
check('NIK bukan 16 digit ditolak', isset($bad['nik']));
check('nomor ponsel tidak sah ditolak', isset($bad['phone']));
check('field wajib yang kosong ditolak', isset($bad['nama']));

$good = Validator::make([
    'email' => 'warga@contoh.id',
    'password' => 'wia12345',
    'password_confirmation' => 'wia12345',
    'nik' => '3401011203990002',
    'phone' => '081322001180',
    'nama' => 'Rudi Maulana',
    'tanggal' => date('Y-m-d', strtotime('+2 day')),
], [
    'email' => 'required|email',
    'password' => 'required|min:8',
    'password_confirmation' => 'required|same:password',
    'nik' => 'digits:16',
    'phone' => 'phone',
    'nama' => 'required|max:120',
    'tanggal' => 'required|date|date_min:today|date_max:+30 days',
]);

check('data benar lolos semua aturan', !$good->fails(), '(' . implode('; ', $good->errors()) . ')');
check('tanggal lebih dari 30 hari ditolak', Validator::make(['t' => date('Y-m-d', strtotime('+60 day'))], ['t' => 'date_max:+30 days'])->fails());
check('tanggal lampau ditolak', Validator::make(['t' => date('Y-m-d', strtotime('-1 day'))], ['t' => 'date_min:today'])->fails());
check('tanggal hari ini diterima', !Validator::make(['t' => date('Y-m-d')], ['t' => 'date_min:today'])->fails());

echo "\nCSRF\n";

$token = Csrf::token();
check('token dibuat', strlen($token) === 64);
check('token stabil dalam satu sesi', Csrf::token() === $token);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/masuk';

$_POST = ['_token' => $token];
check('token benar diterima', Csrf::check(Request::capture()));

$_POST = ['_token' => 'palsu'];
check('token palsu ditolak', !Csrf::check(Request::capture()));

$_POST = [];
check('tanpa token ditolak', !Csrf::check(Request::capture()));

echo "\nHelper\n";

check('masking nama', mask_nama('Rudi Maulana') === 'Ru*** Ma***', '(' . mask_nama('Rudi Maulana') . ')');
check('tanggal Indonesia', tanggal('2026-10-27') === '27 Oktober 2026');
check('nama hari', hari('2026-10-27') === 'Sel');
check('jam', jam('2026-10-27 09:05:00') === '09.05');
check('rupiah', rupiah(185000) === 'Rp 185.000');
check('escape HTML', e('<b>"x"</b>') === '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');
check('label status', status_label('ditangani') === 'Ditangani');
check('kelas badge status', status_class('ditangani') === 'badge badge--ditangani');
check('config termuat', config('app.name') !== null && is_array(config('roles.transitions')));
check('transisi status terdefinisi', config('roles.transitions.baru') === ['ditinjau', 'dibatalkan']);

echo "\nRate limiter\n";

$key = 'smoke:' . bin2hex(random_bytes(4));
RateLimiter::clear($key);
check('awalnya tidak terbatas', !RateLimiter::tooManyAttempts($key, 3, 60));
RateLimiter::hit($key, 60);
RateLimiter::hit($key, 60);
check('belum mencapai batas', !RateLimiter::tooManyAttempts($key, 3, 60));
RateLimiter::hit($key, 60);
check('batas tercapai setelah 3 percobaan', RateLimiter::tooManyAttempts($key, 3, 60));
RateLimiter::clear($key);
check('reset setelah clear', !RateLimiter::tooManyAttempts($key, 3, 60));

echo "\n--------------------------------------\n";
echo "lolos: $pass   gagal: $fail\n";

exit($fail === 0 ? 0 : 1);
