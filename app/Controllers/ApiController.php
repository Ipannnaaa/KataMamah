<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\District;
use App\Models\Location;
use App\Models\Queue;
use App\Services\ChatService;
use App\Services\PhotoService;
use App\Services\RecapService;
use Throwable;

/**
 * Endpoint JSON yang dipakai JS: peta, polling status, dan chat.
 */
final class ApiController extends Controller
{
    /**
     * GeoJSON landmark untuk MapLibre.
     */
    public function landmarks(Request $request): Response
    {
        $slug = $request->str('kecamatan');
        $districtId = null;

        if ($slug !== '') {
            $district = District::findBySlug($slug);

            if ($district === null) {
                return $this->json(['error' => 'Kecamatan tidak ditemukan.'], 404);
            }

            $districtId = (int) $district['id'];
        }

        $features = [];

        foreach (Location::activeWithQuota($districtId) as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row['lng'], (float) $row['lat']],
                ],
                'properties' => [
                    'id' => (int) $row['id'],
                    'slug' => (string) $row['slug'],
                    'name' => (string) $row['name'],
                    'category' => (string) $row['category'],
                    'district' => (string) $row['district_name'],
                    'short_desc' => (string) ($row['short_desc'] ?? ''),
                    'quota_left' => (int) $row['quota_left'],
                    'url' => url('/lokasi/' . $row['slug']),
                ],
            ];
        }

        return $this->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /**
     * Isi popup saat sebuah landmark diklik.
     *
     * @param array<string, string> $params
     */
    public function landmark(Request $request, array $params): Response
    {
        $location = Location::find((int) $params['id']);

        if ($location === null || (int) $location['is_active'] !== 1) {
            return $this->json(['error' => 'Landmark tidak ditemukan.'], 404);
        }

        return $this->json([
            'id' => (int) $location['id'],
            'name' => (string) $location['name'],
            'category' => (string) $location['category'],
            'district' => (string) $location['district_name'],
            'address' => (string) ($location['address'] ?? ''),
            'short_desc' => (string) ($location['short_desc'] ?? ''),
            'photo' => PhotoService::url((string) ($location['cover_image'] ?? '')),
            'photo_credit' => (string) ($location['cover_credit'] ?? ''),
            'open_hours' => json_decode((string) ($location['open_hours'] ?? ''), true),
            'quota_left' => Location::quotaLeft((int) $location['id'], date('Y-m-d')),
            'url' => url('/lokasi/' . $location['slug']),
        ]);
    }

    /**
     * Polling status antrean. Data non-sensitif saja, sama seperti
     * halaman cek antrean publik.
     *
     * @param array<string, string> $params
     */
    public function queueStatus(Request $request, array $params): Response
    {
        $queue = Queue::findByCode(strtoupper($params['code']));

        if ($queue === null) {
            return $this->json(['error' => 'Kode tidak ditemukan.'], 404);
        }

        return $this->json([
            'code' => (string) $queue['code'],
            'number' => (int) $queue['queue_number'],
            'status' => (string) $queue['status'],
            'status_label' => (string) config('roles.status.' . $queue['status'], $queue['status']),
            'queue_date' => (string) $queue['queue_date'],
            'service' => (string) $queue['service_name'],
            'location' => (string) $queue['location_name'],
            'user' => mask_nama((string) $queue['user_name']),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function messages(Request $request, array $params): Response
    {
        $user = Auth::user();
        $queue = Queue::findByCode(strtoupper($params['queue']));

        if ($user === null || $queue === null) {
            return $this->json(['error' => 'Percakapan tidak ditemukan.'], 404);
        }

        try {
            $messages = ChatService::messages($queue, $user, $request->int('after'));
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 403);
        }

        $out = [];

        foreach ($messages as $message) {
            $out[] = [
                'id' => (int) $message['id'],
                'body' => (string) $message['body'],
                'sender_name' => (string) $message['sender_name'],
                'sender_role' => (string) $message['sender_role'],
                'mine' => (int) $message['sender_id'] === (int) $user['id'],
                'at' => (string) $message['created_at'],
            ];
        }

        return $this->json(['messages' => $out, 'open' => ChatService::isOpen($queue)]);
    }

    /**
     * @param array<string, string> $params
     */
    public function send(Request $request, array $params): Response
    {
        $user = Auth::user();
        $queue = Queue::findByCode(strtoupper($params['queue']));

        if ($user === null || $queue === null) {
            return $this->json(['error' => 'Percakapan tidak ditemukan.'], 404);
        }

        $limit = (array) config('app.rate_limit.chat', ['max' => 20, 'decay' => 60]);
        $key = 'chat:' . $user['id'];

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            return $this->json(['error' => 'Terlalu banyak pesan. Tunggu sebentar.'], 429);
        }

        RateLimiter::hit($key, (int) $limit['decay']);

        try {
            $id = ChatService::send($queue, $user, $request->str('body'));
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json(['id' => $id, 'ok' => true], 201);
    }

    /**
     * Data grafik rekap untuk panel petugas dan admin.
     */
    public function stats(Request $request): Response
    {
        $locationId = (int) Auth::locationId();
        $period = $request->str('periode', 'hari');

        if (!in_array($period, ['hari', 'minggu', 'bulan', 'tahun'], true)) {
            $period = 'hari';
        }

        $officerId = Auth::is('officer') ? (int) Auth::id() : null;

        return $this->json([
            'summary' => RecapService::summary($locationId, $period, $officerId),
            'by_service' => RecapService::byService($locationId, $period, $officerId),
            'per_hour' => RecapService::perHour($locationId),
        ]);
    }
}
