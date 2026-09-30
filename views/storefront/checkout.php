<?php
$errors = App\Core\Session::pullFlash('errors') ?? [];
$account = user() ?? [];
$addresses = $addresses ?? [];
$oldCheckout = App\Core\Session::get('_old', []);
$addressChoice = (string) ($oldCheckout['address_choice'] ?? ($addresses[0]['id'] ?? 'new'));
$selectedAddress = null;
foreach ($addresses as $savedAddress) if ((string) $savedAddress['id'] === $addressChoice) { $selectedAddress = $savedAddress; break; }
$initialFirstName = (string) ($oldCheckout['first_name'] ?? ($selectedAddress['first_name'] ?? ($account['first_name'] ?? '')));
$initialLastName = (string) ($oldCheckout['last_name'] ?? ($selectedAddress['last_name'] ?? ($account['last_name'] ?? '')));
$initialPhone = (string) ($oldCheckout['phone'] ?? ($selectedAddress['phone'] ?? ($account['phone'] ?? '')));
$initialCounty = (string) ($oldCheckout['county'] ?? ($selectedAddress['county'] ?? ''));
$initialCity = (string) ($oldCheckout['city'] ?? ($selectedAddress['city'] ?? ''));
$initialAddress = (string) ($oldCheckout['address'] ?? ($selectedAddress['address'] ?? ''));
$initialPostcode = (string) ($oldCheckout['postcode'] ?? ($selectedAddress['postcode'] ?? ''));
$customerType = old('customer_type', $account['customer_type'] ?? 'individual');
$hasPersonalizedProducts = (bool) array_filter($items, static fn (array $item): bool => !empty($item['customization']));
?>
<header class="page-hero compact shell checkout-hero">
    <span class="eyebrow">ULTIMUL PAS</span>
    <h1>Finalizare comandă</h1>
    <p>Detaliile tale sunt protejate și folosite doar pentru facturare și livrare.</p>
</header>
<form class="checkout-layout shell" action="/checkout" method="post" data-checkout>
    <?= csrf_field() ?>
    <section class="checkout-form">
        <div class="form-section customer-section">
            <span class="step">01</span>
            <div class="section-intro"><span>DATE CLIENT</span><h2>Cum dorești să comanzi?</h2></div>
            <div class="customer-type-switch" data-customer-type-switch>
                <label><input type="radio" name="customer_type" value="individual" <?= $customerType !== 'company' ? 'checked' : '' ?>><span><?= icon('user') ?><b>Persoană fizică</b><small>Cumpăr pentru mine</small></span></label>
                <label><input type="radio" name="customer_type" value="company" <?= $customerType === 'company' ? 'checked' : '' ?>><span><?= icon('bag') ?><b>Persoană juridică</b><small>Cumpăr pentru o firmă</small></span></label>
            </div>
            <div class="form-grid">
                <label>Prenume<input name="first_name" value="<?= e($initialFirstName) ?>" data-account-value="<?= e($account['first_name'] ?? '') ?>" required><?= isset($errors['first_name']) ? '<small class="error">'.e($errors['first_name']).'</small>' : '' ?></label>
                <label>Nume<input name="last_name" value="<?= e($initialLastName) ?>" data-account-value="<?= e($account['last_name'] ?? '') ?>" required><?= isset($errors['last_name']) ? '<small class="error">'.e($errors['last_name']).'</small>' : '' ?></label>
                <label>Email<input type="email" name="email" value="<?= old('email', $account['email'] ?? '') ?>" required><?= isset($errors['email']) ? '<small class="error">'.e($errors['email']).'</small>' : '' ?></label>
                <label>Telefon<input type="tel" name="phone" value="<?= e($initialPhone) ?>" data-account-value="<?= e($account['phone'] ?? '') ?>" required><?= isset($errors['phone']) ? '<small class="error">'.e($errors['phone']).'</small>' : '' ?></label>
            </div>
            <div class="company-fields" data-company-fields <?= $customerType !== 'company' ? 'hidden' : '' ?>>
                <div class="company-fields-heading"><span><?= icon('bag') ?></span><div><strong>Date de facturare firmă</strong><small>Aceste informații vor apărea pe documentele comenzii.</small></div></div>
                <div class="form-grid">
                    <label class="span-2">Denumire firmă<input name="company_name" value="<?= old('company_name', $account['company_name'] ?? '') ?>" data-company-required><?= isset($errors['company_name']) ? '<small class="error">'.e($errors['company_name']).'</small>' : '' ?></label>
                    <label>CUI / CIF<input name="company_vat_id" value="<?= old('company_vat_id', $account['company_vat_id'] ?? '') ?>" placeholder="ex. RO12345678" data-company-required><?= isset($errors['company_vat_id']) ? '<small class="error">'.e($errors['company_vat_id']).'</small>' : '' ?></label>
                    <label>Nr. Registrul Comerțului<input name="company_registration_number" value="<?= old('company_registration_number', $account['company_registration_number'] ?? '') ?>" placeholder="ex. J40/1234/2020" data-company-required><?= isset($errors['company_registration_number']) ? '<small class="error">'.e($errors['company_registration_number']).'</small>' : '' ?></label>
                    <label class="span-2">Sediu social / adresă de facturare<input name="company_address" value="<?= old('company_address', $account['company_address'] ?? '') ?>" placeholder="Stradă, număr, localitate, județ" data-company-required><?= isset($errors['company_address']) ? '<small class="error">'.e($errors['company_address']).'</small>' : '' ?></label>
                </div>
            </div>
        </div>

        <div class="form-section">
            <span class="step">02</span>
            <div class="section-intro"><span>LIVRARE</span><h2>Unde trimitem comanda?</h2></div>
            <?php if ($addresses): ?>
                <div class="checkout-address-book" data-checkout-addresses>
                    <div class="checkout-address-book-heading"><span><?= icon('pin') ?></span><div><strong>Adresele tale salvate</strong><small>Alege una și completăm automat toate datele de livrare.</small></div></div>
                    <div class="checkout-address-options">
                        <?php foreach ($addresses as $savedAddress): ?>
                            <label>
                                <input type="radio" name="address_choice" value="<?= (int) $savedAddress['id'] ?>" <?= $addressChoice === (string) $savedAddress['id'] ? 'checked' : '' ?>
                                    data-first-name="<?= e($savedAddress['first_name']) ?>" data-last-name="<?= e($savedAddress['last_name']) ?>" data-phone="<?= e($savedAddress['phone'] ?? '') ?>"
                                    data-county="<?= e($savedAddress['county']) ?>" data-city="<?= e($savedAddress['city']) ?>" data-address="<?= e($savedAddress['address']) ?>" data-postcode="<?= e($savedAddress['postcode'] ?? '') ?>">
                                <span class="checkout-address-card"><i><?= icon('pin') ?></i><b><?= e($savedAddress['label']) ?><?= $savedAddress['is_default'] ? '<em>Implicită</em>' : '' ?></b><small><?= e($savedAddress['first_name'].' '.$savedAddress['last_name']) ?></small><p><?= e($savedAddress['address']) ?><br><?= e($savedAddress['city'].', '.$savedAddress['county'].($savedAddress['postcode'] ? ' '.$savedAddress['postcode'] : '')) ?></p><u>Alege adresa</u></span>
                            </label>
                        <?php endforeach ?>
                        <label class="checkout-address-new">
                            <input type="radio" name="address_choice" value="new" <?= $addressChoice === 'new' ? 'checked' : '' ?>>
                            <span class="checkout-address-card"><i>＋</i><b>Altă adresă</b><small>Completez manual</small><p>Folosește o adresă nouă doar pentru această comandă.</p><u>Introdu altă adresă</u></span>
                        </label>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="address_choice" value="new">
            <?php endif ?>
            <div class="form-grid">
                <label>Județ<input name="county" value="<?= e($initialCounty) ?>" required><?= isset($errors['county']) ? '<small class="error">'.e($errors['county']).'</small>' : '' ?></label>
                <label>Localitate<input name="city" value="<?= e($initialCity) ?>" required><?= isset($errors['city']) ? '<small class="error">'.e($errors['city']).'</small>' : '' ?></label>
                <label class="span-2">Adresă<input name="address" value="<?= e($initialAddress) ?>" placeholder="Stradă, număr, bloc, apartament" required><?= isset($errors['address']) ? '<small class="error">'.e($errors['address']).'</small>' : '' ?></label>
                <label>Cod poștal<input name="postcode" value="<?= e($initialPostcode) ?>"></label>
            </div>
            <label>Observații<textarea name="notes" rows="4" placeholder="Detalii utile pentru pregătirea sau livrarea comenzii"><?= old('notes') ?></textarea></label>
        </div>

        <div class="form-section">
            <span class="step">03</span>
            <div class="section-intro"><span>PLATĂ</span><h2>Alege metoda de plată</h2></div>
            <?php if (!$paymentMethods): ?>
                <div class="alert">Momentan nu există nicio metodă de plată activă. Contactează-ne pentru ajutor.</div>
            <?php else: ?>
                <div class="payment-options">
                    <?php foreach ($paymentMethods as $i => $method): ?>
                        <label class="payment-option"><input type="radio" name="payment_method" value="<?= e($method['key']) ?>" data-fee-type="<?= e($method['fee_type']) ?>" data-fee-value="<?= e($method['fee_value']) ?>" <?= $i === 0 ? 'checked' : '' ?>><span class="radio"></span><span><?= $method['key'] === 'cash_on_delivery' ? icon('truck') : ($method['key'] === 'online_card' ? icon('card') : icon('gift')) ?><strong><?= e($method['name']) ?></strong><small><?= e($method['key'] === 'online_card' ? 'Card, Apple Pay, Google Pay sau Revolut Pay, procesate securizat prin Stripe.' : $method['description']) ?></small><?php if ((float) $method['fee_value'] > 0): ?><em>Taxă: <?= $method['fee_type'] === 'percentage' ? e($method['fee_value']).'%' : money($method['fee_value']) ?></em><?php endif ?></span></label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </section>

    <aside class="order-summary">
        <span class="eyebrow">RECAPITULARE</span><h2>Comanda ta</h2>
        <?php foreach ($items as $item): ?><div class="summary-product"><img src="<?= e(upload_url($item['product']['image_path'])) ?>" alt=""><span><?= e($item['product']['name']) ?><?php if($item['customization']):?><small>✦ Personalizat</small><?php endif?><small><?= $item['quantity'] ?> × <?= money($item['price']) ?></small><?php if(!empty($item['addons'])):?><span class="checkout-addon-group"><b>＋ Completează setul</b><?php foreach($item['addons'] as $addon):?><small><img src="<?=e(upload_url($addon['image_path']))?>" alt=""><span><?=e($addon['name'])?><em><?= (int)$addon['quantity_per_set'] ?> × <?=money($addon['price'])?> / set<?= $item['quantity'] > 1 ? ' · '.(int)$item['quantity'].' seturi' : '' ?></em></span></small><?php endforeach?></span><?php endif?></span><strong><?= money($item['total']) ?></strong></div><?php endforeach ?>
        <div class="summary-line"><span>Subtotal</span><strong data-subtotal="<?= $subtotal ?>"><?= money($subtotal) ?></strong></div>
        <div class="summary-line"><span>Livrare</span><strong><?= $shipping ? money($shipping) : 'Gratuită' ?></strong></div>
        <div class="summary-line" data-payment-fee-row hidden><span>Taxă plată</span><strong data-payment-fee>0,00 lei</strong></div>
        <div class="summary-total"><span>Total</span><strong data-order-total data-base-total="<?= $subtotal + $shipping ?>"><?= money($subtotal + $shipping) ?></strong></div>
        <?php if ($hasPersonalizedProducts): ?>
            <div class="personalized-order-policy">
                <span aria-hidden="true">✦</span>
                <p><strong>Comanda conține produse personalizate</strong><small>Acestea intră în lucru pe bază de avans și nu pot fi returnate pentru simpla răzgândire. Drepturile pentru produse neconforme rămân valabile.</small><a href="/livrare-si-retur" target="_blank">Vezi politica de livrare și retur →</a></p>
            </div>
        <?php endif ?>
        <button class="button" type="submit" <?= !$paymentMethods ? 'disabled' : '' ?>>PLASEAZĂ COMANDA <?= icon('arrow') ?></button>
        <label class="terms-switch"><input type="checkbox" name="terms" value="1" required><span class="terms-toggle" aria-hidden="true"><i></i></span><span class="terms-copy">Am citit și accept <a href="/termeni-si-conditii" target="_blank">termenii și condițiile</a>.</span></label>
        <p class="secure-note">🔒 Datele și plata ta sunt protejate.</p>
    </aside>
</form>
