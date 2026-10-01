<?php $heroImage='assets/images/hero-trusou-botez.png'; ?>
<div class="home-page">
<section class="hero hero-reference shell">
    <picture class="hero-picture"><source media="(max-width: 767px)" srcset="<?= image_asset('images/hero-trusou-botez.webp') ?>"><img class="hero-background" src="<?= image_asset('images/hero-trusou-botez.webp') ?>" alt="Trusou de botez SmileBaby în nuanțe calde, cu lumânare și accesorii delicate" width="1920" height="1080" fetchpriority="high" decoding="async"></picture>
    <div class="hero-copy">
        <span class="eyebrow">MAI MULT DECÂT PRODUSE PENTRU CEI MICI</span>
        <h1>Începuturi delicate pentru povestea botezului.</h1>
        <p>Produse alese cu grijă, pentru momente mai blânde și amintiri care durează.</p>
        <a class="button" href="/magazin">Descoperă colecția <?= icon('arrow') ?></a>
        <span class="hand-note">Little moments,<br>big smiles ♡</span>
    </div>
    <div class="benefit-bar hero-benefits">
        <div><?= icon('leaf') ?><span><strong>Materiale alese cu grijă</strong><small>Pentru momente cu adevărat speciale</small></span></div>
        <div><?= icon('gift') ?><span><strong>Create în atelierul nostru</strong><small>Pregătite atent, cu drag</small></span></div>
        <div><?= icon('heart') ?><span><strong>Te ajutăm să alegi trusoul potrivit</strong><small>Recomandări personalizate</small></span></div>
    </div>
    <div class="hero-mobile-signoff" aria-hidden="true"><i></i><span>CREȘTEM POVEȘTI ÎMPREUNĂ</span><b>♥</b></div>
</section>
<section id="colectii" class="section shell categories-section" data-category-carousel>
    <div class="section-heading centered"><span class="eyebrow">EXPLOREAZĂ</span><h2>Colecțiile noastre</h2><span class="tiny-heart">♡</span></div>
    <div class="category-carousel">
        <button class="category-arrow category-prev" type="button" aria-label="Colecția anterioară" data-category-prev><?= icon('chevron') ?></button>
        <div class="category-track" data-category-track>
            <?php foreach ($categories as $index => $category): ?>
                <a class="category-slide<?= $index === 0 ? ' active' : '' ?>" href="/categorie/<?= e($category['slug']) ?>" data-category-slide>
                    <span><img src="<?= e(optimized_image_url($category['image_path'] ?? null, 'card')) ?>" alt="<?= e($category['name']) ?>" width="720" height="720" loading="lazy" decoding="async"></span>
                    <strong><?= e($category['name']) ?></strong>
                    <small><?= e($category['short_description'] ?: match ($category['slug']) { 'botez-fetite' => 'Trusouri delicate și personalizate', 'botez-baieti' => 'Seturi elegante pentru cei mici', 'lumanari-botez' => 'Lumină caldă pentru ceremonie', default => 'Amintiri oferite cu drag' }) ?></small>
                </a>
            <?php endforeach ?>
        </div>
        <button class="category-arrow category-next" type="button" aria-label="Colecția următoare" data-category-next><?= icon('chevron') ?></button>
    </div>
    <div class="category-dots" aria-label="Alege colecția"><?php foreach ($categories as $index => $category): ?><button class="<?= $index === 0 ? 'active' : '' ?>" type="button" aria-label="<?= e($category['name']) ?>" data-category-dot="<?= $index ?>"></button><?php endforeach ?></div>
    <a class="category-all" href="/magazin">VEZI TOATE COLECȚIILE <?= icon('arrow') ?></a>
</section>
<section id="despre-smilebaby" class="story-home shell" data-story-home>
    <header class="story-intro">
        <span class="eyebrow">FIRUL ÎNCEPUTURILOR</span>
        <h2>O poveste care se descoperă pas cu pas.</h2>
        <p>De la prima alegere până la emoția unui dar pregătit cu grijă.</p>
    </header>
    <nav class="story-steps" aria-label="Etapele poveștii SmileBaby">
        <a class="active" href="#poveste-1" data-story-step="0"><i>01</i><span>Alegerea</span></a>
        <a href="#poveste-2" data-story-step="1"><i>02</i><span>Personalizarea</span></a>
        <a href="#poveste-3" data-story-step="2"><i>03</i><span>Darul</span></a>
    </nav>
    <div class="story-panels">
        <article id="poveste-1" class="story-panel in-view" data-story-panel="0">
            <figure><img src="<?= image_asset('images/story-smilebaby-ladybug.png') ?>" alt="Trusou de botez personalizat SmileBaby cu tematică buburuză" loading="lazy" decoding="async"></figure>
            <div><span>01 · ALEGEREA TA</span><h3>Un început ales cu suflet.</h3><p>Descoperi produse create pentru ceremonia voastră și alegi modelul care spune cel mai bine povestea familiei.</p></div>
        </article>
        <article id="poveste-2" class="story-panel story-panel-reverse" data-story-panel="1">
            <figure><img src="<?= image_asset('images/categories/lumanari-botez.png') ?>" alt="Lumânări de botez personalizate SmileBaby" loading="lazy" decoding="async"></figure>
            <div><span>02 · PERSONALIZAREA</span><h3>Fiecare detaliu devine al vostru.</h3><p>Numele, data, culorile și accesoriile sunt armonizate cu grijă, pentru un rezultat personal și delicat.</p></div>
        </article>
        <article id="poveste-3" class="story-panel" data-story-panel="2">
            <figure><img src="<?= image_asset('images/categories/marturii-botez.png') ?>" alt="Mărturie de botez ambalată pentru a fi dăruită" loading="lazy" decoding="async"></figure>
            <div><span>03 · DARUL</span><h3>Pregătit să rămână amintire.</h3><p>Comanda este verificată și ambalată atent, pentru ca momentul în care o deschizi să păstreze toată emoția.</p></div>
        </article>
    </div>
    <footer class="story-quote"><span>♡</span><blockquote>„Cele mai frumoase începuturi merită cele mai delicate alegeri.”</blockquote><a class="text-button" href="/despre-noi">DESCOPERĂ POVESTEA NOASTRĂ <?= icon('arrow') ?></a></footer>
</section>
<?php if ($products): ?><section class="section shell products-section" data-product-carousel>
    <div class="section-heading"><div><span class="eyebrow">SELECȚII</span><h2>Pentru începuturi prețioase</h2></div><a class="text-button" href="/magazin">VEZI TOATE PRODUSELE <?= icon('arrow') ?></a></div>
    <div class="home-products-carousel">
        <?php if (count($products) > 1): ?><button class="home-product-arrow home-product-prev" type="button" aria-label="Produsul anterior" data-product-prev><?= icon('chevron') ?></button><?php endif ?>
        <div class="home-products-viewport" data-product-viewport>
            <div class="home-products-track" data-product-track><?php foreach ($products as $product) require BASE_PATH . '/views/components/product-card.php'; ?></div>
        </div>
        <?php if (count($products) > 1): ?><button class="home-product-arrow home-product-next" type="button" aria-label="Produsul următor" data-product-next><?= icon('chevron') ?></button><?php endif ?>
    </div>
    <?php if (count($products) > 1): ?><div class="home-product-dots" aria-label="Produse recomandate"><?php foreach ($products as $index => $product): ?><button class="<?= $index === 0 ? 'active' : '' ?>" type="button" aria-label="<?= e($product['name']) ?>" data-product-dot="<?= $index ?>"></button><?php endforeach ?></div><?php endif ?>
</section><?php endif ?>
<section class="home-testimonials shell" aria-labelledby="testimonials-title">
    <header class="testimonials-heading">
        <div class="testimonials-title"><span class="eyebrow">CU DRAG, DE LA CLIENȚII NOȘTRI</span><h2 id="testimonials-title">Cuvinte care ne bucură.</h2><p>Experiențe povestite de oamenii care au ales darurile și produsele create în atelierul nostru.</p></div>
        <div class="google-review-summary" aria-label="Recenzii verificate Google, evaluare de cinci stele"><span class="google-g" aria-hidden="true"><svg viewBox="0 0 48 48" focusable="false"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.223 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917Z"/><path fill="#FF3D00" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4c-7.682 0-14.344 4.337-17.694 10.691Z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44Z"/><path fill="#1976D2" d="M43.611 20.083 43.595 20H42 24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917Z"/></svg></span><div class="google-score"><p><strong>5,0</strong><span aria-hidden="true">★★★★★</span></p><small>Recenzii verificate pe Google</small></div></div>
    </header>
    <div class="testimonials-track" data-testimonials-track>
        <article class="testimonial-card">
            <div class="testimonial-stars" aria-label="5 din 5 stele">★★★★★</div>
            <blockquote>„Recomand serviciile voastre cu toată încrederea. Trusoul personalizat a fost foarte frumos, produsele de calitate superioară, iar disponibilitatea și creativitatea de care ați dat dovadă sunt de apreciat. Vă mulțumesc pentru tot!”</blockquote>
            <footer class="testimonial-author"><span class="testimonial-avatar"><img src="<?= asset('images/testimonials/nicoleta.png') ?>" alt="" loading="lazy"></span><div><p><strong>Nicoleta</strong><span class="verified-mark" aria-label="Recenzie verificată">✓</span></p><small>Recenzie verificată · Google</small></div></footer>
        </article>
        <article class="testimonial-card">
            <div class="testimonial-stars" aria-label="5 din 5 stele">★★★★★</div>
            <blockquote>„Îi recomand cu drag! Servicii și produse ireproșabile, o foarte mare atenție la nevoile clientului și mereu la înălțime. Am fost foarte mulțumită. Calitatea produselor este excepțională. Livrarea a fost rapidă.”</blockquote>
            <footer class="testimonial-author"><span class="testimonial-avatar"><img src="<?= asset('images/testimonials/nadia.png') ?>" alt="" loading="lazy"></span><div><p><strong>Nadia</strong><span class="verified-mark" aria-label="Recenzie verificată">✓</span></p><small>Recenzie verificată · Google</small></div></footer>
        </article>
        <article class="testimonial-card">
            <div class="testimonial-stars" aria-label="5 din 5 stele">★★★★★</div>
            <blockquote>„Recomand cu încredere serviciile ireproșabile, gata la timp, deschiși la nou și adaptare la cerințele și nevoile clientului. Voi recomanda cu drag tuturor celor care vor să aibă un eveniment de neuitat, cu produse de calitate!”</blockquote>
            <footer class="testimonial-author"><span class="testimonial-avatar"><img src="<?= asset('images/testimonials/andreea.png') ?>" alt="" loading="lazy"></span><div><p><strong>Andreea</strong><span class="verified-mark" aria-label="Recenzie verificată">✓</span></p><small>Recenzie verificată · Google</small></div></footer>
        </article>
    </div>
    <div class="testimonials-mobile-hint"><button class="active" type="button" data-testimonial-dot aria-label="Arată recenzia 1"></button><button type="button" data-testimonial-dot aria-label="Arată recenzia 2"></button><button type="button" data-testimonial-dot aria-label="Arată recenzia 3"></button><small>Glisează pentru mai multe recenzii</small></div>
</section>
<?php if ($posts): ?><section class="section shell"><div class="section-heading centered"><span class="eyebrow">POVEȘTI ȘI INSPIRAȚIE</span><h2>Din atelierul SmileBaby</h2></div><div class="blog-grid"><?php foreach ($posts as $post): ?><article class="blog-card"><a href="/blog/<?= e($post['slug']) ?>"><img src="<?= e(optimized_image_url($post['featured_image'], 'card')) ?>" alt="<?= e($post['title']) ?>" loading="lazy" decoding="async"><span><?= date('d.m.Y', strtotime($post['published_at'])) ?></span><h3><?= e($post['title']) ?></h3><p><?= e($post['excerpt']) ?></p></a></article><?php endforeach ?></div></section><?php endif ?>
<section id="newsletter" class="newsletter newsletter-premium shell">
    <span class="newsletter-seal" aria-hidden="true">♡</span>
    <div class="newsletter-copy">
        <span class="eyebrow">RĂMÂI APROAPE</span>
        <h2>Vești frumoase,<br>trimise cu măsură.</h2>
        <p>Noutăți din atelier, idei pentru daruri și inspirație pentru începuturi care merită păstrate.</p>
        <div class="newsletter-promises" aria-label="Despre newsletter"><span>Fără mesaje inutile</span><span>Doar lucruri alese</span></div>
    </div>
    <form class="newsletter-form-card" action="/newsletter" method="post">
        <?= csrf_field() ?>
        <div class="newsletter-form-title"><span>Scrisori rare din atelier</span><i aria-hidden="true">♡</i></div>
        <label class="sr-only" for="newsletter-email">Adresa de email</label>
        <div class="newsletter-input"><input id="newsletter-email" type="email" name="email" placeholder="adresa@email.ro" autocomplete="email" required><button class="button" type="submit">MĂ ABONEZ <?= icon('arrow') ?></button></div>
        <label class="check"><input type="checkbox" name="consent" value="1" required><span>Sunt de acord cu <a href="/confidentialitate">politica de confidențialitate</a>.</span></label>
        <small>Te poți dezabona oricând, dintr-un singur click.</small>
    </form>
</section>
</div>
