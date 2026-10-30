<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\District;
use App\Models\Location;
use App\Models\Service;
use App\Services\MapScene;

/**
 * Halaman peta. Petanya digambar sendiri sebagai scene isometrik dari data
 * kecamatan dan lokasi, jadi tidak bergantung pada penyedia tile mana pun.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render(null);
    }

    /**
     * @param array<string, string> $params
     */
    public function district(Request $request, array $params): Response
    {
        $district = District::findBySlug($params['slug']);

        if ($district === null) {
            return $this->abort($request, 404, 'Kecamatan tidak ditemukan.');
        }

        return $this->render($district);
    }

    /**
     * @param array<string, mixed>|null $active
     */
    private function render(?array $active): Response
    {
        $districts = District::withCounts();

        // Seluruh landmark tetap digambar walau satu kecamatan sedang dipilih,
        // supaya pengunjung tidak kehilangan gambaran wilayah keseluruhan.
        $all = Location::activeWithQuota();
        $slug = $active === null ? null : (string) $active['slug'];

        return $this->view('home/index', [
            'title' => $active === null ? 'Peta layanan' : 'Kecamatan ' . $active['name'],
            'districts' => $districts,
            'locations' => $active === null ? $all : Location::activeWithQuota((int) $active['id']),
            'active' => $active,
            'scene' => MapScene::build($districts, $all, Service::namesByLocation(), $slug),
            'zoomScale' => (float) config('map.zoom_scale', 2.6),
            'scripts' => ['map.js', 'search.js'],
        ]);
    }
}
