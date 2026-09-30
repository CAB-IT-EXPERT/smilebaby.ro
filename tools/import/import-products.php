<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\ImportService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$json = $argv[1] ?? BASE_PATH . '/produse_woocommerce_complet.json';
$uploads = $argv[2] ?? BASE_PATH . '/uploads-source';
if (!is_file($json) || !is_dir($uploads)) {
    fwrite(STDERR, "Utilizare: php tools/import/import-products.php produse.json uploads-source\n");
    exit(1);
}
$result = (new ImportService())->import($json, $uploads);
echo "Produse importate/actualizate: {$result['count']}\nImagini copiate: {$result['images']}\nImagini lipsă: " . count($result['missing']) . "\n";
if ($result['missing']) file_put_contents(BASE_PATH . '/storage/logs/import-missing.log', implode(PHP_EOL, array_unique($result['missing'])));
