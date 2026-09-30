<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Crypto;
use PDO;
use RuntimeException;

/**
 * Stripe Checkout is built exclusively from the immutable order snapshot.
 * No live catalog price is consulted after the order has been created.
 */
final class StripeCheckoutService
{
    public function __construct(private readonly StripeClient $stripe = new StripeClient()) {}

    public function createSession(array $order): array
    {
        $snapshot = $this->orderSnapshot($order);
        $lines = $this->stripeLines($snapshot['order'], $snapshot['items']);
        if (count($lines) > 100) throw new RuntimeException('Comanda conține prea multe poziții pentru plata online. Contactează-ne pentru finalizare.');

        $params = [
            'mode' => 'payment',
            'locale' => 'ro',
            'client_reference_id' => (string) $snapshot['order']['order_number'],
            'customer_email' => (string) $snapshot['order']['email'],
            'success_url' => config('app.url') . '/plata/rezultat?order=' . rawurlencode((string) $snapshot['order']['order_number']) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url') . '/plata/rezultat?order=' . rawurlencode((string) $snapshot['order']['order_number']) . '&cancelled=1',
            'metadata' => [
                'order_id' => (string) $snapshot['order']['id'],
                'order_number' => (string) $snapshot['order']['order_number'],
            ],
            'payment_intent_data' => ['metadata' => [
                'order_id' => (string) $snapshot['order']['id'],
                'order_number' => (string) $snapshot['order']['order_number'],
            ]],
            'line_items' => $lines,
            'billing_address_collection' => 'auto',
            'submit_type' => 'pay',
        ];

        $session = $this->stripe->post('/v1/checkout/sessions', $params, 'smilebaby-checkout-' . $snapshot['order']['id'] . '-' . bin2hex(random_bytes(8)));
        if (empty($session['id']) || empty($session['url'])) throw new RuntimeException('Stripe nu a generat pagina securizată de plată.');
        Database::connection()->prepare('UPDATE payments SET provider="stripe",provider_transaction_id=?,provider_response_safe=? WHERE order_id=?')
            ->execute([(string) $session['id'], json_encode(['checkout_session' => $session['id'], 'amount_total' => $session['amount_total'] ?? null], JSON_UNESCAPED_SLASHES), (int) $snapshot['order']['id']]);

        return ['status' => 'pending', 'redirect_url' => (string) $session['url'], 'transaction_id' => (string) $session['id']];
    }

    public function verifyReturn(string $sessionId, string $orderNumber): array
    {
        if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) throw new RuntimeException('Sesiunea de plată nu este validă.');
        $session = $this->stripe->get('/v1/checkout/sessions/' . rawurlencode($sessionId));
        $this->assertSessionMatchesOrder($session, $orderNumber);
        if (($session['payment_status'] ?? '') === 'paid') {
            $this->markPaid($session);
        } elseif (($session['status'] ?? '') === 'expired') {
            $this->markFailed($session, 'cancelled');
        }
        return $session;
    }

    public function handleWebhook(string $rawBody, string $signature): array
    {
        $event = $this->verifyWebhookSignature($rawBody, $signature);
        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? null;
        if ($eventId === '' || !is_array($object)) throw new RuntimeException('Eveniment Stripe incomplet.');

        $orderId = (int) ($object['metadata']['order_id'] ?? 0);
        $safe = json_encode([
            'object_id' => $object['id'] ?? null,
            'payment_status' => $object['payment_status'] ?? null,
            'status' => $object['status'] ?? null,
            'amount_total' => $object['amount_total'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insert = Database::connection()->prepare('INSERT IGNORE INTO payment_events (provider,event_id,order_id,event_type,payload_safe) VALUES ("stripe",?,?,?,?)');
        $insert->execute([$eventId, $orderId ?: null, $eventType, $safe]);
        if ($insert->rowCount() === 0) {
            $processed = Database::connection()->prepare('SELECT processed_at FROM payment_events WHERE provider="stripe" AND event_id=?');
            $processed->execute([$eventId]);
            if ($processed->fetchColumn()) return ['duplicate' => true, 'type' => $eventType];
        }

        if (in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($object['payment_status'] ?? '') === 'paid') {
            $this->markPaid($object);
        } elseif (in_array($eventType, ['checkout.session.async_payment_failed', 'checkout.session.expired'], true)) {
            $this->markFailed($object, $eventType === 'checkout.session.expired' ? 'cancelled' : 'failed');
        }
        Database::connection()->prepare('UPDATE payment_events SET processed_at=NOW() WHERE provider="stripe" AND event_id=?')->execute([$eventId]);
        return ['duplicate' => false, 'type' => $eventType];
    }

    public function paymentStatus(string $sessionId): string
    {
        $session = $this->stripe->get('/v1/checkout/sessions/' . rawurlencode($sessionId));
        return ($session['payment_status'] ?? '') === 'paid' ? 'paid' : (($session['status'] ?? '') === 'expired' ? 'cancelled' : 'pending');
    }

    public function refund(array $payment, float $amount): array
    {
        $transaction = (string) ($payment['provider_transaction_id'] ?? '');
        if (!str_starts_with($transaction, 'pi_')) throw new RuntimeException('Plata nu are un PaymentIntent Stripe eligibil pentru rambursare.');
        return $this->stripe->post('/v1/refunds', [
            'payment_intent' => $transaction,
            'amount' => self::moneyToCents($amount),
            'metadata' => ['order_id' => (string) ($payment['order_id'] ?? '')],
        ], 'smilebaby-refund-' . ($payment['id'] ?? 'payment') . '-' . self::moneyToCents($amount));
    }

    /** Visible for verification tests. */
    public function calculation(array $order, array $items): array
    {
        $lines = $this->stripeLines($order, $items);
        $sum = array_sum(array_map(static fn (array $line): int => (int) $line['price_data']['unit_amount'] * (int) $line['quantity'], $lines));
        return ['lines' => $lines, 'total_cents' => $sum, 'order_total_cents' => self::moneyToCents($order['total'])];
    }

    public static function moneyToCents(float|int|string $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', $normalized);
        return ((int) $whole * 100) + ((int) $fraction * ((int) $whole < 0 ? -1 : 1));
    }

    private function stripeLines(array $order, array $items): array
    {
        $currency = (string) config('payments.stripe.currency', 'ron');
        $lines = [];
        foreach ($items as $item) {
            $quantity = max(1, (int) $item['quantity']);
            $customizationCents = self::moneyToCents($item['customization_price'] ?? 0);
            $storedUnitCents = self::moneyToCents($item['price'] ?? 0);
            $baseCents = $storedUnitCents - $customizationCents;
            $label = trim((string) $item['product_name'] . (!empty($item['variant_name']) ? ' — ' . $item['variant_name'] : ''));
            if ($baseCents > 0) $lines[] = $this->line($label, $baseCents, $quantity, $currency, 'Produs · ' . ((string) ($item['sku'] ?? 'fără SKU')));
            if ($customizationCents > 0) $lines[] = $this->line('Personalizare — ' . (string) $item['product_name'], $customizationCents, $quantity, $currency, 'Opțiune aplicată fiecărui produs din această configurație');

            foreach ((array) json_decode((string) ($item['addons_json'] ?? ''), true) as $addon) {
                $addonCents = self::moneyToCents($addon['price'] ?? 0);
                $addonQuantity = max(1, (int) ($addon['quantity'] ?? 1));
                if ($addonCents > 0) $lines[] = $this->line('Opțiune suplimentară — ' . (string) ($addon['name'] ?? 'Produs'), $addonCents, $addonQuantity, $currency, 'Preț special configurat pentru produsul principal');
            }
        }
        $shipping = self::moneyToCents($order['shipping_total'] ?? 0);
        if ($shipping > 0) $lines[] = $this->line('Livrare', $shipping, 1, $currency, 'Cost calculat din setările magazinului la plasarea comenzii');
        $fee = self::moneyToCents($order['payment_fee'] ?? 0);
        if ($fee > 0) $lines[] = $this->line('Taxă metodă de plată', $fee, 1, $currency);

        $calculated = array_sum(array_map(static fn (array $line): int => (int) $line['price_data']['unit_amount'] * (int) $line['quantity'], $lines));
        $expected = self::moneyToCents($order['total']);
        if ($calculated !== $expected) {
            error_log(sprintf('Stripe amount mismatch for order %s: calculated=%d expected=%d', $order['order_number'] ?? '?', $calculated, $expected));
            throw new RuntimeException('Totalul comenzii nu corespunde cu suma detaliilor de plată. Plata a fost oprită în siguranță; te rugăm să reîncarci checkout-ul.');
        }
        return $lines;
    }

    private function line(string $name, int $unitAmount, int $quantity, string $currency, string $description = ''): array
    {
        $productData = ['name' => mb_substr($name, 0, 127)];
        if ($description !== '') $productData['description'] = mb_substr($description, 0, 255);
        return ['price_data' => ['currency' => $currency, 'unit_amount' => $unitAmount, 'product_data' => $productData], 'quantity' => $quantity];
    }

    private function orderSnapshot(array $order): array
    {
        $db = Database::connection();
        if (!empty($order['id'])) {
            $stmt = $db->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
            $stmt->execute([(int) $order['id']]);
        } else {
            $stmt = $db->prepare('SELECT * FROM orders WHERE order_number=? LIMIT 1');
            $stmt->execute([(string) ($order['order_number'] ?? '')]);
        }
        $saved = $stmt->fetch();
        if (!$saved) throw new RuntimeException('Comanda nu mai este disponibilă pentru plată.');
        if (($saved['payment_method'] ?? '') !== 'online_card') throw new RuntimeException('Comanda nu folosește plata online.');
        if (($saved['status'] ?? '') === 'cancelled') throw new RuntimeException('Comanda este anulată și nu mai poate fi plătită.');
        $items = $db->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $items->execute([(int) $saved['id']]);
        $rows = $items->fetchAll();
        if (!$rows) throw new RuntimeException('Comanda nu conține produse.');
        return ['order' => $saved, 'items' => $rows];
    }

    private function assertSessionMatchesOrder(array $session, string $orderNumber): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM orders WHERE order_number=? LIMIT 1');
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        if (!$order) throw new RuntimeException('Comanda asociată plății nu există.');
        if ((string) ($session['client_reference_id'] ?? '') !== $orderNumber || (string) ($session['metadata']['order_number'] ?? '') !== $orderNumber) {
            throw new RuntimeException('Sesiunea Stripe nu aparține acestei comenzi.');
        }
        if (strtolower((string) ($session['currency'] ?? '')) !== strtolower((string) config('payments.stripe.currency', 'ron'))) {
            throw new RuntimeException('Moneda confirmată de Stripe este incorectă.');
        }
        if ((int) ($session['amount_total'] ?? -1) !== self::moneyToCents($order['total'])) {
            throw new RuntimeException('Suma confirmată de Stripe nu corespunde totalului comenzii.');
        }
        return $order;
    }

    private function markPaid(array $session): void
    {
        $orderNumber = (string) ($session['client_reference_id'] ?? $session['metadata']['order_number'] ?? '');
        $order = $this->assertSessionMatchesOrder($session, $orderNumber);
        $notify = Database::transaction(function (PDO $db) use ($session, $order): bool {
            $locked = $db->prepare('SELECT status,payment_status FROM orders WHERE id=? FOR UPDATE');
            $locked->execute([(int) $order['id']]);
            $state = $locked->fetch();
            if (!$state) throw new RuntimeException('Comanda asociată plății nu există.');
            $wasPaid = $state['payment_status'] === 'paid';
            $nextStatus = $state['status'] === 'received' ? 'confirmed' : $state['status'];
            $db->prepare('UPDATE orders SET payment_status="paid",status=? WHERE id=?')->execute([$nextStatus, (int) $order['id']]);
            $transactionId = (string) ($session['payment_intent'] ?? $session['id'] ?? '');
            $db->prepare('UPDATE payments SET provider="stripe",status="paid",provider_transaction_id=?,provider_response_safe=? WHERE order_id=?')
                ->execute([$transactionId ?: null, json_encode(['checkout_session' => $session['id'] ?? null, 'payment_intent' => $session['payment_intent'] ?? null, 'amount_total' => $session['amount_total'] ?? null], JSON_UNESCAPED_SLASHES), (int) $order['id']]);
            if ($nextStatus !== $state['status']) {
                $db->prepare('INSERT INTO order_status_history (order_id,old_status,new_status,message) VALUES (?,?,"confirmed","Plata Stripe a fost confirmată automat.")')->execute([(int) $order['id'], $state['status']]);
            }
            return !$wasPaid;
        });
        if ($notify) (new MailService())->orderStatus((int) $order['id']);
    }

    private function markFailed(array $session, string $paymentStatus): void
    {
        $orderNumber = (string) ($session['client_reference_id'] ?? $session['metadata']['order_number'] ?? '');
        if ($orderNumber === '') return;
        $stmt = Database::connection()->prepare('SELECT id,total FROM orders WHERE order_number=? LIMIT 1');
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        if (!$order) return;
        if (isset($session['amount_total']) && (int) $session['amount_total'] !== self::moneyToCents($order['total'])) throw new RuntimeException('Suma evenimentului Stripe nu corespunde comenzii.');
        Database::connection()->prepare('UPDATE payments SET provider="stripe",status=?,provider_transaction_id=? WHERE order_id=? AND status<>"paid"')
            ->execute([$paymentStatus, (string) ($session['id'] ?? ''), (int) $order['id']]);
        Database::connection()->prepare('UPDATE orders SET payment_status="failed" WHERE id=? AND payment_status<>"paid"')->execute([(int) $order['id']]);
    }

    private function verifyWebhookSignature(string $payload, string $header): array
    {
        $secret = (string) config('payments.stripe.webhook_secret', '');
        if (!str_starts_with($secret, 'whsec_') && Database::available()) {
            $stmt = Database::connection()->prepare('SELECT settings_json FROM payment_methods WHERE `key`="online_card" LIMIT 1');
            $stmt->execute();
            $settings = json_decode((string) $stmt->fetchColumn(), true) ?: [];
            $secret = Crypto::decrypt((string) ($settings['stripe_webhook_secret_encrypted'] ?? ''));
        }
        if (!str_starts_with($secret, 'whsec_')) throw new RuntimeException('Secretul webhook Stripe nu este configurat.');
        $timestamp = 0; $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') $timestamp = (int) $value;
            if ($key === 'v1') $signatures[] = $value;
        }
        if (!$timestamp || abs(time() - $timestamp) > 300 || !$signatures) throw new RuntimeException('Semnătura webhook Stripe este expirată sau incompletă.');
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $valid = false;
        foreach ($signatures as $signature) if (hash_equals($expected, $signature)) { $valid = true; break; }
        if (!$valid) throw new RuntimeException('Semnătura webhook Stripe este invalidă.');
        $event = json_decode($payload, true);
        if (!is_array($event)) throw new RuntimeException('Eveniment Stripe invalid.');
        return $event;
    }
}
