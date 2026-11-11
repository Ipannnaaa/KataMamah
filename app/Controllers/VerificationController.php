<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\MailService;
use App\Services\VerificationService;
use Throwable;

/**
 * Verifikasi alamat email dengan kode enam angka.
 *
 * Seluruh halaman di sini terbuka tanpa login — justru karena akun yang
 * belum terverifikasi tidak bisa masuk, pemiliknya tidak punya sesi untuk
 * meminta kode baru.
 *
 * Alamat yang sedang menunggu disimpan di sesi, bukan hanya di flash:
 * flash habis sekali baca, sedangkan halaman ini dimuat ulang setiap kali
 * kodenya salah. Tanpa itu, pengguna harus mengetik ulang alamatnya pada
 * setiap percobaan.
 */
final class VerificationController extends Controller
{
    private const KUNCI_EMAIL = 'verif_email';

    /** Halaman "masukkan kode", sekaligus tempat meminta kode baru. */
    public function notice(Request $request): Response
    {
        if (Auth::check() && (int) (Auth::user()['is_verified'] ?? 0) === 1) {
            return $this->redirect('/akun');
        }

        $dariFlash = (string) Session::getFlash('email_belum_verif', '');

        if ($dariFlash !== '') {
            Session::put(self::KUNCI_EMAIL, $dariFlash);
        }

        return $this->view('auth/verify-notice', [
            'title' => 'Verifikasi email',
            'email' => (string) Session::get(self::KUNCI_EMAIL, ''),
            'siap' => MailService::siap(),
            'menit' => EmailVerification::ttlMenit(),
            'percobaan' => EmailVerification::maxPercobaan(),
        ]);
    }

    /**
     * Memeriksa kode yang dimasukkan.
     *
     * Kodenya dicari per akun, tidak pernah global. Enam angka hanya punya
     * sejuta kemungkinan: pencarian global akan membuat satu tebakan acak
     * berlaku untuk semua akun yang sedang menunggu sekaligus, dan peluang
     * kenanya naik sebanding dengan jumlah akun itu.
     */
    public function confirm(Request $request): Response
    {
        $limit = (array) config('app.rate_limit.verify_code', ['max' => 10, 'decay' => 900]);
        $key = 'verify-code:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            $menit = max(1, (int) ceil(RateLimiter::secondsLeft($key) / 60));

            return $this->failure(
                'Terlalu banyak percobaan. Coba lagi dalam ' . $menit . ' menit.',
                '/verifikasi'
            );
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:160',
            'kode' => 'required|digits:6',
        ], [
            'email' => 'Email',
            'kode' => 'Kode verifikasi',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors('/verifikasi', $validator->errors(), [
                'email' => $request->str('email'),
            ]);
        }

        RateLimiter::hit($key, (int) $limit['decay']);

        $email = $request->str('email');
        Session::put(self::KUNCI_EMAIL, $email);

        /*
         * Dicari juga lewat pending_email. Pada penggantian alamat, kodenya
         * dikirim ke alamat BARU sementara akunnya masih terdaftar dengan
         * yang lama — orang yang menerima kode itu wajar mengetik alamat
         * yang menerimanya, dan tanpa pencarian kedua ini kodenya tidak
         * akan pernah cocok.
         */
        $user = User::findByEmail($email) ?? User::findByPendingEmail($email);
        $row = $user === null
            ? null
            : EmailVerification::match((int) $user['id'], $request->str('kode'));

        /*
         * Akun tidak ada, tidak punya kode hidup, kodenya salah, atau
         * jatah percobaannya habis — keempatnya dijawab sama. Membedakannya
         * memberi tahu penebak alamat mana yang terdaftar dan sudah sedekat
         * apa tebakannya, tanpa menolong pemilik akun yang sah.
         */
        if ($user === null || $row === null) {
            return $this->failure(
                'Kode salah atau sudah tidak berlaku. Minta kode baru di bawah.',
                '/verifikasi'
            );
        }

        /*
         * Satu kode, dua maksud. Bila akun punya pending_email, kode
         * terakhir yang diterbitkan untuknya adalah konfirmasi penggantian
         * alamat — menerbitkan kode baru selalu membatalkan yang lama, jadi
         * tidak ada dua jenis kode hidup bersamaan.
         */
        $pending = (string) ($user['pending_email'] ?? '');
        Session::forget(self::KUNCI_EMAIL);

        if ($pending !== '') {
            // Alamat itu bisa saja keburu dipakai akun lain sejak diminta.
            if (User::emailExists($pending, (int) $user['id'])) {
                EmailVerification::consume($row);
                User::clearPendingEmail((int) $user['id']);
                Session::flash('error', 'Alamat ' . $pending . ' sudah dipakai akun lain. Alamat lama tetap berlaku.');

                return $this->redirect('/masuk');
            }

            EmailVerification::consume($row);
            User::promotePendingEmail((int) $user['id']);

            Session::flash('success', 'Alamat akun Anda sekarang ' . $pending . '. Silakan masuk dengan alamat itu.');

            return $this->redirectVia('/masuk', 'Alamat email diganti', 'Membuka halaman masuk…');
        }

        EmailVerification::consume($row);

        /*
         * Sengaja tidak langsung memberi sesi. Kode ini melintasi kotak
         * surat dan bisa saja dibaca di perangkat yang bukan milik
         * pemiliknya — meminta sandi sekali lagi memastikan yang masuk
         * memang dia.
         */
        Session::flash('success', 'Email ' . $user['email'] . ' terverifikasi. Silakan masuk.');

        return $this->redirectVia('/masuk', 'Email terverifikasi', 'Membuka halaman masuk…');
    }

    /** Mengirim ulang kode verifikasi. */
    public function resend(Request $request): Response
    {
        $limit = (array) config('app.rate_limit.verify', ['max' => 3, 'decay' => 900]);
        $key = 'verify:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            $menit = (int) ceil(RateLimiter::secondsLeft($key) / 60);

            return $this->failure('Terlalu banyak permintaan. Coba lagi dalam ' . $menit . ' menit.', '/verifikasi');
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:160',
        ], ['email' => 'Email']);

        if ($validator->fails()) {
            return $this->backWithErrors('/verifikasi', $validator->errors(), ['email' => $request->str('email')]);
        }

        RateLimiter::hit($key, (int) $limit['decay']);

        $email = $request->str('email');
        Session::put(self::KUNCI_EMAIL, $email);
        $user = User::findByEmail($email);

        /*
         * Jawabannya selalu sama, apa pun kenyataannya: akunnya ada, tidak
         * ada, atau sudah terverifikasi. Halaman ini terbuka untuk siapa
         * saja, jadi jawaban yang berbeda-beda akan berubah menjadi alat
         * untuk memeriksa alamat email mana yang terdaftar di sini.
         */
        $jawaban = 'Kalau alamat itu terdaftar dan belum terverifikasi, kode barunya sudah dikirim. '
            . 'Periksa juga folder spam.';

        if ($user === null || (int) $user['is_verified'] === 1) {
            return $this->success($jawaban, '/verifikasi');
        }

        // Jeda per akun menahan satu alamat dibanjiri lewat banyak IP.
        if (EmailVerification::cooldownMinutes((int) $user['id']) > 0) {
            return $this->success($jawaban, '/verifikasi');
        }

        try {
            VerificationService::send($user, $request->ip());
        } catch (Throwable $e) {
            error_log('[verifikasi] gagal kirim ulang ke ' . $email . ': ' . $e->getMessage());

            return $this->failure(
                MailService::siap()
                    ? 'Email gagal dikirim. Coba lagi beberapa saat lagi.'
                    : 'Email belum bisa dikirim karena SMTP belum disetel. Hubungi pengelola.',
                '/verifikasi'
            );
        }

        return $this->success($jawaban, '/verifikasi');
    }
}
