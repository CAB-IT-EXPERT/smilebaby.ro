<?php

declare(strict_types=1);

define('API_REQUEST', true);

$localBootstrap = dirname(__DIR__) . '/app/bootstrap.php';
$packagedBootstrap = __DIR__ . '/_backend/app/bootstrap.php';
require is_file($packagedBootstrap) ? $packagedBootstrap : $localBootstrap;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$allowedOrigins = config('api.allowed_origins', []);
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Cart-Token, Accept');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code($origin === '' || in_array($origin, $allowedOrigins, true) ? 204 : 403);
    exit;
}

$router = new Router();
require (is_file(__DIR__ . '/_backend/routes/api.php') ? __DIR__ . '/_backend/routes/api.php' : dirname(__DIR__) . '/routes/api.php');
$router->dispatch(Request::capture());
