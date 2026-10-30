<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use App\Models\EmailVerification;
use RuntimeException;

/**
 * Menerbitkan tautan verifikasi dan mengirimnya.
 *
 * Dipisah dari MailService supaya yang satu tetap soal pengiriman dan yang
 * ini soal verifikasi — keduanya berubah karena alasan yang berbeda.
 */
final class VerificationService
{
    /**
     * Menerbitkan token baru lalu mengirim emailnya.
     *
     * Melempar RuntimeException bila SMTP belum disetel atau pengiriman
     * gagal. Token sudah terbit sebelum email dicoba, jadi tautan lama
     * tetap batal walaupun pengiriman gagal — itu disengaja: lebih baik
     * pengguna meminta ulang daripada ada dua tautan hidup bersamaan.
     *
     * @param array<string, mixed> $user
     */
    public static function send(array $user, string $ip = ''): void
    {
        if (!MailService::siap()) {
            throw new RuntimeException('SMTP belum disetel.');
        }

        $token = EmailVerification::issue((int) $user['id'], $ip);
        $jam = max(1, (int) config('mail.verification.ttl_hours', 24));

        $html = View::render('emails/verify', [
            'nama' => (string) $user['name'],
            'tautan' => url('/verifikasi/' . $token),
            'jam' => $jam,
        ]);

        MailService::send(
            (string) $user['email'],
            (string) $user['name'],
            'Verifikasi email Anda · ' . (string) config('app.name'),
            $html
        );
    }

    /**
     * Tautan konfirmasi penggantian alamat email.
     *
     * Dikirim ke alamat BARU (users.pending_email), bukan ke yang lama:
     * yang perlu dibuktikan adalah bahwa alamat baru itu benar-benar bisa
     * diakses pemiliknya. Alamat lama tetap berlaku sampai tautan ini
     * dibuka, jadi satu salah ketik tidak mengunci siapa pun.
     *
     * @param array<string, mixed> $user
     */
    public static function sendEmailChange(array $user, string $ip = ''): void
    {
        $tujuan = (string) ($user['pending_email'] ?? '');

        if ($tujuan === '') {
            throw new RuntimeException('Tidak ada alamat baru yang menunggu konfirmasi.');
        }

        if (!MailService::siap()) {
            throw new RuntimeException('SMTP belum disetel.');
        }

        $token = EmailVerification::issue((int) $user['id'], $ip);
        $jam = max(1, (int) config('mail.verification.ttl_hours', 24));

        $html = View::render('emails/verify-change', [
            'nama' => (string) $user['name'],
            'lama' => (string) $user['email'],
            'baru' => $tujuan,
            'tautan' => url('/verifikasi/' . $token),
            'jam' => $jam,
        ]);

        MailService::send(
            $tujuan,
            (string) $user['name'],
            'Konfirmasi penggantian email · ' . (string) config('app.name'),
            $html
        );
    }
}
