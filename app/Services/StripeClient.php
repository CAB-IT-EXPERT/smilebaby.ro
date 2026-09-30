<?php

namespace App\Services;

use RuntimeException;

/** Minimal Stripe API client. Secrets never leave the Authorization header. */
final class StripeClient
{
    private const BASE_URL = 'https://api.stripe.com';

    public function configured(): bool
    {
        return str_starts_with((string) config('payments.stripe.secret_key', ''), 'sk_');
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $path, $payload, $idempotencyKey);
    }

    private function request(string $method, string $path, array $data, ?string $idempotencyKey = null): array
    {
        $secret = (string) config('payments.stripe.secret_key', '');
        if (!str_starts_with($secret, 'sk_')) {
            throw new RuntimeException('Stripe nu este configurat. Completează cheia secretă pe server.');
        }

        $encoded = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        $url = self::BASE_URL . '/' . ltrim($path, '/');
        if ($method === 'GET' && $encoded !== '') $url .= '?' . $encoded;
        $headers = [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: SmileBaby/1.0',
            'Stripe-Version: ' . (string) config('payments.stripe.api_version', '2025-08-27.basil'),
        ];
        if ($idempotencyKey) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;

        [$status, $body] = function_exists('curl_init')
            ? $this->requestWithCurl($method, $url, $encoded, $headers)
            : $this->requestWithStreams($method, $url, $encoded, $headers);

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) throw new RuntimeException('Stripe a trimis un răspuns care nu poate fi citit.');
        if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
            $message = trim((string) ($decoded['error']['message'] ?? 'Solicitarea Stripe nu a putut fi procesată.'));
            throw new RuntimeException('Stripe: ' . $message);
        }
        return $decoded;
    }

    private function requestWithCurl(string $method, string $url, string $encoded, array $headers): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $method === 'POST' ? $encoded : null,
        ]);
        $body = curl_exec($curl);
        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('Conexiunea securizată cu Stripe a eșuat: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return [$status, (string) $body];
    }

    private function requestWithStreams(string $method, string $url, string $encoded, array $headers): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'POST' ? $encoded : '',
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) throw new RuntimeException('Conexiunea securizată cu Stripe nu este disponibilă pe server.');
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) $status = (int) $match[1];
        }
        return [$status, (string) $body];
    }
}
