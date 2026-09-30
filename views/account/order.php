<?php
$statusLabels = [
    'received' => 'Primită', 'confirmed' => 'Confirmată', 'processing' => 'În pregătire',
    'prepared' => 'Pregătită', 'shipped' => 'Expediată', 'delivered' => 'Livrată',
    'cancelled' => 'Anulată', 'returned' => 'Returnată',
];
$paymentLabels = ['unpaid' => 'Neachitată', 'pending' => 'În așteptare', 'paid' => 'Achitată', 'failed' => 'Eșuată', 'refunded' => 'Rambursată'];
$status = (string) $order['status'];
$paymentStatus = (string) $order['payment_status'];
$isTest = str_contains((string) ($order['notes'] ?? ''), '[COMANDĂ TEST]');
$orderProductCount = array_sum(array_map(static fn(array $item): int => (int) $item['quantity'], $items));
foreach ($items as $item) foreach ((array) json_decode((string) ($item['addons_json'] ?? ''), true) as $addon) $orderProductCount += (int) ($addon['quantity'] ?? 0);
?>
<section class="account-order-page shell">
    <header class="account-order-hero">
        <a href="/cont/comenzi" data-account-link><?= icon('arrow') ?> ÎNAPOI LA COMENZI</a>
        <div class="account-order-hero-copy">
            <span>DETALIILE COMENZII</span>
            <h1><?= e($order['order_number']) ?></h1>
            <p>Plasată la <?= date('d.m.Y, H:i', strtotime($order['created_at'])) ?></p>
        </div>
        <div class="account-order-hero-badges">
            <?php if ($isTest): ?><span class="order-test-badge">COMANDĂ TEST</span><?php endif; ?>
            <span class="status-badge status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
        </div>
    </header>

    <div class="account-layout account-order-layout">
        <?php require BASE_PATH . '/views/components/account-nav.php'; ?>
        <section class="account-content order-detail-premium">
            <div class="order-fact-grid">
                <article><span><?= icon('bag') ?></span><div><small>STATUS COMANDĂ</small><strong><?= e($statusLabels[$status] ?? $status) ?></strong></div></article>
                <article><span><?= icon('card') ?></span><div><small>STATUS PLATĂ</small><strong><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></strong></div></article>
                <article><span><?= icon('gift') ?></span><div><small>PRODUSE</small><strong><?= $orderProductCount ?></strong></div></article>
                <article class="order-fact-total"><div><small>TOTAL COMANDĂ</small><strong><?= money($order['total']) ?></strong></div></article>
            </div>

            <div class="order-detail-grid-premium">
                <div class="order-detail-main-column">
                    <section class="order-premium-panel order-products-panel">
                        <header><div><span>CE AI ALES</span><h2>Produsele comenzii</h2></div><small><?= count($items) ?> <?= count($items) === 1 ? 'articol' : 'articole' ?></small></header>
                        <div class="order-products-premium">
                            <?php foreach ($items as $item): ?>
                                <article>
                                    <img src="<?= e(upload_url($item['image_path'])) ?>" alt="">
                                    <div><strong><?= e($item['product_name']) ?></strong><?php if (!empty($item['variant_name'])): ?><small><?= e($item['variant_name']) ?></small><?php endif; ?><?php $personalization = !empty($item['customization_json']) ? json_decode($item['customization_json'], true) : []; if ($personalization): ?><small class="order-personalization-note">✦ <?php foreach($personalization as $index=>$detail): ?><?= $index ? ' · ' : '' ?><?= e($detail['label'] ?? '') ?>: <?= e(($detail['type'] ?? '') === 'date' ? date('d.m.Y', strtotime($detail['value'] ?? '')) : ($detail['value'] ?? '')) ?><?php endforeach ?></small><?php endif; ?><span><?= (int) $item['quantity'] ?> × <?= money($item['price']) ?></span><?php $accountAddons=!empty($item['addons_json'])?json_decode($item['addons_json'],true):[]; if($accountAddons):?><span class="order-addon-group"><b>＋ Completează setul</b><?php foreach($accountAddons as $addon):?><small><img src="<?=e(upload_url($addon['image_path']??null))?>" alt=""><span><?=e($addon['name']??'Produs')?><em><?= (int)($addon['quantity']??1) ?> × <?=money($addon['price']??0)?></em></span></small><?php endforeach?></span><?php endif?></div>
                                    <b><?= money($item['total']) ?></b>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-totals-premium">
                            <p><span>Subtotal</span><strong><?= money($order['subtotal']) ?></strong></p>
                            <p><span>Livrare</span><strong><?= (float) $order['shipping_total'] > 0 ? money($order['shipping_total']) : 'Gratuită' ?></strong></p>
                            <?php if ((float) $order['payment_fee'] > 0): ?><p><span>Taxă plată</span><strong><?= money($order['payment_fee']) ?></strong></p><?php endif; ?>
                            <?php if ((float) $order['discount_total'] > 0): ?><p><span>Reducere</span><strong>−<?= money($order['discount_total']) ?></strong></p><?php endif; ?>
                            <p class="grand-total"><span>Total</span><strong><?= money($order['total']) ?></strong></p>
                        </div>
                    </section>

                    <section class="order-premium-panel order-delivery-panel">
                        <header><div><span>DESTINAȚIE</span><h2>Detalii de livrare</h2></div><?= icon('truck') ?></header>
                        <div class="order-delivery-grid">
                            <p><small>DESTINATAR</small><strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong><span><?= e($order['phone']) ?></span></p>
                            <p><small>ADRESĂ</small><strong><?= e($order['shipping_address']) ?></strong><span><?= e($order['shipping_city'] . ', ' . $order['shipping_county'] . ' ' . ($order['shipping_postcode'] ?? '')) ?></span></p>
                            <p><small>PLATĂ</small><strong><?= e($order['payment_method_label']) ?></strong><span><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></span></p>
                            <?php if (($order['customer_type'] ?? 'individual') === 'company'): ?><div class="order-company-details"><p><small>FACTURARE</small><strong>Persoană juridică</strong><span><?= e($order['company_name']) ?></span></p><p><small>CUI / CIF</small><strong><?= e($order['company_vat_id']) ?></strong></p><p><small>REGISTRUL COMERȚULUI</small><strong><?= e($order['company_registration_number']) ?></strong></p><p><small>SEDIU SOCIAL</small><strong><?= e($order['company_address']) ?></strong></p></div><?php endif; ?>
                        </div>
                    </section>
                </div>

                <aside class="order-premium-panel order-progress-panel">
                    <header><span>URMĂRIRE</span><h2>Parcursul comenzii</h2><p>Vezi fiecare etapă, de la confirmare până la livrare.</p></header>
                    <ol class="order-timeline-premium">
                        <?php foreach ($history as $index => $entry): ?>
                            <li class="<?= $index === array_key_last($history) ? 'current' : '' ?>">
                                <i><?= $index === array_key_last($history) ? '✓' : '' ?></i>
                                <div><strong><?= e($statusLabels[$entry['new_status']] ?? $entry['new_status']) ?></strong><span><?= date('d.m.Y · H:i', strtotime($entry['created_at'])) ?></span><?php if ($entry['message']): ?><p><?= e($entry['message']) ?></p><?php endif; ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="order-progress-note"><?= icon('clock') ?><span><strong>Te ținem la curent</strong></span></div>
                </aside>
            </div>
        </section>
    </div>
</section>
