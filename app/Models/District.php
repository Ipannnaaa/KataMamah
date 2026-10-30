<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class District
{
    /**
     * Daftar kecamatan beserta jumlah lokasi aktif dan antrean hari ini.
     * Dipakai panel kiri peta 3D.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withCounts(): array
    {
        return DB::all(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM locations l
                      WHERE l.district_id = d.id AND l.is_active = 1) AS places,
                    (SELECT COUNT(*) FROM queues q
                       JOIN locations l2 ON l2.id = q.location_id
                      WHERE l2.district_id = d.id AND q.queue_date = CURDATE()) AS queues_today
               FROM districts d
           ORDER BY d.name'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return DB::all('SELECT * FROM districts ORDER BY name');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return DB::first('SELECT * FROM districts WHERE slug = ? LIMIT 1', [$slug]);
    }
}
