<?php
$statusLabels = [
    'received' => 'Comandă nouă', 'confirmed' => 'Confirmată', 'processing' => 'În pregătire',
    'prepared' => 'Pregătită pentru livrare', 'shipped' => 'Expediată', 'delivered' => 'Livrată',
    'cancelled' => 'Anulată', 'returned' => 'Returnată',
];
$statusDescriptions = [
    'received' => 'Comanda a fost înregistrată și așteaptă confirmarea.',
    'confirmed' => 'Comanda este confirmată și intră în pregătire.',
    'processing' => 'Produsele sunt pregătite cu grijă în atelier.',
    'prepared' => 'Comanda este ambalată și pregătită pentru livrare.',
    'shipped' => 'Comanda a plecat spre client.',
    'delivered' => 'Comanda a ajuns cu bine la client.',
    'cancelled' => 'Comanda este anulată și stocul este restaurat.',
    'returned' => 'Returul comenzii este înregistrat.',
];
$statusMessages = [
    'confirmed' => 'Comanda ta a fost confirmată și intră în pregătire.',
    'processing' => 'Pregătim cu grijă fiecare detaliu al comenzii tale.',
    'prepared' => 'Comanda ta este pregătită pentru livrare.',
    'shipped' => 'Comanda ta a plecat spre tine.',
    'delivered' => 'Comanda ta a fost livrată. Îți mulțumim!',
    'cancelled' => 'Comanda a fost anulată.',
    'returned' => 'Returul comenzii a fost înregistrat.',
];
$steps = ['received', 'confirmed', 'processing', 'prepared', 'shipped', 'delivered'];
$workflowNext = [
    'received' => ['confirmed', 'cancelled'], 'confirmed' => ['processing', 'cancelled'],
    'processing' => ['prepared', 'cancelled'], 'prepared' => ['shipped', 'cancelled'],
    'shipped' => ['delivered', 'returned'], 'delivered' => ['returned'],
    'cancelled' => [], 'returned' => [],
];
$status = (string) $order['status'];
$currentStep = array_search($status, $steps, true);
if ($currentStep === false) {
    $currentStep = 0;
    foreach ($history as $historyEntry) {
        $historyIndex = array_search((string) $historyEntry['new_status'], $steps, true);
        if ($historyIndex !== false) $currentStep = max($currentStep, $historyIndex);
    }
}
$nextStatuses = $workflowNext[$status] ?? [];
$paymentLabels = ['unpaid' => 'Neplătită', 'pending' => 'În așteptare', 'paid' => 'Încasată', 'failed' => 'Eșuată', 'refunded' => 'Rambursată'];
$customerType = ($order['customer_type'] ?? 'individual') === 'company' ? 'Persoană juridică' : 'Persoană fizică';
$customerMessage = trim(str_replace('[COMANDĂ TEST]', '', (string) ($order['notes'] ?? '')));
$isCod = ($order['payment_method'] ?? '') === 'cash_on_delivery';
?>
<div class="admin-order-page" data-admin-order-page>
    <a class="admin-order-back" href="/admin/comenzi"><?= icon('arrow') ?> ÎNAPOI LA COMENZI</a>

    <header class="admin-order-status-hero status-<?= e($status) ?>">
        <div><small>STATUS CURENT</small><h1><?= e($statusLabels[$status] ?? $status) ?></h1><p><?= e($statusDescriptions[$status] ?? '') ?></p></div>
        <span><?= e(mb_strtoupper($statusLabels[$status] ?? $status)) ?></span>
    </header>

    <section class="admin-order-progress" aria-label="Parcursul comenzii">
        <div class="admin-order-progress-line" style="--order-progress:<?= number_format(($currentStep / (count($steps) - 1)) * 100, 2, '.', '') ?>%"><i></i></div>
        <?php foreach ($steps as $index => $step):
            $state = $index < $currentStep ? 'done' : ($index === $currentStep ? 'current' : 'upcoming');
        ?>
            <div class="admin-order-progress-step <?= $state ?>" style="--progress-index:<?= $index ?>">
                <span><?= $index < $currentStep ? icon('check') : $index + 1 ?></span>
                <small><?= e($statusLabels[$step]) ?></small>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="admin-order-layout">
        <main class="admin-order-main">
            <?php if ($customerMessage !== ''): ?>
                <section class="admin-order-card admin-order-message-card">
                    <span>“</span><div><small>MESAJUL CLIENTULUI</small><h2>Cu mesaj</h2><p>„<?= e($customerMessage) ?>”</p></div>
                </section>
            <?php endif; ?>

            <section class="admin-order-card admin-order-products-card">
                <header><h2>Produse comandate</h2><span><?= count($items) ?> <?= count($items) === 1 ? 'poziție' : 'poziții' ?></span></header>
                <div class="admin-order-product-list">
                    <?php foreach ($items as $item): ?>
                        <article>
                            <button class="admin-order-product-image" type="button" data-order-image="<?= e(upload_url($item['image_path'])) ?>" data-order-image-alt="<?= e($item['product_name']) ?>">
                                <img src="<?= e(upload_url($item['image_path'])) ?>" alt="<?= e($item['product_name']) ?>"><i><?= icon('plus') ?></i>
                            </button>
                            <div class="admin-order-product-copy">
                                <strong><?= e($item['product_name']) ?></strong>
                                <small><?= e($item['display_sku'] ?: 'Fără SKU') ?> · <?= (int) $item['quantity'] ?> buc.</small>
                                <?php if (!empty($item['variant_name'])): ?><span><?= e($item['variant_name']) ?></span><?php endif; ?>
                                <?php $personalization = !empty($item['customization_json']) ? json_decode($item['customization_json'], true) : []; if ($personalization): ?><div class="admin-order-personalization"><b>✦ PERSONALIZARE</b><?php foreach($personalization as $detail): ?><small><span><?= e($detail['label'] ?? '') ?></span><strong><?= e(($detail['type'] ?? '') === 'date' ? date('d.m.Y', strtotime($detail['value'] ?? '')) : ($detail['value'] ?? '')) ?></strong></small><?php endforeach ?></div><?php endif; ?>
                                <?php $orderAddons = !empty($item['addons_json']) ? json_decode($item['addons_json'], true) : []; if ($orderAddons): ?><div class="admin-order-addons"><b>＋ PRODUSE SUPLIMENTARE</b><?php foreach($orderAddons as $addon): ?><article><img src="<?=e(upload_url($addon['image_path']??null))?>" alt=""><p><strong><?=e($addon['name']??'Produs')?></strong><small><?= (int)($addon['quantity']??1) ?> × <?=money($addon['price']??0)?></small></p><em><?=money($addon['total']??0)?></em></article><?php endforeach ?></div><?php endif; ?>
                                <?php if (!empty($item['product_slug'])): ?><a href="/produs/<?= e($item['product_slug']) ?>" target="_blank" rel="noopener">VEZI PRODUSUL PE SITE <?= icon('arrow') ?></a><?php endif; ?>
                            </div>
                            <b><?= money($item['total']) ?></b>
                        </article>
                    <?php endforeach; ?>
                </div>
                <footer>
                    <div><span>Subtotal</span><strong><?= money($order['subtotal']) ?></strong></div>
                    <?php if ((float) $order['shipping_total'] > 0): ?><div><span>Livrare</span><strong><?= money($order['shipping_total']) ?></strong></div><?php endif; ?>
                    <?php if ((float) $order['payment_fee'] > 0): ?><div><span>Taxă plată</span><strong><?= money($order['payment_fee']) ?></strong></div><?php endif; ?>
                    <div class="grand-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
                </footer>
            </section>

            <section class="admin-order-card admin-order-history-card">
                <header><h2>Istoric și note</h2><small>Cele mai noi apar primele</small></header>
                <ol>
                    <?php foreach ($history as $entry): ?>
                        <li><i></i><div><strong><?= e($statusLabels[$entry['new_status']] ?? $entry['new_status']) ?></strong><?php if (!empty($entry['message'])): ?><p><?= e($entry['message']) ?></p><?php endif; ?><time><?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?><?= !empty($entry['changed_by_name']) ? ' · ' . e($entry['changed_by_name']) : '' ?></time></div></li>
                    <?php endforeach; ?>
                    <?php foreach ($notes as $note): ?>
                        <li class="internal"><i></i><div><strong>Notă internă</strong><p><?= e($note['note']) ?></p><time><?= date('d.m.Y H:i', strtotime($note['created_at'])) ?><?= !empty($note['author_name']) ? ' · ' . e($note['author_name']) : '' ?></time></div></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </main>

        <aside class="admin-order-side">
            <form class="admin-order-card admin-order-update-card" action="/admin/comenzi/<?= (int) $order['id'] ?>" method="post" data-order-status-form>
                <?= csrf_field() ?>
                <span class="admin-order-kicker">URMĂTORUL PAS</span><h2>Actualizează statusul</h2>
                <?php if ($nextStatuses): ?>
                    <fieldset><legend>ALEGE URMĂTOAREA ETAPĂ</legend>
                        <?php foreach ($nextStatuses as $index => $nextStatus): ?>
                            <label class="admin-order-status-choice">
                                <input type="radio" name="status" value="<?= e($nextStatus) ?>" data-order-status-message="<?= e($statusMessages[$nextStatus] ?? '') ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                <i></i><span><strong><?= e(mb_strtoupper($statusLabels[$nextStatus])) ?></strong><small><?= e($statusDescriptions[$nextStatus]) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <label class="admin-order-textarea"><span>MESAJ VIZIBIL CLIENTULUI</span><textarea name="message" rows="3" data-order-status-message-input><?= e($statusMessages[$nextStatuses[0]] ?? '') ?></textarea></label>
                    <label class="admin-order-textarea"><span>NOTĂ INTERNĂ <em>(NU ESTE TRIMISĂ CLIENTULUI)</em></span><textarea name="internal_note" rows="3"></textarea></label>
                    <label class="admin-order-notify"><input type="checkbox" name="notify" value="1" checked><i><?= icon('check') ?></i><span>TRIMITE NOTIFICARE CLIENTULUI PRIN EMAIL</span></label>
                    <button class="admin-order-submit" type="submit">CONFIRMĂ SCHIMBAREA STATUSULUI</button>
                <?php else: ?>
                    <div class="admin-order-finished"><?= icon('check') ?><strong>Flux finalizat</strong><p>Această comandă nu mai are o etapă următoare disponibilă.</p></div>
                <?php endif; ?>
            </form>

            <?php if ($isCod): ?>
                <form class="admin-order-card admin-order-payment-card" action="/admin/comenzi/<?= (int) $order['id'] ?>/plata" method="post">
                    <?= csrf_field() ?>
                    <header><div><span class="admin-order-kicker">RAMBURS</span><h2>Încasarea comenzii</h2></div><b class="<?= $order['payment_status'] === 'paid' ? 'paid' : '' ?>"><?= $order['payment_status'] === 'paid' ? 'ÎNCASATĂ' : 'NEÎNCASATĂ' ?></b></header>
                    <label class="admin-order-payment-toggle"><input type="checkbox" name="paid" value="1" <?= $order['payment_status'] === 'paid' ? 'checked' : '' ?>><i></i><span><strong>Plata a fost încasată</strong><small>Poți bifa sau debifa manual. La marcarea comenzii ca „Livrată”, plata se confirmă automat.</small></span></label>
                    <p>Starea plății este preluată automat și în evidența comenzii.</p>
                    <button class="admin-order-submit" type="submit">SALVEAZĂ STAREA PLĂȚII</button>
                </form>
            <?php endif; ?>

            <section class="admin-order-card admin-order-customer-card">
                <h2>Client și plată</h2>
                <dl>
                    <div><dt>Nume</dt><dd><strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong></dd></div>
                    <div><dt>Email</dt><dd><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd></div>
                    <div><dt>Telefon</dt><dd><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></dd></div>
                    <div><dt>Tip client</dt><dd><?= e($customerType) ?></dd></div>
                    <div><dt>Tip plată</dt><dd><span><?= e(mb_strtoupper($order['payment_method_label'])) ?></span></dd></div>
                    <div><dt>Stare plată</dt><dd><strong><?= e($paymentLabels[$order['payment_status']] ?? $order['payment_status']) ?></strong></dd></div>
                    <div><dt>Livrare</dt><dd>Livrare la adresă</dd></div>
                    <div><dt>Adresă livrare</dt><dd><?= e($order['shipping_address']) ?><br><?= e($order['shipping_city'] . ', ' . $order['shipping_county'] . ' ' . ($order['shipping_postcode'] ?? '')) ?></dd></div>
                    <?php if (($order['customer_type'] ?? 'individual') === 'company'): ?><div><dt>Companie</dt><dd><strong><?= e($order['company_name']) ?></strong><br>CUI <?= e($order['company_vat_id']) ?><br><?= e($order['company_registration_number']) ?></dd></div><?php endif; ?>
                </dl>
            </section>
        </aside>
    </div>
</div>

<dialog class="admin-order-image-dialog" data-order-image-dialog aria-label="Previzualizare produs">
    <button type="button" aria-label="Închide imaginea" data-order-image-close>×</button>
    <img src="" alt="" data-order-image-preview>
</dialog>
