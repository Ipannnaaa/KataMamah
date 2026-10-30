<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Agregasi rekap. Semua query menerima location_id (dan opsional officer_id)
 * agar satu lokasi tidak pernah melihat angka lokasi lain.
 *
 * location_id NULL berarti tanpa batas lokasi: superadmin tidak terikat satu
 * kantor (users.location_id NULL), dan mencarinya sebagai location_id = 0
 * membuat seluruh rekapnya nol.
 */
final class RecapService
{
    /**
     * Potongan WHERE untuk lingkup lokasi, beserta parameternya.
     *
     * @return array{0: string, 1: array<int, int>}
     */
    private static function lingkup(?int $locationId, string $alias = 'q'): array
    {
        return $locationId === null
            ? ['1 = 1', []]
            : [$alias . '.location_id = ?', [$locationId]];
    }

    /**
     * Rentang tanggal untuk periode hari | minggu | bulan | tahun.
     *
     * @return array{from: string, to: string, label: string}
     */
    public static function range(string $period): array
    {
        switch ($period) {
            case 'minggu':
                return ['from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d'), 'label' => 'Minggu ini'];

            case 'bulan':
                return ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'label' => 'Bulan ini'];

            case 'tahun':
                return ['from' => date('Y-01-01'), 'to' => date('Y-m-d'), 'label' => 'Tahun ini'];

            case 'hari':
            default:
                return ['from' => date('Y-m-d'), 'to' => date('Y-m-d'), 'label' => 'Hari ini'];
        }
    }

    /**
     * Ringkasan utama: jumlah per status, rata-rata tunggu dan proses,
     * serta nominal pembayaran lunas.
     *
     * @return array<string, mixed>
     */
    public static function summary(?int $locationId, string $period, ?int $officerId = null): array
    {
        $range = self::range($period);
        [$klausa, $lingkup] = self::lingkup($locationId);

        $sql = <<<'SQL'
            SELECT COUNT(*) AS total,
                   SUM(q.status = 'baru') AS baru,
                   SUM(q.status = 'ditinjau') AS ditinjau,
                   SUM(q.status = 'ditangani') AS ditangani,
                   SUM(q.status = 'selesai') AS selesai,
                   SUM(q.status = 'dibatalkan') AS dibatalkan,
                   AVG(CASE WHEN q.accepted_at IS NOT NULL
                            THEN TIMESTAMPDIFF(MINUTE, q.created_at, q.accepted_at) END) AS avg_wait,
                   AVG(CASE WHEN q.finished_at IS NOT NULL AND q.accepted_at IS NOT NULL
                            THEN TIMESTAMPDIFF(MINUTE, q.accepted_at, q.finished_at) END) AS avg_handle
              FROM queues q
             WHERE %LINGKUP%
               AND q.queue_date BETWEEN ? AND ?
        SQL;

        $sql = str_replace('%LINGKUP%', $klausa, $sql);
        $params = array_merge($lingkup, [$range['from'], $range['to']]);

        if ($officerId !== null) {
            $sql .= ' AND q.officer_id = ?';
            $params[] = $officerId;
        }

        $row = DB::first($sql, $params) ?? [];

        $paid = DB::column(
            "SELECT COALESCE(SUM(p.amount), 0)
               FROM payments p
               JOIN queues q ON q.id = p.queue_id
              WHERE " . $klausa . "
                AND p.status = 'lunas'
                AND q.queue_date BETWEEN ? AND ?",
            array_merge($lingkup, [$range['from'], $range['to']])
        );

        return [
            'period' => $period,
            'label' => $range['label'],
            'from' => $range['from'],
            'to' => $range['to'],
            'total' => (int) ($row['total'] ?? 0),
            'baru' => (int) ($row['baru'] ?? 0),
            'ditinjau' => (int) ($row['ditinjau'] ?? 0),
            'ditangani' => (int) ($row['ditangani'] ?? 0),
            'selesai' => (int) ($row['selesai'] ?? 0),
            'dibatalkan' => (int) ($row['dibatalkan'] ?? 0),
            'avg_wait' => (int) round((float) ($row['avg_wait'] ?? 0)),
            'avg_handle' => (int) round((float) ($row['avg_handle'] ?? 0)),
            'paid' => (float) ($paid ?? 0),
        ];
    }

    /**
     * Distribusi per layanan.
     *
     * @return array<int, array{label: string, total: int}>
     */
    public static function byService(?int $locationId, string $period, ?int $officerId = null): array
    {
        $range = self::range($period);
        [$klausa, $lingkup] = self::lingkup($locationId);

        $sql = 'SELECT s.name AS label, COUNT(*) AS total
                  FROM queues q
                  JOIN services s ON s.id = q.service_id
                 WHERE ' . $klausa . '
                   AND q.queue_date BETWEEN ? AND ?';

        $params = array_merge($lingkup, [$range['from'], $range['to']]);

        if ($officerId !== null) {
            $sql .= ' AND q.officer_id = ?';
            $params[] = $officerId;
        }

        $rows = DB::all($sql . ' GROUP BY s.id, s.name ORDER BY total DESC', $params);

        return array_map(
            static fn (array $r): array => ['label' => (string) $r['label'], 'total' => (int) $r['total']],
            $rows
        );
    }

    /**
     * Distribusi per petugas (hanya untuk admin).
     *
     * @return array<int, array{label: string, total: int}>
     */
    public static function byOfficer(?int $locationId, string $period): array
    {
        $range = self::range($period);
        [$klausa, $lingkup] = self::lingkup($locationId);

        $rows = DB::all(
            'SELECT u.name AS label, COUNT(*) AS total
               FROM queues q
               JOIN users u ON u.id = q.officer_id
              WHERE ' . $klausa . '
                AND q.queue_date BETWEEN ? AND ?
           GROUP BY u.id, u.name
           ORDER BY total DESC',
            array_merge($lingkup, [$range['from'], $range['to']])
        );

        return array_map(
            static fn (array $r): array => ['label' => (string) $r['label'], 'total' => (int) $r['total']],
            $rows
        );
    }

    /**
     * Antrean masuk per jam untuk hari ini.
     *
     * @return array<int, array{label: string, total: int}>
     */
    public static function perHour(?int $locationId): array
    {
        [$klausa, $lingkup] = self::lingkup($locationId, 'queues');

        $rows = DB::all(
            'SELECT HOUR(created_at) AS h, COUNT(*) AS total
               FROM queues
              WHERE ' . $klausa . ' AND queue_date = CURDATE()
           GROUP BY HOUR(created_at)',
            $lingkup
        );

        $byHour = [];

        foreach ($rows as $row) {
            $byHour[(int) $row['h']] = (int) $row['total'];
        }

        $out = [];

        for ($h = 8; $h <= 15; $h++) {
            $out[] = ['label' => str_pad((string) $h, 2, '0', STR_PAD_LEFT), 'total' => $byHour[$h] ?? 0];
        }

        return $out;
    }

    /**
     * Rekap harian: dipakai tabel "rekap per hari" dan export CSV.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function perDay(?int $locationId, string $from, string $to): array
    {
        [$klausa, $lingkup] = self::lingkup($locationId);

        /*
         * Pengelompokan dan subquery "paid" mengikuti lingkupnya.
         *
         * Untuk satu kantor, keduanya per lokasi per tanggal. Untuk
         * superadmin (lingkup NULL) pengelompokan per lokasi harus dilepas,
         * kalau tidak satu tanggal muncul berkali-kali — sekali untuk tiap
         * kantor — dan tabel rekapnya terbaca seperti data ganda.
         */
        $perLokasi = $locationId !== null;

        $paid = $perLokasi
            ? "COALESCE((SELECT SUM(p.amount) FROM payments p
                          JOIN queues q2 ON q2.id = p.queue_id
                         WHERE q2.location_id = q.location_id
                           AND q2.queue_date = q.queue_date
                           AND p.status = 'lunas'), 0) AS paid"
            : "COALESCE((SELECT SUM(p.amount) FROM payments p
                          JOIN queues q2 ON q2.id = p.queue_id
                         WHERE q2.queue_date = q.queue_date
                           AND p.status = 'lunas'), 0) AS paid";

        $sql = "SELECT q.queue_date,
                       COUNT(*) AS total,
                       SUM(q.status = 'selesai') AS selesai,
                       SUM(q.status = 'dibatalkan') AS dibatalkan,
                       AVG(CASE WHEN q.accepted_at IS NOT NULL
                                THEN TIMESTAMPDIFF(MINUTE, q.created_at, q.accepted_at) END) AS avg_wait,
                       " . $paid . "
                  FROM queues q
                 WHERE " . $klausa . "
                   AND q.queue_date BETWEEN ? AND ?
              GROUP BY q.queue_date" . ($perLokasi ? ', q.location_id' : '') . "
              ORDER BY q.queue_date DESC";

        return DB::all($sql, array_merge($lingkup, [$from, $to]));
    }

    /**
     * Baris CSV untuk /admin/rekap/export.
     */
    public static function csv(?int $locationId, string $from, string $to): string
    {
        $rows = self::perDay($locationId, $from, $to);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['Tanggal', 'Total antrean', 'Selesai', 'Dibatalkan', 'Rata-rata tunggu (menit)', 'Pembayaran lunas']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                (string) $row['queue_date'],
                (int) $row['total'],
                (int) $row['selesai'],
                (int) $row['dibatalkan'],
                (int) round((float) $row['avg_wait']),
                (float) $row['paid'],
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
