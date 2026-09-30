<?php

namespace App\Models;

final class DemoCatalog
{
    private static ?array $data = null;

    private static function load(): array
    {
        if (self::$data !== null) return self::$data;
        $file = BASE_PATH . '/produse_woocommerce_complet.json';
        if (!is_file($file)) return self::$data = ['products' => [], 'categories_detected' => []];
        return self::$data = json_decode((string) file_get_contents($file), true) ?: ['products' => [], 'categories_detected' => []];
    }

    public static function products(int $limit = 16, array $filters = []): array
    {
        $result = [];
        foreach (array_reverse(self::load()['products'] ?? []) as $row) {
            if ((int) ($row['published'] ?? 0) !== 1 || trim((string) ($row['name'] ?? '')) === '') continue;
            $name = (string) $row['name'];
            $legacyCategory = (string) (($row['categories'][0]['path'] ?? '') ?: '');
            $category = self::mapCategory($legacyCategory, $name);
            $price = (float) (($row['pricing']['sale_price'] ?? null) ?: ($row['pricing']['regular_price'] ?? 0));
            $item = [
                'id' => (int) $row['id'], 'legacy_wp_id' => (int) $row['id'], 'name' => $name,
                'slug' => slugify($name) . '-' . (int) $row['id'], 'sku' => $row['sku'] ?? null,
                'short_description' => $row['descriptions']['short_html'] ?? '', 'description' => $row['descriptions']['full_html'] ?? '',
                'regular_price' => (float) ($row['pricing']['regular_price'] ?? 0), 'sale_price' => $row['pricing']['sale_price'] ?? null,
                'price' => $price, 'stock_status' => ($row['inventory']['stock_quantity'] ?? null) === null || ($row['inventory']['in_stock'] ?? true) ? 'in_stock' : 'out_of_stock',
                'stock_quantity' => $row['inventory']['stock_quantity'] ?? null, 'manage_stock' => ($row['inventory']['stock_quantity'] ?? null) !== null,
                'featured' => (int) ($row['featured'] ?? false),
                'is_customizable' => str_contains(slugify($name . ' ' . strip_tags((string) ($row['descriptions']['short_html'] ?? ''))), 'personaliz') ? 1 : 0,
                'badge_text' => null, 'image_path' => $row['images'][0]['uploads_relative_path'] ?? null,
                'category_name' => $category ?: null, 'category_slug' => $category ? slugify($category) : null, 'rating' => null, 'review_count' => 0,
                'images' => array_map(fn ($img, $i) => ['image_path' => $img['uploads_relative_path'] ?? '', 'alt_text' => $name . ' - SmileBaby', 'is_featured' => $i === 0], $row['images'] ?? [], array_keys($row['images'] ?? [])),
                'categories' => $category ? [['name' => $category, 'slug' => slugify($category)]] : [], 'variants' => [], 'reviews' => [],
            ];
            if (!empty($filters['category']) && $item['category_slug'] !== $filters['category']) continue;
            if (!empty($filters['q']) && !str_contains(mb_strtolower($name . ' ' . $category), mb_strtolower((string) $filters['q']))) continue;
            if (isset($filters['min']) && $filters['min'] !== '' && $price < (float) $filters['min']) continue;
            if (isset($filters['max']) && $filters['max'] !== '' && $price > (float) $filters['max']) continue;
            if (!empty($filters['stock']) && $item['stock_status'] !== 'in_stock') continue;
            $result[] = $item;
            if (count($result) >= $limit) break;
        }
        $sort = $filters['sort'] ?? '';
        if ($sort === 'price_asc') usort($result, fn ($a, $b) => $a['price'] <=> $b['price']);
        if ($sort === 'price_desc') usort($result, fn ($a, $b) => $b['price'] <=> $a['price']);
        if ($sort === 'name') usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $result;
    }

    public static function findBySlug(string $slug): ?array
    {
        foreach (self::products(271) as $item) if ($item['slug'] === $slug) return $item;
        return null;
    }

    public static function find(int $id): ?array
    {
        foreach (self::products(271) as $item) if ((int) $item['id'] === $id) return $item;
        return null;
    }

    public static function categories(): array
    {
        $categories = [
            ['id'=>1,'name'=>'Botez Fetițe','slug'=>'botez-fetite','short_description'=>'Trusouri și accesorii delicate pentru botezul fetiței.','description'=>'','image_path'=>'assets/images/categories/botez-fetite.png','product_count'=>null],
            ['id'=>2,'name'=>'Botez Băieți','slug'=>'botez-baieti','short_description'=>'Trusouri și accesorii speciale pentru botezul băiețelului.','description'=>'','image_path'=>'assets/images/categories/botez-baieti.png','product_count'=>null],
            ['id'=>3,'name'=>'Lumânări Botez','slug'=>'lumanari-botez','short_description'=>'Lumânări de botez create și decorate cu grijă.','description'=>'','image_path'=>'assets/images/categories/lumanari-botez.png','product_count'=>null],
            ['id'=>4,'name'=>'Mărturii Botez','slug'=>'marturii-botez','short_description'=>'Mărturii personalizate pentru o amintire de neuitat.','description'=>'','image_path'=>'assets/images/categories/marturii-botez.png','product_count'=>null],
        ];
        foreach($categories as &$category)$category['product_count']=count(self::products(271,['category'=>$category['slug']]));
        return $categories;
    }

    public static function mapCategory(string $legacy, string $productName): ?string
    {
        $legacy = slugify($legacy);
        $name = slugify($productName);
        if (str_contains($legacy, 'martur')) return 'Mărturii Botez';
        if (str_contains($legacy, 'luman')) return 'Lumânări Botez';
        if (str_contains($legacy, 'trusouri-fete')) return 'Botez Fetițe';
        if (str_contains($legacy, 'trusouri-baieti')) return 'Botez Băieți';
        if (str_contains($legacy, 'costum') || str_contains($legacy, 'traditional')) return 'Botez Băieți';
        if (str_contains($legacy, 'jucarii')) {
            $boys = ['rares','tigrisor','adrian','alex','alin','andrei','bogdan','dl-fox','rudolf','bocanila','peter','foxi'];
            foreach ($boys as $boy) if (str_contains($name, $boy)) return 'Botez Băieți';
            return 'Botez Fetițe';
        }
        if (str_contains($name, 'martur')) return 'Mărturii Botez';
        if (str_contains($name, 'luman')) return 'Lumânări Botez';
        if (preg_match('/(?:^|-)(roz|pink|balerin|printes|fetita)(?:-|$)/', $name)) return 'Botez Fetițe';
        return 'Botez Băieți';
    }
}
