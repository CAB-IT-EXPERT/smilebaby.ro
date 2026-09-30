<?php
$accountUser = user();
$firstName = trim((string) ($accountUser['first_name'] ?? '')) ?: 'dragă client';
$fullName = trim($firstName . ' ' . (string) ($accountUser['last_name'] ?? ''));
$initial = mb_strtoupper(mb_substr($firstName, 0, 1));
$favoriteCount = count(App\Core\Session::get('wishlist', []));
$statusLabels = [
    'pending' => 'În așteptare',
    'processing' => 'În pregătire',
    'completed' => 'Finalizată',
    'shipped' => 'Expediată',
    'cancelled' => 'Anulată',
    'refunded' => 'Rambursată',
];
?>
<section class="account-dashboard shell">
    <header class="account-welcome-card">
        <div class="account-welcome-copy">
            <span class="account-welcome-kicker"><i></i>BINE AI VENIT ÎNAPOI</span>
            <h1>Bună, <em><?= e($firstName) ?>!</em></h1>
            <p>De aici poți urmări comenzile și păstra aproape toate detaliile importante pentru următoarea ta poveste.</p>
            <div class="account-welcome-actions">
                <a class="button" href="/magazin">DESCOPERĂ COLECȚIA <?= icon('arrow') ?></a>
                <a class="account-soft-link" href="/cont/profil" data-account-link>Actualizează profilul</a>
            </div>
        </div>
        <div class="account-identity-card" aria-label="Contul <?= e($firstName) ?>">
            <span><?= e($initial) ?></span>
            <div><small>CONTUL TĂU SMILEBABY</small><strong><?= e($fullName) ?></strong><em>Poveștile tale, într-un singur loc</em></div>
            <i><?= icon('heart') ?></i>
        </div>
    </header>

    <div class="account-layout account-dashboard-layout">
        <?php require BASE_PATH . '/views/components/account-nav.php'; ?>
        <section class="account-content account-overview">
            <div class="account-shortcut-grid" aria-label="Scurtături cont">
                <a href="/cont/comenzi" data-account-link>
                    <span><?= icon('bag') ?></span>
                    <div><small>ISTORIC</small><strong>Comenzile mele</strong><em><?= count($orders) ? count($orders) . ' recente' : 'Nicio comandă' ?></em></div>
                    <?= icon('chevron') ?>
                </a>
                <a href="/favorite">
                    <span><?= icon('heart') ?></span>
                    <div><small>LISTA TA</small><strong>Produse favorite</strong><em><?= $favoriteCount ?> <?= $favoriteCount === 1 ? 'produs salvat' : 'produse salvate' ?></em></div>
                    <?= icon('chevron') ?>
                </a>
                <a href="/cont/adrese" data-account-link>
                    <span><?= icon('pin') ?></span>
                    <div><small>LIVRARE</small><strong>Adresele mele</strong><em>Gestionează rapid</em></div>
                    <?= icon('chevron') ?>
                </a>
            </div>

            <section class="dashboard-orders-card">
                <div class="dashboard-orders-head">
                    <div><span>ULTIMELE TALE ALEGERI</span><h2>Comenzi recente</h2></div>
                    <?php if ($orders): ?><a href="/cont/comenzi" data-account-link>Vezi toate <?= icon('arrow') ?></a><?php endif; ?>
                </div>

                <?php if ($orders): ?>
                    <div class="dashboard-order-list">
                        <?php foreach ($orders as $order): ?>
                            <?php $status = (string) ($order['status'] ?? 'pending'); ?>
                            <a href="/cont/comenzi/<?= e($order['order_number']) ?>" data-account-link>
                                <span class="dashboard-order-icon"><?= icon('gift') ?></span>
                                <span class="dashboard-order-main"><small>COMANDA</small><strong><?= e($order['order_number']) ?></strong></span>
                                <span class="dashboard-order-date"><small>PLASATĂ LA</small><strong><?= date('d.m.Y', strtotime($order['created_at'])) ?></strong></span>
                                <span class="status-badge status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                                <b><?= money($order['total']) ?></b>
                                <?= icon('chevron') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty-orders">
                        <div class="dashboard-empty-art" aria-hidden="true"><span><?= icon('bag') ?></span><i>♡</i><b>✦</b></div>
                        <div>
                            <span>PRIMA POVESTE ÎNCEPE AICI</span>
                            <h2>Coșul tău așteaptă o alegere specială.</h2>
                            <p>Când vei plasa prima comandă, aici vei găsi toate detaliile, statusul și parcursul coletului.</p>
                            <a class="button" href="/magazin">ALEGE CEVA FRUMOS <?= icon('arrow') ?></a>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </div>
</section>
