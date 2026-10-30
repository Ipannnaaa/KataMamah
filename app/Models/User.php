<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class User
{
    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            return DB::column('SELECT 1 FROM users WHERE email = ? LIMIT 1', [strtolower($email)]) !== null;
        }

        return DB::column(
            'SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1',
            [strtolower($email), $exceptId]
        ) !== null;
    }

    public static function nikExists(string $nik, ?int $exceptId = null): bool
    {
        if ($nik === '') {
            return false;
        }

        if ($exceptId === null) {
            return DB::column('SELECT 1 FROM users WHERE nik = ? LIMIT 1', [$nik]) !== null;
        }

        return DB::column('SELECT 1 FROM users WHERE nik = ? AND id <> ? LIMIT 1', [$nik, $exceptId]) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        return DB::first('SELECT * FROM users WHERE email = ? LIMIT 1', [strtolower($email)]);
    }

    /**
     * Akun tamu: dibuat saat warga mengambil antrean tanpa masuk.
     * Kolom password dibiarkan kosong sehingga tidak bisa dipakai login, dan
     * akun yang sama bisa "diklaim" nanti lewat halaman daftar.
     */
    public static function isClaimable(array $user): bool
    {
        return (string) $user['password_hash'] === '';
    }

    /**
     * Mencari akun berdasarkan email, atau membuat akun tamu baru.
     * Data yang sudah terisi tidak pernah ditimpa.
     *
     * @param array<string, mixed> $data  name, email, phone, nik, district_id
     */
    public static function findOrCreateGuest(array $data): int
    {
        $email = strtolower((string) $data['email']);
        $existing = self::findByEmail($email);

        if ($existing !== null) {
            $patch = [];

            foreach (['name', 'phone', 'district_id'] as $field) {
                if (empty($existing[$field]) && !empty($data[$field])) {
                    $patch[$field] = $data[$field];
                }
            }

            // NIK hanya diisi bila kosong dan belum dipakai akun lain.
            if (empty($existing['nik']) && !empty($data['nik']) && !self::nikExists((string) $data['nik'])) {
                $patch['nik'] = $data['nik'];
            }

            if ($patch !== []) {
                DB::update('users', $patch, 'id = :id', ['id' => $existing['id']]);
            }

            return (int) $existing['id'];
        }

        $nik = (string) ($data['nik'] ?? '');

        return DB::insert('users', [
            'role' => 'user',
            'name' => (string) $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'nik' => ($nik !== '' && !self::nikExists($nik)) ? $nik : null,
            'district_id' => $data['district_id'] ?? null,
            'password_hash' => '',
            'is_verified' => 0,
        ]);
    }

    /**
     * Alamat baru yang sudah dikonfirmasi menggantikan yang lama.
     *
     * is_verified ikut dipastikan 1: alamat inilah yang baru saja dibuktikan
     * bisa diakses pemiliknya.
     */
    public static function promotePendingEmail(int $userId): void
    {
        DB::run(
            'UPDATE users
                SET email = pending_email, pending_email = NULL, is_verified = 1
              WHERE id = ? AND pending_email IS NOT NULL',
            [$userId]
        );
    }

    public static function clearPendingEmail(int $userId): void
    {
        DB::update('users', ['pending_email' => null], 'id = :id', ['id' => $userId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data, string $password): int
    {
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $data['email'] = strtolower((string) $data['email']);

        return DB::insert('users', $data);
    }

    /**
     * Petugas pada satu lokasi beserta jumlah antrean yang sedang dipegang.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function officersAt(?int $locationId): array
    {
        $sql = <<<'SQL'
            SELECT u.id, u.name, u.email, u.last_login_at,
                   (SELECT COUNT(*) FROM queues q
                     WHERE q.officer_id = u.id
                       AND q.status IN ('ditinjau','ditangani')) AS active_queues,
                   (SELECT COUNT(*) FROM queues q2
                     WHERE q2.officer_id = u.id
                       AND q2.status = 'selesai'
                       AND q2.queue_date = CURDATE()) AS finished_today
              FROM users u
             WHERE u.role = 'officer'
        SQL;

        $params = [];

        // NULL = seluruh petugas se-kabupaten, untuk superadmin.
        if ($locationId !== null) {
            $sql .= ' AND u.location_id = ?';
            $params[] = $locationId;
        }

        return DB::all($sql . ' ORDER BY u.name', $params);
    }

    /**
     * Statistik ringkas untuk halaman profil warga.
     *
     * @return array<string, int|float>
     */
    public static function stats(int $userId): array
    {
        $sql = <<<'SQL'
            SELECT COUNT(*) AS total,
                   SUM(status = 'selesai') AS selesai,
                   SUM(status = 'dibatalkan') AS dibatalkan,
                   SUM(status IN ('baru','ditinjau','ditangani')) AS aktif
              FROM queues
             WHERE user_id = ?
        SQL;

        $row = DB::first($sql, [$userId]) ?? [];

        $paid = DB::column(
            "SELECT COALESCE(SUM(p.amount), 0)
               FROM payments p
               JOIN queues q ON q.id = p.queue_id
              WHERE q.user_id = ? AND p.status = 'lunas'",
            [$userId]
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'selesai' => (int) ($row['selesai'] ?? 0),
            'dibatalkan' => (int) ($row['dibatalkan'] ?? 0),
            'aktif' => (int) ($row['aktif'] ?? 0),
            'biaya' => (float) ($paid ?? 0),
        ];
    }
}
