<?php
$statusLabels = [
    'received' => 'Nouă',
    'confirmed' => 'Confirmată',
    'processing' => 'În pregătire',
    'prepared' => 'Pregătită pentru curier',
    'shipped' => 'Expediată',
    'delivered' => 'Livrată',
    'cancelled' => 'Anulată',
    'returned' => 'Returnată',
];
$paymentStatusLabels = [
    'unpaid' => 'Neplătită',
    'pending' => 'În așteptare',
    'paid' => 'Plătită',
    'failed' => 'Expirată',
    'refunded' => 'Rambursată',
];
$cleanMessage = static function (?string $message): string {
    return trim(str_replace('[COMANDĂ TEST]', '', (string) $message));
};
$search = trim((string) ($filters['q'] ?? ''));
$statusFilter = trim((string) ($filters['status'] ?? ''));
$query = array_filter([
    'q' => $search,
    'status' => $statusFilter,
], static fn ($value): bool => $value !== '');
$exportSuffix = $query ? '?' . http_build_query($query) : '';
$firstRow = $total ? (($page - 1) * $perPage + 1) : 0;
$lastRow = min($total, $page * $perPage);
$filtersActive = $search !== '' || $statusFilter !== '';
$pageUrl = static function (int $target) use ($search, $statusFilter, $perPage): string {
    return '/admin/comenzi?' . http_build_query(array_filter([
        'q' => $search,
        'status' => $statusFilter,
        'per_page' => $perPage,
        'page' => $target,
    ], static fn ($value): bool => $value !== ''));
};
$visiblePages = [1, $pages];
for ($candidate = max(1, $page - 2); $candidate <= min($pages, $page + 2); $candidate++) $visiblePages[] = $candidate;
$visiblePages = array_values(array_unique($visiblePages));
sort($visiblePages);
?>

<div class="admin-orders-page">
    <header class="admin-orders-head">
        <div>
            <span>OPERAȚIUNI</span>
            <h1>Comenzi</h1>
            <p>Găsește rapid o comandă și urmărește plata, pregătirea și livrarea.</p>
        </div>
        <nav aria-label="Exportă comenzile">
            <a class="admin-orders-export secondary" href="/admin/comenzi/export/csv<?= e($exportSuffix) ?>">Descarcă CSV</a>
            <a class="admin-orders-export primary" href="/admin/comenzi/export/pdf<?= e($exportSuffix) ?>">Descarcă PDF</a>
        </nav>
    </header>

    <section class="admin-orders-panel">
        <form class="admin-orders-filters" method="get" action="/admin/comenzi">
            <label class="admin-orders-search">
                <span class="sr-only">Caută o comandă</span>
                <?= icon('search') ?>
                <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Număr, client, email sau telefon" autocomplete="off">
            </label>
            <label class="admin-orders-select">
                <span class="sr-only">Filtrează după status</span>
                <select name="status">
                    <option value="">Toate statusurile</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <button type="submit">Filtrează</button>
            <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
            <?php if ($query): ?><a class="admin-orders-reset" href="/admin/comenzi" aria-label="Șterge filtrele" title="Șterge filtrele">×</a><?php endif ?>
        </form>

        <div class="admin-orders-resultbar">
            <p><strong><?= (int) $total ?></strong> <?= $total === 1 ? 'comandă găsită' : 'comenzi găsite' ?><?php if ($search !== ''): ?> pentru „<?= e($search) ?>”<?php endif ?></p>
            <?php if ($filtersActive): ?><a href="/admin/comenzi?per_page=<?= (int) $perPage ?>">Resetează filtrele <span aria-hidden="true">×</span></a><?php else: ?><span>Apasă pe orice rând pentru a deschide comanda.</span><?php endif ?>
        </div>

        <?php if ($orders): ?>
            <div class="admin-orders-table-wrap">
                <table class="admin-orders-table">
                    <thead>
                        <tr>
                            <th>Comandă</th>
                            <th>Client</th>
                            <th>Mesaj cadou</th>
                            <th>Tip plată</th>
                            <th>Plată</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $index => $order):
                            $message = $cleanMessage($order['notes'] ?? null);
                            $paymentMethod = (string) ($order['payment_method'] ?? '');
                            $paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
                            $status = (string) ($order['status'] ?? 'received');
                            $isCard = $paymentMethod === 'online_card';
                            $isBank = $paymentMethod === 'bank_transfer';
                            $messagePreview = mb_substr($message, 0, 62);
                            if ($messagePreview !== $message) $messagePreview .= '…';
                            $paymentType = $isCard
                                ? 'Card — ' . mb_strtolower($paymentStatusLabels[$paymentStatus] ?? $paymentStatus)
                                : ($isBank ? 'Transfer bancar' : 'Ramburs la curier');
                        ?>
                            <tr data-order-row="/admin/comenzi/<?= (int) $order['id'] ?>" tabindex="0" style="--order-row-index:<?= min($index, 14) ?>">
                                <td data-label="Comandă">
                                    <a class="admin-order-number" href="/admin/comenzi/<?= (int) $order['id'] ?>"><?= e($order['order_number']) ?></a>
                                </td>
                                <td data-label="Client">
                                    <div class="admin-order-client">
                                        <strong><?= e(trim((string) $order['first_name'] . ' ' . (string) $order['last_name']) ?: 'Client fără nume') ?></strong>
                                        <small><?= e($order['email']) ?></small>
                                        <small><?= e($order['phone']) ?></small>
                                    </div>
                                </td>
                                <td data-label="Mesaj cadou">
                                    <div class="admin-order-message <?= $message !== '' ? 'has-message' : '' ?>">
                                        <span><?= $message !== '' ? 'Cu mesaj' : 'Fără mesaj' ?></span>
                                        <?php if ($message !== ''): ?><small>„<?= e($messagePreview) ?>”</small><?php endif ?>
                                    </div>
                                </td>
                                <td data-label="Tip plată">
                                    <span class="admin-order-badge payment-type <?= $isCard ? ($paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'failed' ? 'danger' : 'neutral')) : ($isBank && $paymentStatus === 'paid' ? 'success' : 'neutral') ?>"><?= e($paymentType) ?></span>
                                </td>
                                <td data-label="Plată">
                                    <div class="admin-order-payment-state">
                                        <span class="admin-order-badge <?= $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'failed' ? 'danger' : 'neutral') ?>"><?= e($paymentStatusLabels[$paymentStatus] ?? $paymentStatus) ?></span>
                                        <?php if ($paymentStatus === 'failed'): ?><small>Sesiunea de plată a expirat înainte de finalizare.</small><?php endif ?>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="admin-order-badge order-status status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                                </td>
                                <td data-label="Data"><time datetime="<?= e($order['created_at']) ?>"><strong><?= date('d.m.Y', strtotime($order['created_at'])) ?></strong><small><?= date('H:i', strtotime($order['created_at'])) ?></small></time></td>
                                <td data-label="Total"><span class="admin-order-total-wrap"><strong class="admin-order-total"><?= money($order['total']) ?></strong><i aria-hidden="true">›</i></span></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-orders-empty">
                <span><?= icon('search') ?></span>
                <h2>Nu am găsit comenzi</h2>
                <p>Încearcă un alt număr, email, telefon sau status.</p>
                <a href="/admin/comenzi">Șterge filtrele</a>
            </div>
        <?php endif ?>

        <footer class="admin-orders-pagination">
            <form method="get" action="/admin/comenzi">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <label><span>Rânduri pe pagină</span><select name="per_page" data-orders-page-size><?php foreach ([10, 20, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach ?></select></label>
            </form>
            <p><?= $firstRow ?>–<?= $lastRow ?> din <?= (int) $total ?></p>
            <nav aria-label="Paginare comenzi">
                <a class="admin-order-page-arrow <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : e($pageUrl($page - 1)) ?>" aria-label="Pagina anterioară"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></a>
                <?php $previousVisible = 0; foreach ($visiblePages as $visiblePage): ?>
                    <?php if ($previousVisible && $visiblePage > $previousVisible + 1): ?><span class="admin-order-page-gap">…</span><?php endif ?>
                    <?php if ($visiblePage === $page): ?><span class="admin-order-page-number active" aria-current="page"><?= $visiblePage ?></span><?php else: ?><a class="admin-order-page-number" href="<?= e($pageUrl($visiblePage)) ?>"><?= $visiblePage ?></a><?php endif ?>
                    <?php $previousVisible = $visiblePage; ?>
                <?php endforeach ?>
                <a class="admin-order-page-arrow <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page >= $pages ? '#' : e($pageUrl($page + 1)) ?>" aria-label="Pagina următoare"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
            </nav>
        </footer>
    </section>
</div>
