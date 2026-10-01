<?php

namespace App\Services;

use PDO;

final class SeoService
{
    private const BRAND = 'SmileBaby';

    public function generateProductMetadata(array $product): array
    {
        $name = $this->cleanText((string) ($product['name'] ?? 'Produs SmileBaby'));
        $name = $this->romanianize($name);
        $category = $this->productCategory($product);

        $suffix = ' | ' . self::BRAND;
        $titleBase = $this->truncate($name, 60 - $this->length($suffix));
        $title = str_contains($this->lower($titleBase), $this->lower(self::BRAND))
            ? $this->truncate($titleBase, 60)
            : $titleBase . $suffix;

        $subject = $this->truncate($name, 82);
        $kind = $this->productKind($name, $category);
        $personalization = !empty($product['is_customizable'])
            ? ' Poate fi personalizat pentru evenimentul tău.'
            : '';
        $description = 'Descoperă ' . $subject . ', ' . $kind . ' realizat cu grijă în România.'
            . $personalization . ' Comandă online de la SmileBaby.';

        return [
            'title' => $this->truncate($title, 60),
            'description' => $this->truncate($description, 160),
        ];
    }

    public function ensureProductMetadata(PDO $db, int $productId, bool $refreshWeak = false): array
    {
        $statement = $db->prepare($this->productMetadataSql() . ' WHERE p.id=? GROUP BY p.id LIMIT 1');
        $statement->execute([$productId]);
        $product = $statement->fetch();
        if (!$product) return ['updated' => false];

        return $this->updateProductMetadata($db, $product, $refreshWeak);
    }

    public function backfillProductMetadata(PDO $db, bool $refreshWeak = true): array
    {
        $products = $db->query($this->productMetadataSql() . ' GROUP BY p.id ORDER BY p.id')->fetchAll();
        $updated = 0;
        $preserved = 0;
        $samples = [];

        foreach ($products as $product) {
            $result = $this->updateProductMetadata($db, $product, $refreshWeak);
            if ($result['updated']) {
                $updated++;
                if (count($samples) < 8) $samples[] = [
                    'id' => (int) $product['id'],
                    'title' => $result['title'],
                    'description' => $result['description'],
                ];
            } else {
                $preserved++;
            }
        }

        return ['total' => count($products), 'updated' => $updated, 'preserved' => $preserved, 'samples' => $samples];
    }

    public function backfillCategoryMetadata(PDO $db, bool $refreshWeak = true): array
    {
        $categories = $db->query('SELECT id,name,short_description,description,meta_title,meta_description FROM categories ORDER BY id')->fetchAll();
        $updated = 0;
        $preserved = 0;

        foreach ($categories as $category) {
            $result = $this->updateCategoryMetadata($db, $category, $refreshWeak);
            $result['updated'] ? $updated++ : $preserved++;
        }

        return ['total' => count($categories), 'updated' => $updated, 'preserved' => $preserved];
    }

    public function ensureCategoryMetadata(PDO $db, int $categoryId): array
    {
        $statement = $db->prepare('SELECT id,name,short_description,description,meta_title,meta_description FROM categories WHERE id=? LIMIT 1');
        $statement->execute([$categoryId]);
        $category = $statement->fetch();
        return $category ? $this->updateCategoryMetadata($db, $category, false) : ['updated' => false];
    }

    public function productSchema(array $product, array $images): array
    {
        $appUrl = rtrim((string) \config('app.url'), '/');
        $productUrl = $appUrl . '/produs/' . rawurlencode((string) $product['slug']);
        $now = time();
        $saleActive = !empty($product['sale_price'])
            && (empty($product['sale_start']) || strtotime((string) $product['sale_start']) <= $now)
            && (empty($product['sale_end']) || strtotime((string) $product['sale_end']) >= $now);
        $price = (float) ($saleActive ? $product['sale_price'] : $product['regular_price']);
        $availability = empty($product['manage_stock']) || (string) ($product['stock_status'] ?? '') === 'in_stock'
            ? 'https://schema.org/InStock'
            : ((string) ($product['stock_status'] ?? '') === 'on_backorder' ? 'https://schema.org/BackOrder' : 'https://schema.org/OutOfStock');
        $imageUrls = array_values(array_unique(array_map(
            fn (array $image): string => $this->absoluteImageUrl($image['image_path'] ?? null, 'display'),
            $images
        )));
        $category = $this->productCategory($product);
        $deliveryDays = $this->deliveryDays((string) \setting('estimated_delivery_text', '2–3 zile lucrătoare'));
        $shippingCost = max(0, (float) \setting('standard_shipping_cost', 20));
        $freeThreshold = max(0, (float) \setting('free_shipping_threshold', 0));
        if ($freeThreshold > 0 && $price >= $freeThreshold) $shippingCost = 0;

        $returnPolicy = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'RO',
            'returnPolicyCountry' => 'RO',
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnPolicyCategory' => !empty($product['is_customizable'])
                ? 'https://schema.org/MerchantReturnNotPermitted'
                : 'https://schema.org/MerchantReturnFiniteReturnWindow',
        ];
        if (empty($product['is_customizable'])) {
            $returnPolicy['merchantReturnDays'] = 14;
            $returnPolicy['returnFees'] = 'https://schema.org/ReturnShippingFees';
            $returnPolicy['returnShippingFeesAmount'] = [
                '@type' => 'MonetaryAmount',
                'value' => max(0, (float) \setting('return_shipping_cost', 20)),
                'currency' => 'RON',
            ];
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $productUrl,
            'priceCurrency' => 'RON',
            'price' => number_format($price, 2, '.', ''),
            'availability' => $availability,
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@id' => $appUrl . '/#organization'],
            'shippingDetails' => [
                '@type' => 'OfferShippingDetails',
                'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'RO'],
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => number_format($shippingCost, 2, '.', ''), 'currency' => 'RON'],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                    'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => $deliveryDays[0], 'maxValue' => $deliveryDays[1], 'unitCode' => 'DAY'],
                ],
            ],
            'hasMerchantReturnPolicy' => $returnPolicy,
        ];
        if ($saleActive && !empty($product['sale_end'])) $offer['priceValidUntil'] = date('Y-m-d', strtotime((string) $product['sale_end']));

        $schemaDescription = (string) (($product['short_description'] ?? '') ?: ($product['description'] ?? ''));
        if ($this->cleanText($schemaDescription) === '') $schemaDescription = $this->generateProductMetadata($product)['description'];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $productUrl . '#product',
            'url' => $productUrl,
            'name' => (string) $product['name'],
            'image' => $imageUrls,
            'description' => $this->truncate($this->cleanText($schemaDescription), 500),
            'sku' => (string) ($product['sku'] ?? ''),
            'brand' => ['@type' => 'Brand', 'name' => (string) (($product['brand'] ?? '') ?: self::BRAND)],
            'category' => $category,
            'inLanguage' => 'ro-RO',
            'offers' => $offer,
        ];

        $gtin = preg_replace('/\D+/', '', (string) ($product['gtin'] ?? ''));
        $gtinKey = match (strlen($gtin)) { 8 => 'gtin8', 12 => 'gtin12', 13 => 'gtin13', 14 => 'gtin14', default => null };
        if ($gtinKey) $schema[$gtinKey] = $gtin;

        $reviews = array_values(array_filter((array) ($product['reviews'] ?? []), static fn (array $review): bool => (int) ($review['rating'] ?? 0) > 0));
        if ($reviews) {
            $ratingTotal = array_sum(array_map(static fn (array $review): int => (int) $review['rating'], $reviews));
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($ratingTotal / count($reviews), 1),
                'reviewCount' => count($reviews),
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $schema['review'] = array_map(static fn (array $review): array => [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => (string) ($review['author_name'] ?? 'Client SmileBaby')],
                'datePublished' => date('Y-m-d', strtotime((string) ($review['created_at'] ?? 'now'))),
                'name' => (string) (($review['title'] ?? '') ?: 'Recenzie produs'),
                'reviewBody' => (string) ($review['body'] ?? ''),
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $review['rating'], 'bestRating' => 5, 'worstRating' => 1],
            ], array_slice($reviews, 0, 10));
        }

        return array_filter($schema, static fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null);
    }

    public function itemListSchema(array $products, string $name, string $url): array
    {
        $appUrl = rtrim((string) \config('app.url'), '/');
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $url . '#collection',
            'url' => $url,
            'name' => $name,
            'inLanguage' => 'ro-RO',
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($products),
                'itemListElement' => array_map(fn (array $product, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $appUrl . '/produs/' . rawurlencode((string) $product['slug']),
                    'name' => (string) $product['name'],
                    'image' => $this->absoluteImageUrl($product['image_path'] ?? null, 'card'),
                ], array_values($products), array_keys(array_values($products))),
            ],
        ];
    }

    public function breadcrumbSchema(array $items): array
    {
        $appUrl = rtrim((string) \config('app.url'), '/');
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) $item['name'],
                'item' => preg_match('#^https?://#i', (string) $item['url']) ? (string) $item['url'] : $appUrl . '/' . ltrim((string) $item['url'], '/'),
            ], array_values($items), array_keys(array_values($items))),
        ];
    }

    private function updateProductMetadata(PDO $db, array $product, bool $refreshWeak): array
    {
        $generated = $this->generateProductMetadata($product);
        $currentTitle = trim((string) ($product['meta_title'] ?? ''));
        $currentDescription = trim((string) ($product['meta_description'] ?? ''));
        $replaceTitle = $currentTitle === '' || ($refreshWeak && $this->weakTitle($currentTitle));
        $replaceDescription = $currentDescription === '' || ($refreshWeak && $this->weakDescription($currentDescription));

        if (!$replaceTitle && !$replaceDescription) return ['updated' => false, 'title' => $currentTitle, 'description' => $currentDescription];

        $title = $replaceTitle ? $generated['title'] : $currentTitle;
        $description = $replaceDescription ? $generated['description'] : $currentDescription;
        $statement = $db->prepare('UPDATE products SET meta_title=?,meta_description=? WHERE id=?');
        $statement->execute([$title, $description, (int) $product['id']]);
        return ['updated' => true, 'title' => $title, 'description' => $description];
    }

    private function updateCategoryMetadata(PDO $db, array $category, bool $refreshWeak): array
    {
        $name = $this->romanianize($this->cleanText((string) $category['name']));
        $title = $this->truncate($name . ' pentru botez | ' . self::BRAND, 60);
        $source = $this->cleanText((string) (($category['short_description'] ?? '') ?: ($category['description'] ?? '')));
        $description = $source !== ''
            ? $this->truncate($source . ' Descoperă colecția SmileBaby și comandă online.', 160)
            : $this->truncate('Descoperă colecția ' . $name . ' de la SmileBaby: produse pentru botez alese cu grijă, disponibile online în România.', 160);
        $currentTitle = trim((string) ($category['meta_title'] ?? ''));
        $currentDescription = trim((string) ($category['meta_description'] ?? ''));
        $replaceTitle = $currentTitle === '' || ($refreshWeak && $this->weakTitle($currentTitle));
        $replaceDescription = $currentDescription === '' || ($refreshWeak && $this->weakDescription($currentDescription));
        if (!$replaceTitle && !$replaceDescription) return ['updated' => false];

        $statement = $db->prepare('UPDATE categories SET meta_title=?,meta_description=? WHERE id=?');
        $statement->execute([
            $replaceTitle ? $title : $currentTitle,
            $replaceDescription ? $description : $currentDescription,
            (int) $category['id'],
        ]);
        return ['updated' => true];
    }

    private function productMetadataSql(): string
    {
        return 'SELECT p.*,GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") category_names'
            . ' FROM products p LEFT JOIN product_categories pc ON pc.product_id=p.id LEFT JOIN categories c ON c.id=pc.category_id';
    }

    private function weakTitle(string $title): bool
    {
        $length = $this->length($this->cleanText($title));
        return $length < 18 || $length > 65;
    }

    private function weakDescription(string $description): bool
    {
        $length = $this->length($this->cleanText($description));
        return $length < 100 || $length > 180;
    }

    private function productCategory(array $product): string
    {
        if (!empty($product['category_names'])) return $this->romanianize(explode(',', (string) $product['category_names'])[0]);
        if (!empty($product['categories'][0]['name'])) return $this->romanianize((string) $product['categories'][0]['name']);
        return 'produs pentru botez';
    }

    private function productKind(string $name, string $category): string
    {
        $haystack = $this->lower($name . ' ' . $category);
        return match (true) {
            str_contains($haystack, 'trusou') => 'un trusou de botez',
            str_contains($haystack, 'lumân') || str_contains($haystack, 'luman') => 'o lumânare de botez',
            str_contains($haystack, 'mărtur') || str_contains($haystack, 'martur') => 'o mărturie de botez',
            str_contains($haystack, 'jucăr') || str_contains($haystack, 'jucar') || str_contains($haystack, 'amigurumi') => 'o jucărie croșetată manual',
            str_contains($haystack, 'costum') => 'un costum de botez',
            default => 'un produs pentru botez',
        };
    }

    private function deliveryDays(string $text): array
    {
        preg_match_all('/\d+/', $text, $matches);
        $days = array_map('intval', $matches[0] ?? []);
        return [max(1, $days[0] ?? 2), max($days[0] ?? 2, $days[1] ?? ($days[0] ?? 3))];
    }

    private function absoluteImageUrl(?string $path, string $preset): string
    {
        $url = (string) \optimized_image_url($path, $preset);
        if (preg_match('#^https?://#i', $url)) return $url;
        return rtrim((string) \config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $value), " \t\n\r\0\x0B-–—,.;:");
    }

    private function romanianize(string $value): string
    {
        return str_ireplace(
            ['jucarie', 'crosetata', 'lumanare', 'marturie', 'baieti', 'fetita', 'ingeras'],
            ['jucărie', 'croșetată', 'lumânare', 'mărturie', 'băieți', 'fetiță', 'îngeraș'],
            $value
        );
    }

    private function truncate(string $value, int $limit): string
    {
        $value = $this->cleanText($value);
        if ($this->length($value) <= $limit) return $value;
        $cut = $this->slice($value, 0, max(1, $limit - 1));
        $space = strrpos($cut, ' ');
        if ($space !== false && $this->length(substr($cut, 0, $space)) > (int) ($limit * .65)) $cut = substr($cut, 0, $space);
        return rtrim($cut, " \t\n\r\0\x0B-–—,.;:") . '…';
    }

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value);
        if (function_exists('iconv_strlen')) return (int) iconv_strlen($value, 'UTF-8');
        return strlen($value);
    }

    private function slice(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) return mb_substr($value, $start, $length);
        if (function_exists('iconv_substr')) return (string) iconv_substr($value, $start, $length, 'UTF-8');
        return substr($value, $start, $length);
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}
