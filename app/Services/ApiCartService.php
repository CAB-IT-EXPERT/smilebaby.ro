<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class ApiCartService
{
    private ?string $plainToken;
    private ?int $userId;
    private ?int $cartId = null;

    public function __construct(?string $plainToken = null, ?int $userId = null)
    {
        $this->plainToken = $plainToken && strlen($plainToken) >= 24 ? trim($plainToken) : null;
        $this->userId = $userId;
    }

    public function token(): ?string { $this->ensureCart(); return $this->userId ? null : $this->plainToken; }

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): array
    {
        $this->assertPurchasable($productId, $variantId);
        $cartId = $this->ensureCart();
        $quantity = min(99, max(1, $quantity));
        $stmt = Database::connection()->prepare('SELECT id,quantity FROM cart_items WHERE cart_id=? AND product_id=? AND variant_id <=> ? LIMIT 1');
        $stmt->execute([$cartId, $productId, $variantId]);
        $line = $stmt->fetch();
        if ($line) Database::connection()->prepare('UPDATE cart_items SET quantity=LEAST(99,quantity+?) WHERE id=?')->execute([$quantity, $line['id']]);
        else Database::connection()->prepare('INSERT INTO cart_items (cart_id,product_id,variant_id,quantity) VALUES (?,?,?,?)')->execute([$cartId, $productId, $variantId, $quantity]);
        return $this->summary();
    }

    public function update(int $itemId, int $quantity): array
    {
        $cartId = $this->ensureCart();
        if ($quantity < 1) Database::connection()->prepare('DELETE FROM cart_items WHERE id=? AND cart_id=?')->execute([$itemId, $cartId]);
        else Database::connection()->prepare('UPDATE cart_items SET quantity=? WHERE id=? AND cart_id=?')->execute([min(99, $quantity), $itemId, $cartId]);
        return $this->summary();
    }

    public function remove(int $itemId): array
    {
        Database::connection()->prepare('DELETE FROM cart_items WHERE id=? AND cart_id=?')->execute([$itemId, $this->ensureCart()]);
        return $this->summary();
    }

    public function summary(): array
    {
        $items = $this->items();
        return [
            'token' => $this->userId ? null : $this->plainToken,
            'items' => $items,
            'count' => array_sum(array_column($items, 'quantity')),
            'subtotal' => round(array_sum(array_column($items, 'total')), 2),
            'currency' => 'RON',
        ];
    }

    public function items(): array
    {
        $sql = 'SELECT ci.id,ci.product_id,ci.variant_id,ci.quantity,p.name,p.slug,p.sku product_sku,p.manage_stock,p.stock_quantity,p.stock_status,p.allow_backorders,v.label variant_name,v.sku variant_sku,v.stock_quantity variant_stock_quantity,v.stock_status variant_stock_status,COALESCE(v.sale_price,v.regular_price,p.sale_price,p.regular_price) price,COALESCE(v.image_path,(SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_featured DESC,sort_order,id LIMIT 1)) image_path FROM cart_items ci JOIN products p ON p.id=ci.product_id LEFT JOIN product_variants v ON v.id=ci.variant_id WHERE ci.cart_id=? AND p.status="active" ORDER BY ci.id';
        $stmt = Database::connection()->prepare($sql); $stmt->execute([$this->ensureCart()]);
        return array_map(static function (array $line): array {
            $line['id'] = (int) $line['id']; $line['product_id'] = (int) $line['product_id'];
            $line['variant_id'] = $line['variant_id'] ? (int) $line['variant_id'] : null;
            $line['quantity'] = (int) $line['quantity']; $line['price'] = (float) $line['price'];
            $line['total'] = round($line['price'] * $line['quantity'], 2);
            return $line;
        }, $stmt->fetchAll());
    }

    public function sessionLines(): array
    {
        $lines = [];
        foreach ($this->items() as $item) {
            $key = $item['product_id'] . ':' . ($item['variant_id'] ?: 0);
            $lines[$key] = ['product_id' => $item['product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity']];
        }
        return $lines;
    }

    public function markOrdered(): void
    {
        Database::connection()->prepare('UPDATE carts SET status="ordered" WHERE id=?')->execute([$this->ensureCart()]);
    }

    private function ensureCart(): int
    {
        if ($this->cartId) return $this->cartId;
        if ($this->userId) {
            $stmt = Database::connection()->prepare('SELECT id FROM carts WHERE user_id=? AND status="active" ORDER BY id DESC LIMIT 1');
            $stmt->execute([$this->userId]);
        } else {
            if (!$this->plainToken) $this->plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $stmt = Database::connection()->prepare('SELECT id FROM carts WHERE session_id=? AND status="active" ORDER BY id DESC LIMIT 1');
            $stmt->execute([hash('sha256', $this->plainToken)]);
        }
        $this->cartId = (int) $stmt->fetchColumn();
        if (!$this->cartId) {
            Database::connection()->prepare('INSERT INTO carts (user_id,session_id,status) VALUES (?,? ,"active")')->execute([$this->userId, $this->userId ? null : hash('sha256', $this->plainToken)]);
            $this->cartId = (int) Database::connection()->lastInsertId();
        }
        return $this->cartId;
    }

    private function assertPurchasable(int $productId, ?int $variantId): void
    {
        $stmt = Database::connection()->prepare('SELECT id,manage_stock,stock_status FROM products WHERE id=? AND status="active" LIMIT 1'); $stmt->execute([$productId]); $product = $stmt->fetch();
        if (!$product || (!empty($product['manage_stock']) && $product['stock_status'] === 'out_of_stock')) throw new RuntimeException('Produsul nu este disponibil.');
        if ($variantId) { $stmt=Database::connection()->prepare('SELECT id,stock_quantity,stock_status FROM product_variants WHERE id=? AND product_id=? AND status="active" LIMIT 1');$stmt->execute([$variantId,$productId]);$variant=$stmt->fetch();if(!$variant||($variant['stock_quantity']!==null&&$variant['stock_status']==='out_of_stock'))throw new RuntimeException('Varianta nu este disponibilă.'); }
    }
}
