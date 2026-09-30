<?php

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function validate(string $token): bool
    {
        return $token !== '' && hash_equals((string) Session::get('_csrf', ''), $token);
    }
}
