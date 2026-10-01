<div class="drawer-head"><h2>Coșul tău</h2><button class="icon-button" type="button" aria-label="Închide" data-cart-close><?=icon('close')?></button></div>
<?php if(!$items):?>
    <div class="drawer-empty"><p>Coșul tău așteaptă prima alegere.</p><a class="button" href="/magazin">DESCOPERĂ COLECȚIA</a></div>
<?php else:?>
    <div class="drawer-items">
        <?php foreach($items as $item):?>
            <article class="<?= $item['customization'] ? 'is-personalized' : 'is-standard' ?>">
                <img src="<?=e(optimized_image_url($item['variant']['image_path']??$item['product']['image_path'], 'card'))?>" alt="" loading="lazy" decoding="async">
                <span>
                    <strong><?=e($item['product']['name'])?></strong>
                    <?php if($item['variant']):?><small><?=e($item['variant']['label']?:$item['variant']['sku'])?></small><?php endif?>
                    <?php if(!empty($item['fields'])):?><small class="cart-drawer-customization <?= $item['customization'] ? 'is-personalized' : 'is-standard' ?>"><?= $item['customization'] ? '✦ Cu personalizare' : '○ Fără personalizare' ?></small><?php endif?>
                    <?php if($item['customization']): foreach(($item['customization']['options'] ?? []) as $option):?><small class="cart-drawer-customization-value"><?=e($option['label'])?>: <b><?= (float)$option['price'] > 0 ? '+'.money($option['price']).' / buc.' : 'Gratuit' ?></b></small><?php endforeach; foreach($item['customization']['values'] as $value):?><small class="cart-drawer-customization-value"><?=e($value['label'])?>: <b><?=e($value['type']==='date'?date('d.m.Y',strtotime($value['value'])):$value['value'])?></b></small><?php endforeach; endif?>
                    <small><?=$item['quantity']?> × <?=money($item['price'])?></small>
                    <?php if(!empty($item['addons'])):?><span class="drawer-addon-group"><b>＋ Completează setul</b><?php foreach($item['addons'] as $addon):?><small><img src="<?=e(optimized_image_url($addon['image_path'], 'card'))?>" alt="" loading="lazy" decoding="async"><span><?=e($addon['name'])?><em><?= (int)$addon['quantity_per_set'] ?> × <?=money($addon['price'])?> / set<?= $item['quantity'] > 1 ? ' · '.(int)$item['quantity'].' seturi' : '' ?></em></span></small><?php endforeach?></span><?php endif?>
                </span>
                <b><?=money($item['total'])?></b>
            </article>
        <?php endforeach?>
    </div>
    <div class="drawer-summary"><span>Subtotal</span><strong><?=money(array_sum(array_column($items,'total')))?></strong></div>
    <a class="button drawer-checkout" href="/checkout">FINALIZEAZĂ COMANDA</a><a class="text-link drawer-cart-link" href="/cos">Vezi coșul complet</a>
<?php endif?>
