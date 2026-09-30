<?php
$firstRow = $total ? (($page - 1) * $perPage + 1) : 0;
$lastRow = min($total, $page * $perPage);
$filtersActive = $query !== '' || $status !== '' || $rating !== 0 || $reply !== '' || $sort !== 'newest';
$queryValues = ['q'=>$query,'status'=>$status,'rating'=>$rating ?: '','reply'=>$reply,'sort'=>$sort !== 'newest' ? $sort : '','per_page'=>$perPage];
$returnTo = '/admin/recenzii?' . http_build_query(array_filter($queryValues + ['page'=>$page], static fn ($value) => $value !== ''));
$pageUrl = static function (int $target) use ($queryValues): string {
    return '/admin/recenzii?' . http_build_query(array_filter($queryValues + ['page'=>$target], static fn ($value) => $value !== ''));
};
$visiblePages = [1, $pages];
for ($candidate = max(1, $page - 2); $candidate <= min($pages, $page + 2); $candidate++) $visiblePages[] = $candidate;
$visiblePages = array_values(array_unique($visiblePages)); sort($visiblePages);
$statusLabels = ['pending'=>'În așteptare','approved'=>'Publicată','rejected'=>'Respinsă'];
?>
<section class="admin-reviews-page" data-review-directory>
    <header class="admin-reviews-head">
        <div><span>ÎNCREDERE & COMUNITATE</span><h1>Recenzii</h1><p>Moderează opiniile clienților și răspunde public, direct în pagina produsului.</p></div>
        <div class="admin-review-stats" aria-label="Rezumat recenzii">
            <article><span>Total</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></article>
            <article class="pending"><span>De moderat</span><strong><?= (int) ($stats['pending'] ?? 0) ?></strong></article>
            <article><span>Publicate</span><strong><?= (int) ($stats['approved'] ?? 0) ?></strong></article>
            <article><span>Cu răspuns</span><strong><?= (int) ($stats['answered'] ?? 0) ?></strong></article>
        </div>
    </header>

    <section class="admin-reviews-panel">
        <form class="admin-reviews-toolbar" method="get" action="/admin/recenzii" data-review-filter-form>
            <div class="admin-review-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
                <label for="review-search">Căutare inteligentă</label>
                <input id="review-search" type="search" name="q" value="<?= e($query) ?>" placeholder="Client, email, recenzie, produs sau SKU…" autocomplete="off" data-review-search>
                <?php if ($query !== ''): ?><button type="button" class="admin-review-search-clear" aria-label="Șterge căutarea" data-review-search-clear>×</button><?php endif ?>
                <button type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg><span>Caută</span></button>
            </div>
            <div class="admin-review-filter-grid">
                <label><span>Status</span><select name="status" data-review-filter><option value="">Toate</option><option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>De moderat</option><option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Publicate</option><option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Respinse</option></select></label>
                <label><span>Evaluare</span><select name="rating" data-review-filter><option value="">Toate</option><?php for ($stars = 5; $stars >= 1; $stars--): ?><option value="<?= $stars ?>" <?= $rating === $stars ? 'selected' : '' ?>><?= $stars ?> <?= $stars === 1 ? 'stea' : 'stele' ?></option><?php endfor ?></select></label>
                <label><span>Răspuns</span><select name="reply" data-review-filter><option value="">Toate</option><option value="answered" <?= $reply === 'answered' ? 'selected' : '' ?>>Cu răspuns</option><option value="unanswered" <?= $reply === 'unanswered' ? 'selected' : '' ?>>Fără răspuns</option></select></label>
                <label><span>Ordonează</span><select name="sort" data-review-filter><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Prioritate moderare</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cele mai vechi</option><option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Rating descrescător</option><option value="rating_asc" <?= $sort === 'rating_asc' ? 'selected' : '' ?>>Rating crescător</option><option value="product" <?= $sort === 'product' ? 'selected' : '' ?>>După produs</option></select></label>
            </div>
            <input type="hidden" name="per_page" value="<?= $perPage ?>">
        </form>

        <div class="admin-reviews-resultbar"><p><strong><?= $total ?></strong> <?= $total === 1 ? 'recenzie găsită' : 'recenzii găsite' ?><?php if ($query !== ''): ?> pentru „<?= e($query) ?>”<?php endif ?></p><?php if ($filtersActive): ?><a href="/admin/recenzii">Resetează filtrele <span>×</span></a><?php else: ?><span>Recenziile în așteptare sunt afișate primele.</span><?php endif ?></div>

        <?php if ($reviews): ?>
            <div class="admin-reviews-table-wrap"><table class="admin-reviews-table">
                <thead><tr><th>Recenzie</th><th>Produs</th><th>Client</th><th>Status</th><th>Răspuns</th><th>Data</th><th>Acțiuni</th></tr></thead>
                <tbody>
                <?php foreach ($reviews as $index => $review): ?>
                    <tr style="--review-row-index:<?= min($index, 14) ?>">
                        <td data-label="Recenzie"><div class="admin-review-copy"><span class="admin-review-stars" aria-label="<?= (int) $review['rating'] ?> din 5 stele"><?= str_repeat('★', (int) $review['rating']) ?><i><?= str_repeat('★', 5 - (int) $review['rating']) ?></i></span><strong><?= e($review['title'] ?: 'Recenzie fără titlu') ?></strong><p><?= e($review['body']) ?></p><?php if ($review['verified_purchase']): ?><small><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg> Achiziție verificată</small><?php endif ?></div></td>
                        <td data-label="Produs"><a class="admin-review-product" href="/produs/<?= e($review['product_slug']) ?>" target="_blank" rel="noopener"><img src="<?= e(upload_url($review['product_image'])) ?>" alt=""><span><strong><?= e($review['product_name']) ?></strong><small><?= e($review['product_sku'] ?: 'Fără SKU') ?></small></span><svg viewBox="0 0 24 24"><path d="M14 5h5v5M19 5l-8 8"/><path d="M17 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h5"/></svg></a></td>
                        <td data-label="Client"><div class="admin-review-author"><strong><?= e($review['author_name']) ?></strong><a href="mailto:<?= e($review['email']) ?>"><?= e($review['email']) ?></a></div></td>
                        <td data-label="Status"><span class="admin-review-status <?= e($review['status']) ?>"><i></i><?= e($statusLabels[$review['status']] ?? $review['status']) ?></span></td>
                        <td data-label="Răspuns"><?php if (!empty($review['admin_reply'])): ?><button class="admin-review-answer-state answered" type="button" data-review-reply-open="<?= (int) $review['id'] ?>"><svg viewBox="0 0 24 24"><path d="M21 14a4 4 0 0 1-4 4H9l-5 3v-3a4 4 0 0 1-2-4V8a4 4 0 0 1 4-4h11a4 4 0 0 1 4 4Z"/><path d="m8 11 2 2 5-5"/></svg><span>Răspuns publicat<small>Editează</small></span></button><?php else: ?><button class="admin-review-answer-state" type="button" data-review-reply-open="<?= (int) $review['id'] ?>"><svg viewBox="0 0 24 24"><path d="M21 14a4 4 0 0 1-4 4H9l-5 3v-3a4 4 0 0 1-2-4V8a4 4 0 0 1 4-4h11a4 4 0 0 1 4 4Z"/></svg><span>Fără răspuns<small>Răspunde acum</small></span></button><?php endif ?></td>
                        <td data-label="Data"><time datetime="<?= e($review['created_at']) ?>"><?= date('d.m.Y', strtotime($review['created_at'])) ?><small><?= date('H:i', strtotime($review['created_at'])) ?></small></time></td>
                        <td data-label="Acțiuni"><div class="admin-review-actions">
                            <form action="/admin/recenzii/<?= (int) $review['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><button class="approve <?= $review['status'] === 'approved' ? 'current' : '' ?>" name="status" value="approved" aria-label="Aprobă recenzia" data-tooltip="Aprobă și publică"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></button><button class="pending <?= $review['status'] === 'pending' ? 'current' : '' ?>" name="status" value="pending" aria-label="Pune în așteptare" data-tooltip="În așteptare"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg></button><button class="reject <?= $review['status'] === 'rejected' ? 'current' : '' ?>" name="status" value="rejected" aria-label="Respinge recenzia" data-tooltip="Respinge"><svg viewBox="0 0 24 24"><path d="m7 7 10 10M17 7 7 17"/></svg></button></form>
                            <button class="reply" type="button" aria-label="Răspunde recenziei" data-tooltip="Răspuns public" data-review-reply-open="<?= (int) $review['id'] ?>"><svg viewBox="0 0 24 24"><path d="M21 14a4 4 0 0 1-4 4H9l-5 3v-3a4 4 0 0 1-2-4V8a4 4 0 0 1 4-4h11a4 4 0 0 1 4 4Z"/></svg></button>
                            <button class="delete" type="button" aria-label="Șterge recenzia" data-tooltip="Șterge definitiv" data-admin-delete data-delete-kind="recenzia" data-delete-name="<?= e($review['title'] ?: $review['author_name']) ?>" data-delete-action="/admin/recenzii/<?= (int) $review['id'] ?>/stergere"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 4h6l1 3H8l1-3Z"/><path d="m6 7 1 13h10l1-13M10 11v5M14 11v5"/></svg></button>
                        </div></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <div class="admin-reviews-empty"><span><svg viewBox="0 0 24 24"><path d="M21 14a4 4 0 0 1-4 4H9l-5 3v-3a4 4 0 0 1-2-4V8a4 4 0 0 1 4-4h11a4 4 0 0 1 4 4Z"/><path d="m8 11 2 2 5-5"/></svg></span><h2><?= $filtersActive ? 'Nu am găsit recenzii' : 'Nu există recenzii încă' ?></h2><p><?= $filtersActive ? 'Încearcă alt termen sau elimină unul dintre filtre.' : 'Recenziile trimise de clienți vor apărea aici pentru moderare.' ?></p><?php if ($filtersActive): ?><a href="/admin/recenzii">Arată toate recenziile</a><?php endif ?></div>
        <?php endif ?>

        <footer class="admin-reviews-pagination">
            <form method="get" action="/admin/recenzii"><?php foreach (['q'=>$query,'status'=>$status,'rating'=>$rating ?: '','reply'=>$reply,'sort'=>$sort] as $name=>$value): ?><input type="hidden" name="<?= $name ?>" value="<?= e($value) ?>"><?php endforeach ?><label><span>Rânduri pe pagină</span><select name="per_page" data-review-page-size><?php foreach ([10,20,50,100] as $size): ?><option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach ?></select></label></form>
            <p><?= $firstRow ?>–<?= $lastRow ?> din <?= $total ?></p>
            <nav aria-label="Paginare recenzii"><a class="admin-review-page-arrow <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : e($pageUrl($page - 1)) ?>" aria-label="Pagina anterioară">‹</a><?php $previousVisible=0; foreach ($visiblePages as $visiblePage): ?><?php if ($previousVisible && $visiblePage > $previousVisible + 1): ?><span>…</span><?php endif ?><?php if ($visiblePage === $page): ?><span class="admin-review-page-number active" aria-current="page"><?= $visiblePage ?></span><?php else: ?><a class="admin-review-page-number" href="<?= e($pageUrl($visiblePage)) ?>"><?= $visiblePage ?></a><?php endif ?><?php $previousVisible=$visiblePage; endforeach ?><a class="admin-review-page-arrow <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page >= $pages ? '#' : e($pageUrl($page + 1)) ?>" aria-label="Pagina următoare">›</a></nav>
        </footer>
    </section>

    <?php foreach ($reviews as $review): ?>
        <dialog class="admin-review-reply-dialog" data-review-reply-dialog="<?= (int) $review['id'] ?>">
            <button type="button" class="admin-review-dialog-close" aria-label="Închide" data-review-reply-close>×</button>
            <header><span>RĂSPUNS OFICIAL SMILEBABY</span><h2><?= !empty($review['admin_reply']) ? 'Editează răspunsul' : 'Răspunde clientului' ?></h2><p>Răspunsul va apărea public sub recenzia lui <?= e($review['author_name']) ?>, pe pagina produsului.</p></header>
            <div class="admin-review-dialog-context"><span class="admin-review-stars"><?= str_repeat('★', (int) $review['rating']) ?><i><?= str_repeat('★', 5 - (int) $review['rating']) ?></i></span><strong><?= e($review['title'] ?: $review['product_name']) ?></strong><blockquote><?= e($review['body']) ?></blockquote><small><?= e($review['author_name']) ?> · <?= e($review['product_name']) ?></small></div>
            <form action="/admin/recenzii/<?= (int) $review['id'] ?>" method="post" data-review-reply-form><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><label><span>Răspunsul magazinului</span><textarea name="admin_reply" rows="7" maxlength="3000" required placeholder="Scrie un răspuns cald, clar și util…"><?= e($review['admin_reply'] ?? '') ?></textarea><small><b data-review-reply-count><?= mb_strlen((string) ($review['admin_reply'] ?? '')) ?></b>/3000 caractere</small></label><div class="admin-review-dialog-note"><svg viewBox="0 0 24 24"><path d="m12 3 8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg><span><strong>Publicare inteligentă</strong><small>Salvarea răspunsului aprobă automat recenzia și o afișează pe site.</small></span></div><footer><button type="button" data-review-reply-close>Renunță</button><button type="submit">Publică răspunsul <span>→</span></button></footer></form>
        </dialog>
    <?php endforeach ?>
</section>
