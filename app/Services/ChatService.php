<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\Message;
use RuntimeException;

/**
 * Satu thread percakapan per antrean. Hak akses diperiksa di SETIAP
 * pemanggilan, bukan hanya ketika halaman dibuka.
 */
final class ChatService
{
    /**
     * @param array<string, mixed> $queue
     * @param array<string, mixed> $user
     */
    public static function canAccess(array $queue, array $user): bool
    {
        $role = (string) $user['role'];
        $id = (int) $user['id'];

        if ($role === 'user') {
            return (int) $queue['user_id'] === $id;
        }

        if ($role === 'officer') {
            return (int) ($queue['officer_id'] ?? 0) === $id
                && (int) $queue['location_id'] === (int) ($user['location_id'] ?? 0);
        }

        if ($role === 'admin') {
            return (int) $queue['location_id'] === (int) ($user['location_id'] ?? 0);
        }

        return $role === 'superadmin';
    }

    /**
     * Chat terbuka setelah antrean ditinjau, dan tertutup seminggu setelah selesai.
     *
     * @param array<string, mixed> $queue
     */
    public static function isOpen(array $queue): bool
    {
        if ((string) $queue['status'] === 'baru') {
            return false;
        }

        if ((string) $queue['status'] === 'selesai' && $queue['finished_at'] !== null) {
            return strtotime((string) $queue['finished_at']) > strtotime('-7 days');
        }

        return (string) $queue['status'] !== 'dibatalkan';
    }

    /**
     * @param array<string, mixed> $queue
     * @param array<string, mixed> $user
     * @return array<int, array<string, mixed>>
     */
    public static function messages(array $queue, array $user, int $afterId = 0): array
    {
        if (!self::canAccess($queue, $user)) {
            throw new RuntimeException('Anda tidak berhak membuka percakapan ini.');
        }

        $messages = Message::forQueue((int) $queue['id'], $afterId);

        Message::markRead((int) $queue['id'], (int) $user['id']);

        return $messages;
    }

    /**
     * @param array<string, mixed> $queue
     * @param array<string, mixed> $user
     */
    public static function send(array $queue, array $user, string $body): int
    {
        if (!self::canAccess($queue, $user)) {
            throw new RuntimeException('Anda tidak berhak mengirim pesan di antrean ini.');
        }

        if (!self::isOpen($queue)) {
            throw new RuntimeException('Percakapan untuk antrean ini sudah ditutup.');
        }

        $body = trim($body);

        if ($body === '') {
            throw new RuntimeException('Pesan tidak boleh kosong.');
        }

        return DB::insert('messages', [
            'queue_id' => (int) $queue['id'],
            'sender_id' => (int) $user['id'],
            'body' => mb_substr($body, 0, 4000),
        ]);
    }
}
