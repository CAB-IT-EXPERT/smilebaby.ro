<?php
$paid = $order['payment_status'] === 'paid';
$failed = $order['payment_status'] === 'failed';
?>
<section class="message-page shell">
    <span class="message-icon"><?= $paid ? '✓' : ($failed ? '!' : '◷') ?></span>
    <span class="eyebrow">PLATA COMENZII <?= e($order['order_number']) ?></span>
    <h1><?= $paid ? 'Plata a fost confirmată.' : ($failed ? 'Plata nu a fost finalizată.' : 'Confirmarea plății este în curs.') ?></h1>
    <p><?= $paid ? 'Comanda ta a fost înregistrată și o pregătim cu grijă.' : ($failed ? 'Poți încerca din nou fără să creăm o comandă duplicată.' : 'Uneori confirmarea procesatorului durează câteva momente. Poți reveni pe această pagină.') ?></p>
    <div class="confirmation-card"><span>Total</span><strong><?= money($order['total']) ?></strong><span>Status plată</span><strong><?= e($order['payment_status']) ?></strong></div>
    <?php if (!$paid): ?><form action="/plata/reincearca/<?= e($order['order_number']) ?>" method="post"><?= csrf_field() ?><button class="button" type="submit">ÎNCEARCĂ DIN NOU</button></form><?php endif ?>
    <a class="text-link" href="/urmareste-comanda">Urmărește comanda</a>
</section>
