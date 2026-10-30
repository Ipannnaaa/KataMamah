<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }

        return $token;
    }

    public static function check(Request $request): bool
    {
        $sent = $request->str('_token');
        $stored = Session::get('_csrf');

        if (!is_string($stored) || $stored === '' || $sent === '') {
            return false;
        }

        return hash_equals($stored, $sent);
    }
}
