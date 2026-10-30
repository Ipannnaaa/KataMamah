<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\Queue;
use App\Services\ChatService;
use App\Services\UploadService;

/**
 * Berkas warga disimpan di luar web root, jadi satu-satunya jalan membacanya
 * adalah lewat sini — setelah hak akses diperiksa.
 */
final class FileController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $document = DB::first(
            'SELECT qd.*, q.code
               FROM queue_documents qd
               JOIN queues q ON q.id = qd.queue_id
              WHERE qd.id = ? LIMIT 1',
            [(int) $params['id']]
        );

        if ($document === null) {
            return $this->abort($request, 404, 'Berkas tidak ditemukan.');
        }

        $user = Auth::user();
        $queue = Queue::findByCode((string) $document['code']);

        if ($user === null || $queue === null || !ChatService::canAccess($queue, $user)) {
            return $this->abort($request, 403, 'Anda tidak berhak membuka berkas ini.');
        }

        $path = UploadService::absolutePath((string) $document['file_path']);

        if (!is_file($path)) {
            return $this->abort($request, 404, 'Berkas sudah tidak ada di penyimpanan.');
        }

        $contents = (string) file_get_contents($path);

        return Response::text($contents)
            ->withHeader('Content-Type', (string) $document['mime'])
            ->withHeader('Content-Length', (string) strlen($contents))
            ->withHeader('Content-Disposition', 'inline; filename="' . basename((string) $document['original_name']) . '"');
    }
}
