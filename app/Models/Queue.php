<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Queue
{
    private const SELECT = <<<'SQL'
        SELECT q.*,
               l.name AS location_name, l.slug AS location_slug,
               d.name AS district_name,
               s.name AS service_name, s.fee AS service_fee, s.sla_days,
               u.name AS user_name, u.phone AS user_phone, u.nik AS user_nik,
               o.name AS officer_name
          FROM queues q
          JOIN locations l ON l.id = q.location_id
          JOIN districts d ON d.id = l.district_id
          JOIN services s ON s.id = q.service_id
          JOIN users u ON u.id = q.user_id
     LEFT JOIN users o ON o.id = q.officer_id
SQL;

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        return DB::first(self::SELECT . ' WHERE q.code = ? LIMIT 1', [$code]);
    }

    /**
     * Pencarian yang sekaligus menjaga kepemilikan: dipakai halaman warga.
     *
     * @return array<string, mixed>|null
     */
    public static function findForUser(string $code, int $userId): ?array
    {
        return DB::first(self::SELECT . ' WHERE q.code = ? AND q.user_id = ? LIMIT 1', [$code, $userId]);
    }

    /**
     * Pencarian yang sekaligus menjaga batas lokasi: dipakai petugas & admin.
     *
     * @return array<string, mixed>|null
     */
    /**
     * $locationId NULL berarti tanpa batas lokasi — superadmin tidak terikat
     * satu kantor (users.location_id NULL). Sebelumnya nilainya dicor ke 0
     * dan setiap query mencari location_id = 0, sehingga seluruh panel
     * superadmin tampil kosong dan tiap aksinya berakhir 404.
     */
    public static function findForLocation(string $code, ?int $locationId): ?array
    {
        if ($locationId === null) {
            return DB::first(self::SELECT . ' WHERE q.code = ? LIMIT 1', [$code]);
        }

        return DB::first(self::SELECT . ' WHERE q.code = ? AND q.location_id = ? LIMIT 1', [$code, $locationId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(int $userId, ?string $status = null): array
    {
        $sql = self::SELECT . ' WHERE q.user_id = ?';
        $params = [$userId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }

        return DB::all($sql . ' ORDER BY q.queue_date DESC, q.queue_number DESC', $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeForUser(int $userId): array
    {
        return DB::all(
            self::SELECT . " WHERE q.user_id = ? AND q.status IN ('baru','ditinjau','ditangani')"
            . ' ORDER BY q.queue_date, q.queue_number',
            [$userId]
        );
    }

    /**
     * Papan antrean petugas: tab "baru" berisi antrean lokasi yang belum
     * diklaim, tab "milik saya" hanya milik petugas itu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function board(int $locationId, string $tab, ?int $officerId = null): array
    {
        if ($tab === 'baru') {
            return DB::all(
                self::SELECT . " WHERE q.location_id = ? AND q.status = 'baru'"
                . ' ORDER BY q.queue_date, q.queue_number',
                [$locationId]
            );
        }

        if ($tab === 'selesai') {
            return DB::all(
                self::SELECT . " WHERE q.location_id = ? AND q.officer_id = ?"
                . " AND q.status = 'selesai' AND q.queue_date = CURDATE()"
                . ' ORDER BY q.finished_at DESC',
                [$locationId, $officerId]
            );
        }

        return DB::all(
            self::SELECT . " WHERE q.location_id = ? AND q.officer_id = ?"
            . " AND q.status IN ('ditinjau','ditangani')"
            . ' ORDER BY q.queue_date, q.queue_number',
            [$locationId, $officerId]
        );
    }

    /**
     * Tabel antrean admin, difilter lokasi dari sesi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forLocation(?int $locationId, ?string $status = null, ?string $date = null, int $limit = 100): array
    {
        $sql = self::SELECT . ' WHERE 1 = 1';
        $params = [];

        if ($locationId !== null) {
            $sql .= ' AND q.location_id = ?';
            $params[] = $locationId;
        }

        if ($status !== null && $status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }

        if ($date !== null && $date !== '') {
            $sql .= ' AND q.queue_date = ?';
            $params[] = $date;
        }

        $sql .= ' ORDER BY q.queue_date DESC, q.queue_number DESC LIMIT ' . max(1, min(500, $limit));

        return DB::all($sql, $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function statusLogs(int $queueId): array
    {
        return DB::all(
            'SELECT sl.*, u.name AS actor_name
               FROM queue_status_logs sl
          LEFT JOIN users u ON u.id = sl.actor_id
              WHERE sl.queue_id = ?
           ORDER BY sl.id',
            [$queueId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function documents(int $queueId): array
    {
        return DB::all(
            'SELECT qd.*, sr.label AS requirement_label
               FROM queue_documents qd
          LEFT JOIN service_requirements sr ON sr.id = qd.requirement_id
              WHERE qd.queue_id = ?
           ORDER BY qd.id',
            [$queueId]
        );
    }

    /**
     * Hitung antrean per status untuk satu lokasi pada satu tanggal.
     *
     * @return array<string, int>
     */
    public static function countsByStatus(?int $locationId, string $date): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM queues WHERE queue_date = ?';
        $params = [$date];

        if ($locationId !== null) {
            $sql .= ' AND location_id = ?';
            $params[] = $locationId;
        }

        $rows = DB::all($sql . ' GROUP BY status', $params);

        $counts = array_fill_keys(array_keys((array) config('roles.status', [])), 0);

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
