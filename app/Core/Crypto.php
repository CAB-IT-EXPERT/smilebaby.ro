<?php

namespace App\Core;

use RuntimeException;

final class Crypto
{
    public static function encrypt(string $value): string
    {
        $key = hash('sha256', (string) config('payments.encryption_key'), true);
        if (!function_exists('openssl_encrypt') || trim((string) config('payments.encryption_key')) === '') throw new RuntimeException('Configurează APP_KEY/PAYMENT_ENCRYPTION_KEY și extensia OpenSSL înainte de salvarea secretelor.');
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $value): string
    {
        if (!$value || !function_exists('openssl_decrypt')) return '';
        $raw = base64_decode($value, true); if ($raw === false || strlen($raw) < 29) return '';
        $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $cipher = substr($raw, 28);
        return (string) openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', (string) config('payments.encryption_key'), true), OPENSSL_RAW_DATA, $iv, $tag);
    }

    public static function mask(string $value): string { return $value === '' ? '' : str_repeat('•', 8) . mb_substr($value, -4); }
}
