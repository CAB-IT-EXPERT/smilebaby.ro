<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use App\Models\DemoCatalog;

final class ImportService
{
    public function import(string $jsonFile, string $uploadsRoot): array
    {
        $data = json_decode((string) file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
        $db = Database::connection();
        $remoteMedia = getenv('IMPORT_REMOTE_MEDIA') === '1';
        $count = 0; $images = 0; $missing = [];
        foreach ($data['products'] ?? [] as $row) {
            if (trim((string) ($row['name'] ?? '')) === '') continue;
            Database::transaction(function (PDO $db) use ($row, $uploadsRoot, $remoteMedia, &$count, &$images, &$missing) {
                $slug = slugify((string) $row['name']) . '-' . (int) $row['id'];
                $sql = 'INSERT INTO products (legacy_wp_id,name,slug,sku,gtin,short_description,description,regular_price,sale_price,sale_start,sale_end,manage_stock,stock_quantity,low_stock_threshold,stock_status,allow_backorders,featured,is_customizable,badge_text,brand,weight,length,width,height,status,meta_title,meta_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),sku=VALUES(sku),gtin=VALUES(gtin),short_description=VALUES(short_description),description=VALUES(description),regular_price=VALUES(regular_price),sale_price=VALUES(sale_price),manage_stock=VALUES(manage_stock),stock_quantity=VALUES(stock_quantity),stock_status=VALUES(stock_status),allow_backorders=VALUES(allow_backorders),featured=VALUES(featured),is_customizable=VALUES(is_customizable),status=VALUES(status),updated_at=NOW(),id=LAST_INSERT_ID(id)';
                $inv = $row['inventory'] ?? []; $dim = $row['dimensions'] ?? []; $pricing = $row['pricing'] ?? [];
                $manageStock = ($inv['stock_quantity'] ?? null) !== null ? 1 : 0;
                $stockStatus = $manageStock ? (($inv['in_stock'] ?? true) ? 'in_stock' : 'out_of_stock') : 'in_stock';
                $customizable = str_contains(slugify((string) $row['name'] . ' ' . strip_tags((string) ($row['descriptions']['short_html'] ?? ''))), 'personaliz') ? 1 : 0;
                $stmt = $db->prepare($sql);
                $stmt->execute([(int) $row['id'], $row['name'], $slug, $row['sku'] ?: null, $row['gtin_upc_ean_isbn'] ?: null, $row['descriptions']['short_html'] ?? null, $row['descriptions']['full_html'] ?? null, (float) ($pricing['regular_price'] ?? 0), $pricing['sale_price'] ?: null, $row['promotion']['start'] ?: null, $row['promotion']['end'] ?: null, $manageStock, $manageStock ? ($inv['stock_quantity'] ?? null) : null, $inv['low_stock_threshold'] ?? 3, $stockStatus, $manageStock ? (int) ($inv['backorders_allowed'] ?? false) : 0, (int) ($row['featured'] ?? false), $customizable, null, implode(', ', $row['brands'] ?? []), $dim['weight_kg'] ?? null, $dim['length_cm'] ?? null, $dim['width_cm'] ?? null, $dim['height_cm'] ?? null, ($row['published'] ?? false) ? 'active' : 'draft', null, null]);
                $productId = (int) $db->lastInsertId();
                $db->prepare('DELETE FROM product_categories WHERE product_id=?')->execute([$productId]);
                $mapped = [];
                foreach ($row['categories'] ?? [] as $cat) {
                    $name = DemoCatalog::mapCategory((string) ($cat['path'] ?? ''), (string) $row['name']);
                    if ($name) $mapped[$name] = true;
                }
                if (!$mapped) { $name = DemoCatalog::mapCategory('', (string) $row['name']); if ($name) $mapped[$name] = true; }
                foreach (array_keys($mapped) as $name) {
                    $catSlug = slugify($name);
                    $stmt = $db->prepare('SELECT id FROM categories WHERE slug=? LIMIT 1'); $stmt->execute([$catSlug]);
                    $categoryId = (int) $stmt->fetchColumn();
                    if ($categoryId) $db->prepare('INSERT IGNORE INTO product_categories (product_id,category_id) VALUES (?,?)')->execute([$productId, $categoryId]);
                }
                $db->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$productId]);
                foreach ($row['images'] ?? [] as $index => $image) {
                    $relative = str_replace('\\', '/', (string) ($image['uploads_relative_path'] ?? ''));
                    if ($remoteMedia && !empty($image['url'])) {
                        $db->prepare('INSERT INTO product_images (product_id,image_path,alt_text,sort_order,is_featured) VALUES (?,?,?,?,?)')->execute([$productId, (string) $image['url'], $row['name'] . ' - SmileBaby', $index, $index === 0 ? 1 : 0]);
                        $images++;
                        continue;
                    }
                    $source = rtrim($uploadsRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, preg_replace('#^uploads/#', '', $relative));
                    if (!is_file($source)) { $missing[] = $relative; continue; }
                    $targetRelative = 'uploads/products/imported/' . ltrim(preg_replace('#^uploads/#', '', $relative), '/');
                    $target = BASE_PATH . '/' . $targetRelative;
                    if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
                    if (!is_file($target)) copy($source, $target);
                    $db->prepare('INSERT INTO product_images (product_id,image_path,alt_text,sort_order,is_featured) VALUES (?,?,?,?,?)')->execute([$productId, $targetRelative, $row['name'] . ' - SmileBaby', $index, $index === 0 ? 1 : 0]);
                    $images++;
                }
                $count++;
            });
        }
        if ((int) $db->query('SELECT COUNT(*) FROM products WHERE status="active" AND featured=1')->fetchColumn() === 0) {
            $featuredIds = $db->query('SELECT id FROM products WHERE status="active" ORDER BY legacy_wp_id DESC, id DESC LIMIT 8')->fetchAll(PDO::FETCH_COLUMN);
            $feature = $db->prepare('UPDATE products SET featured=1,featured_order=? WHERE id=?');
            foreach ($featuredIds as $order => $productId) $feature->execute([$order + 1, (int) $productId]);
        }
        return compact('count', 'images', 'missing');
    }
}
