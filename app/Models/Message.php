<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

final class Message
{
    /**
     * Pesan satu thread antrean. $afterId dipakai polling agar hanya pesan
     * baru yang dikirim ulang.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forQueue(int $queueId, int $afterId = 0): array
    {
        return DB::all(
            'SELECT m.*, u.name AS sender_name, u.role AS sender_role
               FROM messages m
               JOIN users u ON u.id = m.sender_id
              WHERE m.queue_id = ? AND m.id > ?
           ORDER BY m.id',
            [$queueId, $afterId]
        );
    }

    public static function unreadCount(int $queueId, int $forUserId): int
    {
        return (int) DB::column(
            'SELECT COUNT(*) FROM messages
              WHERE queue_id = ? AND sender_id <> ? AND read_at IS NULL',
            [$queueId, $forUserId]
        );
    }

    public static function markRead(int $queueId, int $forUserId): void
    {
        DB::run(
            'UPDATE messages SET read_at = NOW()
              WHERE queue_id = ? AND sender_id <> ? AND read_at IS NULL',
            [$queueId, $forUserId]
        );
    }

    /**
     * Daftar thread untuk sidebar chat warga.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function threadsForUser(int $userId): array
    {
        $sql = <<<'SQL'
            SELECT q.id, q.code, q.status, s.name AS service_name, l.name AS location_name,
                   (SELECT m.body FROM messages m WHERE m.queue_id = q.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                   (SELECT m.created_at FROM messages m WHERE m.queue_id = q.id ORDER BY m.id DESC LIMIT 1) AS last_at,
                   (SELECT COUNT(*) FROM messages m
                     WHERE m.queue_id = q.id AND m.sender_id <> q.user_id AND m.read_at IS NULL) AS unread
              FROM queues q
              JOIN services s ON s.id = q.service_id
              JOIN locations l ON l.id = q.location_id
             WHERE q.user_id = ?
               AND q.status <> 'baru'
          ORDER BY last_at IS NULL, last_at DESC, q.id DESC
        SQL;

        return DB::all($sql, [$userId]);
    }
}
