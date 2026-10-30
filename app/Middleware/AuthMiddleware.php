<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    /**
     * @param array<int, string> $args
     * @param array<string, string> $params
     */
    public static function handle(Request $request, array $args = [], array $params = []): ?Response
    {
        if (Auth::check()) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'Perlu masuk terlebih dahulu.'], 401);
        }

        Session::flash('error', 'Masuk terlebih dahulu untuk melanjutkan.');

        return Response::redirect('/masuk?next=' . urlencode($request->path));
    }
}
