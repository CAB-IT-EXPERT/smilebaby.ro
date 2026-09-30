<?php
$phone = trim((string) setting('site_phone', '')) ?: '+40 740 002 848';
$email = trim((string) setting('site_email', 'contact@smilebaby.ro')) ?: 'contact@smilebaby.ro';
$address = trim((string) setting('site_address', 'România')) ?: 'România';
$phoneHref = preg_replace('/[^0-9+]/', '', $phone);
?>
<section class="contact-premium shell">
    <header class="contact-hero">
        <span class="contact-kicker"><i></i>SUNTEM APROAPE<i></i></span>
        <h1>Hai să dăm formă<br>unei povești frumoase.</h1>
        <p>Ai o întrebare, o idee de personalizare sau ai nevoie de ajutor cu o comandă? Scrie-ne, iar noi îți răspundem cu drag.</p>
    </header>
    <div class="contact-stage">
        <aside class="contact-details">
            <span class="contact-detail-eyebrow">CU DRAG, DIN ATELIER</span>
            <h2>Suntem aici pentru tine.</h2>
            <p>Fie că alegi primul trusou sau pregătești un dar cu totul personal, te ajutăm să găsești detaliile potrivite.</p>
            <div class="contact-detail-list">
                <?php if ($phone !== ''): ?><a href="tel:<?= e($phoneHref) ?>" class="contact-detail-item"><span><?= icon('phone') ?></span><div><small>TELEFON</small><strong><?= e($phone) ?></strong></div><?= icon('arrow') ?></a><?php endif ?>
                <a href="mailto:<?= e($email) ?>" class="contact-detail-item"><span><?= icon('mail') ?></span><div><small>EMAIL</small><strong><?= e($email) ?></strong></div><?= icon('arrow') ?></a>
                <div class="contact-detail-item"><span><?= icon('pin') ?></span><div><small>ADRESĂ</small><strong><?= e($address) ?></strong></div></div>
            </div>
            <div class="contact-schedule"><?= icon('clock') ?><span><small>PROGRAM DE RĂSPUNS</small><strong>Luni–Vineri · 09:00–18:00</strong></span></div>
            <p class="contact-signature">Fiecare mesaj este citit de un om, nu de un robot. ♡</p>
        </aside>
        <form class="contact-form-premium" action="/contact" method="post">
            <?= csrf_field() ?>
            <div class="contact-form-head"><span>SCRIE-NE</span><h2>Cu ce te putem ajuta?</h2><p>Completează formularul și revenim către tine cât mai curând.</p></div>
            <div class="contact-form-grid">
                <label><span>Numele tău</span><input name="name" autocomplete="name" placeholder="Cum te numești?" required></label>
                <label><span>Adresa de email</span><input type="email" name="email" autocomplete="email" placeholder="nume@email.ro" required></label>
                <label><span>Telefon <em>opțional</em></span><input name="phone" autocomplete="tel" placeholder="07xx xxx xxx"></label>
                <label><span>Subiect</span><input name="subject" placeholder="Despre ce vrei să vorbim?"></label>
                <label class="contact-message-field"><span>Mesajul tău</span><textarea name="message" rows="6" placeholder="Povestește-ne cum te putem ajuta…" required></textarea><small>Cu cât avem mai multe detalii, cu atât te putem ajuta mai repede.</small></label>
            </div>
            <button class="button contact-submit" type="submit">TRIMITE MESAJUL <?= icon('arrow') ?></button>
            <small class="contact-privacy">Prin trimiterea mesajului ești de acord să folosim datele doar pentru a-ți răspunde. Vezi <a href="/confidentialitate">politica de confidențialitate</a>.</small>
        </form>
    </div>
    <div class="contact-shortcuts" aria-label="Linkuri utile">
        <a href="/urmareste-comanda"><span>01</span><div><small>AI DEJA O COMANDĂ?</small><strong>Urmărește comanda</strong></div><?= icon('arrow') ?></a>
        <a href="/livrare-si-retur"><span>02</span><div><small>INFORMAȚII UTILE</small><strong>Livrare și retur</strong></div><?= icon('arrow') ?></a>
        <a href="/magazin"><span>03</span><div><small>CAUȚI INSPIRAȚIE?</small><strong>Descoperă colecțiile</strong></div><?= icon('arrow') ?></a>
    </div>
</section>
