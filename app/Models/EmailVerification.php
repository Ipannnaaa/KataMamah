<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Tautan verifikasi email.
 *
 * Token aslinya tidak pernah disimpan — hanya SHA-256-nya. Yang memegang
 * basis data karena itu tidak bisa menyusun ulang tautan yang masih
 * berlaku; satu-satunya salinan token ada di email yang sudah terkirim.
 *
 * SHA-256 cukup di sini (bukan password_hash) karena tokennya 32 byte acak
 * dari random_bytes: tidak ada yang bisa ditebak, jadi hash lambat tidak
 * menambah apa pun selain beban saat memverifikasi.
 */
final class EmailVerification
{
    /**
     * Menerbitkan token baru dan mengembalikan token mentahnya — satu-satunya
     * kesempatan membacanya, karena yang tersimpan hanya hash.
     *
     * Token lama pengguna yang sama ditandai terpakai lebih dulu: meminta
     * tautan baru harus membuat tautan lama tidak berlaku, kalau tidak
     * email lama yang bocor tetap bisa dipakai.
     */
    public static function issue(int $userId, string $ip = ''): string
    {
        self::invalidateFor($userId);

        $token = bin2hex(random_bytes(32));
        $jam = max(1, (int) config('mail.verification.ttl_hours', 24));

        DB::insert('email_verifications', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $jam * 3600),
            'ip' => $ip !== '' ? mb_substr($ip, 0, 45) : null,
        ]);

        return $token;
    }

    /**
     * Mencari token yang masih berlaku. Mengembalikan null bila tidak ada,
     * sudah terpakai, atau kedaluwarsa — pemanggil tidak perlu membedakan
     * ketiganya di hadapan pengunjung.
     *
     * @return array<string, mixed>|null
     */
    public static function findUsable(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }

        return DB::first(
            'SELECT * FROM email_verifications
              WHERE token_hash = ?
                AND used_at IS NULL
                AND expires_at > NOW()
              LIMIT 1',
            [hash('sha256', $token)]
        );
    }

    /**
     * Menandai token terpakai sekaligus memverifikasi akunnya.
     *
     * Dua tabel sekaligus, jadi dibungkus transaksi: akun yang terverifikasi
     * tanpa tokennya ikut tertutup berarti tautan yang sama masih bisa
     * dipakai lagi.
     */
    public static function consume(array $row): void
    {
        DB::transaction(static function () use ($row): void {
            DB::update(
                'email_verifications',
                ['used_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $row['id']]
            );

            DB::update(
                'users',
                ['is_verified' => 1],
                'id = :id',
                ['id' => $row['user_id']]
            );
        });
    }

    /** Berapa menit lagi pengguna ini boleh meminta tautan baru; 0 berarti boleh sekarang. */
    public static function cooldownMinutes(int $userId, int $jeda = 2): int
    {
        $terakhir = DB::column(
            'SELECT created_at FROM email_verifications
              WHERE user_id = ? ORDER BY id DESC LIMIT 1',
            [$userId]
        );

        if ($terakhir === null) {
            return 0;
        }

        $lewat = time() - (int) strtotime((string) $terakhir);

        return $lewat >= $jeda * 60 ? 0 : (int) ceil(($jeda * 60 - $lewat) / 60);
    }

    private static function invalidateFor(int $userId): void
    {
        DB::run(
            'UPDATE email_verifications SET used_at = NOW()
              WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
    }
}
