<?php
$max = max(1, ...array_column($salesSeries, 'total'));
$seriesTotal = array_sum(array_column($salesSeries, 'total'));
$seriesAverage = count($salesSeries) ? $seriesTotal / count($salesSeries) : 0;
$peakIndex = 0;
foreach ($salesSeries as $index => $point) if ($point['total'] > $salesSeries[$peakIndex]['total']) $peakIndex = $index;
$periodOptions = ['7d'=>'7 zile','week'=>'Săptămâna curentă','month'=>'O lună','3m'=>'3 luni','6m'=>'6 luni','1y'=>'Un an','all'=>'Tot timpul'];
$statusLabels = ['received'=>'Nouă','confirmed'=>'Confirmată','processing'=>'În pregătire','prepared'=>'Pregătită','shipped'=>'Expediată','delivered'=>'Livrată','cancelled'=>'Anulată','returned'=>'Returnată'];
?>
<div class="admin-page-head dashboard-heading">
    <div><span class="eyebrow">BUN VENIT ÎN ATELIER</span><h1>Prezentare generală</h1><p>Vezi rapid ce se întâmplă în magazin și ce necesită atenție.</p></div>
    <div class="admin-date"><span>▣ <?= date('d-M-Y') ?></span><span>◷ <?= date('H:i') ?></span></div>
</div>
<nav class="period-tabs" aria-label="Perioada raportului">
    <?php foreach ($periodOptions as $value => $label): ?><a class="<?= $period === $value ? 'active' : '' ?>" href="/admin?period=<?= e($value) ?>" aria-current="<?= $period === $value ? 'page' : 'false' ?>"><?= e($label) ?></a><?php endforeach ?>
</nav>
<div class="stat-grid dashboard-stats">
    <article><span>Vânzări în perioadă</span><strong><?= money($stats['sales_period']) ?></strong><small><?= e(mb_strtolower($periodLabels[$period])) ?> · fără comenzi anulate</small></article>
    <article><span>Comenzi</span><strong><?= (int) $stats['orders_period'] ?></strong><small>înregistrate în perioada selectată</small></article>
    <article><span>Clienți noi</span><strong><?= (int) $stats['new_customers_period'] ?></strong><small>conturi create în perioada selectată</small></article>
    <article class="<?= $stats['low_stock'] ? 'warning' : '' ?>"><span>Stoc redus</span><strong><?= (int) $stats['low_stock'] ?></strong><small>produse sub prag</small></article>
</div>
<div class="dashboard-grid">
    <section class="admin-card sales-chart admin-sales-chart" data-sales-chart>
        <div class="admin-card-head sales-chart-head">
            <div><span class="eyebrow">EVOLUȚIE REALĂ</span><h2>Vânzări nete</h2><p>Sunt incluse comenzile valide din perioada aleasă.</p></div>
            <span class="sales-period-pill"><i></i><?= e($periodLabels[$period]) ?></span>
        </div>
        <div class="sales-chart-stage">
            <svg viewBox="0 0 1000 310" role="img" aria-label="Evoluția vânzărilor pentru <?= e($periodLabels[$period]) ?>" data-sales-svg>
                <defs><linearGradient id="salesAreaGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#aa7c5f" stop-opacity=".34"/><stop offset="1" stop-color="#aa7c5f" stop-opacity=".02"/></linearGradient><filter id="salesGlow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="7" stdDeviation="7" flood-color="#8e6046" flood-opacity=".22"/></filter></defs>
                <g data-sales-grid></g>
                <path class="sales-chart-area" data-sales-area></path>
                <path class="sales-chart-line" data-sales-line></path>
                <g data-sales-points></g>
                <g data-sales-labels></g>
                <line class="sales-chart-crosshair" y1="18" y2="270" data-sales-crosshair hidden></line>
                <rect class="sales-chart-hit" x="58" y="16" width="920" height="258" data-sales-hit></rect>
            </svg>
            <div class="sales-chart-tooltip" data-sales-tooltip hidden><small></small><strong></strong><span></span></div>
            <div class="sales-chart-empty" data-sales-empty hidden><span>♡</span><strong>Încă nu există vânzări în această perioadă</strong><small>Graficul se va actualiza automat la prima comandă validă.</small></div>
        </div>
        <footer class="sales-chart-summary">
            <div><span>Total perioadă</span><strong><?= money($seriesTotal) ?></strong></div>
            <div><span>Medie / <?= in_array($period, ['6m','1y','all'], true) ? 'lună' : ($period === '3m' ? 'săptămână' : 'zi') ?></span><strong><?= money($seriesAverage) ?></strong></div>
            <div><span>Cea mai bună perioadă</span><strong><?= e($salesSeries[$peakIndex]['label'] ?? '—') ?></strong><small><?= money($salesSeries[$peakIndex]['total'] ?? 0) ?></small></div>
        </footer>
        <script type="application/json" data-sales-data><?= json_encode($salesSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    </section>
    <section class="admin-card notifications">
        <div class="admin-card-head"><div><span class="eyebrow">ACȚIUNI</span><h2>Notificări</h2></div><a href="/admin/comenzi">Vezi toate</a></div>
        <?php foreach (array_slice($orders, 0, 6) as $order): ?><a href="/admin/comenzi/<?= (int) $order['id'] ?>"><strong>Comandă <?= e($order['order_number']) ?></strong><small><?= e($order['email']) ?> · <?= money($order['total']) ?></small></a><?php endforeach ?>
        <?php if (!$orders): ?><p>Nu există notificări noi.</p><?php endif ?>
    </section>
</div>
<section class="admin-card recent-orders">
    <div class="admin-card-head"><div><span class="eyebrow">ULTIMELE ÎNREGISTRĂRI</span><h2>Comenzi recente</h2></div><a href="/admin/comenzi">Toate comenzile</a></div>
    <div class="table-wrap"><table><thead><tr><th>Comandă</th><th>Client</th><th>Data</th><th>Status</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($orders as $order): ?><tr><td><a href="/admin/comenzi/<?= (int) $order['id'] ?>"><strong><?= e($order['order_number']) ?></strong></a></td><td><?= e($order['email']) ?></td><td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td><td><span class="admin-status <?= e($order['status']) ?>"><?= e($statusLabels[$order['status']] ?? $order['status']) ?></span></td><td><?= money($order['total']) ?></td></tr><?php endforeach ?>
    </tbody></table></div>
</section>
