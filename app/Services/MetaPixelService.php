<?php

namespace App\Services;

final class MetaPixelService
{
    public const PIXEL_ID = '1855843608929498';

    public static function product(array $product): array
    {
        $id = (string) (int) ($product['id'] ?? 0);
        $price = (float) ($product['price'] ?? (!empty($product['sale_price']) ? $product['sale_price'] : ($product['regular_price'] ?? 0)));

        return [
            'content_ids' => [$id],
            'content_type' => 'product',
            'contents' => [['id' => $id, 'quantity' => 1]],
            'value' => round($price, 2),
            'currency' => 'RON',
        ];
    }

    public static function cart(array $items, float $value): array
    {
        $contents = [];
        $numItems = 0;
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $productId = (int) ($item['product']['id'] ?? 0);
            if ($productId > 0) {
                $contents[] = ['id' => (string) $productId, 'quantity' => $quantity, 'item_price' => round((float) ($item['price'] ?? 0), 2)];
                $numItems += $quantity;
            }
            foreach ((array) ($item['addons'] ?? []) as $addon) {
                $addonId = (int) ($addon['product_id'] ?? 0);
                $addonQuantity = (int) ($addon['line_quantity'] ?? (($addon['quantity_per_set'] ?? $addon['quantity'] ?? 0) * $quantity));
                if ($addonId < 1 || $addonQuantity < 1) continue;
                $contents[] = ['id' => (string) $addonId, 'quantity' => $addonQuantity, 'item_price' => round((float) ($addon['price'] ?? 0), 2)];
                $numItems += $addonQuantity;
            }
        }

        return self::commerce($contents, $value, $numItems);
    }

    public static function cartLines(array $items): array
    {
        return array_map(static function (array $item): array {
            return [
                'key' => (string) $item['key'],
                'product_id' => (int) $item['product']['id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => round((float) $item['price'], 2),
                'unit_total' => round((float) $item['price'] + (float) $item['addonsUnitTotal'], 2),
                'addons' => array_map(static fn (array $addon): array => [
                    'product_id' => (int) $addon['product_id'],
                    'quantity_per_set' => (int) $addon['quantity_per_set'],
                    'unit_price' => round((float) $addon['price'], 2),
                ], (array) ($item['addons'] ?? [])),
            ];
        }, $items);
    }

    public static function order(array $order, array $items): array
    {
        $contents = [];
        $numItems = 0;
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0) {
                $contents[] = ['id' => (string) $productId, 'quantity' => $quantity, 'item_price' => round((float) ($item['price'] ?? 0), 2)];
                $numItems += $quantity;
            }
            $addons = json_decode((string) ($item['addons_json'] ?? ''), true);
            foreach (is_array($addons) ? $addons : [] as $addon) {
                $addonId = (int) ($addon['product_id'] ?? 0);
                $addonQuantity = (int) ($addon['quantity'] ?? 0);
                if ($addonId < 1 || $addonQuantity < 1) continue;
                $contents[] = ['id' => (string) $addonId, 'quantity' => $addonQuantity, 'item_price' => round((float) ($addon['price'] ?? 0), 2)];
                $numItems += $addonQuantity;
            }
        }

        return self::commerce($contents, (float) ($order['total'] ?? 0), $numItems) + [
            'order_id' => (string) ($order['order_number'] ?? ''),
        ];
    }

    public static function purchaseEvent(array $order, array $items, string $displayState): ?array
    {
        $cardPaid = $order['payment_method'] === 'online_card'
            && $displayState === 'paid'
            && $order['payment_status'] === 'paid';
        $cashOrderCreated = $order['payment_method'] === 'cash_on_delivery'
            && $displayState === 'received'
            && $order['status'] !== 'cancelled';
        if ((!$cardPaid && !$cashOrderCreated) || !$items) return null;

        $number = (string) $order['order_number'];
        return [
            'name' => 'Purchase',
            'data' => self::order($order, $items),
            'event_id' => 'smilebaby-purchase-' . $number,
            'once_key' => 'smilebaby_meta_purchase:' . $number,
        ];
    }

    private static function commerce(array $contents, float $value, int $numItems): array
    {
        return [
            'content_ids' => array_values(array_unique(array_column($contents, 'id'))),
            'content_type' => 'product',
            'contents' => $contents,
            'num_items' => $numItems,
            'value' => round($value, 2),
            'currency' => 'RON',
        ];
    }
}
