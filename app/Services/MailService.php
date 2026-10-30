<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Pengiriman email lewat PHPMailer (app/Vendor/PHPMailer, dimuat autoloader
 * di bootstrap.php — proyek ini tidak memakai Composer).
 *
 * Sengaja tanpa "driver log" atau penampung palsu. Email yang hanya dicatat
 * ke berkas membuat fitur verifikasi tampak jalan padahal warga tidak pernah
 * menerima apa pun; lebih baik aplikasi mengaku SMTP-nya belum disetel.
 * Karena itu siap() dipanggil sebelum alur yang bergantung pada email.
 */
final class MailService
{
    /** Apakah SMTP sudah cukup disetel untuk mencoba mengirim. */
    public static function siap(): bool
    {
        return (string) config('mail.host', '') !== '';
    }

    /**
     * Mengirim satu email HTML. Melempar RuntimeException bila gagal —
     * pemanggil yang memutuskan apakah itu menggagalkan seluruh aksinya.
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html, string $text = ''): void
    {
        if (!self::siap()) {
            throw new RuntimeException('SMTP belum disetel (MAIL_HOST kosong di .env).');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->Host = (string) config('mail.host');
            $mail->Port = (int) config('mail.port', 587);
            $mail->Timeout = (int) config('mail.timeout', 15);
            $mail->SMTPDebug = (int) config('mail.debug', 0);
            $mail->Debugoutput = static function (string $pesan): void {
                error_log('[smtp] ' . rtrim($pesan));
            };

            $mail->SMTPAuth = (bool) config('mail.auth', true);

            if ($mail->SMTPAuth) {
                $mail->Username = (string) config('mail.username', '');
                $mail->Password = (string) config('mail.password', '');
            }

            $enkripsi = strtolower((string) config('mail.encryption', 'tls'));

            if ($enkripsi === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($enkripsi === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                // Penampung SMTP lokal tidak bicara TLS sama sekali.
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom(
                (string) config('mail.from.address'),
                (string) config('mail.from.name')
            );

            $balas = (string) config('mail.reply_to', '');

            if ($balas !== '') {
                $mail->addReplyTo($balas);
            }

            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text !== '' ? $text : self::toText($html);

            $mail->send();
        } catch (PHPMailerException $e) {
            // ErrorInfo lebih berguna daripada pesan pengecualiannya, tetapi
            // keduanya bisa memuat alamat server — jangan ditampilkan mentah
            // ke pengunjung; pemanggil yang meringkasnya.
            throw new RuntimeException($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(), 0, $e);
        }
    }

    /**
     * Versi teks biasa dari badan HTML, untuk klien email yang tidak
     * menampilkan HTML. Tautan dipertahankan karena di email verifikasi
     * justru tautan itulah isinya.
     */
    private static function toText(string $html): string
    {
        $s = preg_replace('/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2: $1', $html) ?? $html;
        $s = preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/tr)\s*\/?>/i', "\n", $s) ?? $s;
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/[ \t]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\n{3,}/', "\n\n", $s) ?? $s;

        return trim($s);
    }
}
