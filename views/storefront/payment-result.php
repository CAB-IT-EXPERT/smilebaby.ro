<?php
$displayState = $displayState ?? (($order['payment_status'] ?? '') === 'paid' ? 'paid' : 'pending');
$state = match ($displayState) {
    'received' => ['received','✓','COMANDĂ PRIMITĂ','Am primit comanda ta','Îți mulțumim! Comanda a ajuns la noi și ți-am trimis rezumatul complet pe email.','Comandă nouă'],
    'paid' => ['paid','✓','PLATĂ CONFIRMATĂ','Plata a fost confirmată','Comanda este confirmată automat și intră în pregătire. Vei primi pe email fiecare actualizare importantă.','Achitată'],
    'failed' => ['failed','!','PLATĂ NEFINALIZATĂ','Plata nu a reușit','Nu am încasat suma. Comanda a rămas salvată și poți încerca din nou fără să dublezi produsele.','Neachitată'],
    'cancelled' => ['cancelled','×','PLATĂ ANULATĂ','Plata a fost anulată','Nu am încasat suma. Comanda rămâne disponibilă dacă dorești să reiei plata în siguranță.','Anulată'],
    'order_cancelled' => ['cancelled','×','COMANDĂ ANULATĂ','Comanda a fost anulată','Comanda nu mai este procesată. Pentru o plată deja efectuată, echipa noastră va gestiona rambursarea.','Anulată'],
    default => ['pending','◷','VERIFICĂM PLATA','Confirmarea este în curs','Stripe procesează confirmarea. Pagina poate fi reîncărcată în câteva momente; comanda ta este salvată.','În așteptare'],
};
$completedOrderNumber = in_array($displayState, ['received', 'paid'], true) ? (string) $order['order_number'] : '';
?>
<main class="order-result-page <?= e($state[0]) ?>"<?= $completedOrderNumber !== '' ? ' data-order-completed="'.e($completedOrderNumber).'"' : '' ?>>
    <section class="order-result-hero shell">
        <div class="order-result-status-mark"><span><?= e($state[1]) ?></span><i></i><i></i></div>
        <span class="eyebrow"><?= e($state[2]) ?></span>
        <h1><?= e($state[3]) ?></h1>
        <p><?= e($state[4]) ?></p>
        <?php if (!empty($paymentError)): ?><p class="order-result-error"><?= e($paymentError) ?></p><?php endif ?>
        <div class="order-result-statuses"><span><small>COMANDĂ</small><b><?= e($order['order_number']) ?></b></span><span><small>STATUS</small><b><?= e($state[5]) ?></b></span><span><small>PLATĂ</small><b><?= $order['payment_method'] === 'online_card' ? 'Card online' : e($order['payment_method_label']) ?></b></span></div>
    </section>
    <div class="order-result-layout shell">
        <?php require BASE_PATH . '/views/storefront/partials/order-receipt.php'; ?>
        <aside class="order-result-aside">
            <section><span class="order-result-mini-icon"><?= icon('mail') ?></span><div><small>CONFIRMARE EMAIL</small><h2>Rezumatul este în drum spre tine</h2><p>L-am trimis la <strong><?= e($order['email']) ?></strong>.</p></div></section>
            <section><span class="order-result-mini-icon"><?= icon('pin') ?></span><div><small>LIVRARE</small><h2><?= e($order['first_name'] . ' ' . $order['last_name']) ?></h2><p><?= e($order['shipping_address']) ?><br><?= e($order['shipping_city'] . ', ' . $order['shipping_county']) ?></p></div></section>
            <?php if ($order['payment_method'] === 'online_card'): ?><section class="order-result-stripe"><span class="order-result-mini-icon"><?= icon('card') ?></span><div><small>PLATĂ SECURIZATĂ</small><h2>Stripe</h2><p>Card · Apple Pay · Google Pay · Revolut Pay</p></div></section><?php endif ?>
            <div class="order-result-actions">
                <?php if (in_array($displayState, ['failed','cancelled','pending'], true) && $order['payment_method'] === 'online_card'): ?><form action="/plata/reincearca/<?= e($order['order_number']) ?>" method="post"><?= csrf_field() ?><button class="button" type="submit">REIA PLATA <?= icon('arrow') ?></button></form><?php endif ?>
                <?php if (!in_array($displayState, ['failed', 'cancelled'], true)): ?><a class="button secondary" href="<?= e((new App\Services\OrderTrackingService())->path($order)) ?>">URMĂREȘTE COMANDA</a><?php endif ?>
                <a class="text-link" href="/magazin">Continuă cumpărăturile</a>
            </div>
        </aside>
    </div>
</main>
