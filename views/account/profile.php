<?php
$errors = App\Core\Session::pullFlash('errors') ?? [];
$account = user() ?? [];
$customerType = old('customer_type', $account['customer_type'] ?? 'individual');
?>
<section class="account-profile-page shell">
    <header class="account-profile-hero"><div><span>CONTUL MEU</span><h1>Datele mele</h1><p>Gestionează datele personale și informațiile de facturare folosite la comenzi.</p></div><i><?= icon('user') ?></i></header>
    <div class="account-profile-layout account-layout">
        <?php require BASE_PATH.'/views/components/account-nav.php'; ?>
        <section class="account-content">
            <form class="profile-premium-form" action="/cont/profil" method="post" data-customer-profile>
                <?= csrf_field() ?>
                <div class="profile-form-head"><div><span>TIP CLIENT</span><h2>Cum plasezi comenzile?</h2></div><small>Alegerea va fi preluată automat în checkout.</small></div>
                <div class="customer-type-switch" data-customer-type-switch>
                    <label><input type="radio" name="customer_type" value="individual" <?= $customerType !== 'company' ? 'checked' : '' ?>><span><?= icon('user') ?><b>Persoană fizică</b><small>Date personale</small></span></label>
                    <label><input type="radio" name="customer_type" value="company" <?= $customerType === 'company' ? 'checked' : '' ?>><span><?= icon('bag') ?><b>Persoană juridică</b><small>Date de firmă</small></span></label>
                </div>
                <div class="profile-form-block"><div class="profile-block-title"><span>01</span><div><h3>Date de contact</h3><p>Le folosim pentru confirmări și livrare.</p></div></div><div class="form-grid"><label>Prenume<input name="first_name" value="<?= old('first_name', $account['first_name'] ?? '') ?>" required><?= isset($errors['first_name']) ? '<small class="error">'.e($errors['first_name']).'</small>' : '' ?></label><label>Nume<input name="last_name" value="<?= old('last_name', $account['last_name'] ?? '') ?>" required><?= isset($errors['last_name']) ? '<small class="error">'.e($errors['last_name']).'</small>' : '' ?></label><label>Email<input value="<?= e($account['email'] ?? '') ?>" disabled><small>Adresa de email nu poate fi schimbată de aici.</small></label><label>Telefon<input name="phone" value="<?= old('phone', $account['phone'] ?? '') ?>"></label></div></div>
                <div class="profile-form-block company-fields" data-company-fields <?= $customerType !== 'company' ? 'hidden' : '' ?>><div class="profile-block-title"><span>02</span><div><h3>Date de facturare</h3><p>Vor fi salvate în cont și precompletate la următoarea comandă.</p></div></div><div class="form-grid"><label class="span-2">Denumire firmă<input name="company_name" value="<?= old('company_name', $account['company_name'] ?? '') ?>" data-company-required><?= isset($errors['company_name']) ? '<small class="error">'.e($errors['company_name']).'</small>' : '' ?></label><label>CUI / CIF<input name="company_vat_id" value="<?= old('company_vat_id', $account['company_vat_id'] ?? '') ?>" placeholder="ex. RO12345678" data-company-required><?= isset($errors['company_vat_id']) ? '<small class="error">'.e($errors['company_vat_id']).'</small>' : '' ?></label><label>Nr. Registrul Comerțului<input name="company_registration_number" value="<?= old('company_registration_number', $account['company_registration_number'] ?? '') ?>" placeholder="ex. J40/1234/2020" data-company-required><?= isset($errors['company_registration_number']) ? '<small class="error">'.e($errors['company_registration_number']).'</small>' : '' ?></label><label class="span-2">Sediu social / adresă de facturare<input name="company_address" value="<?= old('company_address', $account['company_address'] ?? '') ?>" data-company-required><?= isset($errors['company_address']) ? '<small class="error">'.e($errors['company_address']).'</small>' : '' ?></label></div></div>
                <div class="profile-form-actions"><button class="button" type="submit">SALVEAZĂ DATELE <?= icon('arrow') ?></button><span><?= icon('check') ?> Datele sunt păstrate în siguranță.</span></div>
            </form>
            <form class="danger-zone profile-danger-zone" action="/cont/sterge" method="post" onsubmit="return confirm('Sigur dorești dezactivarea contului?')"><?= csrf_field() ?><div><h3>Ștergerea contului</h3><p>Datele de profil vor fi anonimizate. Comenzile rămân păstrate conform obligațiilor legale.</p></div><button class="button button-ghost" type="submit">ȘTERGE CONTUL</button></form>
        </section>
    </div>
</section>
