<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class PaymentService
{
    public function active(float $subtotal): array
    {
        if (!Database::available()) return [['key' => 'cash_on_delivery', 'name' => 'Plată ramburs', 'description' => 'Plătești curierului la primirea coletului.', 'fee_type' => 'none', 'fee_value' => 0, 'instructions' => '']];
        $default = (string) setting('default_payment_method', '');
        $stmt = Database::connection()->prepare('SELECT * FROM payment_methods WHERE enabled=1 AND `key` IN ("cash_on_delivery","online_card") AND (minimum_order IS NULL OR minimum_order<=?) AND (maximum_order IS NULL OR maximum_order>=?) ORDER BY (`key`=?) DESC,sort_order,id');
        $stmt->execute([$subtotal, $subtotal, $default]);
        return $stmt->fetchAll();
    }

    public function validate(string $key, float $subtotal): array
    {
        foreach ($this->active($subtotal) as $method) if ($method['key'] === $key) return $method;
        throw new RuntimeException('Metoda de plată selectată nu este disponibilă.');
    }

    public function fee(array $method, float $subtotal): float
    {
        return match ($method['fee_type']) {
            'fixed' => round((float) $method['fee_value'], 2),
            'percentage' => round($subtotal * (float) $method['fee_value'] / 100, 2),
            default => 0.0,
        };
    }

    public function gateway(string $key): \App\Payment\PaymentGatewayInterface
    {
        return match ($key) {
            'cash_on_delivery' => new \App\Payment\CashOnDeliveryGateway(),
            'online_card' => new \App\Payment\OnlineCardGateway(),
            default => throw new RuntimeException('Gateway necunoscut.'),
        };
    }
}
