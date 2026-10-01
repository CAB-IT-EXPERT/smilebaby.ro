<?php
$stripeSettings = [];
foreach ($methods as $candidate) if ($candidate['key'] === 'online_card') $stripeSettings = $candidate['settings'] ?? [];
$stripeConfigured = str_starts_with((string) config('payments.stripe.secret_key', ''), 'sk_');
?>
<div class="admin-page-head"><div><span class="eyebrow">CONFIGURARE</span><h1>Setări</h1><p>Livrare, plăți, email și vizibilitate în motoarele de căutare.</p></div></div>
<nav class="settings-tabs"><button class="active" data-settings-tab="livrare">Livrare</button><button data-settings-tab="plati">Plăți</button><button data-settings-tab="email">Email</button><button data-settings-tab="seo">SEO</button></nav>

<form class="settings-panel active" id="livrare" action="/admin/setari" method="post">
    <?= csrf_field() ?>
    <section class="admin-card settings-focused-card">
        <span class="eyebrow">EXPEDIERE</span><h2>Livrare</h2><p>Costul salvat aici este calculat la checkout și transmis identic către Stripe.</p>
        <input type="hidden" name="shipping_enabled" value="0">
        <label class="switch-row"><span><strong>Livrare activă</strong><small>Permite calcularea automată a costului.</small></span><input type="checkbox" name="shipping_enabled" value="1" <?=($settings['shipping_enabled']??'1')?'checked':''?> role="switch"><i></i></label>
        <div class="admin-form-grid"><label>Cost standard<input type="number" step="0.01" min="0" name="standard_shipping_cost" value="<?=e($settings['standard_shipping_cost']??20)?>"></label><label>Livrare gratuită peste<input type="number" step="0.01" min="0" name="free_shipping_threshold" value="<?=e($settings['free_shipping_threshold']??300)?>"></label><label>Estimare livrare<input name="estimated_delivery_text" value="<?=e($settings['estimated_delivery_text']??'2–3 zile lucrătoare')?>"></label></div>
        <button class="admin-button">SALVEAZĂ LIVRAREA</button>
    </section>
</form>

<section class="settings-panel" id="plati">
    <div class="stripe-connection-card <?= $stripeConfigured ? 'is-ready' : 'has-error' ?>">
        <div class="stripe-connection-mark"><?= icon('card') ?></div><div><span>STRIPE CHECKOUT</span><h2><?= $stripeConfigured ? 'Conexiunea de test este pregătită' : 'Stripe necesită configurare' ?></h2><p>Card bancar, Apple Pay, Google Pay și Revolut Pay. Sumele sunt verificate la nivel de ban înainte de inițierea plății.</p></div><strong><?= $stripeConfigured ? 'CONECTAT' : 'NECONECTAT' ?></strong>
    </div>
    <div class="payment-admin-grid compact-payment-grid">
        <?php foreach ($methods as $method): ?>
            <form class="admin-card payment-admin-card compact" action="/admin/setari/plati/<?=e($method['key'])?>" method="post">
                <?=csrf_field()?>
                <div class="payment-card-head"><div class="payment-icon"><?= $method['key']==='cash_on_delivery'?icon('truck'):icon('card') ?></div><div><h2><?=e($method['name'])?></h2><span class="payment-health"><?= $method['key']==='online_card'?($stripeConfigured?'Stripe configurat':'Lipsește cheia Stripe'):'Configurat' ?></span></div><label class="switch-row compact"><input type="checkbox" name="enabled" value="1" <?=$method['enabled']?'checked':''?> role="switch"><i></i><span class="sr-only">Activ</span></label></div>
                <label>Nume afișat<input name="name" value="<?=e($method['name'])?>"></label>
                <label>Descriere<textarea name="description" rows="2"><?=e($method['description'])?></textarea></label>
                <div class="admin-form-grid"><label>Taxă<select name="fee_type"><option value="none">Fără taxă</option><option value="fixed" <?=$method['fee_type']==='fixed'?'selected':''?>>Fixă</option><option value="percentage" <?=$method['fee_type']==='percentage'?'selected':''?>>Procent</option></select></label><label>Valoare<input type="number" step="0.01" min="0" name="fee_value" value="<?=e($method['fee_value'])?>"></label><label>Comandă minimă<input type="number" step="0.01" name="minimum_order" value="<?=e($method['minimum_order'])?>"></label><label>Comandă maximă<input type="number" step="0.01" name="maximum_order" value="<?=e($method['maximum_order'])?>"></label><input type="hidden" name="sort_order" value="<?=e($method['sort_order'])?>"></div>
                <button class="admin-button wide">SALVEAZĂ METODA</button>
            </form>
        <?php endforeach ?>
    </div>
</section>

<section class="settings-panel" id="email"><div class="admin-card settings-focused-card"><span class="eyebrow">DATAHOST SMTP</span><h2>Emailuri tranzacționale</h2><p><strong>site@smilebaby.ro</strong> trimite notificările interne către magazin, iar <strong>contact@smilebaby.ro</strong> trimite confirmările și statusurile către client.</p><dl class="settings-status"><dt>Server</dt><dd><?=e(config('mail.host')?:'Neconfigurat')?></dd><dt>Port</dt><dd><?=e(config('mail.port'))?></dd><dt>Criptare</dt><dd><?=e(strtoupper((string)config('mail.encryption')))?></dd><dt>Notificări magazin</dt><dd><?=e(config('mail.order_recipient'))?></dd></dl><a class="admin-button" href="/admin/setari/email">DESCHIDE SETĂRILE EMAIL</a></div></section>

<form class="settings-panel" id="seo" action="/admin/setari" method="post"><?=csrf_field()?><section class="admin-card settings-focused-card"><span class="eyebrow">CĂUTARE</span><h2>SEO general</h2><label>Titlu implicit<input name="seo_title" value="<?=e($settings['seo_title']??'')?>"></label><label>Descriere implicită<textarea name="seo_description" rows="4"><?=e($settings['seo_description']??'')?></textarea></label><p>Fișiere de descoperire: <a href="/sitemap.xml" target="_blank">Sitemap</a> · <a href="/robots.txt" target="_blank">Robots</a> · <a href="/llms.txt" target="_blank">LLMs</a></p><small>Titlurile și descrierile produselor se gestionează individual în pasul „Publicare &amp; Google”. Dacă sunt lăsate necompletate, se generează automat la salvarea produsului.</small><button class="admin-button">SALVEAZĂ SEO</button></section></form>
