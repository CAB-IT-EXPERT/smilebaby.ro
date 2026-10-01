<header class="category-showcase">
    <?php if ($category['image_path']): ?><img src="<?= e(optimized_image_url($category['image_path'], 'display')) ?>" alt="" fetchpriority="high" decoding="async"><?php endif ?>
    <div class="category-showcase-shade"></div>
    <div class="category-showcase-copy shell">
        <nav class="breadcrumb"><a href="/">Acasă</a><span>/</span><a href="/magazin">Magazin</a><span>/</span><span><?= e($category['name']) ?></span></nav>
        <span class="eyebrow">COLECȚIE SMILEBABY</span>
        <h1><?= e($category['name']) ?></h1>
        <p><?= e($category['short_description'] ?: $category['description']) ?></p>
    </div>
</header>
<button class="mobile-filter-trigger category-filter-trigger shell" type="button" data-filter-open>☷ <strong>FILTREAZĂ PRODUSELE</strong></button>
<button class="filter-backdrop" type="button" aria-label="Închide filtrele" hidden data-filter-backdrop></button>
<div class="shop-layout category-shop shell">
    <aside class="filters category-filters" data-filters>
        <div class="filter-title"><div><span class="eyebrow">RAFINARE</span><h2>Filtre</h2></div><button type="button" aria-label="Închide filtrele" data-filter-close><?= icon('close') ?></button></div>
        <form method="get" action="/categorie/<?= e($category['slug']) ?>">
            <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
            <fieldset><legend>Disponibilitate</legend><label class="filter-check"><input type="checkbox" name="stock" value="1" <?= $filters['stock'] ? 'checked' : '' ?>><i></i><span>În stoc</span></label></fieldset>
            <fieldset><legend>Preț</legend><div class="price-fields"><label><small>De la (lei)</small><input type="number" name="min" value="<?= e($filters['min']) ?>" min="0" inputmode="decimal"></label><label><small>Până la (lei)</small><input type="number" name="max" value="<?= e($filters['max']) ?>" min="0" inputmode="decimal"></label></div></fieldset>
            <div class="filter-actions"><button class="button" type="submit">ARATĂ <?= (int) $total ?> PRODUSE</button><a class="text-link" href="/categorie/<?= e($category['slug']) ?>">RESETEAZĂ FILTRELE</a></div>
        </form>
    </aside>
    <section class="shop-results category-results">
        <form class="shop-search" method="get" action="/categorie/<?= e($category['slug']) ?>"><?= icon('search') ?><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Caută în această colecție…"><input type="hidden" name="min" value="<?= e($filters['min']) ?>"><input type="hidden" name="max" value="<?= e($filters['max']) ?>"><?php if ($filters['stock']): ?><input type="hidden" name="stock" value="1"><?php endif ?><button class="button">CAUTĂ</button></form>
        <div class="shop-toolbar"><span><strong><?= (int) $total ?></strong> PRODUSE</span><form method="get"><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><input type="hidden" name="min" value="<?= e($filters['min']) ?>"><input type="hidden" name="max" value="<?= e($filters['max']) ?>"><?php if ($filters['stock']): ?><input type="hidden" name="stock" value="1"><?php endif ?><label><small>SORTEAZĂ</small><select name="sort" onchange="this.form.submit()"><option value="">Recomandate</option><option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Preț crescător</option><option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Preț descrescător</option><option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Alfabetic</option></select></label></form></div>
        <div class="product-grid"><?php foreach ($products as $product) require BASE_PATH . '/views/components/product-card.php'; ?></div>
        <?php if (!$products): ?><div class="empty-state"><span>♡</span><h2>Niciun produs găsit</h2><p>Încearcă alte filtre sau revino la întreaga colecție.</p><a class="button" href="/categorie/<?= e($category['slug']) ?>">VEZI COLECȚIA</a></div><?php endif ?>
        <?php require BASE_PATH . '/views/components/pagination.php'; ?>
    </section>
</div>
