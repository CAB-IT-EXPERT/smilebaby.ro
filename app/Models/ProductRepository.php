<?php

namespace App\Models;

use App\Core\Database;
use App\Services\ProductAddonService;
use App\Services\ProductCustomizationService;
use App\Services\SmartProductSearchService;
use PDO;

final class ProductRepository
{
    public function featured(int $limit = 8): array
    {
        if (!Database::available()) {
            $products = array_values(array_filter(DemoCatalog::products(271), fn (array $product) => !empty($product['featured'])));
            return array_slice($products ?: DemoCatalog::products($limit), 0, $limit);
        }
        $sql = $this->baseSql() . ' WHERE p.status = "active" AND p.featured = 1 ORDER BY p.featured_order, p.created_at DESC LIMIT ?';
        $stmt = Database::connection()->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function list(array $filters = [], int $page = 1, int $perPage = 16): array
    {
        if (!Database::available()) {
            $items = DemoCatalog::products(271, $filters);
            $total = count($items);
            return ['items' => array_slice($items, ($page - 1) * $perPage, $perPage), 'total' => $total];
        }
        $where = ['p.status = "active"'];
        $params = [];
        $searchOrder = '';
        if (!empty($filters['category'])) { $where[] = 'c.slug = ?'; $params[] = $filters['category']; }
        if (!empty($filters['q'])) {
            $ids = (new SmartProductSearchService())->rankedIds((string) $filters['q'], true);
            if ($ids) {
                $idList = implode(',', array_map('intval', $ids));
                $where[] = 'p.id IN (' . $idList . ')';
                $searchOrder = 'FIELD(p.id,' . $idList . ')';
            } else $where[] = '0=1';
        }
        if (isset($filters['min']) && $filters['min'] !== '') { $where[] = 'COALESCE(p.sale_price, p.regular_price) >= ?'; $params[] = (float) $filters['min']; }
        if (isset($filters['max']) && $filters['max'] !== '') { $where[] = 'COALESCE(p.sale_price, p.regular_price) <= ?'; $params[] = (float) $filters['max']; }
        if (!empty($filters['stock'])) $where[] = '(p.manage_stock = 0 OR p.stock_status IN ("in_stock","on_backorder"))';
        if (isset($filters['featured']) && $filters['featured'] !== '') { $where[] = 'p.featured = ?'; $params[] = filter_var($filters['featured'], FILTER_VALIDATE_BOOL) ? 1 : 0; }
        if (isset($filters['customizable']) && $filters['customizable'] !== '') { $where[] = 'p.is_customizable = ?'; $params[] = filter_var($filters['customizable'], FILTER_VALIDATE_BOOL) ? 1 : 0; }
        $order = match ($filters['sort'] ?? '') {
            'price_asc' => 'price ASC', 'price_desc' => 'price DESC', 'name' => 'p.name ASC', default => $searchOrder ?: 'p.created_at DESC'
        };
        $joins = ' LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id ';
        $count = Database::connection()->prepare('SELECT COUNT(DISTINCT p.id) FROM products p ' . $joins . ' WHERE ' . implode(' AND ', $where));
        $count->execute($params);
        $sql = $this->baseSql() . $joins . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY p.id ORDER BY ' . $order . ' LIMIT ? OFFSET ?';
        $stmt = Database::connection()->prepare($sql);
        $i = 1;
        foreach ($params as $value) $stmt->bindValue($i++, $value);
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function findBySlug(string $slug): ?array
    {
        if (!Database::available()) return DemoCatalog::findBySlug($slug);
        $customization = new ProductCustomizationService();
        $customization->ensureSchema();
        $addons = new ProductAddonService();
        $addons->ensureSchema();
        $stmt = Database::connection()->prepare($this->baseSql() . ' WHERE p.slug = ? AND p.status = "active" LIMIT 1');
        $stmt->execute([$slug]);
        $product = $stmt->fetch();
        if (!$product) return null;
        $product['images'] = $this->images((int) $product['id']);
        $product['categories'] = $this->categories((int) $product['id']);
        $product['variants'] = $this->variants((int) $product['id']);
        $product['reviews'] = $this->reviews((int) $product['id']);
        $product['customization_fields'] = !empty($product['is_customizable']) ? $customization->fields((int) $product['id']) : [];
        $product['customization_options'] = !empty($product['is_customizable']) ? $customization->options((int) $product['id']) : [];
        $product['addons'] = !empty($product['addons_enabled']) ? $addons->storefrontOptions((int) $product['id']) : [];
        return $product;
    }

    public function find(int $id): ?array
    {
        if (!Database::available()) return DemoCatalog::find($id);
        (new ProductCustomizationService())->ensureSchema();
        (new ProductAddonService())->ensureSchema();
        $stmt = Database::connection()->prepare($this->baseSql() . ' WHERE p.id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function images(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM product_images WHERE product_id=? ORDER BY is_featured DESC, sort_order, id');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function categories(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT c.* FROM categories c JOIN product_categories pc ON pc.category_id=c.id WHERE pc.product_id=? ORDER BY c.name');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function variants(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT v.*, COALESCE(NULLIF(v.label,""), GROUP_CONCAT(CONCAT(a.name, ": ", av.value) SEPARATOR ", ")) variant_name FROM product_variants v LEFT JOIN variant_attribute_values vav ON vav.variant_id=v.id LEFT JOIN attribute_values av ON av.id=vav.attribute_value_id LEFT JOIN attributes a ON a.id=av.attribute_id WHERE v.product_id=? AND v.status="active" GROUP BY v.id');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function reviews(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM reviews WHERE product_id=? AND status="approved" ORDER BY created_at DESC');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    private function baseSql(): string
    {
        return 'SELECT p.*, COALESCE(p.sale_price, p.regular_price) price, (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC, sort_order, id LIMIT 1) image_path, (SELECT ROUND(AVG(rating),1) FROM reviews WHERE product_id=p.id AND status="approved") rating, (SELECT COUNT(*) FROM reviews WHERE product_id=p.id AND status="approved") review_count FROM products p';
    }
}
