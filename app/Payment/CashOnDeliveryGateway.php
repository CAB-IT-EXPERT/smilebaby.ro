<?php

namespace App\Payment;

final class CashOnDeliveryGateway implements PaymentGatewayInterface
{
    public function initializePayment(array $order, array $method): array { return ['status' => 'unpaid', 'redirect_url' => null]; }
    public function handleCallback(array $payload, array $method): array { return ['status' => 'unpaid']; }
    public function getPaymentStatus(string $transactionId, array $method): string { return 'unpaid'; }
    public function refund(array $payment, float $amount, array $method): array { return ['status' => 'not_applicable']; }
}
