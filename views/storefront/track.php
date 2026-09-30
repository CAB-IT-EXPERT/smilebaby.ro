<?php
$orderNumber = $orderNumber ?? '';
$email = $email ?? '';
?>
<section class="track-search-page shell">
    <div class="track-search-ornament track-search-ornament-left" aria-hidden="true"><?= icon('gift') ?></div>
    <div class="track-search-ornament track-search-ornament-right" aria-hidden="true"><?= icon('truck') ?></div>
    <header class="track-search-heading">
        <span class="eyebrow">DE LA NOI, CĂTRE TINE</span>
        <h1>Unde se află<br>comanda ta?</h1>
        <p>Introdu datele folosite la plasarea comenzii, iar noi îți arătăm imediat fiecare pas al poveștii ei.</p>
    </header>

    <form class="track-search-card" action="/urmareste-comanda" method="post">
        <?= csrf_field() ?>
        <div class="track-search-card-head">
            <span><?= icon('pin') ?></span>
            <div><small>URMĂRIRE COMANDĂ</small><strong>Găsește coletul în câteva secunde</strong></div>
        </div>
        <?php if ($order === false): ?>
            <div class="track-search-error" role="alert">
                <span>!</span>
                <div><strong>Nu am găsit această comandă</strong><small>Verifică numărul comenzii și adresa de email, apoi încearcă din nou.</small></div>
            </div>
        <?php endif; ?>
        <label class="track-field">
            <span>Număr comandă</span>
            <div><?= icon('bag') ?><input name="order_number" value="<?= e($orderNumber) ?>" placeholder="SB-2026-000001" autocomplete="off" required></div>
            <small>Îl găsești în emailul de confirmare.</small>
        </label>
        <label class="track-field">
            <span>Adresa de email</span>
            <div><?= icon('mail') ?><input type="email" name="email" value="<?= e($email) ?>" placeholder="numele.tau@email.ro" autocomplete="email" required></div>
            <small>Folosește adresa introdusă la finalizarea comenzii.</small>
        </label>
        <button class="track-search-submit" type="submit"><span>VEZI PARCURSUL COMENZII</span><?= icon('arrow') ?></button>
        <p class="track-search-private"><?= icon('check') ?> Datele tale sunt folosite doar pentru verificarea comenzii.</p>
    </form>
</section>
