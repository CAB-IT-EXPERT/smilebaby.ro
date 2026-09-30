<?php

namespace App\Services;

use App\Core\Database;

/**
 * Small-catalog fuzzy search shared by storefront and administration.
 * The catalogue is intentionally ranked in PHP so Romanian inflections,
 * diacritics and typing mistakes behave identically on every search surface.
 */
final class SmartProductSearchService
{
    public function rankedIds(string $query, bool $activeOnly = false, int $limit = 2000): array
    {
        if (!Database::available() || trim($query) === '') return [];
        $sql = 'SELECT p.id,p.name,p.slug,p.sku,p.regular_price,p.sale_price,p.featured,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR " ") categories,
                GROUP_CONCAT(DISTINCT c.slug ORDER BY c.slug SEPARATOR " ") category_slugs,
                (SELECT GROUP_CONCAT(CONCAT_WS(" ",pv.label,pv.sku) SEPARATOR " ") FROM product_variants pv WHERE pv.product_id=p.id) variants
            FROM products p
            LEFT JOIN product_categories pc ON pc.product_id=p.id
            LEFT JOIN categories c ON c.id=pc.category_id'
            . ($activeOnly ? ' WHERE p.status="active"' : '') . '
            GROUP BY p.id';
        $ranked = $this->rankRows(Database::connection()->query($sql)->fetchAll(), $query, $limit);
        return array_map(static fn (array $row): int => (int) $row['id'], $ranked);
    }

    public function rankRows(array $rows, string $query, int $limit = 2000): array
    {
        $tokens = $this->queryTokens($query);
        if (!$tokens) return [];
        $ranked = [];
        foreach ($rows as $row) {
            $score = $this->score($row, $tokens, $query);
            if ($score <= 0) continue;
            $row['_search_score'] = $score;
            $ranked[] = $row;
        }
        usort($ranked, static fn (array $a, array $b): int =>
            ($b['_search_score'] <=> $a['_search_score'])
            ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );
        return array_slice($ranked, 0, max(1, $limit));
    }

    public function matchesText(string $query, string $candidate): bool
    {
        $tokens = $this->queryTokens($query);
        return $tokens && $this->score(['name' => $candidate], $tokens, $query) > 0;
    }

    private function score(array $row, array $tokens, string $rawQuery): float
    {
        $price = (float) (($row['sale_price'] ?? null) ?: ($row['regular_price'] ?? $row['price'] ?? 0));
        $fields = [
            'name' => [(string) ($row['name'] ?? ''), 1.0],
            'sku' => [(string) ($row['sku'] ?? ''), 1.18],
            'slug' => [(string) ($row['slug'] ?? ''), 1.0],
            'categories' => [trim((string) ($row['categories'] ?? $row['category_name'] ?? '') . ' ' . (string) ($row['category_slugs'] ?? '')), .92],
            'variants' => [(string) ($row['variants'] ?? ''), .86],
            'price' => [$price > 0 ? number_format($price, 2, '.', '') . ' ' . (string) $price . ' ' . (string) (int) $price : '', 1.12],
        ];
        $normalizedQuery = $this->normalize($rawQuery);
        $score = !empty($row['featured']) ? 2.0 : 0.0;
        foreach ($fields as $field => [$value, $weight]) {
            $normalized = $this->normalize($value);
            if ($normalized === '') continue;
            if ($normalized === $normalizedQuery) $score += 150 * $weight;
            elseif ($normalizedQuery !== '' && str_contains($normalized, $normalizedQuery)) $score += 65 * $weight;
            if ($field === 'sku' && $normalizedQuery !== '' && str_replace(' ', '', $normalized) === str_replace(' ', '', $normalizedQuery)) $score += 175;
        }

        foreach ($tokens as $token) {
            $best = 0.0;
            foreach ($fields as [$value, $weight]) {
                $words = $this->words($value);
                foreach ($words as $word) $best = max($best, $this->similarity($token, $word) * $weight);
            }
            // Every meaningful query token must have a credible match. This
            // prevents a price or category hit from returning unrelated items.
            if ($best < .58) return 0.0;
            $score += 34 * $best;
        }
        return $score;
    }

    private function queryTokens(string $query): array
    {
        $stopWords = ['lei', 'ron', 'pret', 'pretul', 'produs', 'produse', 'cauta', 'dupa', 'pentru'];
        return array_values(array_unique(array_filter(
            $this->words($query),
            static fn (string $token): bool => $token !== '' && !in_array($token, $stopWords, true)
        )));
    }

    private function words(string $value): array
    {
        $normalized = $this->normalize($value);
        return $normalized === '' ? [] : array_values(array_filter(explode(' ', $normalized), static fn (string $word): bool => $word !== ''));
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', slugify($value))) ?? '');
    }

    private function similarity(string $query, string $candidate): float
    {
        if ($query === $candidate) return 1.0;
        if ($this->stem($query) === $this->stem($candidate)) return .98;
        $minLength = min(strlen($query), strlen($candidate));
        if ($minLength >= 3 && (str_starts_with($candidate, $query) || str_starts_with($query, $candidate))) return .94;
        if ($minLength >= 4 && (str_contains($candidate, $query) || str_contains($query, $candidate))) return .9;
        $maxLength = max(strlen($query), strlen($candidate));
        if ($maxLength < 3) return 0.0;
        $distance = levenshtein($query, $candidate);
        $allowed = $maxLength <= 4 ? 1 : ($maxLength <= 8 ? 2 : 3);
        if ($distance > $allowed) return 0.0;
        return max(.6, 1 - ($distance / $maxLength));
    }

    private function stem(string $word): string
    {
        $common = [
            'trusou' => 'trusou', 'trusoul' => 'trusou', 'trusouri' => 'trusou', 'trusourile' => 'trusou',
            'lumanare' => 'lumanar', 'lumanarea' => 'lumanar', 'lumanari' => 'lumanar', 'lumanarile' => 'lumanar',
            'marturie' => 'martur', 'marturii' => 'martur', 'marturiile' => 'martur',
            'botez' => 'botez', 'botezuri' => 'botez', 'botezului' => 'botez',
            'fetita' => 'fetit', 'fetite' => 'fetit', 'fetitelor' => 'fetit',
            'baiat' => 'baiet', 'baieti' => 'baiet', 'baietilor' => 'baiet',
            'cutie' => 'cuti', 'cutii' => 'cuti', 'set' => 'set', 'seturi' => 'set',
        ];
        if (isset($common[$word])) return $common[$word];
        foreach (['urilor', 'iilor', 'elor', 'ului', 'urile'] as $suffix) {
            if (strlen($word) > strlen($suffix) + 3 && str_ends_with($word, $suffix)) return substr($word, 0, -strlen($suffix));
        }
        return $word;
    }
}
