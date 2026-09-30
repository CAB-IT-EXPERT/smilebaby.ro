<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class StripeCatalogService
{
    private static bool $schemaReady = false;

    public function __construct(private readonly StripeClient $stripe = new StripeClient()) {}

    public function ensureSchema(?PDO $db = null): void
    {
        if (self::$schemaReady || !Database::available()) return;
        $db ??= Database::connection();
        $columns = [
            'stripe_product_id' => 'VARCHAR(100) NULL AFTER indexable',
            'stripe_price_id' => 'VARCHAR(100) NULL AFTER stripe_product_id',
            'stripe_price_amount' => 'INT NULL AFTER stripe_price_id',
            'stripe_synced_at' => 'DATETIME NULL AFTER stripe_price_amount',
            'stripe_sync_error' => 'VARCHAR(500) NULL AFTER stripe_synced_at',
        ];
        foreach ($columns as $column => $definition) {
            $check = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="products" AND COLUMN_NAME=?');
            $check->execute([$column]);
            if (!(int) $check->fetchColumn()) $db->exec('ALTER TABLE products ADD ' . $column . ' ' . $definition);
        }
        self::$schemaReady = true;
    }

    public function sync(int $productId): array
    {
        $db = Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT p.*,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path,(SELECT COUNT(*) FROM product_variants WHERE product_id=p.id AND status="active") variant_count FROM products p WHERE p.id=? LIMIT 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) throw new RuntimeException('Produsul local nu există.');

        try {
            $active = $product['status'] === 'active';
            $description = trim(strip_tags((string) ($product['short_description'] ?: $product['description'])));
            $payload = [
                'name' => mb_substr((string) $product['name'], 0, 250),
                'description' => mb_substr($description, 0, 5000),
                'active' => $active ? 'true' : 'false',
                'metadata' => [
                    'smilebaby_product_id' => (string) $product['id'],
                    'sku' => (string) ($product['sku'] ?? ''),
                    'slug' => (string) $product['slug'],
                    'customizable' => !empty($product['is_customizable']) ? 'yes' : 'no',
                    'customization_price' => number_format((float) ($product['customization_price'] ?? 0), 2, '.', ''),
                    'addons_enabled' => !empty($product['addons_enabled']) ? 'yes' : 'no',
                    'variant_count' => (string) ($product['variant_count'] ?? 0),
                ],
            ];
            $publicBase = (string) config('app.url');
            if (str_starts_with($publicBase, 'https://')) {
                $payload['url'] = rtrim($publicBase, '/') . '/produs/' . rawurlencode((string) $product['slug']);
                $image = (string) ($product['image_path'] ?? '');
                if ($image !== '') $payload['images'] = [rtrim($publicBase, '/') . '/' . ltrim($image, '/')];
            }

            $remoteId = trim((string) ($product['stripe_product_id'] ?? ''));
            if ($remoteId === '') {
                $remote = $this->stripe->post('/v1/products', $payload, 'smilebaby-product-create-' . $productId);
                $remoteId = (string) ($remote['id'] ?? '');
                if (!str_starts_with($remoteId, 'prod_')) throw new RuntimeException('Stripe nu a returnat identificatorul produsului.');
                $db->prepare('UPDATE products SET stripe_product_id=? WHERE id=?')->execute([$remoteId, $productId]);
            } else {
                $this->stripe->post('/v1/products/' . rawurlencode($remoteId), $payload, 'smilebaby-product-update-' . $productId . '-' . bin2hex(random_bytes(5)));
            }

            $amount = StripeCheckoutService::moneyToCents($product['sale_price'] ?: $product['regular_price']);
            $oldPriceId = trim((string) ($product['stripe_price_id'] ?? ''));
            $oldAmount = $product['stripe_price_amount'] !== null ? (int) $product['stripe_price_amount'] : null;
            $newPriceId = $oldPriceId;
            if ($amount > 0 && ($oldPriceId === '' || $oldAmount !== $amount)) {
                $price = $this->stripe->post('/v1/prices', [
                    'product' => $remoteId,
                    'currency' => (string) config('payments.stripe.currency', 'ron'),
                    'unit_amount' => $amount,
                    'metadata' => ['smilebaby_product_id' => (string) $productId],
                ], 'smilebaby-price-' . $productId . '-' . $amount);
                $newPriceId = (string) ($price['id'] ?? '');
                if (!str_starts_with($newPriceId, 'price_')) throw new RuntimeException('Stripe nu a returnat identificatorul prețului.');
                $this->stripe->post('/v1/products/' . rawurlencode($remoteId), ['default_price' => $newPriceId]);
                if ($oldPriceId !== '' && $oldPriceId !== $newPriceId) $this->stripe->post('/v1/prices/' . rawurlencode($oldPriceId), ['active' => 'false']);
            }
            $db->prepare('UPDATE products SET stripe_price_id=?,stripe_price_amount=?,stripe_synced_at=NOW(),stripe_sync_error=NULL WHERE id=?')
                ->execute([$newPriceId ?: null, $amount > 0 ? $amount : null, $productId]);
            return ['product_id' => $remoteId, 'price_id' => $newPriceId, 'amount' => $amount];
        } catch (\Throwable $error) {
            $db->prepare('UPDATE products SET stripe_sync_error=? WHERE id=?')->execute([mb_substr($error->getMessage(), 0, 500), $productId]);
            throw $error;
        }
    }

    public function deactivate(int $productId): void
    {
        $db = Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT stripe_product_id,stripe_price_id FROM products WHERE id=?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) return;
        if (!empty($product['stripe_product_id'])) $this->stripe->post('/v1/products/' . rawurlencode((string) $product['stripe_product_id']), ['active' => 'false']);
        if (!empty($product['stripe_price_id'])) $this->stripe->post('/v1/prices/' . rawurlencode((string) $product['stripe_price_id']), ['active' => 'false']);
    }

    public function syncAll(): array
    {
        $this->ensureSchema();
        $ids = Database::connection()->query('SELECT id FROM products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $success = 0; $errors = [];
        foreach ($ids as $id) {
            try { $this->sync((int) $id); $success++; }
            catch (\Throwable $error) { $errors[(int) $id] = $error->getMessage(); }
        }
        return ['success' => $success, 'failed' => count($errors), 'errors' => $errors];
    }
}
