<?php

namespace App\Models;

use App\Core\Database;

final class CategoryRepository
{
    public function homepage(): array
    {
        if (!Database::available()) return DemoCatalog::categories();
        return Database::connection()->query('SELECT * FROM categories WHERE status="active" AND show_on_homepage=1 ORDER BY homepage_order, name LIMIT 8')->fetchAll();
    }

    public function all(): array
    {
        if (!Database::available()) return DemoCatalog::categories();
        return Database::connection()->query('SELECT c.*, (SELECT COUNT(*) FROM product_categories pc JOIN products p ON p.id=pc.product_id WHERE pc.category_id=c.id AND p.status="active") product_count FROM categories c WHERE c.status="active" ORDER BY c.name')->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $category) if ($category['slug'] === $slug) return $category;
        return null;
    }
}
