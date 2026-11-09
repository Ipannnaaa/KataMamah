<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * Kotak aspirasi warga: masukan yang tidak menyangkut satu kantor tertentu.
 *
 * Bedanya dengan FacilityReport: aduan fasilitas selalu menunjuk satu kantor
 * dan ditangani admin kantor itu, sedangkan aspirasi berlaku se-kabupaten dan
 * hanya dibuka super admin. Karena itu tabelnya terpisah dan tidak punya
 * kolom location_id sama sekali.
 */
final class Aspiration
{
    /** Topik aspirasi => label yang dibaca warga. */
    public const TOPIK = [
        'layanan_baru' => 'Usulan layanan baru',
        'perbaikan' => 'Saran perbaikan layanan yang sudah ada',
        'aplikasi' => 'Masukan untuk situs ini',
        'lainnya' => 'Lainnya',
    ];

    /** Status penanganan => label. Sengaja sama dengan aduan fasilitas
     *  supaya warga tidak perlu menghafal dua daftar status. */
    public const STATUS = [
        'baru' => 'Aspirasi baru',
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
        return DB::insert('aspirations', [
            'user_id' => $data['user_id'] ?? null,
            'topic' => $data['topic'],
            'message' => $data['message'],
            'contact' => $data['contact'] ?? null,
            'status' => 'baru',
            'ip' => $data['ip'] ?? null,
        ]);
    }

    /**
     * Daftar untuk panel super admin, terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPanel(string $status = '', int $limit = 100): array
    {
        $sql = 'SELECT a.*, u.name AS pengirim, p.name AS penangan
                  FROM aspirations a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN users p ON p.id = a.handled_by';
        $params = [];

        if ($status !== '' && isset(self::STATUS[$status])) {
            $sql .= ' WHERE a.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT ' . max(1, min(500, $limit));

        return DB::all($sql, $params);
    }

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM aspirations WHERE id = ?', [$id]);
    }

    /**
     * Jumlah per status, untuk lencana penyaring di panel.
     *
     * @return array<string, int>
     */
    public static function countByStatus(): array
    {
        $out = array_fill_keys(array_keys(self::STATUS), 0);

        foreach (DB::all('SELECT status, COUNT(*) AS n FROM aspirations GROUP BY status') as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    public static function setStatus(int $id, string $status, ?string $note, int $handledBy): bool
    {
        if (!isset(self::STATUS[$status])) {
            return false;
        }

        return DB::update('aspirations', [
            'status' => $status,
            'admin_note' => $note,
            'handled_by' => $handledBy,
        ], 'id = :id', ['id' => $id]) > 0;
    }

    public static function labelTopik(string $topik): string
    {
        return self::TOPIK[$topik] ?? self::TOPIK['lainnya'];
    }

    public static function labelStatus(string $status): string
    {
        return self::STATUS[$status] ?? $status;
    }
}
