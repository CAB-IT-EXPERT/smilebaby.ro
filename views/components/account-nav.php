<?php
$accountPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/cont', PHP_URL_PATH) ?: '/cont');
$accountName = trim((string) (user()['first_name'] ?? '')) ?: 'Contul tău';
$accountInitial = mb_strtoupper(mb_substr($accountName, 0, 1));
$accountLinks = [
    ['/cont', 'Prezentare', 'user'],
    ['/cont/comenzi', 'Comenzi', 'bag'],
    ['/cont/adrese', 'Adrese', 'pin'],
    ['/cont/profil', 'Datele mele', 'user'],
    ['/favorite', 'Favorite', 'heart'],
];
?>
<nav class="account-nav account-nav-premium" aria-label="Navigare cont" data-account-nav>
    <div class="account-nav-profile">
        <span><?= e($accountInitial) ?></span>
        <div><small>CONTUL TĂU</small><strong><?= e($accountName) ?></strong></div>
    </div>
    <div class="account-nav-links">
        <?php foreach ($accountLinks as [$href, $label, $iconName]): ?>
            <?php $active = $href === '/cont' ? $accountPath === '/cont' : ($accountPath === $href || str_starts_with($accountPath, $href . '/')); ?>
            <a href="<?= e($href) ?>" class="<?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <span><?= icon($iconName) ?></span><strong><?= e($label) ?></strong><?= icon('chevron') ?>
            </a>
        <?php endforeach; ?>
    </div>
    <form action="/logout" method="post"><?= csrf_field() ?><button type="submit"><span><?= icon('return') ?></span><strong>Ieșire din cont</strong></button></form>
</nav>
