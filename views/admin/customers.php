<?php
$firstRow = $total ? (($page - 1) * $perPage + 1) : 0;
$lastRow = min($total, $page * $perPage);
$filtersActive = $query !== '' || $status !== '' || $activity !== '' || $sort !== 'newest';
$pageUrl = static function (int $target) use ($query, $status, $activity, $sort, $perPage): string {
    return '/admin/clienti?' . http_build_query(array_filter([
        'q' => $query, 'status' => $status, 'activity' => $activity,
        'sort' => $sort !== 'newest' ? $sort : '', 'per_page' => $perPage, 'page' => $target,
    ], static fn ($value) => $value !== ''));
};
$visiblePages = [1, $pages];
for ($candidate = max(1, $page - 2); $candidate <= min($pages, $page + 2); $candidate++) $visiblePages[] = $candidate;
$visiblePages = array_values(array_unique($visiblePages)); sort($visiblePages);
$initialsFor = static function (array $customer): string {
    $first = trim((string) ($customer['first_name'] ?? '')); $last = trim((string) ($customer['last_name'] ?? ''));
    $initials = mb_substr($first, 0, 1) . mb_substr($last, 0, 1);
    return mb_strtoupper($initials !== '' ? $initials : mb_substr((string) $customer['email'], 0, 1));
};
?>
<section class="admin-customers-page" data-customer-directory>
    <header class="admin-customers-head">
        <div class="admin-customers-title"><span>RELAȚII CU CLIENȚII</span><h1>Clienți</h1><p>Găsește rapid un client și vezi istoricul complet al comenzilor sale.</p></div>
        <div class="admin-customers-stats" aria-label="Rezumat clienți">
            <article><span>În total</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></article>
            <article><span>Conturi active</span><strong><?= (int) ($stats['active'] ?? 0) ?></strong></article>
            <article><span>Au comandat</span><strong><?= (int) ($stats['buyers'] ?? 0) ?></strong></article>
        </div>
    </header>

    <section class="admin-customers-panel">
        <form class="admin-customers-toolbar" method="get" action="/admin/clienti" data-customer-filter-form>
            <div class="admin-customer-search-field">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
                <label for="customer-search">Caută un client</label>
                <input id="customer-search" type="search" name="q" value="<?= e($query) ?>" placeholder="Nume, email, telefon sau companie…" autocomplete="off" data-customer-search>
                <?php if ($query !== ''): ?><button class="admin-customer-search-clear" type="button" aria-label="Șterge căutarea" data-customer-search-clear>×</button><?php endif ?>
                <button class="admin-customer-search-submit" type="submit" aria-label="Caută clienți"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg><span>Caută</span></button>
            </div>
            <div class="admin-customer-filter-grid">
                <label><span>Status</span><select name="status" data-customer-filter><option value="">Toate statusurile</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Activi</option><option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Dezactivați</option></select></label>
                <label><span>Activitate</span><select name="activity" data-customer-filter><option value="">Toți clienții</option><option value="with_orders" <?= $activity === 'with_orders' ? 'selected' : '' ?>>Au comandat</option><option value="repeat" <?= $activity === 'repeat' ? 'selected' : '' ?>>Clienți recurenți</option><option value="no_orders" <?= $activity === 'no_orders' ? 'selected' : '' ?>>Fără comenzi</option></select></label>
                <label><span>Ordonează</span><select name="sort" data-customer-filter><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Cei mai noi</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cei mai vechi</option><option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Alfabetic</option><option value="orders_desc" <?= $sort === 'orders_desc' ? 'selected' : '' ?>>Cele mai multe comenzi</option><option value="spent_desc" <?= $sort === 'spent_desc' ? 'selected' : '' ?>>Valoare cumpărături</option></select></label>
            </div>
            <input type="hidden" name="per_page" value="<?= $perPage ?>">
        </form>

        <div class="admin-customers-resultbar">
            <p><strong><?= $total ?></strong> <?= $total === 1 ? 'client găsit' : 'clienți găsiți' ?><?php if ($query !== ''): ?> pentru „<?= e($query) ?>”<?php endif ?></p>
            <?php if ($filtersActive): ?><a href="/admin/clienti">Resetează filtrele <span aria-hidden="true">×</span></a><?php else: ?><span>Caută simultan în nume, email, telefon și companie</span><?php endif ?>
        </div>

        <?php if ($customers): ?>
            <div class="admin-customers-table-wrap"><table class="admin-customers-table">
                <thead><tr><th>Client</th><th>Contact</th><th>Comenzi</th><th>Total cumpărături</th><th>Status</th><th>Activitate</th><th>Fișă client</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $index => $customer): $name = trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name']); $name = $name !== '' ? $name : 'Client fără nume'; ?>
                    <tr style="--customer-row-index:<?= min($index, 14) ?>" data-customer-row="/admin/clienti/<?= (int) $customer['id'] ?>" tabindex="0">
                        <td data-label="Client"><div class="admin-customer-identity"><span class="admin-customer-avatar"><?= e($initialsFor($customer)) ?></span><span><strong><?= e($name) ?></strong><small><?= $customer['customer_type'] === 'company' ? e($customer['company_name'] ?: 'Client companie') : 'Client persoană fizică' ?></small></span></div></td>
                        <td data-label="Contact"><div class="admin-customer-contact"><a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a><small><?= e($customer['phone'] ?: 'Telefon necompletat') ?></small></div></td>
                        <td data-label="Comenzi"><span class="admin-customer-order-count"><?= (int) $customer['orders_count'] ?></span></td>
                        <td data-label="Total cumpărături"><strong class="admin-customer-spent"><?= money($customer['spent']) ?></strong></td>
                        <td data-label="Status"><span class="admin-customer-status <?= e($customer['status']) ?>"><i></i><?= $customer['status'] === 'active' ? 'Activ' : 'Dezactivat' ?></span></td>
                        <td data-label="Activitate"><div class="admin-customer-dates"><strong><?= $customer['last_order_at'] ? date('d.m.Y', strtotime($customer['last_order_at'])) : 'Nicio comandă' ?></strong><small>Cont creat <?= date('d.m.Y', strtotime($customer['created_at'])) ?></small></div></td>
                        <td data-label="Fișă client"><a class="admin-customer-view" href="/admin/clienti/<?= (int) $customer['id'] ?>" aria-label="Deschide fișa clientului <?= e($name) ?>"><span>Deschide</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <div class="admin-customers-empty"><span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10" cy="9" r="3.5"/><path d="M3.5 19c.7-3.2 3-5 6.5-5 1.4 0 2.6.3 3.6.9M16 16l4 4M18 14v4h-4"/></svg></span><h2><?= $filtersActive ? 'Nu am găsit clienți' : 'Nu există încă clienți' ?></h2><p><?= $filtersActive ? 'Încearcă un alt termen sau elimină unul dintre filtre.' : 'Conturile noi vor apărea automat aici.' ?></p><?php if ($filtersActive): ?><a href="/admin/clienti">Arată toți clienții</a><?php endif ?></div>
        <?php endif ?>

        <footer class="admin-customers-pagination">
            <form method="get" action="/admin/clienti"><input type="hidden" name="q" value="<?= e($query) ?>"><input type="hidden" name="status" value="<?= e($status) ?>"><input type="hidden" name="activity" value="<?= e($activity) ?>"><input type="hidden" name="sort" value="<?= e($sort) ?>"><label><span>Rânduri pe pagină</span><select name="per_page" data-customer-page-size><?php foreach ([10, 20, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach ?></select></label></form>
            <p><?= $firstRow ?>–<?= $lastRow ?> din <?= $total ?></p>
            <nav aria-label="Paginare clienți"><a class="admin-customer-page-arrow <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : e($pageUrl($page - 1)) ?>" aria-label="Pagina anterioară"><svg viewBox="0 0 24 24"><path d="m15 5-7 7 7 7"/></svg></a><?php $previousVisible = 0; foreach ($visiblePages as $visiblePage): ?><?php if ($previousVisible && $visiblePage > $previousVisible + 1): ?><span class="admin-customer-page-gap">…</span><?php endif ?><?php if ($visiblePage === $page): ?><span class="admin-customer-page-number active" aria-current="page"><?= $visiblePage ?></span><?php else: ?><a class="admin-customer-page-number" href="<?= e($pageUrl($visiblePage)) ?>"><?= $visiblePage ?></a><?php endif ?><?php $previousVisible = $visiblePage; endforeach ?><a class="admin-customer-page-arrow <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page >= $pages ? '#' : e($pageUrl($page + 1)) ?>" aria-label="Pagina următoare"><svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a></nav>
        </footer>
    </section>
</section>
