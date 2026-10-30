<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Aduan warga tentang satu kantor layanan.
 *
 * Sengaja terbuka untuk siapa saja, termasuk yang belum punya akun: data yang
 * salah paling sering ditemukan orang yang baru pertama datang ke sana, dan
 * memaksa mereka mendaftar dulu berarti aduan itu tidak pernah masuk.
 */
final class FacilityReport
{
    /** Kategori aduan => label yang dibaca warga. */
    public const KATEGORI = [
        'data_salah' => 'Data di halaman ini tidak cocok',
        'pelayanan' => 'Pelayanan tidak sesuai',
        'fasilitas' => 'Fasilitas rusak atau tidak memadai',
        'lainnya' => 'Lainnya',
    ];

    /** Status penanganan => label. */
    public const STATUS = [
        'baru' => 'Aduan baru',
        'ditinjau' => 'Sedang ditinjau',
        'ditindaklanjuti' => 'Ditindaklanjuti',
        'selesai' => 'Selesai',
        'ditolak' => 'Tidak dapat diproses',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        return DB::insert('facility_reports', [
            'location_id' => $data['location_id'],
            'user_id' => $data['user_id'] ?? null,
            'category' => $data['category'],
            'message' => $data['message'],
            'contact' => $data['contact'] ?? null,
            'status' => 'baru',
            'ip' => $data['ip'] ?? null,
        ]);
    }

    /**
     * Aduan untuk satu kantor, terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forLocation(int $locationId, int $limit = 50): array
    {
        return DB::all(
            'SELECT r.*, u.name AS pelapor, p.name AS penangan
               FROM facility_reports r
               LEFT JOIN users u ON u.id = r.user_id
               LEFT JOIN users p ON p.id = r.handled_by
              WHERE r.location_id = ?
              ORDER BY r.created_at DESC
              LIMIT ' . max(1, $limit),
            [$locationId]
        );
    }

    /**
     * Daftar untuk panel admin. Admin lokasi hanya melihat kantornya sendiri;
     * superadmin ($locationId null) melihat semuanya.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPanel(?int $locationId, string $status = '', int $limit = 100): array
    {
        $sql = 'SELECT r.*, l.name AS lokasi, l.slug AS lokasi_slug,
                       u.name AS pelapor, p.name AS penangan
                  FROM facility_reports r
                  JOIN locations l ON l.id = r.location_id
                  LEFT JOIN users u ON u.id = r.user_id
                  LEFT JOIN users p ON p.id = r.handled_by
                 WHERE 1 = 1';
        $params = [];

        if ($locationId !== null) {
            $sql .= ' AND r.location_id = ?';
            $params[] = $locationId;
        }

        if ($status !== '' && isset(self::STATUS[$status])) {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }

        return DB::all($sql . ' ORDER BY r.created_at DESC LIMIT ' . max(1, $limit), $params);
    }

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM facility_reports WHERE id = ?', [$id]);
    }

    /**
     * Berapa aduan yang belum selesai per status, untuk lencana di panel.
     *
     * @return array<string, int>
     */
    public static function countByStatus(?int $locationId): array
    {
        $sql = 'SELECT status, COUNT(*) AS n FROM facility_reports';
        $params = [];

        if ($locationId !== null) {
            $sql .= ' WHERE location_id = ?';
            $params[] = $locationId;
        }

        $out = array_fill_keys(array_keys(self::STATUS), 0);

        foreach (DB::all($sql . ' GROUP BY status', $params) as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    public static function setStatus(int $id, string $status, ?string $note, int $handledBy): bool
    {
        if (!isset(self::STATUS[$status])) {
            return false;
        }

        return DB::update('facility_reports', [
            'status' => $status,
            'admin_note' => $note,
            'handled_by' => $handledBy,
        ], 'id = :id', ['id' => $id]) > 0;
    }

    public static function labelKategori(string $kategori): string
    {
        return self::KATEGORI[$kategori] ?? self::KATEGORI['lainnya'];
    }

    public static function labelStatus(string $status): string
    {
        return self::STATUS[$status] ?? $status;
    }
}
