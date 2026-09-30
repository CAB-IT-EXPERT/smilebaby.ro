<?php
$firstRow = $total ? (($page - 1) * $perPage + 1) : 0;
$lastRow = min($total, $page * $perPage);
$pageUrl = static function (int $target) use ($q, $status, $perPage): string {
    return '/admin/produse?' . http_build_query(array_filter(['q' => $q, 'status' => $status, 'per_page' => $perPage, 'page' => $target], static fn ($value) => $value !== ''));
};
$statusLabels = ['active' => 'Activ', 'draft' => 'Draft', 'hidden' => 'Ascuns', 'archived' => 'Arhivat'];
$suggestionProducts = array_slice($products, 0, 7);
$limitReached = $catalogTotal >= $productLimit;
?>
<section class="admin-catalog-page">
    <header class="admin-catalog-head">
        <div><span class="eyebrow">CATALOG</span><h1>Produse</h1><p><strong><?= (int) $catalogTotal ?></strong> produse din <strong><?= (int) $productLimit ?></strong><?php if ($q !== '' || $status !== ''): ?> · <?= (int) $total ?> rezultate afișate<?php endif ?></p></div>
        <div class="admin-catalog-head-actions"><form action="/admin/produse/stripe/sincronizare" method="post"><?= csrf_field() ?><button class="admin-button secondary" type="submit">SINCRONIZEAZĂ STRIPE</button></form><?php if ($limitReached): ?><button class="admin-button catalog-add-button is-limited" type="button" data-product-limit-open>ADAUGĂ PRODUS</button><?php else: ?><a class="admin-button catalog-add-button" href="/admin/produse/creare">ADAUGĂ PRODUS</a><?php endif ?></div>
    </header>
    <?php if ($limitReached): ?><dialog class="product-limit-dialog" data-product-limit-dialog><button type="button" aria-label="Închide" data-product-limit-close>×</button><span>LIMITĂ PLAN</span><i><?= icon('bag') ?></i><h2>Catalogul este complet</h2><p><?= e($productLimitMessage) ?></p><button class="admin-button" type="button" data-product-limit-close>AM ÎNȚELES</button></dialog><?php endif ?>
    <div class="admin-product-search" data-product-search>
        <form class="admin-catalog-search" method="get" action="/admin/produse">
            <span aria-hidden="true"><?= icon('search') ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Caută după nume, SKU, slug, preț, categorie sau variantă..." aria-label="Caută produse" autocomplete="off" data-product-search-input>
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
            <input type="hidden" name="per_page" value="<?= $perPage ?>">
            <?php if ($q !== ''): ?><a href="/admin/produse?per_page=<?= $perPage ?>" aria-label="Șterge căutarea">×</a><?php endif ?>
            <button type="submit" aria-label="Caută"><?= icon('search') ?></button>
        </form>
        <div class="admin-search-suggestions" data-product-search-panel hidden>
            <header><span><small>SUGESTII INTELIGENTE</small><strong data-product-search-heading>Produse recente</strong></span><b data-product-search-count><?= count($suggestionProducts) ?> sugestii</b></header>
            <div class="admin-search-results" data-product-search-results>
                <?php foreach ($suggestionProducts as $suggestion):
                    $suggestionPrice = (float) ($suggestion['sale_price'] ?: $suggestion['regular_price']);
                    $searchTerms = trim($suggestion['name'] . ' ' . ($suggestion['slug'] ?? '') . ' ' . ($suggestion['sku'] ?? '') . ' ' . ($suggestion['categories'] ?? '') . ' ' . $suggestionPrice . ' lei');
                ?>
                    <a href="/admin/produse/<?= (int) $suggestion['id'] ?>/editare" data-product-suggestion data-search-terms="<?= e(mb_strtolower($searchTerms)) ?>">
                        <img src="<?= e(upload_url($suggestion['image_path'])) ?>" alt="">
                        <span><strong><?= e($suggestion['name']) ?></strong><small><?= e($suggestion['categories'] ?: 'Fără categorie') ?> · <?= e($suggestion['sku'] ?: 'Fără SKU') ?></small></span>
                        <b><?= money($suggestionPrice) ?></b>
                    </a>
                <?php endforeach ?>
                <p class="admin-search-empty" data-product-search-empty hidden>Nicio sugestie în lista recentă. Apasă Enter pentru căutarea completă.</p>
            </div>
            <footer><span>Poți căuta și la singular, plural sau fără diacritice.</span><span><kbd>↑</kbd><kbd>↓</kbd> navigare <kbd>Enter</kbd> selectare</span></footer>
        </div>
    </div>
    <div class="admin-catalog-table-wrap">
        <table class="admin-catalog-table">
            <thead><tr><th>Produs</th><th>SKU</th><th>Categorie</th><th>Preț</th><th>Stoc online</th><th>Status</th><th>Acțiuni</th></tr></thead>
            <tbody>
            <?php foreach ($products as $product):
                $price = (float) ($product['sale_price'] ?: $product['regular_price']);
                $managed = (bool) $product['manage_stock'];
                $stock = $managed ? (int) $product['stock_quantity'] : null;
                $onlineStock = $managed ? $stock : ((int) $product['variant_count'] > 0 && (int) $product['variant_limited_count'] > 0 ? (int) $product['variant_stock'] : null);
                $stockClass = $onlineStock !== null && $onlineStock <= (int) $product['low_stock_threshold'] ? 'low' : 'ok';
            ?>
                <tr>
                    <td><a class="catalog-product" href="/admin/produse/<?= (int) $product['id'] ?>/editare"><img src="<?= e(upload_url($product['image_path'])) ?>" alt=""><span><strong><?= e($product['name']) ?></strong><?php if ((int) $product['variant_count']): ?><small><?= (int) $product['variant_count'] ?> variante</small><?php endif ?></span></a></td>
                    <td><code><?= e($product['sku'] ?: '—') ?></code></td>
                    <td><span class="catalog-category" title="<?= e($product['categories'] ?: 'Fără categorie') ?>"><?= e($product['categories'] ?: 'Fără categorie') ?></span></td>
                    <td><strong class="catalog-price"><?= money($price) ?></strong><?php if ($product['sale_price']): ?><small class="catalog-old-price"><?= money($product['regular_price']) ?></small><?php endif ?></td>
                    <td><?php if ($onlineStock === null): ?><span class="stock-pill unlimited">Stoc nelimitat</span><?php else: ?><span class="stock-pill <?= $stockClass ?>"><?= $onlineStock ?> buc.</span><?php endif ?></td>
                    <td><span class="catalog-status <?= e($product['status']) ?>"><?= e($statusLabels[$product['status']] ?? $product['status']) ?></span></td>
                    <td><div class="catalog-actions"><a href="/admin/produse/<?= (int) $product['id'] ?>/editare" aria-label="Editează <?= e($product['name']) ?>" data-tooltip="Editează produsul"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.2-1 10.9-10.9a2 2 0 0 0-2.8-2.8L5.4 16.2 4 20Z"/><path d="m14.8 6.8 2.8 2.8"/></svg></a><button type="button" class="catalog-delete-button" aria-label="Șterge <?= e($product['name']) ?>" data-tooltip="Șterge produsul" data-admin-delete data-delete-kind="produsul" data-delete-name="<?= e($product['name']) ?>" data-delete-action="/admin/produse/<?= (int) $product['id'] ?>/stergere"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 4h6l1 3H8l1-3Z"/><path d="m6 7 1 13h10l1-13M10 11v5M14 11v5"/></svg></button></div></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$products): ?><tr><td class="catalog-empty" colspan="7"><strong>Nu am găsit produse.</strong><span>Încearcă o altă căutare sau adaugă un produs nou.</span></td></tr><?php endif ?>
            </tbody>
        </table>
        <footer class="admin-catalog-pagination">
            <form method="get" action="/admin/produse"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="status" value="<?= e($status) ?>"><label>RÂNDURI AFIȘATE:<select name="per_page" onchange="this.form.submit()"><?php foreach ([20,50,100] as $size): ?><option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach ?></select></label></form>
            <strong><?= $firstRow ?>–<?= $lastRow ?> din <?= $total ?></strong>
            <nav aria-label="Paginare produse"><a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : e($pageUrl(1)) ?>" aria-label="Prima pagină">|‹</a><a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : e($pageUrl($page - 1)) ?>" aria-label="Pagina anterioară">‹</a><a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page >= $pages ? '#' : e($pageUrl($page + 1)) ?>" aria-label="Pagina următoare">›</a><a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page >= $pages ? '#' : e($pageUrl($pages)) ?>" aria-label="Ultima pagină">›|</a></nav>
        </footer>
    </div>
</section>
<?php if (!empty($productSaved) && is_array($productSaved)): ?>
    <?php $productWasCreated = ($productSaved['mode'] ?? '') === 'created'; ?>
    <dialog class="admin-product-success-dialog" data-product-success-dialog aria-labelledby="product-success-title">
        <button class="admin-product-success-close" type="button" aria-label="Închide" data-product-success-close>×</button>
        <div class="admin-product-success-visual" aria-hidden="true">
            <span><img src="<?= asset('images/favicon-owl.png') ?>" alt=""></span>
            <i><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></i>
        </div>
        <span class="admin-product-success-eyebrow"><?= $productWasCreated ? 'PRODUS ADĂUGAT CU SUCCES' : 'MODIFICĂRI SALVATE' ?></span>
        <h2 id="product-success-title"><?= $productWasCreated ? 'Produsul este pregătit!' : 'Produsul a fost actualizat!' ?></h2>
        <p><strong><?= e($productSaved['name'] ?? 'Produsul') ?></strong> <?= $productWasCreated ? 'a fost adăugat în catalog.' : 'are acum toate modificările salvate.' ?></p>
        <div class="admin-product-success-note"><span>✓</span><p><strong>Totul este în regulă</strong><small>Poți continua în catalog sau poți reveni oricând la editare.</small></p></div>
        <div class="admin-product-success-actions">
            <button type="button" data-product-success-close>Rămân în catalog</button>
            <a href="/admin/produse/<?= (int) ($productSaved['id'] ?? 0) ?>/editare">Editează produsul</a>
            <a class="primary" href="/produs/<?= e($productSaved['slug'] ?? '') ?>" target="_blank" rel="noopener">Vezi pe site <span>↗</span></a>
        </div>
    </dialog>
<?php endif ?>
