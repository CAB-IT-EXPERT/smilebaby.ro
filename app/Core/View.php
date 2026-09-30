<?php

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/storefront'): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/views/' . $view . '.php';
        $content = ob_get_clean();
        require BASE_PATH . '/views/' . $layout . '.php';
    }
}
