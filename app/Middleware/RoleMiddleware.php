<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class RoleMiddleware
{
    /**
     * Dipakai sebagai 'role:officer,admin'. Petugas dan admin juga wajib
     * punya location_id, karena seluruh query panel memfilter dengan itu.
     *
     * @param array<int, string> $args
     * @param array<string, string> $params
     */
    public static function handle(Request $request, array $args = [], array $params = []): ?Response
    {
        if (!Auth::is(...$args)) {
            return self::deny($request, 'Anda tidak punya akses ke halaman ini.');
        }

        $bound = (array) config('roles.location_bound', []);

        if (in_array((string) Auth::role(), $bound, true) && Auth::locationId() === null) {
            return self::deny($request, 'Akun ini belum ditautkan ke lokasi layanan mana pun.');
        }

        return null;
    }

    private static function deny(Request $request, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], 403);
        }

        return Response::html(
            View::render('errors/status', ['status' => 403, 'message' => $message], 'public'),
            403
        );
    }
}
