<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Configures and validates products that may be bought together with a parent
 * product at a product-specific price.
 */
final class ProductAddonService
{
    private static bool $schemaReady = false;

    public function ensureSchema(?PDO $db = null): void
    {
        if (self::$schemaReady || !Database::available()) return;
        $db ??= Database::connection();

        try {
            $db->query('SELECT addons_enabled FROM products LIMIT 0');
            $db->query('SELECT id FROM product_addons LIMIT 0');
            $db->query('SELECT addons_json,addons_total FROM order_items LIMIT 0');
            self::$schemaReady = true;
            return;
        } catch (PDOException) {
            // The first request after deployment applies this idempotent upgrade.
        }

        if (!$this->columnExists($db, 'products', 'addons_enabled')) {
            $db->exec('ALTER TABLE products ADD addons_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER badge_text');
        }
        $db->exec('CREATE TABLE IF NOT EXISTS product_addons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            addon_product_id BIGINT UNSIGNED NOT NULL,
            custom_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_product_addon (product_id,addon_product_id),
            INDEX idx_product_addons (product_id,sort_order,id),
            CONSTRAINT fk_addon_parent FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_addon_product FOREIGN KEY (addon_product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        if (!$this->columnExists($db, 'order_items', 'addons_json')) {
            $db->exec('ALTER TABLE order_items ADD addons_json MEDIUMTEXT NULL AFTER customization_price');
        }
        if (!$this->columnExists($db, 'order_items', 'addons_total')) {
            $db->exec('ALTER TABLE order_items ADD addons_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER addons_json');
        }
        self::$schemaReady = true;
    }

    public function catalog(int $excludeProductId = 0, ?PDO $db = null): array
    {
        if (!Database::available()) return [];
        $db ??= Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT p.id,p.name,p.slug,p.sku,p.regular_price,p.sale_price,
                COALESCE(p.sale_price,p.regular_price) price,
                (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path,
                GROUP_CONCAT(DISTINCT c.id ORDER BY c.name SEPARATOR ",") category_ids,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR " · ") categories
            FROM products p
            LEFT JOIN product_categories pc ON pc.product_id=p.id
            LEFT JOIN categories c ON c.id=pc.category_id
            WHERE p.status="active" AND p.id<>?
            GROUP BY p.id
            ORDER BY COALESCE(MIN(c.name),"zzzz"),p.name');
        $stmt->execute([$excludeProductId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['category_ids'] = array_values(array_filter(array_map('intval', explode(',', (string) ($row['category_ids'] ?? '')))));
            $row['price'] = (float) $row['price'];
        }
        unset($row);
        return $rows;
    }

    public function configured(int $productId, ?PDO $db = null): array
    {
        if ($productId < 1 || !Database::available()) return [];
        $db ??= Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT pa.id,pa.product_id,pa.addon_product_id,pa.custom_price,pa.sort_order,
                p.name,p.slug,p.sku,p.regular_price,p.sale_price,
                COALESCE(p.sale_price,p.regular_price) catalog_price,
                (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path,
                (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR " · ") FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=p.id) categories
            FROM product_addons pa
            JOIN products p ON p.id=pa.addon_product_id
            WHERE pa.product_id=?
            ORDER BY pa.sort_order,pa.id');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public function storefrontOptions(int $productId, ?PDO $db = null): array
    {
        if ($productId < 1 || !Database::available()) return [];
        $db ??= Database::connection();
        $this->ensureSchema($db);
        $stmt = $db->prepare('SELECT pa.addon_product_id product_id,pa.custom_price price,pa.sort_order,
                p.name,p.slug,p.sku,p.regular_price,p.sale_price,p.manage_stock,p.stock_quantity,p.stock_status,p.allow_backorders,
                COALESCE(p.sale_price,p.regular_price) catalog_price,
                (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path,
                (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR " · ") FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=p.id) categories
            FROM products parent
            JOIN product_addons pa ON pa.product_id=parent.id
            JOIN products p ON p.id=pa.addon_product_id AND p.status="active"
            WHERE parent.id=? AND parent.addons_enabled=1
            ORDER BY pa.sort_order,pa.id');
        $stmt->execute([$productId]);
        return array_values(array_filter($stmt->fetchAll(), fn (array $row): bool => $this->available($row)));
    }

    public function save(PDO $db, int $productId, bool $enabled, array $productIds, array $prices): void
    {
        $this->ensureSchema($db);
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0 && $id !== $productId)));
        $db->prepare('UPDATE products SET addons_enabled=? WHERE id=?')->execute([(int) ($enabled && $ids), $productId]);
        $db->prepare('DELETE FROM product_addons WHERE product_id=?')->execute([$productId]);
        // Keep the configuration while the feature is temporarily disabled so
        // the merchant can reactivate it without rebuilding the selection.
        if (!$ids) return;

        $ids = array_slice($ids, 0, 100);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $available = $db->prepare("SELECT id,COALESCE(sale_price,regular_price) price FROM products WHERE status=\"active\" AND id IN ($marks)");
        $available->execute($ids);
        $catalogPrices = [];
        foreach ($available->fetchAll() as $row) $catalogPrices[(int) $row['id']] = (float) $row['price'];

        $insert = $db->prepare('INSERT INTO product_addons (product_id,addon_product_id,custom_price,sort_order) VALUES (?,?,?,?)');
        foreach ($ids as $index => $addonId) {
            if (!array_key_exists($addonId, $catalogPrices)) continue;
            $rawPrice = $prices[(string) $addonId] ?? $prices[$addonId] ?? '';
            $price = $rawPrice === '' ? $catalogPrices[$addonId] : max(0, round((float) $rawPrice, 2));
            $insert->execute([$productId, $addonId, $price, $index]);
        }
    }

    public function validateSelections(int $productId, array $rawSelections, ?PDO $db = null, bool $lock = false, int $multiplier = 1): array
    {
        $multiplier = min(99, max(1, $multiplier));
        $requested = $this->requested($rawSelections);
        if (!$requested) return [];
        if (!Database::available()) throw new RuntimeException('Produsele suplimentare nu pot fi verificate momentan.');
        $db ??= Database::connection();
        $this->ensureSchema($db);

        $ids = array_keys($requested);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT pa.addon_product_id product_id,pa.custom_price price,pa.sort_order,
                p.name,p.slug,p.sku,p.regular_price,p.sale_price,p.manage_stock,p.stock_quantity,p.stock_status,p.allow_backorders,
                (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path,
                (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR " · ") FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=p.id) categories
            FROM products parent
            JOIN product_addons pa ON pa.product_id=parent.id
            JOIN products p ON p.id=pa.addon_product_id AND p.status="active"
            WHERE parent.id=? AND parent.addons_enabled=1 AND pa.addon_product_id IN (' . $marks . ')
            ORDER BY pa.sort_order,pa.id' . ($lock ? ' FOR UPDATE' : '');
        $stmt = $db->prepare($sql);
        $stmt->execute([$productId, ...$ids]);
        $configured = [];
        foreach ($stmt->fetchAll() as $row) $configured[(int) $row['product_id']] = $row;

        if (count($configured) !== count($requested)) {
            throw new RuntimeException('Una dintre opțiunile suplimentare nu mai este disponibilă pentru acest produs.');
        }

        $result = [];
        foreach ($requested as $addonId => $quantity) {
            $row = $configured[$addonId];
            if (!$this->available($row)) throw new RuntimeException('Produsul suplimentar „' . $row['name'] . '” nu mai este disponibil.');
            if (!empty($row['manage_stock']) && empty($row['allow_backorders']) && (int) $row['stock_quantity'] < $quantity * $multiplier) {
                throw new RuntimeException('Stoc insuficient pentru produsul suplimentar „' . $row['name'] . '”.');
            }
            $price = max(0, round((float) $row['price'], 2));
            $result[] = [
                'product_id' => $addonId,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'sku' => $row['sku'],
                'image_path' => $row['image_path'],
                'categories' => $row['categories'] ?? '',
                'price' => $price,
                'quantity' => $quantity,
                'total' => round($price * $quantity, 2),
                'manage_stock' => (int) $row['manage_stock'],
                'allow_backorders' => (int) $row['allow_backorders'],
                'stock_quantity' => $row['stock_quantity'] !== null ? (int) $row['stock_quantity'] : null,
            ];
        }
        usort($result, static fn (array $a, array $b): int => ((int) $configured[$a['product_id']]['sort_order']) <=> ((int) $configured[$b['product_id']]['sort_order']));
        return $result;
    }

    public function rawSelections(array $stored): array
    {
        $raw = [];
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($value['product_id'])) {
                $raw[(string) (int) $value['product_id']] = ['selected' => 1, 'quantity' => (int) ($value['quantity'] ?? 1)];
            } elseif (is_array($value)) {
                $raw[(string) (int) $key] = $value;
            }
        }
        return $raw;
    }

    private function requested(array $raw): array
    {
        $requested = [];
        foreach (array_slice($raw, 0, 100, true) as $key => $value) {
            if (!is_array($value)) continue;
            $id = isset($value['product_id']) ? (int) $value['product_id'] : (int) $key;
            $selected = isset($value['product_id']) || !empty($value['selected']);
            if ($id < 1 || !$selected) continue;
            $requested[$id] = min(99, max(1, (int) ($value['quantity'] ?? 1)));
        }
        ksort($requested, SORT_NUMERIC);
        return $requested;
    }

    private function available(array $row): bool
    {
        // Products with unmanaged stock are, by definition, continuously available.
        if (empty($row['manage_stock'])) return true;
        if (!empty($row['allow_backorders']) || ($row['stock_status'] ?? '') === 'on_backorder') return true;
        return ($row['stock_status'] ?? '') !== 'out_of_stock' && (int) ($row['stock_quantity'] ?? 0) > 0;
    }

    private function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
