<section class="order-result-receipt" aria-label="Rezumatul comenzii">
    <header><div><span>COMANDA TA</span><h2><?= e($order['order_number']) ?></h2></div><strong><?= e(date('d.m.Y', strtotime((string) $order['created_at']))) ?></strong></header>
    <div class="order-result-items">
        <?php foreach ($items as $item):
            $personalization = (array) json_decode((string) ($item['customization_json'] ?? ''), true);
            $addons = (array) json_decode((string) ($item['addons_json'] ?? ''), true);
        ?>
            <article>
                <img src="<?= e(upload_url($item['image_path'] ?? null)) ?>" alt="">
                <div class="order-result-item-copy">
                    <h3><?= e($item['product_name']) ?></h3>
                    <?php if (!empty($item['variant_name'])): ?><p><?= e($item['variant_name']) ?></p><?php endif ?>
                    <span><?= (int) $item['quantity'] ?> × <?= money($item['price']) ?></span>
                    <?php if ($personalization): ?><div class="order-result-personalization"><b>✦ Personalizare</b><?php foreach ($personalization as $detail): ?><small><span><?= e($detail['label'] ?? 'Detaliu') ?></span><strong><?= e($detail['value'] ?? '') ?></strong></small><?php endforeach ?></div><?php endif ?>
                    <?php if ($addons): ?><div class="order-result-addons"><b>＋ Produse suplimentare</b><?php foreach ($addons as $addon): ?><small><img src="<?= e(upload_url($addon['image_path'] ?? null)) ?>" alt=""><span><strong><?= e($addon['name'] ?? 'Produs') ?></strong><em><?= (int) ($addon['quantity'] ?? 1) ?> × <?= money($addon['price'] ?? 0) ?></em></span><b><?= money($addon['total'] ?? 0) ?></b></small><?php endforeach ?></div><?php endif ?>
                </div>
                <strong class="order-result-line-total"><?= money($item['total']) ?></strong>
            </article>
        <?php endforeach ?>
    </div>
    <div class="order-result-totals">
        <p><span>Subtotal produse</span><strong><?= money($order['subtotal']) ?></strong></p>
        <p><span>Livrare</span><strong><?= (float) $order['shipping_total'] > 0 ? money($order['shipping_total']) : 'Gratuită' ?></strong></p>
        <?php if ((float) $order['payment_fee'] > 0): ?><p><span>Taxă metodă de plată</span><strong><?= money($order['payment_fee']) ?></strong></p><?php endif ?>
        <p class="total"><span>Total</span><strong><?= money($order['total']) ?></strong></p>
    </div>
</section>
