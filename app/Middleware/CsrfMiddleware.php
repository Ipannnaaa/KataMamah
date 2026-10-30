<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class CsrfMiddleware
{
    /**
     * @param array<int, string> $args
     * @param array<string, string> $params
     */
    public static function handle(Request $request, array $args = [], array $params = []): ?Response
    {
        if (!in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        if (Csrf::check($request)) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'Token kedaluwarsa, muat ulang halaman.'], 419);
        }

        Session::flash('error', 'Sesi formulir kedaluwarsa. Silakan ulangi.');

        return Response::html(
            View::render('errors/status', [
                'status' => 419,
                'message' => 'Token formulir tidak sah atau kedaluwarsa. Muat ulang halaman lalu coba lagi.',
            ], 'public'),
            419
        );
    }
}
