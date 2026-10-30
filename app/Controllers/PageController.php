<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

final class PageController extends Controller
{
    public function about(Request $request): Response
    {
        $stats = DB::first(
            'SELECT (SELECT COUNT(*) FROM districts) AS districts,
                    (SELECT COUNT(*) FROM locations WHERE is_active = 1) AS locations,
                    (SELECT COUNT(*) FROM services WHERE is_active = 1) AS services,
                    (SELECT COUNT(*) FROM queues) AS queues'
        ) ?? [];

        return $this->view('pages/about', [
            'title' => 'Tentang WIA',
            'stats' => $stats,
        ]);
    }
}
