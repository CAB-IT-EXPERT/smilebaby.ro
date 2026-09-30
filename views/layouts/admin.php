<?php
use App\Core\Database;
use App\Core\Session;
$admin = user();
$adminName = trim((string) (($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))) ?: 'Admin SmileBaby';
$adminNameParts = preg_split('/\s+/u', $adminName, -1, PREG_SPLIT_NO_EMPTY) ?: ['A'];
$adminInitials = mb_strtoupper(mb_substr($adminNameParts[0], 0, 1) . (count($adminNameParts) > 1 ? mb_substr($adminNameParts[array_key_last($adminNameParts)], 0, 1) : ''));
$adminNotifications = [];
$adminNotificationCount = 0;
if (Database::available()) {
    try {
        $db = Database::connection();
        $adminNotificationCount = (int) $db->query('SELECT COUNT(*) FROM orders WHERE status IN ("received","confirmed","processing","prepared","shipped")')->fetchColumn();
        $adminNotifications = $db->query('SELECT id,order_number,first_name,last_name,total,status,created_at FROM orders WHERE status IN ("received","confirmed","processing","prepared","shipped") ORDER BY created_at DESC LIMIT 4')->fetchAll();
    } catch (Throwable) {
        $adminNotifications = [];
        $adminNotificationCount = 0;
    }
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$adminSection = $title ?? match (true) {
    str_starts_with($path, '/admin/produse') => 'Produse',
    str_starts_with($path, '/admin/categorii') => 'Categorii & Colecții',
    str_starts_with($path, '/admin/comenzi') => 'Comenzi',
    str_starts_with($path, '/admin/clienti') => 'Clienți',
    str_starts_with($path, '/admin/recenzii') => 'Recenzii',
    str_starts_with($path, '/admin/articole') => 'Atelier',
    str_starts_with($path, '/admin/media') => 'Media',
    str_starts_with($path, '/admin/newsletter') => 'Newsletter',
    str_starts_with($path, '/admin/setari/email') => 'Configurare email',
    str_starts_with($path, '/admin/setari') => 'Setările magazinului',
    default => 'Prezentare generală',
};
$active = static function (string $prefix, bool $exact = false) use ($path): string {
    return ($exact ? $path === $prefix : str_starts_with($path, $prefix)) ? ' class="active"' : '';
};
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($adminSection) ?> · SmileBaby</title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon-owl.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/favicon-owl.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-reference.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-enhancements.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-order-page.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-orders-page.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-customers-page.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-reviews-page.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-product-editor.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-customization.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin-ui-fixes.css') ?>">
</head>
<body class="admin-body" data-announcement-enabled="<?= filter_var(setting('announcement_enabled', '1'), FILTER_VALIDATE_BOOL) ? '1' : '0' ?>" data-return-shipping-cost="<?= e(setting('return_shipping_cost', '20')) ?>">
<script>try{if(localStorage.getItem('smilebaby-admin-sidebar')==='collapsed')document.body.classList.add('admin-sidebar-collapsed')}catch(_){}</script>
<aside class="admin-sidebar" data-admin-sidebar>
    <button class="admin-sidebar-close" type="button" aria-label="Închide meniul" data-admin-sidebar-close>×</button>
    <a class="admin-logo" href="/admin">
        <img class="admin-logo-full" src="<?= asset('images/logo-smilebaby-transparent.png') ?>" alt="SmileBaby">
        <img class="admin-logo-mark" src="<?= asset('images/favicon-owl.png') ?>" alt="" aria-hidden="true">
        <span>Administrare magazin</span>
    </a>
    <button class="admin-sidebar-collapse" type="button" aria-label="Restrânge meniul" aria-pressed="false" data-admin-sidebar-collapse><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4" width="17" height="16" rx="3"/><path d="M9 4v16M15 8l-4 4 4 4"/></svg></button>
    <nav>
        <small>ACTIVITATE ZILNICĂ</small>
        <a href="/admin"<?= $active('/admin', true) ?>><?= icon('dashboard') ?><span><strong>Prezentare generală</strong><em>Imaginea de ansamblu</em></span></a>
        <a href="/admin/comenzi"<?= $active('/admin/comenzi') ?>><?= icon('card') ?><span><strong>Comenzi</strong><em>Pregătire și status</em></span></a>

        <small>PRODUSE ȘI CLIENȚI</small>
        <a href="/admin/produse"<?= $active('/admin/produse') ?>><?= icon('gift') ?><span><strong>Produse</strong><em>Catalog, preț și stoc</em></span></a>
        <a href="/admin/categorii"<?= $active('/admin/categorii') ?>><?= icon('bag') ?><span><strong>Categorii & Colecții</strong><em>Imagini și ordine</em></span></a>
        <a href="/admin/clienti"<?= $active('/admin/clienti') ?>><?= icon('user') ?><span><strong>Clienți</strong><em>Conturi și istoric</em></span></a>
        <a href="/admin/recenzii"<?= $active('/admin/recenzii') ?>><?= icon('heart') ?><span><strong>Recenzii</strong><em>Opinii și răspunsuri</em></span></a>

        <small>CONȚINUT</small>
        <a href="/admin/articole"<?= $active('/admin/articole') ?>><?= icon('leaf') ?><span><strong>Atelier</strong><em>Articole și povești</em></span></a>
        <a href="/admin/newsletter"<?= $active('/admin/newsletter') ?>><?= icon('card') ?><span><strong>Newsletter</strong><em>Abonați și export</em></span></a>

        <small>SETĂRI MAGAZIN</small>
        <a href="/admin/setari#seo"<?= $active('/admin/setari', true) ?>><?= icon('search') ?><span><strong>SEO</strong><em>Vizibilitate în Google</em></span></a>
        <a href="/admin/setari/email"<?= $active('/admin/setari/email') ?>><?= icon('headset') ?><span><strong>Email</strong><em>Expeditor și mesaje</em></span></a>
        <a href="/admin/setari#plati"><?= icon('card') ?><span><strong>Plăți</strong><em>Card și ramburs</em></span></a>
        <a href="/admin/setari#livrare"><?= icon('truck') ?><span><strong>Livrare</strong><em>Costuri și termene</em></span></a>
    </nav>
    <div class="admin-user">
        <div class="admin-user-identity">
            <i aria-hidden="true"><?= e($adminInitials) ?></i>
            <span><strong><?= e($adminName) ?></strong><small><?= e($admin['email'] ?? '') ?></small></span>
        </div>
        <form action="/logout" method="post"><?= csrf_field() ?><button type="submit"><?= icon('return') ?><span>Ieșire din cont</span></button></form>
    </div>
</aside>
<button class="admin-sidebar-backdrop" type="button" aria-label="Închide meniul" data-admin-sidebar-backdrop hidden></button>

<div class="admin-shell">
    <header class="admin-topbar">
        <button class="icon-button admin-menu-toggle" type="button" aria-label="Deschide meniul"><?= icon('menu') ?></button>
        <div class="admin-crumb"><small>SMILEBABY / ADMIN / <?= e(mb_strtoupper($adminSection)) ?></small><strong><?= e($adminSection) ?></strong></div>
        <nav>
            <a class="topbar-pill topbar-primary" href="/admin/produse/creare">＋ <span>Produs nou</span></a>
            <div class="admin-topbar-menu admin-notifications-menu" data-admin-topbar-menu>
                <button class="topbar-trigger" type="button" aria-label="Notificări" aria-expanded="false" data-admin-topbar-toggle><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><span>Notificări</span><?php if ($adminNotificationCount): ?><b class="topbar-count"><?= $adminNotificationCount > 99 ? '99+' : $adminNotificationCount ?></b><?php endif ?></button>
                <div class="admin-topbar-popover notifications-popover" data-admin-topbar-popover hidden>
                    <div class="topbar-popover-head"><span><small>ACTIVITATE</small><strong>Comenzi de rezolvat</strong></span><b><?= $adminNotificationCount ?></b></div>
                    <div class="topbar-notification-list">
                        <?php foreach ($adminNotifications as $notification): ?><a href="/admin/comenzi/<?= (int) $notification['id'] ?>"><span><strong><?= e($notification['order_number']) ?></strong><small><?= e(trim($notification['first_name'] . ' ' . $notification['last_name'])) ?> · <?= e($notification['status']) ?></small></span><b><?= money($notification['total']) ?></b></a><?php endforeach ?>
                        <?php if (!$adminNotifications): ?><p>Nu există comenzi care necesită atenție.</p><?php endif ?>
                    </div>
                    <a class="topbar-popover-all" href="/admin/comenzi">Vezi toate comenzile <span>→</span></a>
                </div>
            </div>
            <a class="topbar-pill topbar-store" href="/" target="_blank" rel="noopener">↗ <span>Vezi magazinul</span></a>
            <div class="admin-topbar-menu admin-account-menu" data-admin-topbar-menu>
                <button class="topbar-trigger" type="button" aria-expanded="false" data-admin-topbar-toggle><?= icon('user') ?><span><?= e($adminName) ?></span></button>
                <div class="admin-topbar-popover account-popover" data-admin-topbar-popover hidden>
                    <div class="topbar-account-identity"><i><?= e(mb_strtoupper(mb_substr($adminName, 0, 1))) ?></i><span><strong><?= e($adminName) ?></strong><small><?= e($admin['email'] ?? '') ?></small></span></div>
                    <a href="/admin/setari">Setările magazinului <span>→</span></a>
                    <a href="/admin/setari/email">Configurare email <span>→</span></a>
                    <form action="/logout" method="post"><?= csrf_field() ?><button type="submit">Ieșire din cont</button></form>
                </div>
            </div>
            <div class="admin-topbar-menu admin-help-menu" data-admin-topbar-menu>
                <button class="topbar-trigger help-pill" type="button" aria-label="Ajutor" aria-expanded="false" data-admin-topbar-toggle>?</button>
                <div class="admin-topbar-popover help-popover" data-admin-topbar-popover hidden>
                    <small>SCURTĂTURI UTILE</small><strong>Cu ce vrei să continui?</strong>
                    <a href="/admin/comenzi">Gestionează comenzile <span>→</span></a>
                    <a href="/admin/produse">Editează produsele <span>→</span></a>
                </div>
            </div>
        </nav>
    </header>
    <?php if ($m = Session::pullFlash('success')): ?><div class="admin-alert success"><?= e($m) ?></div><?php endif ?>
    <?php if ($m = Session::pullFlash('error')): ?><div class="admin-alert error"><?= e($m) ?></div><?php endif ?>
    <main class="admin-main"><?= $content ?></main>
</div>
<dialog class="admin-confirm-dialog" data-admin-delete-modal aria-labelledby="admin-delete-title">
    <button class="admin-confirm-close" type="button" aria-label="Închide" data-admin-delete-cancel>×</button>
    <div class="admin-confirm-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 4h6l1 3H8l1-3Z"/><path d="m6 7 1 13h10l1-13M10 11v5M14 11v5"/></svg></div>
    <span class="eyebrow">CONFIRMARE NECESARĂ</span>
    <h2 id="admin-delete-title">Sigur vrei să ștergi?</h2>
    <p>Urmează să ștergi <strong data-admin-delete-name></strong>. Această acțiune este definitivă și nu poate fi anulată.</p>
    <div class="admin-confirm-actions"><button type="button" data-admin-delete-cancel>Renunță</button><form method="post" data-admin-delete-form><?= csrf_field() ?><button type="submit">Da, șterge definitiv</button></form></div>
</dialog>
<script>window.SmileBaby={csrf:<?= json_encode(App\Core\Csrf::token()) ?>};</script>
<script src="<?= asset('js/admin.js') ?>" defer></script>
<script src="<?= asset('js/admin-order-page.js') ?>" defer></script>
<script src="<?= asset('js/admin-orders.js') ?>" defer></script>
<script src="<?= asset('js/admin-customers.js') ?>" defer></script>
<script src="<?= asset('js/admin-reviews.js') ?>" defer></script>
<script src="<?= asset('js/admin-dashboard.js') ?>" defer></script>
<script src="<?= asset('js/admin-product-editor.js') ?>" defer></script>
</body>
</html>
