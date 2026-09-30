<?php
$statusLabels = [
    'received' => 'Comandă primită', 'confirmed' => 'Comandă confirmată', 'processing' => 'În pregătire',
    'prepared' => 'Pregătită pentru drum', 'shipped' => 'În drum spre tine', 'delivered' => 'Livrată',
    'cancelled' => 'Comandă anulată', 'returned' => 'Comandă returnată',
];
$paymentLabels = ['unpaid' => 'Neachitată', 'pending' => 'În curs de confirmare', 'paid' => 'Achitată', 'failed' => 'Plată eșuată', 'refunded' => 'Rambursată'];
$stageContent = [
    'received' => ['title' => 'Am primit comanda', 'short' => 'Primită', 'description' => 'Comanda a ajuns cu bine la noi.', 'icon' => 'bag'],
    'confirmed' => ['title' => 'Comanda este confirmată', 'short' => 'Confirmată', 'description' => 'Am verificat toate detaliile comenzii.', 'icon' => 'check'],
    'processing' => ['title' => 'O pregătim cu grijă', 'short' => 'În pregătire', 'description' => 'Lucrăm la fiecare detaliu al produselor tale.', 'icon' => 'gift'],
    'prepared' => ['title' => 'Este gata de drum', 'short' => 'Pregătită', 'description' => 'Pachetul este pregătit pentru curier.', 'icon' => 'gift'],
    'shipped' => ['title' => 'A plecat spre tine', 'short' => 'Expediată', 'description' => 'Curierul se îndreaptă către tine.', 'icon' => 'truck'],
    'delivered' => ['title' => 'Povestea a ajuns la tine', 'short' => 'Livrată', 'description' => 'Comanda a fost livrată. Să vă bucurați de ea!', 'icon' => 'heart'],
];
$stageKeys = array_keys($stageContent);
$status = (string) ($order['status'] ?? 'received');
$isExceptional = in_array($status, ['cancelled', 'returned'], true);
$historyByStatus = [];
foreach ($history as $entry) $historyByStatus[(string) $entry['new_status']] = $entry;
$currentIndex = array_search($status, $stageKeys, true);
if ($currentIndex === false) {
    $currentIndex = 0;
    foreach ($stageKeys as $index => $key) if (isset($historyByStatus[$key])) $currentIndex = $index;
}
$progress = $isExceptional ? max(8, ($currentIndex / (count($stageKeys) - 1)) * 100) : ($currentIndex / (count($stageKeys) - 1)) * 100;
$itemQuantity = array_sum(array_map(static fn(array $item): int => (int) $item['quantity'], $items));
foreach ($items as $item) foreach ((array) json_decode((string) ($item['addons_json'] ?? ''), true) as $addon) $itemQuantity += (int) ($addon['quantity'] ?? 0);
$currentStage = $stageContent[$stageKeys[$currentIndex]];
?>
<section class="tracking-result-page" data-tracking-page>
    <div class="tracking-ambient tracking-ambient-one" aria-hidden="true"></div>
    <div class="tracking-ambient tracking-ambient-two" aria-hidden="true"></div>

    <header class="tracking-result-hero shell">
        <a class="tracking-back-link" href="/urmareste-comanda"><?= icon('arrow') ?> CAUTĂ ALTĂ COMANDĂ</a>
        <div class="tracking-hero-copy">
            <span class="eyebrow">PARCURSUL COMENZII TALE</span>
            <p class="tracking-hello">Bună, <?= e($order['first_name']) ?>!</p>
            <h1><?= e($currentStage['title']) ?><span>.</span></h1>
            <p><?= e($currentStage['description']) ?> Poți reveni aici oricând pentru cele mai noi informații.</p>
        </div>
        <div class="tracking-live-card <?= $isExceptional ? 'is-exceptional' : '' ?>">
            <span class="tracking-live-icon"><?= icon($isExceptional ? 'return' : $currentStage['icon']) ?><i></i></span>
            <div><small>STATUS ACTUAL</small><strong><?= e($statusLabels[$status] ?? $status) ?></strong><span>Actualizat <?= date('d.m.Y · H:i', strtotime($order['updated_at'] ?? $order['created_at'])) ?></span></div>
        </div>
    </header>

    <div class="tracking-content shell">
        <?php if ($isExceptional): ?>
            <div class="tracking-exception" role="status">
                <span><?= icon('return') ?></span>
                <div><small>INFORMAȚIE IMPORTANTĂ</small><strong><?= e($statusLabels[$status]) ?></strong><p><?= $status === 'cancelled' ? 'Această comandă a fost anulată. Dacă ai nevoie de ajutor, suntem aici pentru tine.' : 'Returul comenzii a fost înregistrat. Te vom ține la curent cu următorii pași.' ?></p></div>
                <a href="/contact">CONTACTEAZĂ-NE <?= icon('arrow') ?></a>
            </div>
        <?php endif; ?>

        <section class="tracking-journey-card" aria-labelledby="tracking-journey-title">
            <div class="tracking-section-heading">
                <div><span>DIN ATELIER, PÂNĂ LA TINE</span><h2 id="tracking-journey-title">Povestea comenzii</h2></div>
                <div class="tracking-order-number"><small>COMANDA</small><strong><?= e($order['order_number']) ?></strong></div>
            </div>

            <div class="tracking-timeline" style="--tracking-progress: <?= number_format($progress, 2, '.', '') ?>%">
                <div class="tracking-line" aria-hidden="true"><i></i></div>
                <?php foreach ($stageKeys as $index => $key):
                    $stage = $stageContent[$key];
                    $stageState = $index < $currentIndex ? 'is-done' : ($index === $currentIndex ? 'is-current' : 'is-upcoming');
                    $entry = $historyByStatus[$key] ?? null;
                ?>
                    <article class="tracking-step <?= $stageState ?>" style="--step-index:<?= $index ?>">
                        <div class="tracking-step-marker"><span><?= $index < $currentIndex ? icon('check') : icon($stage['icon']) ?></span><i></i></div>
                        <div class="tracking-step-copy">
                            <small>ETAPA <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></small>
                            <strong><?= e($stage['short']) ?></strong>
                            <p><?= e($stage['description']) ?></p>
                            <time><?= $entry ? date('d.m.Y · H:i', strtotime($entry['created_at'])) : ($index === $currentIndex ? 'Acum' : 'Urmează') ?></time>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="tracking-detail-grid">
            <section class="tracking-info-card tracking-package-card">
                <header><span><?= icon('gift') ?></span><div><small>COMANDA TA</small><h2>Ce călătorește spre tine</h2></div><b><?= $itemQuantity ?> <?= $itemQuantity === 1 ? 'produs' : 'produse' ?></b></header>
                <div class="tracking-products">
                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                        <article>
                            <img src="<?= e(upload_url($item['image_path'])) ?>" alt="">
                            <div><strong><?= e($item['product_name']) ?></strong><?php if (!empty($item['variant_name'])): ?><small><?= e($item['variant_name']) ?></small><?php endif; ?><?php if(!empty($item['customization_json'])):?><small>✦ Personalizat</small><?php endif?><span><?= (int) $item['quantity'] ?> × <?= money($item['price']) ?></span><?php $trackingAddons=!empty($item['addons_json'])?json_decode($item['addons_json'],true):[]; if($trackingAddons):?><span class="order-addon-group"><b>＋ Completează setul</b><?php foreach($trackingAddons as $addon):?><small><img src="<?=e(upload_url($addon['image_path']??null))?>" alt=""><span><?=e($addon['name']??'Produs')?><em><?= (int)($addon['quantity']??1) ?> × <?=money($addon['price']??0)?></em></span></small><?php endforeach?></span><?php endif?></div>
                            <b><?= money($item['total']) ?></b>
                        </article>
                    <?php endforeach; ?>
                    <?php if (count($items) > 3): ?><p class="tracking-more-products">+ încă <?= count($items) - 3 ?> <?= count($items) - 3 === 1 ? 'articol' : 'articole' ?> în comandă</p><?php endif; ?>
                </div>
                <footer><span>Total comandă</span><strong><?= money($order['total']) ?></strong></footer>
            </section>

            <aside class="tracking-info-stack">
                <section class="tracking-courier-card is-waiting">
                    <span class="tracking-courier-icon"><?= icon($status === 'shipped' ? 'truck' : 'clock') ?></span>
                    <small><?= $status === 'shipped' ? 'LIVRAREA TA' : 'URMĂTORUL PAS' ?></small>
                    <h2><?= $status === 'shipped' ? 'Coletul este pe drum' : 'Te ținem la curent' ?></h2>
                    <p><?= $status === 'shipped' ? 'Comanda a plecat spre tine. Urmărește progresul direct din timeline.' : 'Când comanda avansează, fiecare etapă se actualizează automat aici.' ?></p>
                </section>
                <section class="tracking-summary-card">
                    <div><span><?= icon('card') ?></span><small>PLATĂ</small><strong><?= e($order['payment_method_label']) ?></strong><em><?= e($paymentLabels[$order['payment_status']] ?? $order['payment_status']) ?></em></div>
                    <div><span><?= icon('pin') ?></span><small>LIVRARE</small><strong><?= e($order['shipping_city'] . ', ' . $order['shipping_county']) ?></strong><em><?= e($order['shipping_address']) ?></em></div>
                    <div><span><?= icon('clock') ?></span><small>PLASATĂ</small><strong><?= date('d.m.Y', strtotime($order['created_at'])) ?></strong><em>la <?= date('H:i', strtotime($order['created_at'])) ?></em></div>
                </section>
            </aside>
        </div>

        <section class="tracking-help-card">
            <div><span>AI NEVOIE DE AJUTOR?</span><h2>Suntem alături de tine.</h2><p>Dacă ai o întrebare despre comandă, echipa noastră îți răspunde cu drag.</p></div>
            <a href="/contact">VORBEȘTE CU NOI <?= icon('arrow') ?></a>
        </section>
    </div>
</section>
