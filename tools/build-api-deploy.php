<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
$destination = $argv[1] ?? '';
if ($destination === '' || is_dir($destination) || file_exists($destination)) {
    fwrite(STDERR, "Destinația trebuie să fie o cale nouă și inexistentă.\n");
    exit(1);
}

$required = ['DEPLOY_DB_NAME', 'DEPLOY_DB_USER', 'DEPLOY_DB_PASSWORD', 'DEPLOY_GOOGLE_CREDENTIALS'];
foreach ($required as $key) {
    if ((string) getenv($key) === '') {
        fwrite(STDERR, "Lipsește variabila $key.\n");
        exit(1);
    }
}

$copyTree = static function (string $source, string $target) use (&$copyTree): void {
    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        throw new RuntimeException("Nu pot crea $target");
    }
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) continue;
        $to = $target . DIRECTORY_SEPARATOR . $item->getFilename();
        if ($item->isDir()) $copyTree($item->getPathname(), $to);
        elseif (!copy($item->getPathname(), $to)) throw new RuntimeException("Nu pot copia {$item->getPathname()}");
    }
};

mkdir($destination, 0775, true);
copy($root . '/api/index.php', $destination . '/index.php');
copy($root . '/api/.htaccess', $destination . '/.htaccess');
$copyTree($root . '/app', $destination . '/_backend/app');
$copyTree($root . '/config', $destination . '/_backend/config');
@unlink($destination . '/_backend/config/local.php');
mkdir($destination . '/_backend/routes', 0775, true);
copy($root . '/routes/api.php', $destination . '/_backend/routes/api.php');
mkdir($destination . '/_backend/database', 0775, true);
copy($root . '/database/schema.sql', $destination . '/_backend/database/schema.sql');
copy($root . '/database/seed.sql', $destination . '/_backend/database/seed.sql');
copy($root . '/produse_woocommerce_complet.json', $destination . '/_backend/database/products.json');
mkdir($destination . '/_backend/storage/logs', 0775, true);
mkdir($destination . '/_backend/views', 0775, true);
$copyTree($root . '/views/emails', $destination . '/_backend/views/emails');
copy((string) getenv('DEPLOY_GOOGLE_CREDENTIALS'), $destination . '/_backend/config/google-client.json');

mkdir($destination . '/media/categories', 0775, true);
foreach (glob($root . '/assets/images/categories/*') ?: [] as $image) {
    if (is_file($image)) copy($image, $destination . '/media/categories/' . basename($image));
}

$env = [
    'APP_URL' => 'https://smilebaby.ro',
    'APP_ENV' => 'production',
    'APP_DEBUG' => '0',
    'APP_KEY' => bin2hex(random_bytes(32)),
    'DB_HOST' => getenv('DEPLOY_DB_HOST') ?: 'localhost',
    'DB_PORT' => getenv('DEPLOY_DB_PORT') ?: '3306',
    'DB_DATABASE' => getenv('DEPLOY_DB_NAME'),
    'DB_USERNAME' => getenv('DEPLOY_DB_USER'),
    'DB_PASSWORD' => getenv('DEPLOY_DB_PASSWORD'),
    'GOOGLE_CLIENT_SECRET_FILE' => '__GOOGLE_FILE__',
    'GOOGLE_REDIRECT_URI' => 'https://smilebaby.ro/autentificare/google/callback',
    'API_ALLOWED_ORIGINS' => 'https://smilebaby.ro,https://www.smilebaby.ro',
    'IMPORT_REMOTE_MEDIA' => '1',
];
$local = "<?php\n\n";
foreach ($env as $key => $value) {
    if ($value === '__GOOGLE_FILE__') $local .= "putenv('GOOGLE_CLIENT_SECRET_FILE=' . __DIR__ . '/google-client.json');\n";
    else $local .= 'putenv(' . var_export($key . '=' . $value, true) . ");\n";
}
file_put_contents($destination . '/_backend/config/local.php', $local);

$token = bin2hex(random_bytes(20));
$installerName = 'install-' . bin2hex(random_bytes(8)) . '.php';
$installer = <<<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!hash_equals('__TOKEN__', (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}
define('API_REQUEST', true);
require __DIR__ . '/_backend/app/bootstrap.php';
try {
    $db = App\Core\Database::connection();
    foreach (['schema.sql', 'seed.sql'] as $file) {
        $sql = (string) file_get_contents(__DIR__ . '/_backend/database/' . $file);
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') $db->exec($statement);
        }
    }
    $result = (new App\Services\ImportService())->import(__DIR__ . '/_backend/database/products.json', __DIR__ . '/_backend/storage');
    $counts = [];
    foreach (['users','categories','products','product_images','product_categories','settings'] as $table) {
        $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    @unlink(__FILE__);
    echo json_encode(['ok' => true, 'import' => $result, 'counts' => $counts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
PHP;
$installer = str_replace('__TOKEN__', $token, $installer);
file_put_contents($destination . '/' . $installerName, $installer);

echo json_encode(['destination' => $destination, 'installer' => $installerName, 'token' => $token], JSON_UNESCAPED_SLASHES) . PHP_EOL;
