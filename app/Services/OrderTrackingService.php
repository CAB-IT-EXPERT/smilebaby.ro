<?php

namespace App\Services;

use RuntimeException;

final class OrderTrackingService
{
    public function path(array $order): string
    {
        return '/urmareste-comanda/acces/' . $this->token($order);
    }

    public function url(array $order): string
    {
        return rtrim((string) config('app.url'), '/') . $this->path($order);
    }

    public function token(array $order): string
    {
        $id = (int) ($order['id'] ?? 0);
        $number = trim((string) ($order['order_number'] ?? ''));
        if ($id < 1 || $number === '') throw new RuntimeException('Comanda nu poate primi un link de urmărire.');

        $payload = self::encode(json_encode([
            'v' => 1,
            'id' => $id,
            'number' => $number,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = self::encode(hash_hmac('sha256', $payload, $this->key(), true));
        return $payload . '.' . $signature;
    }

    public function verify(string $token): ?array
    {
        if (strlen($token) > 512 || substr_count($token, '.') !== 1) return null;
        [$payload, $signature] = explode('.', $token, 2);
        $provided = self::decode($signature);
        if ($provided === null || !hash_equals(hash_hmac('sha256', $payload, $this->key(), true), $provided)) return null;

        $json = self::decode($payload);
        if ($json === null) return null;
        try { $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return null; }
        if (!is_array($data) || (int) ($data['v'] ?? 0) !== 1 || (int) ($data['id'] ?? 0) < 1 || trim((string) ($data['number'] ?? '')) === '') return null;
        return ['id' => (int) $data['id'], 'number' => (string) $data['number']];
    }

    private function key(): string
    {
        $key = trim((string) config('app.key', ''));
        if (strlen($key) < 32 || str_contains($key, 'GENEREAZA_')) throw new RuntimeException('APP_KEY nu este configurată pentru linkurile securizate de urmărire.');
        return hash('sha256', 'smilebaby-order-tracking|' . $key, true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) return null;
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
        return $decoded === false ? null : $decoded;
    }
}
