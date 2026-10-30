<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Payment
{
    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * $locationId NULL berarti seluruh kabupaten: superadmin tidak terikat
     * satu kantor, dan mencarinya sebagai location_id = 0 membuat halaman
     * pembayaran superadmin selalu kosong.
     */
    public static function forLocation(?int $locationId, ?string $status = null): array
    {
        $sql = 'SELECT p.*, q.code, q.queue_date, s.name AS service_name, u.name AS user_name
                  FROM payments p
                  JOIN queues q ON q.id = p.queue_id
                  JOIN services s ON s.id = q.service_id
                  JOIN users u ON u.id = q.user_id
                 WHERE 1 = 1';

        $params = [];

        if ($locationId !== null) {
            $sql .= ' AND q.location_id = ?';
            $params[] = $locationId;
        }

        if ($status !== null && $status !== '') {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }

        return DB::all($sql . ' ORDER BY p.created_at DESC LIMIT 200', $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForLocation(int $paymentId, ?int $locationId): ?array
    {
        $sql = 'SELECT p.*, q.location_id, q.code
                  FROM payments p
                  JOIN queues q ON q.id = p.queue_id
                 WHERE p.id = ?';
        $params = [$paymentId];

        if ($locationId !== null) {
            $sql .= ' AND q.location_id = ?';
            $params[] = $locationId;
        }

        return DB::first($sql . ' LIMIT 1', $params);
    }

    /**
     * Total nominal per status untuk kartu ringkasan admin.
     *
     * @return array<string, float>
     */
    public static function totalsToday(?int $locationId): array
    {
        $sql = 'SELECT p.status, COALESCE(SUM(p.amount), 0) AS total
                  FROM payments p
                  JOIN queues q ON q.id = p.queue_id
                 WHERE DATE(p.created_at) = CURDATE()';
        $params = [];

        if ($locationId !== null) {
            $sql .= ' AND q.location_id = ?';
            $params[] = $locationId;
        }

        $rows = DB::all($sql . ' GROUP BY p.status', $params);

        $totals = ['menunggu' => 0.0, 'lunas' => 0.0, 'gagal' => 0.0, 'refund' => 0.0];

        foreach ($rows as $row) {
            $totals[(string) $row['status']] = (float) $row['total'];
        }

        return $totals;
    }
}
