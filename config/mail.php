<?php

declare(strict_types=1);

/**
 * Pengiriman email lewat PHPMailer (app/Vendor/PHPMailer).
 *
 * Tidak ada "driver log" atau penampung palsu di sini: email yang hanya
 * tercatat di berkas log membuat fitur ini tampak jalan padahal belum.
 * Bila SMTP belum diisi, aplikasi mengatakannya terus terang — lihat
 * MailService::siap().
 */
return [
    // Semua dibaca dari .env karena kredensial tidak boleh masuk repositori.
    'host' => (string) env('MAIL_HOST', ''),
    'port' => (int) env('MAIL_PORT', 587),
    'username' => (string) env('MAIL_USERNAME', ''),
    'password' => (string) env('MAIL_PASSWORD', ''),

    // 'tls' (STARTTLS, port 587), 'ssl' (SMTPS, port 465), atau '' tanpa
    // enkripsi — yang terakhir hanya masuk akal untuk penampung SMTP lokal.
    'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),

    // Kosong berarti tanpa autentikasi; penampung uji lokal umumnya begitu.
    'auth' => (bool) env('MAIL_AUTH', true),

    'from' => [
        'address' => (string) env('MAIL_FROM', 'no-reply@tubancivicmap.test'),
        'name' => (string) env('MAIL_FROM_NAME', env('APP_NAME', 'Tuban Civic Map')),
    ],

    // Dibalas ke mana bila warga menekan "reply". Kosong = tidak dipasang.
    'reply_to' => (string) env('MAIL_REPLY_TO', ''),

    'timeout' => (int) env('MAIL_TIMEOUT', 15),

    // 0 diam, 1 pesan klien, 2 beserta jawaban server. Jangan dinyalakan di
    // produksi: isinya termasuk perintah AUTH.
    'debug' => (int) env('MAIL_DEBUG', 0),

    'verification' => [
        // Lama kode verifikasi berlaku, dalam menit.
        //
        // Jauh lebih pendek daripada tautan yang dipakai sebelumnya. Kode
        // enam angka hanya punya sejuta kemungkinan, jadi umur pendek adalah
        // salah satu dari dua penahan tebakan — yang satunya batas percobaan
        // di bawah.
        'ttl_minutes' => (int) env('MAIL_CODE_TTL', 15),

        // Berapa kali salah sebelum kodenya hangus dan harus minta baru.
        'max_attempts' => (int) env('MAIL_CODE_ATTEMPTS', 5),
    ],
];
