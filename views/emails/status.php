<?php
$isPaid = ($order['payment_status'] ?? '') === 'paid';
$isCashOnDelivery = ($order['payment_method'] ?? '') === 'cash_on_delivery';

$statusData = match ($order['status']) {
    'confirmed' => $isPaid
        ? ['PLATĂ CONFIRMATĂ', 'Plata ta a fost confirmată', 'Comanda a intrat automat în pregătire. Îți vom scrie din nou când este gata de expediere.', '✓']
        : ['COMANDĂ CONFIRMATĂ', 'Comanda ta este confirmată', 'Am verificat comanda și a intrat în fluxul nostru de pregătire.', '✓'],
    'processing' => ['ÎN LUCRU', 'Pregătim comanda ta', 'Lucrăm cu grijă la fiecare detaliu și te anunțăm imediat ce totul este pregătit.', '✦'],
    'prepared' => ['PREGĂTITĂ', 'Comanda este gata', 'Am terminat pregătirea și urmează să predăm coletul curierului.', '✓'],
    'shipped' => ['EXPEDIATĂ', 'Comanda a plecat spre tine', 'Coletul a fost predat curierului și este în drum spre adresa ta.', '→'],
    'delivered' => ['LIVRATĂ', 'Sperăm să vă bucure', 'Comanda a fost livrată. Îți mulțumim că ai ales SmileBaby!', '♥'],
    'cancelled' => ['ANULATĂ', 'Comanda a fost anulată', 'Comanda nu va mai fi procesată. Pentru o plată deja efectuată, te contactăm în legătură cu rambursarea.', '×'],
    'returned' => ['RETUR ÎNREGISTRAT', 'Am înregistrat returul', 'Returul a ajuns în sistemul nostru și continuăm procesarea lui.', '↩'],
    default => ['COMANDĂ PRIMITĂ', 'Am primit comanda ta', 'Comanda este înregistrată și revenim cu următoarea actualizare.', '✓'],
};

$paymentStatus = match ($order['payment_status'] ?? '') {
    'paid' => 'Achitată',
    'failed' => 'Plată nereușită',
    'refunded' => 'Rambursată',
    default => $isCashOnDelivery ? 'Ramburs la livrare' : 'În curs de confirmare',
};
$paymentMethod = $isCashOnDelivery ? 'Plată ramburs' : (($order['payment_method'] ?? '') === 'online_card' ? 'Card online' : ($order['payment_method_label'] ?? 'Metoda aleasă'));
$trackingUrl = (new App\Services\OrderTrackingService())->url($order);
$preheader = $statusData[1] . ' · ' . $order['order_number'];
?>
<!doctype html>
<html lang="ro" style="color-scheme:light only;supported-color-schemes:light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
    <title><?= e($statusData[1]) ?></title>
    <style>
        :root{color-scheme:light only!important;supported-color-schemes:light!important}
        body,.email-canvas{background-color:#f3eee9!important;color:#49372e!important}
        .email-card,.email-content{background-color:#fffdfb!important;color:#49372e!important}
        .email-hero{background-color:#f7eee7!important;color:#49372e!important}
        .email-soft{background-color:#faf5f0!important;color:#49372e!important}
        .email-footer{background-color:#4a372e!important;color:#f7ece4!important}
        a{color:#8f5e41}
        @media(prefers-color-scheme:dark){body,.email-canvas{background-color:#f3eee9!important;color:#49372e!important}.email-card,.email-content{background-color:#fffdfb!important;color:#49372e!important}.email-hero{background-color:#f7eee7!important;color:#49372e!important}.email-soft{background-color:#faf5f0!important;color:#49372e!important}.email-muted{color:#7c685d!important}.email-line{border-color:#eee2da!important}.email-footer{background-color:#4a372e!important;color:#f7ece4!important}}
        @media(max-width:620px){.email-pad{padding-left:20px!important;padding-right:20px!important}.email-title{font-size:30px!important}.email-stat{display:block!important;width:100%!important;border-left:0!important;border-top:1px solid #eadbd0!important}.email-stat:first-child{border-top:0!important}}
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f3eee9;color:#49372e;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent"><?= e($preheader) ?></div>
<table class="email-canvas" role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f3eee9" style="width:100%;margin:0;background-color:#f3eee9">
    <tr><td align="center" style="padding:34px 12px">
        <table class="email-card" role="presentation" width="640" cellpadding="0" cellspacing="0" bgcolor="#fffdfb" style="width:100%;max-width:640px;border:1px solid #e6d8ce;border-radius:26px;overflow:hidden;background-color:#fffdfb;box-shadow:0 18px 50px rgba(78,55,42,.08)">
            <tr><td class="email-hero email-pad" align="center" bgcolor="#f7eee7" style="padding:30px 38px 34px;background-color:#f7eee7;color:#49372e">
                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                    <td style="width:42px;height:42px;border-radius:14px;background-color:#b57b59;color:#fff;text-align:center;font-family:Georgia,serif;font-size:13px;font-weight:bold">SB</td>
                    <td style="padding-left:11px;text-align:left"><strong style="display:block;color:#4b352b;font-family:Georgia,serif;font-size:23px;font-weight:normal">SmileBaby</strong><span class="email-muted" style="display:block;color:#9a7b69;font-size:9px;letter-spacing:1.6px">POVESTEA BOTEZULUI</span></td>
                </tr></table>
                <div style="width:68px;height:68px;margin:25px auto 18px;border:8px solid #fff7f1;border-radius:50%;background-color:#a86f50;color:#fff;line-height:68px;font-size:30px;box-shadow:0 10px 24px rgba(111,70,47,.14)"><?= e($statusData[3]) ?></div>
                <p style="margin:0 0 8px;color:#a26949;font-size:11px;font-weight:bold;letter-spacing:2.2px"><?= e($statusData[0]) ?></p>
                <h1 class="email-title" style="margin:0;color:#49342a;font-family:Georgia,'Times New Roman',serif;font-size:38px;font-weight:normal;line-height:1.12"><?= e($statusData[1]) ?></h1>
                <p class="email-muted" style="max-width:480px;margin:14px auto 0;color:#7c685d;font-size:14px;line-height:1.7"><?= e($statusData[2]) ?></p>
            </td></tr>

            <tr><td class="email-content email-pad" bgcolor="#fffdfb" style="padding:30px 38px;background-color:#fffdfb;color:#49372e">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eadbd0;border-radius:18px;overflow:hidden">
                    <tr>
                        <td class="email-stat" width="33.33%" align="center" valign="top" style="padding:17px 10px"><small class="email-muted" style="display:block;color:#9a7e6e;font-size:9px;font-weight:bold;letter-spacing:1.2px">COMANDĂ</small><strong style="display:block;margin-top:7px;color:#5b3f30;font-size:13px"><?= e($order['order_number']) ?></strong></td>
                        <td class="email-stat" width="33.33%" align="center" valign="top" style="padding:17px 10px;border-left:1px solid #eadbd0"><small class="email-muted" style="display:block;color:#9a7e6e;font-size:9px;font-weight:bold;letter-spacing:1.2px">TOTAL</small><strong style="display:block;margin-top:7px;color:#5b3f30;font-size:15px"><?= money($order['total']) ?></strong></td>
                        <td class="email-stat" width="33.33%" align="center" valign="top" style="padding:17px 10px;border-left:1px solid #eadbd0"><small class="email-muted" style="display:block;color:#9a7e6e;font-size:9px;font-weight:bold;letter-spacing:1.2px">PLATĂ</small><strong style="display:block;margin-top:7px;color:#5b3f30;font-size:13px"><?= e($paymentStatus) ?></strong></td>
                    </tr>
                </table>

                <?php if ($items): ?>
                    <p style="margin:26px 0 9px;color:#a06b4b;font-size:10px;font-weight:bold;letter-spacing:1.6px">ÎN COMANDA TA</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="email-line" style="padding:12px 0;border-bottom:1px solid #eee2da"><strong style="display:block;color:#51392d;font-size:13px;line-height:1.45"><?= e($item['product_name']) ?></strong><?php if (!empty($item['variant_name'])): ?><span class="email-muted" style="display:block;margin-top:2px;color:#907b6f;font-size:11px"><?= e($item['variant_name']) ?></span><?php endif ?><span class="email-muted" style="display:block;margin-top:4px;color:#907b6f;font-size:11px"><?= (int) $item['quantity'] ?> × <?= money($item['price']) ?></span></td>
                                <td class="email-line" align="right" valign="middle" style="padding:12px 0;border-bottom:1px solid #eee2da;color:#5d4132;font-size:13px;font-weight:bold"><?= money($item['total']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </table>
                <?php endif ?>

                <table class="email-soft" role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#faf5f0" style="margin-top:22px;border:1px solid #eee1d7;border-radius:16px;background-color:#faf5f0;color:#49372e">
                    <tr><td style="padding:16px 18px"><small class="email-muted" style="display:block;color:#9a7e6e;font-size:9px;font-weight:bold;letter-spacing:1.2px">METODA DE PLATĂ</small><strong style="display:block;margin-top:5px;color:#523a2e;font-size:13px"><?= e($paymentMethod) ?></strong></td></tr>
                </table>

                <p style="margin:28px 0 8px;text-align:center"><a href="<?= e($trackingUrl) ?>" style="display:inline-block;min-width:230px;padding:15px 24px;border-radius:999px;background-color:#986449;color:#fff!important;text-decoration:none;font-size:12px;font-weight:bold;letter-spacing:.6px">VEZI DETALIILE COMENZII →</a></p>
                <p class="email-muted" style="margin:13px 0 0;color:#9a8578;text-align:center;font-size:10px;line-height:1.55">Linkul este personal și te duce direct la comandă, fără să introduci din nou datele.</p>
            </td></tr>

            <tr><td class="email-footer email-pad" bgcolor="#4a372e" style="padding:22px 38px;background-color:#4a372e;color:#f7ece4;text-align:center">
                <strong style="display:block;font-family:Georgia,serif;font-size:16px;font-weight:normal">SmileBaby</strong>
                <span style="display:block;margin-top:5px;color:#dbc8bb;font-size:10px;line-height:1.6">Produse pregătite cu grijă pentru momente speciale.</span>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
