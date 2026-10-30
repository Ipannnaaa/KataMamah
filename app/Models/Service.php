<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Service
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forLocation(int $locationId, bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM services WHERE location_id = ?';

        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }

        return DB::all($sql . ' ORDER BY sort_order, name', [$locationId]);
    }

    /**
     * Layanan + syarat berkasnya, siap dipakai view accordion.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withRequirements(int $locationId): array
    {
        $services = self::forLocation($locationId);

        if ($services === []) {
            return [];
        }

        $ids = array_map(static fn (array $s): int => (int) $s['id'], $services);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $requirements = DB::all(
            'SELECT * FROM service_requirements
              WHERE service_id IN (' . $placeholders . ')
           ORDER BY sort_order, id',
            $ids
        );

        $grouped = [];

        foreach ($requirements as $requirement) {
            $grouped[(int) $requirement['service_id']][] = $requirement;
        }

        foreach ($services as $index => $service) {
            $services[$index]['requirements'] = $grouped[(int) $service['id']] ?? [];
        }

        return $services;
    }

    /**
     * Pencarian yang sekaligus memastikan layanan memang milik lokasi itu.
     *
     * @return array<string, mixed>|null
     */
    public static function findForLocation(int $serviceId, int $locationId): ?array
    {
        return DB::first(
            'SELECT * FROM services
              WHERE id = ? AND location_id = ? AND is_active = 1
              LIMIT 1',
            [$serviceId, $locationId]
        );
    }

    /**
     * Nama layanan aktif dikelompokkan per lokasi. Dipakai kartu pratinjau di
     * peta supaya tidak perlu satu query per landmark.
     *
     * @return array<int, array<int, string>>
     */
    public static function namesByLocation(): array
    {
        $rows = DB::all(
            'SELECT s.location_id, s.name
               FROM services s
               JOIN locations l ON l.id = s.location_id
              WHERE s.is_active = 1 AND l.is_active = 1
           ORDER BY s.location_id, s.sort_order, s.name'
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['location_id']][] = (string) $row['name'];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function requirements(int $serviceId): array
    {
        return DB::all(
            'SELECT * FROM service_requirements WHERE service_id = ? ORDER BY sort_order, id',
            [$serviceId]
        );
    }
}
