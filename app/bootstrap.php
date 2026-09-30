<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $value, ?string $encoding = null): string
    {
        return strtr(strtoupper($value), ['ă' => 'Ă', 'â' => 'Â', 'î' => 'Î', 'ș' => 'Ș', 'ş' => 'Ş', 'ț' => 'Ț', 'ţ' => 'Ţ']);
    }
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Helpers/functions.php';

App\Core\Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) config('app.timezone', 'Europe/Bucharest'));
App\Core\Session::start();

set_exception_handler(static function (Throwable $error): void {
    error_log($error->__toString());
    http_response_code(500);
    if (defined('API_REQUEST') && API_REQUEST) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => ['code' => 'server_error', 'message' => config('app.debug') ? $error->getMessage() : 'Serviciul nu este disponibil momentan.']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
    $view = BASE_PATH . '/views/errors/500.php';
    if (is_file($view)) {
        require $view;
        return;
    }
    echo 'A apărut o eroare. Te rugăm să încerci din nou.';
});
