<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\District;
use App\Models\User;
use App\Services\MailService;
use App\Services\VerificationService;
use Throwable;

final class AuthController extends Controller
{
    public function login(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect($this->homeFor((string) Auth::role()));
        }

        if (!$request->isPost()) {
            return $this->view('auth/login', [
                'title' => 'Masuk',
                'next' => $request->str('next'),
            ]);
        }

        $limit = (array) config('app.rate_limit.login', ['max' => 5, 'decay' => 900]);
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            $minutes = (int) ceil(RateLimiter::secondsLeft($key) / 60);

            return $this->backWithErrors('/masuk', [
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam ' . $minutes . ' menit.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:160',
            'password' => 'required',
        ], ['email' => 'Email', 'password' => 'Kata sandi']);

        if ($validator->fails()) {
            return $this->backWithErrors('/masuk', $validator->errors(), ['email' => $request->str('email')]);
        }

        $email = $request->str('email');
        $user = Auth::attempt($email, $request->str('password'));

        Auth::recordAttempt($email, $request->ip(), $user !== null);

        if ($user === null) {
            RateLimiter::hit($key, (int) $limit['decay']);

            return $this->backWithErrors('/masuk', [
                'email' => 'Email atau kata sandi tidak cocok.',
            ], ['email' => $email]);
        }

        RateLimiter::clear($key);

        /*
         * Sandinya benar, tetapi emailnya belum dipastikan miliknya.
         *
         * Pemeriksaan ini sengaja diletakkan setelah sandi dicocokkan: orang
         * yang belum tahu sandinya tidak boleh bisa memakai halaman masuk
         * untuk mencari tahu alamat mana yang terdaftar tapi belum aktif.
         *
         * Berlaku untuk semua peran. Akun petugas dan admin dibuat lewat
         * seeder dengan is_verified = 1, jadi tidak ada yang terkunci di
         * luar kehendak; akun staf yang dibuat belakangan harus mengikuti.
         */
        if ((int) $user['is_verified'] !== 1) {
            Session::flash('email_belum_verif', (string) $user['email']);

            return $this->backWithErrors('/masuk', [
                'email' => 'Email ini belum diverifikasi. Buka tautan yang kami kirim, '
                    . 'atau minta tautan baru di bawah.',
            ], ['email' => $email]);
        }

        Auth::login($user);

        $next = $request->str('next');
        $target = $next !== '' && strpos($next, '/') === 0 ? $next : $this->homeFor((string) $user['role']);

        Session::flash('success', 'Selamat datang, ' . $user['name'] . '.');

        return $this->redirectVia($target, 'Berhasil masuk', 'Membuka beranda akun Anda…');
    }

    public function register(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect($this->homeFor((string) Auth::role()));
        }

        if (!$request->isPost()) {
            return $this->view('auth/register', [
                'title' => 'Buat akun warga',
                'districts' => District::all(),
            ]);
        }

        $labels = [
            'name' => 'Nama lengkap',
            'email' => 'Email',
            'phone' => 'Nomor ponsel',
            'nik' => 'NIK',
            'password' => 'Kata sandi',
            'password_confirmation' => 'Ulangi sandi',
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:120',
            'email' => 'required|email|max:160',
            'phone' => 'phone',
            'nik' => 'digits:16',
            'password' => 'required|min:8|max:72',
            'password_confirmation' => 'required|same:password',
            'setuju' => 'accepted',
        ], $labels);

        $errors = $validator->errors();
        $email = $request->str('email');
        $nik = $request->str('nik');

        // Email yang sudah dipakai mengambil antrean sebagai tamu boleh
        // "diklaim": akunnya sudah ada, tinggal diberi sandi.
        $existing = User::findByEmail($email);
        $claiming = $existing !== null && User::isClaimable($existing);

        if (!isset($errors['email']) && $existing !== null && !$claiming) {
            $errors['email'] = 'Email ini sudah terdaftar.';
        }

        if ($nik !== '' && !isset($errors['nik']) && User::nikExists($nik, $claiming ? (int) $existing['id'] : null)) {
            $errors['nik'] = 'NIK ini sudah terdaftar.';
        }

        if ($errors !== []) {
            return $this->backWithErrors('/daftar', $errors, [
                'name' => $request->str('name'),
                'email' => $email,
                'phone' => $request->str('phone'),
                'nik' => $nik,
                'district_id' => $request->str('district_id'),
            ]);
        }

        $fields = [
            'name' => $request->str('name'),
            'email' => $email,
            'phone' => $request->str('phone') !== '' ? $request->str('phone') : null,
            'nik' => $nik !== '' ? $nik : null,
            'district_id' => $request->int('district_id') > 0 ? $request->int('district_id') : null,
        ];

        if ($claiming) {
            $fields['password_hash'] = password_hash($request->str('password'), PASSWORD_DEFAULT);
            DB::update('users', $fields, 'id = :id', ['id' => $existing['id']]);
            $userId = (int) $existing['id'];
            $lanjutan = 'Antrean yang pernah Anda ambil sebelumnya sudah tergabung di akun ini.';
        } else {
            $userId = User::create($fields + ['role' => 'user'], $request->str('password'));
            $lanjutan = '';
        }

        $user = User::find($userId);

        /*
         * Tidak ada Auth::login() di sini lagi.
         *
         * Akun baru selalu lahir belum terverifikasi, dan akun yang belum
         * terverifikasi tidak boleh masuk — memberinya sesi langsung di
         * halaman daftar akan melewati aturan itu lewat pintu belakang.
         */
        Session::flash('email_belum_verif', $email);

        try {
            if ($user !== null) {
                VerificationService::send($user, $request->ip());
            }

            Session::flash(
                'success',
                trim('Akun dibuat. Tautan verifikasi sudah dikirim ke ' . $email . '. ' . $lanjutan)
            );
        } catch (Throwable $e) {
            // Akunnya tetap ada, tetapi belum bisa dipakai. Katakan apa
            // adanya — pengunjung yang menunggu email yang tidak pernah
            // datang akan menyangka aplikasinya rusak.
            error_log('[verifikasi] gagal kirim ke ' . $email . ': ' . $e->getMessage());

            Session::flash('error', MailService::siap()
                ? 'Akun dibuat, tetapi email verifikasi gagal dikirim. Coba minta tautan baru di bawah.'
                : 'Akun dibuat, tetapi email verifikasi belum bisa dikirim karena SMTP belum disetel. Hubungi pengelola.');
        }

        return $this->redirectVia('/verifikasi', 'Akun dibuat', 'Membuka halaman verifikasi…');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'Anda telah keluar.');

        return $this->redirect('/');
    }

    private function homeFor(string $role): string
    {
        return (string) config('roles.home.' . $role, '/akun');
    }
}
