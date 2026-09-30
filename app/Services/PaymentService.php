<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class PaymentService
{
    public function active(float $subtotal): array
    {
        if (!Database::available()) return [['key' => 'cash_on_delivery', 'name' => 'Plată ramburs', 'description' => 'Plătești curierului la primirea coletului.', 'fee_type' => 'none', 'fee_value' => 0, 'instructions' => '']];
        $this->ensureStripeMethod();
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

    private function ensureStripeMethod(): void
    {
        static $checked = false;
        if ($checked || !str_starts_with((string) config('payments.stripe.secret_key', ''), 'sk_')) return;
        $checked = true;
        $db = Database::connection();
        $stmt = $db->prepare('SELECT settings_json FROM payment_methods WHERE `key`="online_card" LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch();
        $settings = $row ? (json_decode((string) $row['settings_json'], true) ?: []) : [];
        if (($settings['provider'] ?? '') === 'stripe') return;
        $settings = ['provider' => 'stripe', 'test_mode' => str_starts_with((string) config('payments.stripe.secret_key'), 'sk_test_')];
        $db->prepare('INSERT INTO payment_methods (`key`,name,description,enabled,sort_order,fee_type,fee_value,instructions,settings_json) VALUES ("online_card","Plată online cu cardul","Card, Apple Pay, Google Pay sau Revolut Pay, procesate securizat prin Stripe.",1,2,"none",0,"",?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),enabled=1,settings_json=VALUES(settings_json)')
            ->execute([json_encode($settings, JSON_UNESCAPED_SLASHES)]);
    }
}
