<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Location
{
    /**
     * Semua landmark aktif + nama kecamatan + sisa kuota hari ini.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activeWithQuota(?int $districtId = null): array
    {
        $sql = <<<'SQL'
            SELECT l.*, d.name AS district_name, d.slug AS district_slug,
                   l.quota_per_day - COALESCE(t.used, 0) AS quota_left
              FROM locations l
              JOIN districts d ON d.id = l.district_id
         LEFT JOIN (SELECT location_id, COUNT(*) AS used
                      FROM queues
                     WHERE queue_date = CURDATE()
                       AND status <> 'dibatalkan'
                  GROUP BY location_id) t ON t.location_id = l.id
             WHERE l.is_active = 1
SQL;

        $params = [];

        if ($districtId !== null) {
            $sql .= ' AND l.district_id = ?';
            $params[] = $districtId;
        }

        return DB::all($sql . ' ORDER BY d.name, l.name', $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return DB::first(
            'SELECT l.*, d.name AS district_name, d.slug AS district_slug
               FROM locations l
               JOIN districts d ON d.id = l.district_id
              WHERE l.slug = ? AND l.is_active = 1
              LIMIT 1',
            [$slug]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return DB::first(
            'SELECT l.*, d.name AS district_name
               FROM locations l
               JOIN districts d ON d.id = l.district_id
              WHERE l.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Seluruh landmark sekecamatan dengan lokasi admin, termasuk yang nonaktif.
     *
     * Superadmin tidak terikat satu kantor (users.location_id NULL), jadi
     * untuk mereka $locationId bernilai NULL dan daftarnya mencakup seluruh
     * kabupaten. Tanpa ini panel landmark superadmin tampil "0 terdaftar"
     * dan tidak ada satu pun kantor yang bisa dibuka dari sana.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function siblingsOf(?int $locationId): array
    {
        if ($locationId === null || $locationId <= 0) {
            return DB::all(
                'SELECT l.*, d.name AS district_name
                   FROM locations l
                   JOIN districts d ON d.id = l.district_id
               ORDER BY l.is_active DESC, d.name, l.name'
            );
        }

        return DB::all(
            'SELECT l.*, d.name AS district_name
               FROM locations l
               JOIN districts d ON d.id = l.district_id
              WHERE l.district_id = (SELECT district_id FROM locations WHERE id = ?)
           ORDER BY l.is_active DESC, l.name',
            [$locationId]
        );
    }

    /**
     * Sisa kuota untuk satu tanggal. Antrean yang dibatalkan tidak dihitung.
     */
    public static function quotaLeft(int $locationId, string $date): int
    {
        $sql = <<<'SQL'
            SELECT l.quota_per_day,
                   (SELECT COUNT(*) FROM queues q
                     WHERE q.location_id = l.id
                       AND q.queue_date = ?
                       AND q.status <> 'dibatalkan') AS used
              FROM locations l
             WHERE l.id = ?
            SQL;

        $row = DB::first($sql, [$date, $locationId]);

        if ($row === null) {
            return 0;
        }

        return max(0, (int) $row['quota_per_day'] - (int) $row['used']);
    }

    /**
     * Kuota beberapa hari ke depan untuk tabel jadwal.
     *
     * @return array<int, array{date: string, label: string, left: int, quota: int, closed: bool}>
     */
    public static function upcomingQuota(int $locationId, int $days = 7): array
    {
        $location = self::find($locationId);

        if ($location === null) {
            return [];
        }

        $hours = json_decode((string) ($location['open_hours'] ?? ''), true);
        $hours = is_array($hours) ? $hours : [];
        $keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $ts = strtotime('+' . $i . ' day');
            $date = date('Y-m-d', $ts);
            $key = $keys[(int) date('N', $ts) - 1];
            $closed = !isset($hours[$key]) || $hours[$key] === null || $hours[$key] === [];

            $out[] = [
                'date' => $date,
                'label' => tanggal($date),
                'quota' => (int) $location['quota_per_day'],
                'left' => $closed ? 0 : self::quotaLeft($locationId, $date),
                'closed' => $closed,
            ];
        }

        return $out;
    }
}
