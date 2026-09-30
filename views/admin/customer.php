<?php
$name = trim($customer['first_name'] . ' ' . $customer['last_name']);
$statusLabels = ['received'=>'Nouă','confirmed'=>'Confirmată','processing'=>'În pregătire','prepared'=>'Pregătită','shipped'=>'Expediată','delivered'=>'Livrată','cancelled'=>'Anulată','returned'=>'Returnată'];
?>
<div class="admin-customer-page">
    <header class="admin-customer-profile-head">
        <div><span>CLIENT / ISTORIC</span><h1><?= e($name) ?></h1><a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></div>
        <a href="/admin/clienti">Înapoi la clienți</a>
    </header>
    <section class="admin-customer-metrics">
        <article><span>Comenzi</span><strong><?= count($orders) ?></strong><small>înregistrate</small><i></i></article>
        <article><span>Total cumpărături</span><strong><?= money($spent) ?></strong><small>valoare cumulată</small><i></i></article>
        <article><span>Client din</span><strong><?= date('d.m.Y', strtotime($customer['created_at'])) ?></strong><small><?= $customer['status'] === 'active' ? 'cont activ' : 'cont dezactivat' ?></small><i></i></article>
    </section>
    <section class="admin-customer-history">
        <h2>Istoricul comenzilor</h2>
        <?php if ($orders): ?>
            <div class="admin-customer-orders-wrap"><table class="admin-customer-orders"><thead><tr><th>Comandă</th><th>Data</th><th>Produse cumpărate</th><th>Status</th><th>Total</th><th>Detalii</th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?><tr>
                    <td data-label="Comandă"><a href="/admin/comenzi/<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?></a></td>
                    <td data-label="Data"><time datetime="<?= e($order['created_at']) ?>"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></time></td>
                    <td data-label="Produse"><span class="admin-customer-products"><?= e($order['products_summary'] ?: '—') ?></span></td>
                    <td data-label="Status"><span class="admin-customer-status order-<?= e($order['status']) ?>"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
                    <td data-label="Total"><strong><?= money($order['total']) ?></strong></td>
                    <td data-label="Detalii"><a class="admin-customer-view" href="/admin/comenzi/<?= (int) $order['id'] ?>" aria-label="Vezi comanda <?= e($order['order_number']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.7 12s3.4-5 9.3-5 9.3 5 9.3 5-3.4 5-9.3 5-9.3-5-9.3-5Z"/><circle cx="12" cy="12" r="2.6"/></svg></a></td>
                </tr><?php endforeach ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="admin-customer-no-orders">Acest client nu are încă nicio comandă.</div>
        <?php endif ?>
    </section>
</div>
