<?php

namespace App\Core;

final class Pagination
{
    public static function offset(int $page, int $perPage): int { return max(0, ($page - 1) * $perPage); }
    public static function pages(int $total, int $perPage): int { return max(1, (int) ceil($total / $perPage)); }
}
