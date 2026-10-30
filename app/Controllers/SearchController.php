<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SearchService;

final class SearchController extends Controller
{
    /**
     * Halaman hasil pencarian. Tetap berfungsi tanpa JavaScript.
     */
    public function page(Request $request): Response
    {
        $term = $request->str('q');

        return $this->view('search/index', [
            'title' => $term === '' ? 'Pencarian' : 'Hasil pencarian: ' . $term,
            'term' => $term,
            'results' => $term === '' ? [] : SearchService::query($term, 24),
            'scripts' => ['search.js'],
        ]);
    }

    /**
     * Saran langsung saat mengetik.
     */
    public function suggest(Request $request): Response
    {
        $term = $request->str('q');

        return $this->json([
            'q' => $term,
            'results' => SearchService::query($term, 8),
        ]);
    }
}
