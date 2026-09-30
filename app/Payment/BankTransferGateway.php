<?php

namespace App\Payment;

final class BankTransferGateway implements PaymentGatewayInterface
{
    public function initializePayment(array $order, array $method): array { return ['status' => 'pending', 'redirect_url' => null, 'instructions' => $method['instructions'] ?? '']; }
    public function handleCallback(array $payload, array $method): array { return ['status' => 'pending']; }
    public function getPaymentStatus(string $transactionId, array $method): string { return 'pending'; }
    public function refund(array $payment, float $amount, array $method): array { return ['status' => 'manual']; }
}
