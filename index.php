<?php

declare(strict_types=1);

// The Apache configuration serves existing assets directly. Mirror that behavior
// when the project is previewed with PHP's built-in development server.
if (PHP_SAPI === 'cli-server') {
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
    if ($requestPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$router = new Router();
require __DIR__ . '/routes/web.php';
require __DIR__ . '/routes/account.php';
require __DIR__ . '/routes/admin.php';

$router->dispatch(Request::capture());
