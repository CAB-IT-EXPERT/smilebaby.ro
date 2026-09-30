<?php

namespace App\Core;

final class ApiAuth
{
    public static function token(Request $request): ?string
    {
        $header = trim((string) ($request->server['HTTP_AUTHORIZATION'] ?? $request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) ? trim($matches[1]) : null;
    }

    public static function user(Request $request): ?array
    {
        $token = self::token($request);
        if (!$token || strlen($token) < 32) return null;
        $stmt = Database::connection()->prepare('SELECT u.id,u.email,u.first_name,u.last_name,u.phone,u.customer_type,u.company_name,u.company_vat_id,u.company_registration_number,u.company_address,u.role,u.status,t.id token_id FROM api_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>NOW() AND u.status="active" LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch() ?: null;
        if ($user) Database::connection()->prepare('UPDATE api_tokens SET last_used_at=NOW() WHERE id=?')->execute([$user['token_id']]);
        return $user;
    }

    public static function requireUser(Request $request): array
    {
        $user = self::user($request);
        if (!$user) Response::json(['error' => ['code' => 'unauthenticated', 'message' => 'Autentificarea este necesară.']], 401);
        return $user;
    }

    public static function issue(int $userId, string $name = 'storefront'): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $days = (int) config('api.token_ttl_days', 30);
        $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
        Database::connection()->prepare('INSERT INTO api_tokens (user_id,token_hash,name,expires_at) VALUES (?,?,?,?)')->execute([$userId, hash('sha256', $token), mb_substr(trim($name) ?: 'storefront', 0, 100), $expiresAt]);
        return ['token' => $token, 'token_type' => 'Bearer', 'expires_at' => $expiresAt];
    }

    public static function revoke(Request $request): void
    {
        $token = self::token($request);
        if ($token) Database::connection()->prepare('UPDATE api_tokens SET revoked_at=NOW() WHERE token_hash=?')->execute([hash('sha256', $token)]);
    }
}
