<?php

namespace App\Services;

use App\Core\Database;
use App\Models\CategoryRepository;
use App\Models\DemoCatalog;

final class SearchService
{
    public function search(string $query, int $limit = 8): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        if ($query === '') return ['products'=>[],'categories'=>(new CategoryRepository())->homepage(),'count'=>0];
        $products = Database::available() ? $this->databaseSearch($query, $limit) : $this->demoSearch($query, $limit);
        $smart = new SmartProductSearchService();
        $categories = array_values(array_filter((new CategoryRepository())->all(), fn($c)=>$smart->matchesText($query, $c['name'] . ' ' . $c['slug'])));
        return ['products'=>$products,'categories'=>array_slice($categories,0,4),'count'=>count($products)];
    }

    private function databaseSearch(string $query, int $limit): array
    {
        $ids = (new SmartProductSearchService())->rankedIds($query, true, $limit);
        if (!$ids) return [];
        $idList = implode(',', array_map('intval', $ids));
        $sql = 'SELECT p.id,p.name,p.slug,p.sku,p.regular_price,p.sale_price,COALESCE(p.sale_price,p.regular_price) price,
                MIN(c.name) category_name,MIN(c.slug) category_slug,
                (SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1) image_path
            FROM products p
            LEFT JOIN product_categories pc ON pc.product_id=p.id
            LEFT JOIN categories c ON c.id=pc.category_id
            WHERE p.status="active" AND p.id IN (' . $idList . ')
            GROUP BY p.id ORDER BY FIELD(p.id,' . $idList . ')';
        return Database::connection()->query($sql)->fetchAll();
    }

    private function demoSearch(string $query, int $limit): array
    {
        return (new SmartProductSearchService())->rankRows(DemoCatalog::products(271), $query, $limit);
    }
}
