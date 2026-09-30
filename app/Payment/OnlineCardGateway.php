<?php

namespace App\Payment;

use App\Services\StripeCheckoutService;
use RuntimeException;

final class OnlineCardGateway implements PaymentGatewayInterface
{
    public function initializePayment(array $order, array $method): array
    {
        $settings = json_decode($method['settings_json'] ?? '{}', true) ?: [];
        if (($settings['provider'] ?? 'stripe') !== 'stripe') throw new RuntimeException('Procesatorul de card selectat nu este disponibil.');
        return (new StripeCheckoutService())->createSession($order);
    }

    public function handleCallback(array $payload, array $method): array
    {
        throw new RuntimeException('Callback-ul Stripe necesită corpul brut și se procesează prin endpointul webhook dedicat.');
    }

    public function getPaymentStatus(string $transactionId, array $method): string { return (new StripeCheckoutService())->paymentStatus($transactionId); }
    public function refund(array $payment, float $amount, array $method): array { return (new StripeCheckoutService())->refund($payment, $amount); }
}
