<?php

namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function load(string $path): void
    {
        $local = $path . '/local.php';
        if (is_file($local)) {
            require $local;
        }
        foreach (glob($path . '/*.php') ?: [] as $file) {
            if (basename($file) === 'local.php' || str_ends_with($file, '.example.php')) {
                continue;
            }
            self::$items[pathinfo($file, PATHINFO_FILENAME)] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
