<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CodeGenerator;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\Queue;

/**
 * Cek antrean tanpa login. Hanya data non-sensitif yang ditampilkan:
 * nama pemohon dimasking, berkas dan percakapan tidak disertakan.
 */
final class TrackController extends Controller
{
    public function index(Request $request): Response
    {
        $data = [
            'title' => 'Cek antrean',
            'code' => '',
            'queue' => null,
            'logs' => [],
            'notFound' => false,
            'limited' => false,
        ];

        // Smart search dan halaman sukses mengirim ?kode=…, jadi hasilnya
        // langsung tampil tanpa pengunjung perlu menekan tombol lagi.
        $prefill = $request->str('kode');

        if (!$request->isPost() && $prefill === '') {
            return $this->view('track/index', $data);
        }

        $limit = (array) config('app.rate_limit.track', ['max' => 10, 'decay' => 600]);
        $key = 'track:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $limit['max'], (int) $limit['decay'])) {
            $data['limited'] = true;

            return $this->view('track/index', $data);
        }

        RateLimiter::hit($key, (int) $limit['decay']);

        $code = CodeGenerator::normalize($prefill !== '' ? $prefill : $request->str('code'));
        $data['code'] = $code;

        $queue = Queue::findByCode($code);

        if ($queue === null) {
            $data['notFound'] = true;

            return $this->view('track/index', $data);
        }

        $data['queue'] = [
            'code' => (string) $queue['code'],
            'number' => (int) $queue['queue_number'],
            'status' => (string) $queue['status'],
            'service_name' => (string) $queue['service_name'],
            'location_name' => (string) $queue['location_name'],
            'district_name' => (string) $queue['district_name'],
            'queue_date' => (string) $queue['queue_date'],
            'slot_time' => $queue['slot_time'],
            'user_masked' => mask_nama((string) $queue['user_name']),
            'ahead' => $this->countAhead($queue),
        ];

        $data['logs'] = Queue::statusLogs((int) $queue['id']);

        return $this->view('track/index', $data);
    }

    /**
     * Berapa pemohon di depan yang belum selesai pada tanggal yang sama.
     *
     * @param array<string, mixed> $queue
     */
    private function countAhead(array $queue): int
    {
        return (int) \App\Core\DB::column(
            "SELECT COUNT(*) FROM queues
              WHERE location_id = ?
                AND queue_date = ?
                AND queue_number < ?
                AND status IN ('baru','ditinjau','ditangani')",
            [$queue['location_id'], $queue['queue_date'], $queue['queue_number']]
        );
    }
}
