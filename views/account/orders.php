<?php
$statusLabels = [
    'received' => 'Primită', 'confirmed' => 'Confirmată', 'processing' => 'În pregătire',
    'prepared' => 'Pregătită', 'shipped' => 'Expediată', 'delivered' => 'Livrată',
    'cancelled' => 'Anulată', 'returned' => 'Returnată',
];
$paymentLabels = ['unpaid' => 'Neachitată', 'pending' => 'În așteptare', 'paid' => 'Achitată', 'failed' => 'Eșuată', 'refunded' => 'Rambursată'];
$ordersTotal = array_sum(array_map(static fn(array $order): float => (float) $order['total'], $orders));
?>
<section class="account-orders-page shell">
    <header class="account-orders-hero">
        <div>
            <span class="account-welcome-kicker"><i></i>ISTORICUL TĂU</span>
            <h1>Comenzile mele</h1>
            <p>Toate alegerile tale SmileBaby, păstrate într-un singur loc.</p>
        </div>
        <div class="account-orders-count"><strong><?= count($orders) ?></strong><span><?= count($orders) === 1 ? 'comandă' : 'comenzi' ?></span></div>
    </header>

    <div class="account-layout account-orders-layout">
        <?php require BASE_PATH . '/views/components/account-nav.php'; ?>
        <section class="account-content orders-premium-content">
            <?php if ($orders): ?>
                <div class="orders-summary-strip">
                    <div><span><?= icon('bag') ?></span><p><small>COMENZI PLASATE</small><strong><?= count($orders) ?></strong></p></div>
                    <div><span><?= icon('card') ?></span><p><small>VALOARE TOTALĂ</small><strong><?= money($ordersTotal) ?></strong></p></div>
                    <div><span><?= icon('heart') ?></span><p><small>ÎȚI MULȚUMIM</small><strong>Pentru încredere</strong></p></div>
                </div>

                <div class="account-order-cards">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $status = (string) ($order['status'] ?? 'received');
                        $paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
                        $isTest = str_contains((string) ($order['notes'] ?? ''), '[COMANDĂ TEST]');
                        ?>
                        <article class="account-order-card<?= $isTest ? ' is-test' : '' ?>">
                            <a href="/cont/comenzi/<?= e($order['order_number']) ?>" data-account-link>
                                <div class="account-order-preview">
                                    <?php if (!empty($order['preview_image'])): ?>
                                        <img src="<?= e(upload_url($order['preview_image'])) ?>" alt="">
                                    <?php else: ?>
                                        <span><?= icon('gift') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="account-order-copy">
                                    <div class="account-order-labels">
                                        <?php if ($isTest): ?><span class="order-test-badge">COMANDĂ TEST</span><?php endif; ?>
                                        <span class="status-badge status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                                    </div>
                                    <small>COMANDA <?= e($order['order_number']) ?></small>
                                    <h2><?= e($order['preview_product'] ?: 'Produse SmileBaby') ?></h2>
                                    <p><?= (int) $order['item_count'] ?> <?= (int) $order['item_count'] === 1 ? 'produs' : 'produse' ?> · plasată la <?= date('d.m.Y', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div class="account-order-payment">
                                    <small><?= e($paymentLabels[$paymentStatus] ?? $paymentStatus) ?></small>
                                    <strong><?= money($order['total']) ?></strong>
                                    <span>VEZI DETALIILE <?= icon('arrow') ?></span>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="orders-premium-empty">
                    <div aria-hidden="true"><span><?= icon('bag') ?></span><i>♡</i></div>
                    <span>PRIMA ALEGERE</span>
                    <h2>Încă nu ai nicio comandă.</h2>
                    <p>Când vei găsi ceva drag, comanda și parcursul ei vor apărea aici.</p>
                    <a class="button" href="/magazin">DESCOPERĂ MAGAZINUL <?= icon('arrow') ?></a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
