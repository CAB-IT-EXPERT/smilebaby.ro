<?php
use App\Core\Session;
use App\Core\Database;
use App\Services\CartService;
$meta = $meta ?? [];
$title = $meta['title'] ?? 'SmileBaby';
$description = $meta['description'] ?? 'Produse delicate pentru începuturi frumoase.';
$cartCount = (new CartService())->count();
$wishlistCount = count(Session::get('wishlist', []));
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isShopPath = in_array($currentPath, ['/magazin', '/colectii'], true) || str_starts_with($currentPath, '/categorie/') || str_starts_with($currentPath, '/produs/');
$hasPublishedPosts = Database::available() && (bool) Database::connection()->query('SELECT EXISTS(SELECT 1 FROM posts WHERE status="published" LIMIT 1)')->fetchColumn();
$announcementEnabled = filter_var(setting('announcement_enabled', '1'), FILTER_VALIDATE_BOOL);
$shippingEnabled = filter_var(setting('shipping_enabled', '1'), FILTER_VALIDATE_BOOL);
$freeShippingThreshold = max(0, (float) setting('free_shipping_threshold', 0));
$estimatedDelivery = trim((string) setting('estimated_delivery_text', '2–3 zile lucrătoare')) ?: '2–3 zile lucrătoare';
$showAnnouncement = $announcementEnabled && $shippingEnabled && $freeShippingThreshold > 0;
$announcementShipping = 'Livrare gratuită la comenzi de peste ' . money($freeShippingThreshold);
$siteName = trim((string) setting('site_name', 'SmileBaby')) ?: 'SmileBaby';
$sitePhone = trim((string) setting('site_phone', '')) ?: '+40 740 002 848';
$sitePhoneHref = preg_replace('/[^0-9+]/', '', $sitePhone);
$whatsappNumber = preg_replace('/\D+/', '', $sitePhone);
if (str_starts_with($whatsappNumber, '0')) $whatsappNumber = '40' . substr($whatsappNumber, 1);
$socialLinks = [
    'facebook' => trim((string) setting('facebook_url', '')) ?: 'https://www.facebook.com/SmileBabyPovesteaBotezului',
    'instagram' => trim((string) setting('instagram_url', '')) ?: 'https://www.instagram.com/smilebaby.ro/',
    'tiktok' => trim((string) setting('tiktok_url', '')) ?: 'https://www.tiktok.com/@smilebaby.ro',
];
$appUrl = rtrim((string) config('app.url'), '/');
$pageUrl = (string) ($meta['canonical'] ?? ($appUrl . $currentPath));
if (!preg_match('#^https?://#i', $pageUrl)) $pageUrl = $appUrl . '/' . ltrim($pageUrl, '/');
$logoImage = (string) setting('logo', image_asset('images/logo-smilebaby.png'));
if (!preg_match('#^https?://#i', $logoImage)) $logoImage = $appUrl . '/' . ltrim($logoImage, '/');
$socialImage = (string) ($meta['image'] ?? $logoImage);
if (!preg_match('#^https?://#i', $socialImage)) $socialImage = $appUrl . '/' . ltrim($socialImage, '/');
$returnPolicy = [
    '@type' => 'MerchantReturnPolicy',
    'applicableCountry' => 'RO',
    'returnPolicyCountry' => 'RO',
    'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
    'merchantReturnDays' => 14,
    'returnMethod' => 'https://schema.org/ReturnByMail',
    'returnFees' => 'https://schema.org/ReturnShippingFees',
    'returnShippingFeesAmount' => ['@type' => 'MonetaryAmount', 'value' => max(0, (float) setting('return_shipping_cost', 20)), 'currency' => 'RON'],
];
$organizationSchema = [
    '@context' => 'https://schema.org', '@type' => 'OnlineStore', '@id' => $appUrl . '/#organization', 'name' => $siteName,
    'url' => $appUrl . '/', 'logo' => ['@type' => 'ImageObject', 'url' => $logoImage], 'image' => $logoImage,
    'email' => setting('site_email', 'contact@smilebaby.ro'), 'telephone' => $sitePhone,
    'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => $sitePhone, 'email' => setting('site_email', 'contact@smilebaby.ro'), 'availableLanguage' => ['ro']],
    'areaServed' => ['@type' => 'Country', 'name' => 'România'], 'sameAs' => array_values(array_filter($socialLinks)),
    'hasMerchantReturnPolicy' => $returnPolicy,
];
$websiteSchema = [
    '@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => $appUrl . '/#website',
    'url' => $appUrl . '/', 'name' => $siteName, 'inLanguage' => 'ro-RO',
    'publisher' => ['@id' => $appUrl . '/#organization'],
    'potentialAction' => ['@type' => 'SearchAction', 'target' => $appUrl . '/magazin?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
];
$structuredData = array_merge([$organizationSchema, $websiteSchema], (array) ($meta['schemas'] ?? []));
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
    <meta name="theme-color" content="#fcfaf7">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($meta['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1') ?>">
    <meta name="author" content="SmileBaby">
    <link rel="canonical" href="<?= e($pageUrl) ?>">
    <link rel="alternate" hreflang="ro-RO" href="<?= e($pageUrl) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= e($pageUrl) ?>">
    <meta name="google-site-verification" content="<?= e(trim((string) setting('google_site_verification', '')) ?: 'aPxwrBNLU-Gga2vs-aFL2dyYByHeoW3axzyxLRaiN7g') ?>">
    <meta property="og:locale" content="ro_RO">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
    <meta property="og:url" content="<?= e($pageUrl) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:image" content="<?= e($socialImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($socialImage) ?>">
    <?php if (isset($meta['price'])): ?><meta property="product:price:amount" content="<?= e(number_format((float) $meta['price'], 2, '.', '')) ?>"><meta property="product:price:currency" content="RON"><?php endif ?>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon-owl.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/favicon-owl.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <?php foreach ($structuredData as $schema): ?><script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endforeach ?>
</head>
<body>
<a class="skip-link" href="#continut">Sari la conținut</a>
<?php if ($showAnnouncement): ?><div class="announcement"><?= e($announcementShipping) ?> <span>•</span> Retur simplu în 14 zile</div><?php endif ?>
<header class="site-header" data-header>
    <div class="header-inner shell">
        <button class="icon-button mobile-menu-button" type="button" aria-label="Deschide meniul" aria-controls="mobile-menu" aria-expanded="false" data-menu-toggle><?= icon('menu') ?></button>
        <nav class="desktop-nav" aria-label="Navigare principală">
            <a class="<?= $currentPath === '/' ? 'nav-pill' : '' ?>" href="/"<?= $currentPath === '/' ? ' aria-current="page"' : '' ?>><span class="nav-link-icon"><?= icon('home') ?></span><span>Acasă</span></a>
            <a class="<?= $currentPath === '/despre-noi' ? 'nav-pill' : '' ?>" href="/despre-noi"<?= $currentPath === '/despre-noi' ? ' aria-current="page"' : '' ?>><span class="nav-link-icon"><?= icon('heart') ?></span><span>Despre noi</span></a>
            <a class="<?= $isShopPath ? 'nav-pill' : '' ?>" href="/magazin"<?= $isShopPath ? ' aria-current="page"' : '' ?>><span class="nav-link-icon"><?= icon('gift') ?></span><span>Magazin</span></a>
            <?php if ($hasPublishedPosts): ?><a class="<?= str_starts_with($currentPath, '/blog') ? 'nav-pill' : '' ?>" href="/blog"<?= str_starts_with($currentPath, '/blog') ? ' aria-current="page"' : '' ?>><span class="nav-link-icon"><?= icon('leaf') ?></span><span>Blog</span></a><?php endif ?>
        </nav>
        <a class="brand" href="/" aria-label="SmileBaby — pagina principală"><img src="<?= e(setting('logo', asset('images/logo-smilebaby.png'))) ?>" alt="SmileBaby"></a>
        <nav class="header-actions" aria-label="Acțiuni cont">
            <button class="icon-button" type="button" aria-label="Caută" data-search-open><?= icon('search') ?></button>
            <a class="icon-button account-link" href="<?= user() ? '/cont' : '/autentificare' ?>" aria-label="Contul meu"><?= icon('user') ?></a>
            <a class="icon-button favorite-link<?= $wishlistCount ? ' has-items' : '' ?>" href="/favorite" aria-label="Favorite, <?= $wishlistCount ?> produse"><?= icon('heart') ?><span class="count-badge" data-wishlist-count<?= $wishlistCount ? '' : ' hidden' ?>><?= $wishlistCount ?></span></a>
            <a class="icon-button" href="/cos" aria-label="Coș, <?= $cartCount ?> produse" data-cart-open><?= icon('bag') ?><span class="count-badge" data-cart-count><?= $cartCount ?></span></a>
        </nav>
    </div>
</header>
<div class="mobile-menu-layer" id="mobile-menu" hidden data-mobile-menu-layer>
    <nav class="mobile-menu" aria-label="Navigare mobilă">
        <a class="mobile-account" href="<?= user()?'/cont':'/autentificare' ?>"><?=icon('user')?><span><small><?=user()?'CONTUL TĂU':'AUTENTIFICARE'?></small><strong><?=user()?'Bună, '.e(user()['first_name']):'Intră în cont'?></strong></span><?=icon('chevron')?></a>
        <a href="/"<?= $currentPath === '/' ? ' aria-current="page"' : '' ?>><strong>Acasă</strong><?=icon('chevron')?></a><a href="/despre-noi"<?= $currentPath === '/despre-noi' ? ' aria-current="page"' : '' ?>><strong>Despre noi</strong><?=icon('chevron')?></a><a href="/magazin"<?= $isShopPath ? ' aria-current="page"' : '' ?>><strong>Magazin</strong><?=icon('chevron')?></a><?php if ($hasPublishedPosts): ?><a href="/blog"<?= str_starts_with($currentPath, '/blog') ? ' aria-current="page"' : '' ?>><strong>Atelier</strong><?=icon('chevron')?></a><?php endif ?><a href="/contact"<?= $currentPath === '/contact' ? ' aria-current="page"' : '' ?>><strong>Contact</strong><?=icon('chevron')?></a>
    </nav>
</div>
<div class="search-panel" role="dialog" aria-modal="true" aria-label="Caută produse" hidden data-search-panel>
    <div class="search-box">
        <button class="icon-button search-close" type="button" aria-label="Închide căutarea" data-search-close><?= icon('close') ?></button>
        <span class="search-eyebrow">DESCOPERĂ SMILEBABY</span><label for="site-search">Ce cauți pentru începutul cel mai frumos?</label>
        <div class="search-input-wrap"><?=icon('search')?><input id="site-search" type="search" placeholder="Trusou, lumânare, mărturie sau cod produs…" autocomplete="off" data-search-input><kbd>ESC</kbd></div>
        <div class="search-smart"><div class="search-intro" data-search-intro><div class="search-group-title"><span>Colecțiile noastre</span><small>Alege un început</small></div><div class="search-quick-categories"><?php foreach((new App\Models\CategoryRepository())->homepage() as $searchCategory):?><a href="/categorie/<?=e($searchCategory['slug'])?>"><img src="<?=e(optimized_image_url($searchCategory['image_path'], 'card'))?>" alt="" loading="lazy" decoding="async"><span><?=e($searchCategory['name'])?></span></a><?php endforeach?></div><div class="recent-searches" data-recent-searches hidden><div class="search-group-title"><span>Căutări recente</span><button type="button" data-clear-recent>Șterge</button></div><div data-recent-list></div></div></div><div class="search-loading" data-search-loading hidden><i></i><i></i><i></i></div><div class="search-results" data-search-results hidden></div></div>
        <div class="search-footer"><span>Caută tolerant la greșeli după nume, cod, slug, categorie sau preț.</span><a class="text-link" href="/magazin" data-search-all>Vezi toate produsele <?=icon('arrow')?></a></div>
    </div>
</div>
<div class="drawer-backdrop" hidden data-cart-backdrop></div>
<aside class="cart-drawer" role="dialog" aria-modal="true" aria-label="Coșul tău" aria-hidden="true" data-cart-drawer><button class="drawer-handle" type="button" aria-label="Închide coșul" data-cart-close></button><div data-cart-drawer-content><div class="drawer-loading">Pregătim coșul tău…</div></div></aside>
<?php if ($message = Session::pullFlash('success')): ?><div class="toast toast-success" role="status"><?= e($message) ?></div><?php endif ?>
<?php if ($message = Session::pullFlash('error')): ?><div class="toast toast-error" role="alert"><?= e($message) ?></div><?php endif ?>
<main id="continut"><?= $content ?></main>
<section class="service-strip shell" aria-label="Beneficii SmileBaby">
    <div><?= icon('truck') ?><span><strong>Livrare rapidă</strong><small><?= e(setting('estimated_delivery_text', '2–3 zile lucrătoare')) ?></small></span></div>
    <div><?= icon('card') ?><span><strong>Plată securizată</strong><small>Metode configurabile</small></span></div>
    <div><?= icon('headset') ?><span><strong>Suport clienți</strong><small>Luni–Vineri, 09:00–18:00</small></span></div>
    <div><?= icon('gift') ?><span><strong>Ambalaj premium</strong><small>Pregătit cu iubire</small></span></div>
</section>
<footer class="site-footer">
    <div class="shell footer-identity">
        <a class="footer-logo" href="/" aria-label="SmileBaby — pagina principală"><img src="<?= image_asset('images/logo-smilebaby-transparent.png') ?>" alt="SmileBaby" loading="lazy" decoding="async"></a>
        <div class="footer-brand-copy"><p>Daruri și obiecte delicate, pregătite cu grijă pentru cele mai prețioase începuturi.</p><a class="footer-phone" href="tel:<?= e($sitePhoneHref) ?>"><?= icon('phone') ?><span><?= e($sitePhone) ?></span></a><div class="footer-socials" aria-label="SmileBaby pe rețelele sociale"><?php foreach ($socialLinks as $network => $url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($network)) ?> SmileBaby"><?= icon($network) ?></a><?php endforeach ?></div></div>
    </div>
    <div class="shell footer-rule" aria-hidden="true"></div>
    <div class="shell footer-grid">
        <div class="footer-column"><h2>Magazin</h2><a href="/magazin">Toate produsele</a><a href="/categorie/botez-fetite">Botez fetițe</a><a href="/categorie/botez-baieti">Botez băieți</a><a href="/favorite">Favorite</a><?php if ($hasPublishedPosts): ?><a href="/blog">Blog</a><?php endif ?></div>
        <div class="footer-column"><h2>Ajutor</h2><a href="/despre-noi">Povestea noastră</a><a href="/urmareste-comanda">Urmărește comanda</a><a href="/livrare-si-retur">Livrare și retur</a><a href="/contact">Contact</a><a href="tel:<?= e($sitePhoneHref) ?>"><?= e($sitePhone) ?></a></div>
        <div class="footer-column footer-legal"><h2>Legal</h2><a href="/livrare-si-retur">Livrare și retur</a><a href="/termeni-si-conditii">Termeni și condiții</a><a href="/confidentialitate">Confidențialitate</a><a href="/cookies">Cookie-uri</a><button class="footer-cookie-button" type="button" data-cookie-settings>Setări cookie</button><a class="footer-anpc" href="https://anpc.ro/" target="_blank" rel="noopener" aria-label="Autoritatea Națională pentru Protecția Consumatorilor"><img src="<?= asset('images/anpc-sal.png') ?>" alt="ANPC — Soluționarea alternativă a litigiilor"></a></div>
    </div>
    <div class="shell footer-bottom">
        <span>© <?= date('Y') ?> SmileBaby. Toate drepturile rezervate.</span>
        <a class="footer-credit" href="https://cab-it.ro" target="_blank" rel="noopener" aria-label="Website realizat de CAB-IT">
            <span>Designed by</span><img src="<?= image_asset('images/cab-it-mark.png') ?>" alt="CAB-IT" loading="lazy" decoding="async"><strong>cab-it.ro</strong>
        </a>
        <div class="footer-payments" aria-label="Metode de plată acceptate">
            <span class="payment-badge visa" aria-label="Visa">VISA</span>
            <span class="payment-badge mastercard" aria-label="Mastercard"><i></i><i></i></span>
            <span class="payment-badge google-pay" aria-label="Google Pay"><b>G</b><em>Pay</em></span>
            <span class="payment-badge apple-pay" aria-label="Apple Pay"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.1 12.4c0-2.8 2.3-4.1 2.4-4.2-1.3-1.9-3.4-2.1-4.1-2.1-1.7-.2-3.4 1-4.3 1s-2.2-1-3.7-.9c-1.9 0-3.7 1.1-4.7 2.8-2 3.5-.5 8.7 1.4 11.5.9 1.4 2.1 3 3.6 2.9 1.4-.1 2-1 3.7-1s2.2 1 3.7 1c1.5 0 2.5-1.4 3.4-2.8 1.1-1.6 1.5-3.1 1.5-3.2-.1 0-2.9-1.1-2.9-5m-2.8-8.1c.8-1 1.3-2.3 1.2-3.6-1.1.1-2.5.8-3.3 1.7-.7.8-1.3 2.2-1.2 3.5 1.3.1 2.5-.6 3.3-1.6"/></svg><em>Pay</em></span>
            <span class="payment-badge revolut-pay" aria-label="Revolut Pay"><b>Revolut</b><em>Pay</em></span>
            <span class="payment-badge stripe" aria-label="Stripe">stripe</span>
        </div>
    </div>
</footer>
<div class="cookie-banner" data-cookie-banner hidden>
    <div><strong>Alegerile tale de confidențialitate</strong><p>Folosim cookie-uri necesare pentru magazin și, doar cu acordul tău, cookie-uri de analiză și marketing.</p></div>
    <div class="cookie-actions"><button class="button button-ghost" data-cookie-essential>Doar necesare</button><button class="button" data-cookie-accept>Accept toate</button></div>
</div>
<a class="whatsapp-float" href="https://wa.me/<?= e($whatsappNumber) ?>?text=<?= e(rawurlencode('Bună! Aș dori mai multe informații despre produsele SmileBaby.')) ?>" target="_blank" rel="noopener noreferrer" aria-label="Scrie-ne pe WhatsApp"><span>Scrie-ne pe WhatsApp</span><?= icon('whatsapp') ?></a>
<script>window.SmileBaby={csrf:<?= json_encode(App\Core\Csrf::token()) ?>,userId:<?= json_encode(user()['id'] ?? null) ?>,cart:<?= json_encode((new CartService())->snapshot(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,wishlist:<?= json_encode(array_values(array_map('intval', Session::get('wishlist', [])))) ?>};</script>
<script src="<?= asset('js/app.js') ?>" defer></script>
<script src="<?= asset('js/search.js') ?>" defer></script>
<script src="<?= asset('js/cart.js') ?>" defer></script>
<script src="<?= asset('js/product-customization.js') ?>" defer></script>
<script src="<?= asset('js/product-addons.js') ?>" defer></script>
<script src="<?= asset('js/product-variants.js') ?>" defer></script>
<script src="<?= asset('js/product-price.js') ?>" defer></script>
<script src="<?= asset('js/product-review.js') ?>" defer></script>
<script src="<?= asset('js/wishlist.js') ?>" defer></script>
<script src="<?= asset('js/gallery.js') ?>" defer></script>
<script src="<?= asset('js/filters.js') ?>" defer></script>
<script src="<?= asset('js/checkout.js') ?>" defer></script>
<script src="<?= asset('js/collections.js') ?>" defer></script>
<script src="<?= asset('js/story.js') ?>" defer></script>
<script src="<?= asset('js/home-products.js') ?>" defer></script>
<script src="<?= asset('js/testimonials.js') ?>" defer></script>
<script src="<?= asset('js/related-products.js') ?>" defer></script>
<script src="<?= asset('js/account-navigation.js') ?>" defer></script>
<script src="<?= asset('js/tracking-page.js') ?>" defer></script>
</body>
</html>
