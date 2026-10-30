<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Location;
use App\Models\Queue;
use App\Services\ChatService;
use App\Services\QueueService;
use App\Services\RecapService;
use Throwable;

final class OfficerController extends Controller
{
    public function index(Request $request): Response
    {
        $locationId = (int) Auth::locationId();
        $officerId = (int) Auth::id();
        $tab = $request->str('tab', 'baru');

        if (!in_array($tab, ['baru', 'saya', 'selesai'], true)) {
            $tab = 'baru';
        }

        return $this->view('officer/index', [
            'title' => 'Papan antrean',
            'focus' => 'papan',
            'tab' => $tab,
            'cards' => Queue::board($locationId, $tab, $officerId),
            'counts' => [
                'baru' => count(Queue::board($locationId, 'baru')),
                'saya' => count(Queue::board($locationId, 'saya', $officerId)),
                'selesai' => count(Queue::board($locationId, 'selesai', $officerId)),
            ],
            'location' => Location::find($locationId),
            'summary' => RecapService::summary($locationId, 'hari', $officerId),
            'byService' => RecapService::byService($locationId, 'hari', $officerId),
            'period' => 'hari',
        ], 'panel');
    }

    public function recap(Request $request): Response
    {
        $locationId = (int) Auth::locationId();
        $officerId = (int) Auth::id();
        $period = $request->str('periode', 'hari');

        if (!in_array($period, ['hari', 'minggu', 'bulan', 'tahun'], true)) {
            $period = 'hari';
        }

        return $this->view('officer/index', [
            'title' => 'Rekap kinerja',
            'focus' => 'rekap',
            'tab' => 'saya',
            'cards' => Queue::board($locationId, 'saya', $officerId),
            'counts' => [
                'baru' => count(Queue::board($locationId, 'baru')),
                'saya' => count(Queue::board($locationId, 'saya', $officerId)),
                'selesai' => count(Queue::board($locationId, 'selesai', $officerId)),
            ],
            'location' => Location::find($locationId),
            'summary' => RecapService::summary($locationId, $period, $officerId),
            'byService' => RecapService::byService($locationId, $period, $officerId),
            'period' => $period,
        ], 'panel');
    }

    /**
     * @param array<string, string> $params
     */
    public function claim(Request $request, array $params): Response
    {
        $queue = $this->queueAtMyLocation($params['code']);

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ada di lokasi Anda.');
        }

        try {
            QueueService::changeStatus($queue, 'ditinjau', (int) Auth::id(), 'officer', 'Antrean diterima petugas');
        } catch (Throwable $e) {
            return $this->failure($e->getMessage(), '/petugas');
        }

        return $this->success('Antrean ' . $queue['code'] . ' diterima.', '/petugas?tab=saya');
    }

    /**
     * @param array<string, string> $params
     */
    public function status(Request $request, array $params): Response
    {
        $queue = $this->queueAtMyLocation($params['code']);

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ada di lokasi Anda.');
        }

        $to = $request->str('status');

        if (!array_key_exists($to, (array) config('roles.status', []))) {
            return $this->failure('Status tujuan tidak dikenal.', '/petugas?tab=saya');
        }

        try {
            QueueService::changeStatus($queue, $to, (int) Auth::id(), 'officer', $request->str('catatan') ?: null);
        } catch (Throwable $e) {
            return $this->failure($e->getMessage(), '/petugas?tab=saya');
        }

        return $this->success(
            'Status ' . $queue['code'] . ' menjadi "' . config('roles.status.' . $to) . '".',
            '/petugas?tab=saya'
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function note(Request $request, array $params): Response
    {
        $queue = $this->queueAtMyLocation($params['code']);

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ada di lokasi Anda.');
        }

        if ((int) ($queue['officer_id'] ?? 0) !== (int) Auth::id()) {
            return $this->abort($request, 403, 'Catatan hanya dapat diubah oleh petugas yang menangani.');
        }

        QueueService::saveOfficerNote($queue, $request->str('note_officer'), (int) Auth::id());

        return $this->success('Catatan penanganan disimpan.', '/petugas?tab=saya');
    }

    /**
     * @param array<string, string> $params
     */
    public function chat(Request $request, array $params): Response
    {
        $user = Auth::user() ?? [];
        $queue = $this->queueAtMyLocation($params['code']);

        if ($queue === null) {
            return $this->abort($request, 404, 'Antrean tidak ada di lokasi Anda.');
        }

        try {
            $messages = ChatService::messages($queue, $user);
        } catch (Throwable $e) {
            return $this->abort($request, 403, $e->getMessage());
        }

        return $this->view('chat/thread', [
            'title' => 'Chat ' . $queue['code'],
            'layoutRole' => 'officer',
            'queue' => $queue,
            'messages' => $messages,
            'threads' => [],
            'open' => ChatService::isOpen($queue),
            'me' => $user,
            'scripts' => ['chat.js'],
        ], 'panel');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function queueAtMyLocation(string $code): ?array
    {
        return Queue::findForLocation($code, (int) Auth::locationId());
    }
}
