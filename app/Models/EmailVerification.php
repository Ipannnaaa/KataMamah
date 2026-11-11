<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Kode verifikasi email.
 *
 * Kode aslinya tidak pernah disimpan, hanya hash bcrypt-nya. Yang memegang
 * basis data karena itu tidak bisa membaca kode yang masih berlaku.
 *
 * Bcrypt, bukan SHA-256 seperti token panjang yang dipakai sebelumnya.
 * Token 32 byte acak tidak mungkin ditebak sehingga hash cepat sudah cukup,
 * tetapi kode enam angka hanya punya sejuta kemungkinan: dengan SHA-256,
 * basis data yang bocor bisa dicocokkan habis dalam hitungan detik.
 *
 * Tiga hal menahan penebakan, dan ketiganya diperlukan:
 *   1. Kode dicari per akun, tidak pernah global. Pencarian global membuat
 *      satu tebakan berlaku untuk semua akun yang sedang menunggu sekaligus.
 *   2. Salah beberapa kali membuat kodenya hangus.
 *   3. Umurnya pendek, hitungan menit.
 */
final class EmailVerification
{
    /**
     * Menerbitkan kode baru dan mengembalikan kode mentahnya — satu-satunya
     * kesempatan membacanya, karena yang tersimpan hanya hash.
     *
     * Kode lama pengguna yang sama ditandai terpakai lebih dulu: meminta
     * kode baru harus membuat yang lama batal, kalau tidak email lama yang
     * bocor tetap bisa dipakai.
     */
    public static function issue(int $userId, string $ip = ''): string
    {
        self::invalidateFor($userId);

        // random_int, bukan rand atau mt_rand: yang terakhir bisa diramalkan
        // dari beberapa keluaran sebelumnya.
        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $menit = self::ttlMenit();

        DB::insert('email_verifications', [
            'user_id' => $userId,
            'code_hash' => password_hash($kode, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + $menit * 60),
            'ip' => $ip !== '' ? mb_substr($ip, 0, 45) : null,
        ]);

        return $kode;
    }

    /**
     * Mencocokkan kode milik satu akun.
     *
     * Mengembalikan baris verifikasinya bila cocok, atau null bila tidak ada
     * kode hidup, kodenya salah, atau percobaannya sudah habis. Pemanggil
     * tidak perlu membedakan ketiganya di hadapan pengunjung: membedakannya
     * hanya memberi tahu penebak sudah sedekat apa dia.
     *
     * @return array<string, mixed>|null
     */
    public static function match(int $userId, string $kode): ?array
    {
        $kode = trim($kode);

        if ($kode === '' || !ctype_digit($kode)) {
            return null;
        }

        $row = DB::first(
            'SELECT * FROM email_verifications
              WHERE user_id = ?
                AND used_at IS NULL
                AND expires_at > NOW()
           ORDER BY id DESC
              LIMIT 1',
            [$userId]
        );

        if ($row === null) {
            return null;
        }

        if ((int) $row['attempts'] >= self::maxPercobaan()) {
            // Sudah habis jatahnya. Ditutup sekalian supaya tidak perlu
            // diperiksa lagi pada percobaan berikutnya.
            self::invalidateFor($userId);

            return null;
        }

        if (!password_verify($kode, (string) $row['code_hash'])) {
            DB::run(
                'UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?',
                [$row['id']]
            );

            return null;
        }

        return $row;
    }

    /**
     * Menandai kode terpakai sekaligus memverifikasi akunnya.
     *
     * Dua tabel sekaligus, jadi dibungkus transaksi: akun yang terverifikasi
     * tanpa kodenya ikut tertutup berarti kode yang sama masih bisa dipakai
     * lagi.
     *
     * @param array<string, mixed> $row
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

    /** Berapa menit lagi pengguna ini boleh meminta kode baru; 0 berarti boleh sekarang. */
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

    public static function ttlMenit(): int
    {
        return max(1, (int) config('mail.verification.ttl_minutes', 15));
    }

    public static function maxPercobaan(): int
    {
        return max(1, (int) config('mail.verification.max_attempts', 5));
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
