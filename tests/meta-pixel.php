<?php

require_once dirname(__DIR__) . '/app/Services/MetaPixelService.php';

use App\Services\MetaPixelService;

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$items = [[
    'product_id' => 535,
    'quantity' => 2,
    'price' => 130,
    'addons_json' => json_encode([['product_id' => 777, 'quantity' => 6, 'price' => 100]]),
]];
$order = [
    'order_number' => 'SB-TEST-1',
    'payment_method' => 'online_card',
    'payment_status' => 'paid',
    'status' => 'confirmed',
    'total' => 880,
];

$purchase = MetaPixelService::purchaseEvent($order, $items, 'paid');
expect($purchase !== null, 'Paid card order must produce Purchase.');
expect($purchase['data']['value'] === 880.0, 'Purchase must use server order total, including delivery.');
expect($purchase['data']['currency'] === 'RON', 'Purchase currency must be RON.');
expect($purchase['data']['contents'] === [['id' => '535', 'quantity' => 2, 'item_price' => 130.0], ['id' => '777', 'quantity' => 6, 'item_price' => 100.0]], 'Purchase must include real product and add-on prices and quantities.');
expect($purchase['data']['num_items'] === 8, 'Purchase quantity must include add-ons.');

foreach (['pending', 'failed', 'cancelled', 'order_cancelled'] as $state) {
    expect(MetaPixelService::purchaseEvent($order, $items, $state) === null, $state . ' must not produce Purchase.');
}
expect(MetaPixelService::purchaseEvent($order + [], [], 'paid') === null, 'Empty order must not produce Purchase.');

$cash = $order;
$cash['payment_method'] = 'cash_on_delivery';
$cash['payment_status'] = 'unpaid';
$cash['status'] = 'received';
expect(MetaPixelService::purchaseEvent($cash, $items, 'received') !== null, 'Created cash order must produce Purchase.');
$cash['status'] = 'cancelled';
expect(MetaPixelService::purchaseEvent($cash, $items, 'received') === null, 'Cancelled cash order must not produce Purchase.');

$cart = MetaPixelService::cart([[
    'product' => ['id' => 535],
    'quantity' => 2,
    'price' => 130,
    'addons' => [['product_id' => 777, 'line_quantity' => 6, 'price' => 100]],
]], 860);
expect($cart['value'] === 860.0 && $cart['num_items'] === 8, 'Checkout must use server cart totals and quantities.');
expect($cart['contents'][1]['item_price'] === 100.0, 'Checkout must use the configured add-on sale price.');

echo "Meta Pixel PHP checks passed.\n";
