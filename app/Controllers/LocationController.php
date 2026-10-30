<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Location;
use App\Models\Service;
use App\Services\MapScene;

final class LocationController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        $location = Location::findBySlug($params['slug']);

        if ($location === null) {
            return $this->abort($request, 404, 'Lokasi layanan tidak ditemukan.');
        }

        return $this->view('location/show', [
            'title' => (string) $location['name'],
            'location' => $location,
            'services' => Service::withRequirements((int) $location['id']),
            'schedule' => Location::upcomingQuota((int) $location['id'], 7),
            'openHours' => json_decode((string) ($location['open_hours'] ?? ''), true) ?: [],
            'mapsUrl' => MapScene::mapsUrl((float) $location['lat'], (float) $location['lng']),
        ]);
    }
}
