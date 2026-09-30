<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\ProductRepository;
use RuntimeException;

final class CartService
{
    public function add(int $productId, int $quantity = 1, ?int $variantId = null, bool $personalized = false, array $values = [], array $addonValues = []): void
    {
        $product = (new ProductRepository())->find($productId);
        if (!$product || ($product['status'] ?? '') !== 'active') throw new RuntimeException('Produsul nu mai este disponibil.');
        $customization = $personalized ? $this->makeCustomization($product, $values) : null;
        $addons = (new ProductAddonService())->validateSelections($productId, $addonValues);
        $cart = Session::get('cart', []);
        $key = $this->key($productId, $variantId, $customization, $addons);
        $cart[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => min(99, max(1, (int) ($cart[$key]['quantity'] ?? 0) + $quantity)),
            'customization' => $customization,
            'addons' => $addons,
        ];
        Session::put('cart', $cart);
    }

    public function update(string $key, int $quantity): void
    {
        $cart = Session::get('cart', []);
        if ($quantity < 1) unset($cart[$key]);
        elseif (isset($cart[$key])) $cart[$key]['quantity'] = min(99, $quantity);
        Session::put('cart', $cart);
    }

    public function updateCustomization(string $key, bool $enabled, array $values = []): void
    {
        $cart = Session::get('cart', []);
        if (!isset($cart[$key])) throw new RuntimeException('Produsul nu mai este în coș.');
        $line = $cart[$key];
        $product = (new ProductRepository())->find((int) $line['product_id']);
        if (!$product) throw new RuntimeException('Produsul nu mai este disponibil.');
        $customization = $enabled ? $this->makeCustomization($product, $values) : null;
        unset($cart[$key]);
        $newKey = $this->key((int) $line['product_id'], !empty($line['variant_id']) ? (int) $line['variant_id'] : null, $customization, (array) ($line['addons'] ?? []));
        if (isset($cart[$newKey])) $cart[$newKey]['quantity'] = min(99, (int) $cart[$newKey]['quantity'] + (int) $line['quantity']);
        else { $line['customization'] = $customization; $cart[$newKey] = $line; }
        Session::put('cart', $cart);
    }

    public function remove(string $key): void { $this->update($key, 0); }
    public function clear(): void { Session::put('cart', []); }
    public function count(): int
    {
        $count = 0;
        foreach (Session::get('cart', []) as $line) {
            $count += (int) ($line['quantity'] ?? 0);
            foreach ((array) ($line['addons'] ?? []) as $addon) $count += (int) ($addon['quantity'] ?? 0);
        }
        return $count;
    }

    public function replace(array $lines): void
    {
        $cart = [];
        $repo = new ProductRepository();
        $addonService = new ProductAddonService();
        foreach (array_slice($lines, 0, 100) as $line) {
            if (!is_array($line)) continue;
            $productId = (int) ($line['product_id'] ?? 0);
            $variantId = !empty($line['variant_id']) ? (int) $line['variant_id'] : null;
            $quantity = min(99, max(1, (int) ($line['quantity'] ?? 1)));
            $product = $productId > 0 ? $repo->find($productId) : null;
            if (!$product || ($product['status'] ?? 'active') !== 'active') continue;
            $customization = null;
            if (!empty($line['customization']['enabled'])) {
                // Never silently turn a requested personalized line into a plain
                // product. The client keeps its original cart and can surface the
                // validation message instead of losing the personalization.
                $customization = $this->makeCustomization($product, $this->rawValues((array) ($line['customization']['values'] ?? [])));
            }
            $addons = $addonService->validateSelections($productId, $addonService->rawSelections((array) ($line['addons'] ?? [])));
            $key = $this->key($productId, $variantId, $customization, $addons);
            if (isset($cart[$key])) $cart[$key]['quantity'] = min(99, (int) $cart[$key]['quantity'] + $quantity);
            else $cart[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $quantity, 'customization' => $customization, 'addons' => $addons];
        }
        Session::put('cart', $cart);
    }

    public function snapshot(): array
    {
        $snapshot = [];
        foreach (Session::get('cart', []) as $key => $line) $snapshot[] = $line + ['cart_key' => $key];
        return $snapshot;
    }

    public function items(): array
    {
        $repo = new ProductRepository();
        $customizationService = new ProductCustomizationService();
        $addonService = new ProductAddonService();
        $items = [];
        $cart = Session::get('cart', []);
        $changed = false;
        foreach ($cart as $key => $line) {
            $product = $repo->find((int) $line['product_id']);
            if (!$product) continue;
            $basePrice = (float) ($product['sale_price'] ?: $product['regular_price']);
            $variant = null;
            if (!empty($line['variant_id']) && Database::available()) {
                $stmt = Database::connection()->prepare('SELECT * FROM product_variants WHERE id=? AND product_id=? AND status="active" LIMIT 1');
                $stmt->execute([(int) $line['variant_id'], (int) $product['id']]);
                $variant = $stmt->fetch() ?: null;
                if (!$variant) continue;
                $basePrice = (float) ($variant['sale_price'] ?: $variant['regular_price'] ?: $basePrice);
            }
            $fields = !empty($product['is_customizable']) ? $customizationService->fields((int) $product['id']) : [];
            $customization = null;
            if (!empty($line['customization']['enabled']) && $fields) {
                try {
                    $values = $customizationService->validateValues((int) $product['id'], $this->rawValues((array) ($line['customization']['values'] ?? [])));
                    $customization = ['enabled' => true, 'values' => $values];
                } catch (RuntimeException) {
                    $cart[$key]['customization'] = null;
                    $changed = true;
                }
            }
            $customizationPrice = $customization ? max(0, (float) ($product['customization_price'] ?? 0)) : 0.0;
            $price = round($basePrice + $customizationPrice, 2);
            $addons = [];
            try {
                $addons = $addonService->validateSelections((int) $product['id'], $addonService->rawSelections((array) ($line['addons'] ?? [])));
            } catch (RuntimeException) {
                $cart[$key]['addons'] = [];
                $changed = true;
            }
            $addonsTotal = round(array_sum(array_column($addons, 'total')), 2);
            $items[] = compact('key', 'product', 'variant', 'basePrice', 'customizationPrice', 'price', 'customization', 'fields', 'addons', 'addonsTotal') + ['quantity' => (int) $line['quantity'], 'variant_id' => $line['variant_id'], 'total' => round($price * (int) $line['quantity'] + $addonsTotal, 2)];
        }
        if ($changed) Session::put('cart', $cart);
        return $items;
    }

    public function subtotal(): float { return array_sum(array_column($this->items(), 'total')); }

    private function makeCustomization(array $product, array $values): array
    {
        if (empty($product['is_customizable'])) throw new RuntimeException('Acest produs nu permite personalizare.');
        return ['enabled' => true, 'values' => (new ProductCustomizationService())->validateValues((int) $product['id'], $values)];
    }

    private function rawValues(array $values): array
    {
        $raw = [];
        foreach ($values as $key => $value) {
            if (is_array($value) && isset($value['field_id'])) $raw[(string) $value['field_id']] = (string) ($value['value'] ?? '');
            elseif (!is_array($value)) $raw[(string) $key] = (string) $value;
        }
        return $raw;
    }

    private function key(int $productId, ?int $variantId, ?array $customization, array $addons = []): string
    {
        $key = $productId . ':' . ($variantId ?: 0);
        if ($customization) $key .= ':p:' . substr(hash('sha256', json_encode($customization['values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 16);
        if ($addons) {
            $identity = array_map(static fn (array $addon): array => ['product_id' => (int) $addon['product_id'], 'quantity' => (int) $addon['quantity']], $addons);
            usort($identity, static fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);
            $key .= ':a:' . substr(hash('sha256', json_encode($identity)), 0, 16);
        }
        return $key;
    }
}
