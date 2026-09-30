<?php

namespace App\Payment;

use App\Core\Crypto;
use RuntimeException;

final class OnlineCardGateway implements PaymentGatewayInterface
{
    public function initializePayment(array $order, array $method): array
    {
        $settings = json_decode($method['settings_json'] ?? '{}', true) ?: [];
        if (empty($settings['checkout_url']) || empty($settings['merchant_id'])) {
            throw new RuntimeException('Procesatorul de card nu este configurat complet.');
        }
        $query = http_build_query(['order_id' => $order['order_number'], 'amount' => $order['total'], 'currency' => 'RON', 'return_url' => config('app.url') . '/plata/rezultat?order=' . rawurlencode($order['order_number'])]);
        return ['status' => 'pending', 'redirect_url' => rtrim($settings['checkout_url'], '?') . '?' . $query];
    }

    public function handleCallback(array $payload, array $method): array
    {
        $settings = json_decode($method['settings_json'] ?? '{}', true) ?: [];
        $secret = Crypto::decrypt($settings['webhook_secret_encrypted'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');
        $body = (string) ($payload['signed_payload'] ?? '');
        if ($secret === '' || !hash_equals(hash_hmac('sha256', $body, $secret), $signature)) {
            throw new RuntimeException('Semnătură callback invalidă.');
        }
        return ['status' => in_array($payload['status'] ?? '', ['paid','failed','cancelled'], true) ? $payload['status'] : 'pending', 'transaction_id' => $payload['transaction_id'] ?? null, 'event_id' => $payload['event_id'] ?? hash('sha256', $body)];
    }

    public function getPaymentStatus(string $transactionId, array $method): string { return 'pending'; }
    public function refund(array $payment, float $amount, array $method): array { throw new RuntimeException('Refundul se configurează în adaptorul procesatorului ales.'); }
}
