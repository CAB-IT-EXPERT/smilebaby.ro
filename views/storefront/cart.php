<?php
$subtotal = array_sum(array_column($items, 'total'));
$itemCount = array_sum(array_column($items, 'quantity'));
foreach ($items as $cartLine) foreach (($cartLine['addons'] ?? []) as $addon) $itemCount += (int) ($addon['line_quantity'] ?? $addon['quantity']);
$freeShippingThreshold = (float) setting('free_shipping_threshold', 300);
$freeShippingRemaining = max(0, $freeShippingThreshold - $subtotal);
$freeShippingProgress = $freeShippingThreshold > 0 ? min(100, ($subtotal / $freeShippingThreshold) * 100) : 0;
?>
<section class="cart-page-premium shell">
    <header class="cart-hero-premium">
        <div><span>ALEGERILE TALE</span><h1>Coșul tău</h1><p><?= $items ? 'Ai ales '.(int) $itemCount.' '.($itemCount === 1 ? 'produs pregătit' : 'produse pregătite').' cu grijă pentru tine.' : 'Un loc pentru lucrurile care ți-au rămas la inimă.' ?></p></div>
        <i><?= icon('bag') ?></i>
    </header>

    <?php if (!$items): ?>
        <div class="cart-empty-premium"><div class="cart-empty-art"><span><?= icon('bag') ?></span><i>♡</i><b>✦</b></div><span>COȘUL ESTE GOL</span><h2>Prima alegere te așteaptă.</h2><p>Descoperă obiecte pregătite pentru începuturi frumoase și amintiri care rămân.</p><a class="button" href="/magazin">DESCOPERĂ COLECȚIA <?= icon('arrow') ?></a></div>
    <?php else: ?>
        <div class="cart-layout-premium">
            <section class="cart-products-premium">
                <div class="cart-products-head"><div><span>PRODUSE</span><h2>Alegerile tale</h2></div><strong><?= (int) $itemCount ?> <?= $itemCount === 1 ? 'produs' : 'produse' ?></strong></div>
                <div class="cart-list-premium">
                    <?php foreach ($items as $item): ?>
                        <?php $customizationValues = []; foreach (($item['customization']['values'] ?? []) as $value) $customizationValues[(int) $value['field_id']] = (string) $value['value']; $customizationDialogId = 'personalizare-' . substr(md5($item['key']), 0, 12); ?>
                        <article class="cart-item-premium <?= $item['customization'] ? 'is-personalized' : 'is-standard' ?>">
                            <a class="cart-item-image" href="/produs/<?= e($item['product']['slug']) ?>"><img src="<?= e(optimized_image_url($item['variant']['image_path'] ?? $item['product']['image_path'], 'card')) ?>" alt="<?= e($item['product']['name']) ?>" loading="lazy" decoding="async"><span><?= icon('arrow') ?></span></a>
                            <div class="cart-item-copy"><span><?= e($item['product']['category_name'] ?? 'SmileBaby') ?></span><h2><a href="/produs/<?= e($item['product']['slug']) ?>"><?= e($item['product']['name']) ?></a></h2><?php if ($item['variant']): ?><small><?= e($item['variant']['label'] ?: $item['variant']['sku']) ?></small><?php endif ?><?php if (!empty($item['fields'])): ?><div class="cart-line-kind <?= $item['customization'] ? 'is-personalized' : 'is-standard' ?>"><?= $item['customization'] ? '<b>✦</b> Cu personalizare' : '<b>○</b> Fără personalizare' ?></div><?php endif ?><strong><?= money($item['price']) ?> <small>/ buc.</small></strong>
                                <?php if ($item['customization']): ?><div class="cart-personalization-summary"><b>✦ Personalizat</b><?php foreach($item['customization']['values'] as $value): ?><small><span><?= e($value['label']) ?></span><strong><?= e($value['type'] === 'date' ? date('d.m.Y', strtotime($value['value'])) : $value['value']) ?></strong></small><?php endforeach ?></div><?php endif ?>
                                <?php if (!empty($item['addons'])): ?><section class="cart-addon-group"><header><span>＋</span><div><b>Completează setul cu</b><small><?= count($item['addons']) ?> <?= count($item['addons'])===1?'produs suplimentar':'produse suplimentare' ?><?= $item['quantity'] > 1 ? ' pentru fiecare dintre cele '.(int)$item['quantity'].' bucăți' : '' ?></small></div><strong><?= money($item['addonsTotal']) ?></strong></header><div><?php foreach($item['addons'] as $addon): ?><article><img src="<?=e(optimized_image_url($addon['image_path'], 'card'))?>" alt="" loading="lazy" decoding="async"><p><strong><?=e($addon['name'])?></strong><small><?= (int)$addon['quantity_per_set'] ?> × <?=money($addon['price'])?> / set<?= $item['quantity'] > 1 ? ' · '.(int)$item['quantity'].' seturi' : '' ?></small></p><b><?=money($addon['line_total'])?></b></article><?php endforeach ?></div></section><?php endif ?>
                                <?php if (!empty($item['fields'])): ?><div class="cart-personalization-actions"><button type="button" aria-controls="<?= e($customizationDialogId) ?>" data-cart-customization-open><?= $item['customization'] ? 'Editează personalizarea' : '+ Adaugă personalizare' ?></button><?php if($item['customization']): ?><form action="/cos/personalizare" method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><button type="submit">Șterge personalizarea</button></form><?php endif ?></div><?php endif ?>
                            </div>
                            <form class="cart-quantity-form" action="/cos/actualizeaza" method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><span>CANTITATE</span><div class="cart-quantity"><button type="button" data-cart-quantity="minus" aria-label="Scade cantitatea">−</button><input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="99" aria-label="Cantitate"><button type="button" data-cart-quantity="plus" aria-label="Crește cantitatea">+</button></div></form>
                            <div class="cart-item-total"><span>TOTAL</span><strong><?= money($item['total']) ?></strong><form action="/cos/elimina" method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><button type="submit"><?= icon('close') ?> Elimină</button></form></div>
                        </article>
                        <?php if (!empty($item['fields'])): ?><dialog class="cart-customization-dialog" id="<?= e($customizationDialogId) ?>" data-cart-customization-dialog><form action="/cos/personalizare" method="post"><header><span>PERSONALIZARE PRODUS</span><h2>Detaliile care îl fac unic</h2><p>Completează informațiile exact așa cum vrei să apară pe produs.</p><button type="button" aria-label="Închide" data-cart-customization-close>×</button></header><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($item['key']) ?>"><label class="cart-customization-choice"><input type="checkbox" name="personalization_enabled" value="1" checked data-cart-personalization-toggle><span><i>✦</i><b><strong>Personalizare activă</strong><small><?= (float)$item['product']['customization_price'] > 0 ? '+' . money($item['product']['customization_price']) . ' / bucată' : 'Inclusă gratuit' ?></small></b><em></em></span></label><div class="cart-customization-fields" data-cart-personalization-fields><?php foreach($item['fields'] as $field): ?><label><?= e($field['label']) ?><?= !empty($field['is_required']) ? ' *' : '' ?><input type="<?= $field['field_type'] === 'date' ? 'date' : 'text' ?>" name="customization[<?= (int)$field['id'] ?>]" value="<?= e($customizationValues[(int)$field['id']] ?? '') ?>" placeholder="<?= e($field['placeholder'] ?? '') ?>" maxlength="250" data-personalization-input data-personalization-required="<?= !empty($field['is_required']) ? '1' : '0' ?>"></label><?php endforeach ?></div><footer><button type="button" data-cart-customization-close>Renunță</button><button class="button" type="submit">SALVEAZĂ PERSONALIZAREA</button></footer></form></dialog><?php endif ?>
                    <?php endforeach ?>
                </div>
                <a class="cart-continue-link" href="/magazin"><?= icon('arrow') ?> Continuă cumpărăturile</a>
            </section>

            <aside class="cart-summary-premium">
                <span class="eyebrow">RECAPITULARE</span><h2>Sumarul comenzii</h2>
                <?php if ($freeShippingThreshold > 0): ?><div class="shipping-progress"><div><?= icon('truck') ?><p><?php if ($freeShippingRemaining > 0): ?><strong>Mai ai <?= money($freeShippingRemaining) ?></strong><small>până la livrarea gratuită</small><?php else: ?><strong>Ai livrare gratuită</strong><small>Pragul a fost atins</small><?php endif ?></p></div><span><i style="width:<?= e((string) $freeShippingProgress) ?>%"></i></span></div><?php endif ?>
                <div class="cart-summary-lines"><p><span>Produse (<?= (int) $itemCount ?>)</span><strong><?= money($subtotal) ?></strong></p><p><span>Livrare</span><strong>Calculată la checkout</strong></p></div>
                <div class="cart-summary-total"><span>Subtotal</span><strong><?= money($subtotal) ?></strong></div>
                <p class="cart-summary-note">Costul final, inclusiv livrarea și eventualele taxe ale plății, este afișat înainte de trimiterea comenzii.</p>
                <a class="button" href="/checkout">CONTINUĂ LA CHECKOUT <?= icon('arrow') ?></a>
                <div class="cart-trust"><span><?= icon('card') ?> Plată securizată</span><span><?= icon('return') ?> Retur simplu</span></div>
            </aside>
        </div>
    <?php endif ?>
</section>
