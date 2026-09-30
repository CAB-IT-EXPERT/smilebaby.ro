<?php

namespace App\Payment;

interface PaymentGatewayInterface
{
    public function initializePayment(array $order, array $method): array;
    public function handleCallback(array $payload, array $method): array;
    public function getPaymentStatus(string $transactionId, array $method): string;
    public function refund(array $payment, float $amount, array $method): array;
}
