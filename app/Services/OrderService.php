<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;
use RuntimeException;

final class OrderService
{
    public function create(array $customer, string $paymentKey): array
    {
        $customizationService = new ProductCustomizationService();
        $customizationService->ensureSchema();
        $addonService = new ProductAddonService();
        $addonService->ensureSchema();
        $cart = new CartService();
        $items = $cart->items();
        if (!$items) throw new RuntimeException('Coșul este gol.');
        $subtotal = round(array_sum(array_column($items, 'total')), 2);
        $paymentService = new PaymentService();
        $method = $paymentService->validate($paymentKey, $subtotal);
        $shipping = filter_var(setting('shipping_enabled', '1'), FILTER_VALIDATE_BOOL) ? (float) setting('standard_shipping_cost', 20) : 0;
        $threshold = (float) setting('free_shipping_threshold', 300);
        if ($threshold > 0 && $subtotal >= $threshold) $shipping = 0;
        $fee = $paymentService->fee($method, $subtotal);
        $total = round($subtotal + $shipping + $fee, 2);

        $order = Database::transaction(function (PDO $db) use ($customer, $items, $method, $subtotal, $shipping, $fee, $total, $customizationService, $addonService) {
            foreach ($items as &$line) {
                $stmt = $db->prepare('SELECT id,name,sku,regular_price,sale_price,manage_stock,stock_quantity,stock_status,allow_backorders,is_customizable,customization_price,(SELECT image_path FROM product_images WHERE product_id=products.id ORDER BY is_featured DESC,sort_order LIMIT 1) image_path FROM products WHERE id=? AND status="active" FOR UPDATE');
                $stmt->execute([$line['product']['id']]);
                $fresh = $stmt->fetch();
                if (!$fresh) throw new RuntimeException('Un produs din coș nu mai este disponibil.');
                if (empty($line['variant_id']) && $fresh['manage_stock'] && !$fresh['allow_backorders'] && (int) $fresh['stock_quantity'] < $line['quantity']) throw new RuntimeException('Stoc insuficient pentru ' . $fresh['name'] . '.');
                $line['product'] = $fresh;
                $line['variant'] = null;
                if (!empty($line['variant_id'])) {
                    $variantStmt = $db->prepare('SELECT * FROM product_variants WHERE id=? AND product_id=? AND status="active" FOR UPDATE');
                    $variantStmt->execute([(int) $line['variant_id'], (int) $fresh['id']]);
                    $variant = $variantStmt->fetch();
                    if (!$variant) throw new RuntimeException('Varianta selectată pentru ' . $fresh['name'] . ' nu mai este disponibilă.');
                    if ($variant['stock_quantity'] !== null && $variant['stock_status'] === 'out_of_stock') throw new RuntimeException('Varianta ' . ($variant['label'] ?: $variant['sku']) . ' este indisponibilă.');
                    if ($variant['stock_quantity'] !== null && $variant['stock_status'] !== 'on_backorder' && (int) $variant['stock_quantity'] < $line['quantity']) throw new RuntimeException('Stoc insuficient pentru varianta ' . ($variant['label'] ?: $variant['sku']) . '.');
                    $line['variant'] = $variant;
                    $line['price'] = (float) ($variant['sale_price'] ?: $variant['regular_price'] ?: $fresh['sale_price'] ?: $fresh['regular_price']);
                } else {
                    $line['price'] = (float) ($fresh['sale_price'] ?: $fresh['regular_price']);
                }
                $line['customizationPrice'] = 0.0;
                if (!empty($line['customization']['enabled'])) {
                    if (empty($fresh['is_customizable'])) throw new RuntimeException('Personalizarea pentru ' . $fresh['name'] . ' nu mai este disponibilă.');
                    $rawValues = [];
                    foreach ((array) ($line['customization']['values'] ?? []) as $value) if (is_array($value) && isset($value['field_id'])) $rawValues[(string) $value['field_id']] = (string) ($value['value'] ?? '');
                    $line['customization']['values'] = $customizationService->validateValues((int) $fresh['id'], $rawValues, $db);
                    $line['customization']['options'] = $customizationService->validateOptions((int) $fresh['id'], (array) ($line['customization']['options'] ?? []), $db);
                    $hasPricedOptions = $customizationService->options((int) $fresh['id'], $db) !== [];
                    $line['customizationPrice'] = round(($hasPricedOptions ? 0 : max(0, (float) $fresh['customization_price'])) + array_sum(array_column($line['customization']['options'], 'price')), 2);
                    $line['price'] += $line['customizationPrice'];
                }
                $line['addons'] = $addonService->validateSelections((int) $fresh['id'], $addonService->rawSelections((array) ($line['addons'] ?? [])), $db, true, (int) $line['quantity']);
                $line['addonsUnitTotal'] = round(array_sum(array_column($line['addons'], 'total')), 2);
                foreach ($line['addons'] as &$addon) {
                    $addon['quantity_per_set'] = (int) $addon['quantity'];
                    $addon['parent_quantity'] = (int) $line['quantity'];
                    $addon['line_quantity'] = (int) $addon['quantity'] * (int) $line['quantity'];
                    $addon['unit_total'] = (float) $addon['total'];
                    $addon['line_total'] = round((float) $addon['total'] * (int) $line['quantity'], 2);
                    $addon['quantity'] = $addon['line_quantity'];
                }
                unset($addon);
                $line['addonsTotal'] = round($line['addonsUnitTotal'] * (int) $line['quantity'], 2);
                $line['total'] = round(($line['price'] + $line['addonsUnitTotal']) * (int) $line['quantity'], 2);
            }
            unset($line);
            $addonDemand = [];
            $addonStock = [];
            foreach ($items as $line) foreach ((array) ($line['addons'] ?? []) as $addon) {
                $addonId = (int) $addon['product_id'];
                $addonDemand[$addonId] = ($addonDemand[$addonId] ?? 0) + (int) $addon['line_quantity'];
                $addonStock[$addonId] = $addon;
            }
            foreach ($addonDemand as $addonId => $requestedQuantity) {
                $addon = $addonStock[$addonId];
                if (!empty($addon['manage_stock']) && empty($addon['allow_backorders']) && (int) $addon['stock_quantity'] < $requestedQuantity) {
                    throw new RuntimeException('Stoc insuficient pentru produsul suplimentar „' . $addon['name'] . '”.');
                }
            }
            $serverSubtotal = round(array_sum(array_column($items, 'total')), 2);
            if (abs($serverSubtotal - $subtotal) > 0.01) throw new RuntimeException('Prețurile s-au actualizat. Reîncarcă pagina de checkout.');
            $sql = 'INSERT INTO orders (user_id,email,phone,first_name,last_name,customer_type,company_name,company_vat_id,company_registration_number,company_address,shipping_address,shipping_city,shipping_county,shipping_postcode,subtotal,shipping_total,payment_fee,total,payment_method,payment_method_label,payment_status,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $stmt = $db->prepare($sql);
            $paymentStatus = $method['key'] === 'online_card' ? 'pending' : 'unpaid';
            $stmt->execute([Auth::user()['id'] ?? null, mb_strtolower($customer['email']), $customer['phone'], $customer['first_name'], $customer['last_name'], $customer['customer_type'] ?? 'individual', ($customer['company_name'] ?? '') ?: null, ($customer['company_vat_id'] ?? '') ?: null, ($customer['company_registration_number'] ?? '') ?: null, ($customer['company_address'] ?? '') ?: null, $customer['address'], $customer['city'], $customer['county'], $customer['postcode'] ?: null, $serverSubtotal, $shipping, $fee, $total, $method['key'], $method['name'], $paymentStatus, 'received', $customer['notes'] ?: null]);
            $orderId = (int) $db->lastInsertId();
            $orderNumber = 'SB-' . date('Y') . '-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE orders SET order_number=? WHERE id=?')->execute([$orderNumber, $orderId]);
            $itemStmt = $db->prepare('INSERT INTO order_items (order_id,product_id,variant_id,product_name,sku,variant_name,customization_json,customization_price,addons_json,addons_total,price,quantity,total,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($items as $line) {
                $p = $line['product'];
                $variant = $line['variant'] ?? null;
                $addonSnapshot = array_map(static fn (array $addon): array => [
                    'product_id' => (int) $addon['product_id'], 'name' => $addon['name'], 'slug' => $addon['slug'], 'sku' => $addon['sku'],
                    'image_path' => $addon['image_path'], 'price' => (float) $addon['price'], 'quantity' => (int) $addon['line_quantity'], 'quantity_per_set' => (int) $addon['quantity_per_set'], 'parent_quantity' => (int) $addon['parent_quantity'], 'total' => (float) $addon['line_total'],
                ], (array) ($line['addons'] ?? []));
                $customizationSnapshot = [];
                foreach ((array) ($line['customization']['options'] ?? []) as $option) $customizationSnapshot[] = ['type' => 'option', 'option_id' => (int) $option['option_id'], 'label' => $option['label'], 'value' => (float) $option['price'] > 0 ? '+' . money($option['price']) . ' / buc.' : 'Gratuit', 'price' => (float) $option['price']];
                $customizationSnapshot = array_merge($customizationSnapshot, (array) ($line['customization']['values'] ?? []));
                $itemStmt->execute([$orderId, $p['id'], $line['variant_id'], $p['name'], $variant['sku'] ?? $p['sku'], $variant['label'] ?? null, $customizationSnapshot ? json_encode($customizationSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $line['customizationPrice'] ?? 0, $addonSnapshot ? json_encode($addonSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $line['addonsTotal'] ?? 0, $line['price'], $line['quantity'], $line['total'], $variant['image_path'] ?? $p['image_path']]);
                if ($variant && $variant['stock_quantity'] !== null) $db->prepare('UPDATE product_variants SET stock_quantity=stock_quantity-?,stock_status=IF(stock_quantity-?<=0,"out_of_stock",stock_status) WHERE id=?')->execute([$line['quantity'], $line['quantity'], $variant['id']]);
                elseif ($p['manage_stock']) $db->prepare('UPDATE products SET stock_quantity=stock_quantity-?, stock_status=IF(stock_quantity-?<=0,"out_of_stock",stock_status) WHERE id=?')->execute([$line['quantity'], $line['quantity'], $p['id']]);
                foreach ((array) ($line['addons'] ?? []) as $addon) if (!empty($addon['manage_stock'])) {
                    $db->prepare('UPDATE products SET stock_quantity=stock_quantity-?,stock_status=IF(stock_quantity-?<=0,"out_of_stock",stock_status) WHERE id=?')->execute([(int) $addon['line_quantity'], (int) $addon['line_quantity'], (int) $addon['product_id']]);
                }
            }
            $db->prepare('INSERT INTO payments (order_id,payment_method_key,provider,amount,status) VALUES (?,?,?,?,?)')->execute([$orderId, $method['key'], $method['key'] === 'online_card' ? (json_decode($method['settings_json'] ?? '{}', true)['provider'] ?? 'custom') : null, $total, $method['key'] === 'online_card' ? 'pending' : 'pending']);
            $db->prepare('INSERT INTO order_status_history (order_id,new_status,message) VALUES (?,"received","Comanda a fost înregistrată.")')->execute([$orderId]);
            return ['id' => $orderId, 'order_number' => $orderNumber, 'email' => $customer['email'], 'total' => $total, 'payment_status' => $paymentStatus, 'status' => 'received', 'payment_method' => $method['key'], 'payment_method_label' => $method['name'], 'items' => $items];
        });

        $result = $paymentService->gateway($paymentKey)->initializePayment($order, $method);
        $cart->clear();
        (new MailService())->orderReceived($order, $customer);
        return $order + ['gateway' => $result, 'payment_method_data' => $method];
    }

    public function changeStatus(int $orderId, string $status, bool $notify, array $tracking = []): ?bool
    {
        $allowed = ['received','confirmed','processing','prepared','shipped','delivered','cancelled','returned'];
        if (!in_array($status, $allowed, true)) throw new RuntimeException('Status invalid.');
        Database::transaction(function (PDO $db) use ($orderId, $status, $tracking) {
            $stmt = $db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) throw new RuntimeException('Comanda nu există.');
            $db->prepare('UPDATE orders SET status=?,courier=?,awb=?,tracking_url=? WHERE id=?')->execute([$status, $tracking['courier'] ?: null, $tracking['awb'] ?: null, $tracking['tracking_url'] ?: null, $orderId]);
            $db->prepare('INSERT INTO order_status_history (order_id,old_status,new_status,changed_by,message) VALUES (?,?,?,?,?)')->execute([$orderId, $order['status'], $status, Auth::user()['id'] ?? null, $tracking['message'] ?: null]);
            if ($status === 'delivered' && $order['payment_method'] === 'cash_on_delivery') {
                $db->prepare('UPDATE orders SET payment_status="paid" WHERE id=?')->execute([$orderId]);
                $db->prepare('UPDATE payments SET status="paid" WHERE order_id=?')->execute([$orderId]);
            }
            if (in_array($status, ['cancelled','returned'], true) && !$order['stock_restored_at']) {
                $items = $db->prepare('SELECT product_id,variant_id,quantity,addons_json FROM order_items WHERE order_id=?');
                $items->execute([$orderId]);
                foreach ($items->fetchAll() as $item) {
                    if (!empty($item['variant_id'])) $db->prepare('UPDATE product_variants SET stock_quantity=IF(stock_quantity IS NULL,NULL,stock_quantity+?),stock_status="in_stock" WHERE id=?')->execute([$item['quantity'], $item['variant_id']]);
                    elseif ($item['product_id']) $db->prepare('UPDATE products SET stock_quantity=IF(manage_stock=1,stock_quantity+?,stock_quantity),stock_status=IF(manage_stock=1,"in_stock",stock_status) WHERE id=?')->execute([$item['quantity'], $item['product_id']]);
                    foreach ((array) json_decode((string) ($item['addons_json'] ?? ''), true) as $addon) {
                        $db->prepare('UPDATE products SET stock_quantity=IF(manage_stock=1,stock_quantity+?,stock_quantity),stock_status=IF(manage_stock=1,"in_stock",stock_status) WHERE id=?')->execute([(int) ($addon['quantity'] ?? 0), (int) ($addon['product_id'] ?? 0)]);
                    }
                }
                $db->prepare('UPDATE orders SET stock_restored_at=NOW() WHERE id=?')->execute([$orderId]);
            }
        });
        return $notify ? (new MailService())->orderStatus($orderId) : null;
    }
}
