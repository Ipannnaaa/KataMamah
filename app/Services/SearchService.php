<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\CodeGenerator;
use App\Core\DB;

/**
 * Smart search: satu kotak untuk empat hal sekaligus.
 *
 * Masukan dikenali lebih dulu — kalau bentuknya kode pemesanan, hasil antrean
 * langsung didahulukan; selain itu dicari ke layanan, kantor layanan, dan
 * kecamatan. Setiap hasil dipulangkan dalam bentuk yang sama supaya view dan
 * JSON tinggal menampilkannya tanpa perlu tahu asalnya.
 */
final class SearchService
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function query(string $term, int $limit = 12): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $results = [];

        foreach (self::queueHit($term) as $hit) {
            $results[] = $hit;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        foreach (self::services($like) as $row) {
            $results[] = $row;
        }

        foreach (self::locations($like) as $row) {
            $results[] = $row;
        }

        foreach (self::districts($like) as $row) {
            $results[] = $row;
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Kode pemesanan dikenali walau diketik tanpa tanda hubung atau huruf kecil.
     *
     * @return array<int, array<string, string>>
     */
    private static function queueHit(string $term): array
    {
        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $term) ?? '');

        // Minimal 4 karakter agar tidak setiap ketikan dianggap kode.
        if (mb_strlen($compact) < 4) {
            return [];
        }

        $code = CodeGenerator::normalize($term);

        $queue = DB::first(
            'SELECT q.code, q.status, q.queue_date, q.queue_number,
                    s.name AS service_name, l.name AS location_name
               FROM queues q
               JOIN services s ON s.id = q.service_id
               JOIN locations l ON l.id = q.location_id
              WHERE q.code = ? LIMIT 1',
            [$code]
        );

        if ($queue === null) {
            return [];
        }

        return [[
            'type' => 'antrean',
            'label' => 'Antrean',
            'title' => (string) $queue['code'],
            'subtitle' => $queue['service_name'] . ' · ' . $queue['location_name']
                . ' · nomor ' . str_pad((string) $queue['queue_number'], 3, '0', STR_PAD_LEFT),
            'badge' => status_label((string) $queue['status']),
            'url' => url('/cek-antrean?kode=' . rawurlencode((string) $queue['code'])),
        ]];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function services(string $like): array
    {
        $rows = DB::all(
            'SELECT s.name, s.fee, l.name AS location_name, l.slug AS location_slug
               FROM services s
               JOIN locations l ON l.id = s.location_id
              WHERE s.is_active = 1 AND l.is_active = 1
                AND (s.name LIKE ? OR s.description LIKE ?)
           ORDER BY s.name
              LIMIT 6',
            [$like, $like]
        );

        return array_map(static fn (array $r): array => [
            'type' => 'layanan',
            'label' => 'Layanan',
            'title' => (string) $r['name'],
            'subtitle' => (string) $r['location_name'],
            'badge' => (float) $r['fee'] > 0 ? rupiah((float) $r['fee']) : 'Gratis',
            'url' => url('/lokasi/' . $r['location_slug']),
        ], $rows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function locations(string $like): array
    {
        $rows = DB::all(
            'SELECT l.name, l.slug, l.category, l.short_desc, d.name AS district_name
               FROM locations l
               JOIN districts d ON d.id = l.district_id
              WHERE l.is_active = 1
                AND (l.name LIKE ? OR l.short_desc LIKE ? OR l.category LIKE ?)
           ORDER BY l.name
              LIMIT 6',
            [$like, $like, $like]
        );

        return array_map(static fn (array $r): array => [
            'type' => 'lokasi',
            'label' => 'Kantor layanan',
            'title' => (string) $r['name'],
            'subtitle' => (string) ($r['short_desc'] ?? $r['district_name']),
            'badge' => (string) $r['district_name'],
            'url' => url('/lokasi/' . $r['slug']),
        ], $rows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function districts(string $like): array
    {
        $rows = DB::all(
            'SELECT d.name, d.slug,
                    (SELECT COUNT(*) FROM locations l WHERE l.district_id = d.id AND l.is_active = 1) AS places
               FROM districts d
              WHERE d.name LIKE ?
           ORDER BY d.name
              LIMIT 4',
            [$like]
        );

        return array_map(static fn (array $r): array => [
            'type' => 'kecamatan',
            'label' => 'Kecamatan',
            'title' => 'Kecamatan ' . $r['name'],
            'subtitle' => $r['places'] . ' kantor layanan',
            'badge' => 'Peta',
            'url' => url('/kecamatan/' . $r['slug']),
        ], $rows);
    }
}
