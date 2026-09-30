<?php

namespace App\Core;

final class RateLimiter
{
    public static function allow(string $key, int $max = 8, int $seconds = 300): bool
    {
        $bucket = Session::get('_rate_' . $key, []);
        $now = time();
        $bucket = array_values(array_filter($bucket, fn ($time) => $time > $now - $seconds));
        if (count($bucket) >= $max) {
            return false;
        }
        $bucket[] = $now;
        Session::put('_rate_' . $key, $bucket);
        return true;
    }
}
