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
 * Verifikasi alamat email.
 *
 * Seluruh halaman di sini terbuka tanpa login — justru karena akun yang
 * belum terverifikasi tidak bisa masuk, pemiliknya tidak punya sesi untuk
 * meminta tautan baru.
 */
final class VerificationController extends Controller
{
    /** Halaman "cek email Anda", sekaligus tempat meminta tautan baru. */
    public function notice(Request $request): Response
    {
        if (Auth::check() && (int) (Auth::user()['is_verified'] ?? 0) === 1) {
            return $this->redirect('/akun');
        }

        return $this->view('auth/verify-notice', [
            'title' => 'Verifikasi email',
            'email' => (string) Session::getFlash('email_belum_verif', ''),
            'siap' => MailService::siap(),
            'jam' => max(1, (int) config('mail.verification.ttl_hours', 24)),
        ]);
    }

    /**
     * Membuka tautan dari email.
     *
     * @param array<string, string> $params
     */
    public function confirm(Request $request, array $params): Response
    {
        $row = EmailVerification::findUsable((string) ($params['token'] ?? ''));

        if ($row === null) {
            // Tidak ada, sudah terpakai, atau kedaluwarsa — ketiganya dijawab
            // sama. Membedakannya hanya memberi tahu penebak mana yang pernah
            // ada, tanpa menolong pemilik akun yang sah.
            Session::flash('error', 'Tautan verifikasi tidak berlaku lagi. Minta tautan baru di bawah.');

            return $this->redirect('/verifikasi');
        }

        $user = User::find((int) $row['user_id']);

        if ($user === null) {
            Session::flash('error', 'Akun yang ditautkan sudah tidak ada.');

            return $this->redirect('/verifikasi');
        }

        /*
         * Satu tautan, dua maksud. Bila akun punya pending_email, tautan
         * terakhir yang diterbitkan untuknya adalah konfirmasi penggantian
         * alamat — menerbitkan token baru selalu membatalkan yang lama,
         * jadi tidak ada dua jenis tautan hidup bersamaan.
         */
        $pending = (string) ($user['pending_email'] ?? '');

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
         * Sengaja tidak langsung memberi sesi. Tautan ini melintasi kotak
         * surat dan bisa saja dibuka di perangkat yang bukan milik pemiliknya
         * — meminta sandi sekali lagi memastikan yang masuk memang dia.
         */
        Session::flash('success', 'Email ' . $user['email'] . ' terverifikasi. Silakan masuk.');

        return $this->redirectVia('/masuk', 'Email terverifikasi', 'Membuka halaman masuk…');
    }

    /** Mengirim ulang tautan verifikasi. */
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
        $user = User::findByEmail($email);

        /*
         * Jawabannya selalu sama, apa pun kenyataannya: akunnya ada, tidak
         * ada, atau sudah terverifikasi. Halaman ini terbuka untuk siapa
         * saja, jadi jawaban yang berbeda-beda akan berubah menjadi alat
         * untuk memeriksa alamat email mana yang terdaftar di sini.
         */
        $jawaban = 'Kalau alamat itu terdaftar dan belum terverifikasi, tautan barunya sudah dikirim. '
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
