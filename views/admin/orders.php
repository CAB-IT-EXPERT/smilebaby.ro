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
$query = array_filter([
    'q' => trim((string) ($filters['q'] ?? '')),
    'status' => trim((string) ($filters['status'] ?? '')),
], static fn ($value): bool => $value !== '');
$exportSuffix = $query ? '?' . http_build_query($query) : '';
?>

<div class="admin-orders-page">
    <header class="admin-orders-head">
        <div>
            <span>OPERAȚIUNI</span>
            <h1>Comenzi</h1>
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
            <?php if ($query): ?><a class="admin-orders-reset" href="/admin/comenzi" aria-label="Șterge filtrele" title="Șterge filtrele">×</a><?php endif ?>
        </form>

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
                                        <strong><?= e($order['email']) ?></strong>
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
                                <td data-label="Data"><time datetime="<?= e($order['created_at']) ?>"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></time></td>
                                <td data-label="Total"><strong class="admin-order-total"><?= money($order['total']) ?></strong></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <footer class="admin-orders-footer">
                <span><?= count($orders) ?> <?= count($orders) === 1 ? 'comandă afișată' : 'comenzi afișate' ?></span>
                <small>Apasă pe orice rând pentru detaliile comenzii.</small>
            </footer>
        <?php else: ?>
            <div class="admin-orders-empty">
                <span><?= icon('search') ?></span>
                <h2>Nu am găsit comenzi</h2>
                <p>Încearcă un alt număr, email, telefon sau status.</p>
                <a href="/admin/comenzi">Șterge filtrele</a>
            </div>
        <?php endif ?>
    </section>
</div>
