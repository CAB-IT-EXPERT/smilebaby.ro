<?php
$now = time();
$saleActive = !empty($product['sale_price'])
    && (empty($product['sale_start']) || strtotime((string) $product['sale_start']) <= $now)
    && (empty($product['sale_end']) || strtotime((string) $product['sale_end']) >= $now);
$badges = [];
$available = empty($product['manage_stock']) || ($product['stock_status'] ?? 'in_stock') !== 'out_of_stock';
if (!empty($product['badge_text'])) $badges[] = ['label' => (string) $product['badge_text'], 'type' => 'custom'];
if (!empty($product['is_customizable'])) $badges[] = ['label' => 'Personalizabil', 'type' => 'customizable'];
if ($saleActive) $badges[] = ['label' => 'Reducere', 'type' => 'sale'];
?>
<article class="product-card">
    <div class="product-media">
        <?php if ($badges): ?><div class="product-badges"><?php foreach ($badges as $badge): ?><span class="sale-badge badge-<?= e($badge['type']) ?>"><?= e($badge['label']) ?></span><?php endforeach ?></div><?php endif ?>
        <a href="/produs/<?= e($product['slug']) ?>"><img src="<?= e(optimized_image_url($product['image_path'] ?? null, 'card')) ?>" alt="<?= e($product['name']) ?>" width="720" height="720" loading="lazy" decoding="async"></a>
        <form action="/favorite" method="post" class="wishlist-form" data-wishlist-form><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><button type="submit" aria-label="Adaugă <?= e($product['name']) ?> la favorite"><?= icon('heart') ?></button></form>
    </div>
    <div class="product-info">
        <h3><a href="/produs/<?= e($product['slug']) ?>"><?= e($product['name']) ?></a></h3>
        <div class="product-price"><?php if ($saleActive): ?><del><?= money($product['regular_price']) ?></del><?php endif ?><strong><?= money($saleActive ? $product['sale_price'] : $product['regular_price']) ?></strong></div>
        <form action="/cos/adauga" method="post" class="product-cart-form" data-cart-form>
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" <?= !$available ? 'disabled' : '' ?>><?= icon('bag') ?><span><?= !$available ? 'Stoc epuizat' : 'Adaugă în coș' ?></span></button>
        </form>
    </div>
</article>
