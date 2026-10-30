<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

final class LandingController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = DB::first(
            'SELECT (SELECT COUNT(*) FROM districts) AS districts,
                    (SELECT COUNT(*) FROM locations WHERE is_active = 1) AS locations,
                    (SELECT COUNT(*) FROM services WHERE is_active = 1) AS services,
                    (SELECT COUNT(*) FROM queues WHERE queue_date = CURDATE()) AS queues_today'
        ) ?? [];

        return $this->view('landing/index', [
            'title' => 'Beranda',
            'stats' => array_map(static fn ($v): int => (int) $v, $stats),
        ], 'landing');
    }
}
