<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class GoogleOAuthService
{
    public function configured(): bool
    {
        return (string) config('google.client_id', '') !== '' && (string) config('google.client_secret', '') !== '';
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();
        return (string) config('google.authorize_url') . '?' . http_build_query([
            'client_id' => config('google.client_id'),
            'redirect_uri' => config('google.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'include_granted_scopes' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function profileFromCode(string $code): array
    {
        $this->assertConfigured();
        $tokens = $this->request((string) config('google.token_url'), [
            'code' => $code,
            'client_id' => config('google.client_id'),
            'client_secret' => config('google.client_secret'),
            'redirect_uri' => config('google.redirect_uri'),
            'grant_type' => 'authorization_code',
        ], true);
        if (empty($tokens['id_token'])) throw new RuntimeException('Google nu a returnat un token de identitate.');
        return $this->profileFromIdToken((string) $tokens['id_token']);
    }

    public function profileFromIdToken(string $idToken): array
    {
        $this->assertConfigured();
        if ($idToken === '') throw new RuntimeException('Tokenul Google lipsește.');
        $profile = $this->request((string) config('google.token_info_url') . '?' . http_build_query(['id_token' => $idToken]), [], false);
        $issuer = (string) ($profile['iss'] ?? '');
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) throw new RuntimeException('Emitent Google invalid.');
        if (!hash_equals((string) config('google.client_id'), (string) ($profile['aud'] ?? ''))) throw new RuntimeException('Tokenul Google nu aparține acestei aplicații.');
        if ((int) ($profile['exp'] ?? 0) <= time()) throw new RuntimeException('Tokenul Google a expirat.');
        if (!filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL) || !filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) throw new RuntimeException('Adresa Google nu este verificată.');
        if (empty($profile['sub'])) throw new RuntimeException('Identitatea Google este incompletă.');
        return $profile;
    }

    public function findOrCreateUser(array $profile): array
    {
        $db = Database::connection();
        $subject = (string) $profile['sub'];
        $email = mb_strtolower(trim((string) $profile['email']));
        $stmt = $db->prepare('SELECT * FROM users WHERE google_subject=? OR email=? ORDER BY google_subject IS NOT NULL DESC LIMIT 1');
        $stmt->execute([$subject, $email]);
        $user = $stmt->fetch();
        if ($user) {
            if (!empty($user['google_subject']) && !hash_equals((string) $user['google_subject'], $subject)) throw new RuntimeException('Adresa este deja legată de un alt cont Google.');
            $provider = ($user['auth_provider'] ?? 'password') === 'password' ? 'both' : 'google';
            $db->prepare('UPDATE users SET google_subject=?,avatar_url=?,auth_provider=?,email_verified_at=COALESCE(email_verified_at,NOW()),status="active" WHERE id=?')->execute([$subject, $profile['picture'] ?? null, $provider, $user['id']]);
            return $this->findUser((int) $user['id']);
        }

        [$firstName, $lastName] = $this->names($profile);
        $db->prepare('INSERT INTO users (email,password_hash,first_name,last_name,google_subject,avatar_url,auth_provider,status,email_verified_at) VALUES (?,?,?,?,?,?,"google","active",NOW())')->execute([$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $firstName, $lastName, $subject, $profile['picture'] ?? null]);
        return $this->findUser((int) $db->lastInsertId());
    }

    private function names(array $profile): array
    {
        $first = trim((string) ($profile['given_name'] ?? ''));
        $last = trim((string) ($profile['family_name'] ?? ''));
        if ($first === '') {
            $parts = preg_split('/\s+/u', trim((string) ($profile['name'] ?? 'Cont Google')), 2) ?: [];
            $first = $parts[0] ?? 'Cont';
            $last = $parts[1] ?? 'Google';
        }
        return [mb_substr($first, 0, 100), mb_substr($last ?: 'Google', 0, 100)];
    }

    private function findUser(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) throw new RuntimeException('Contul nu a putut fi creat.');
        return $user;
    }

    private function request(string $url, array $data, bool $post): array
    {
        $body = $data ? http_build_query($data, '', '&', PHP_QUERY_RFC3986) : '';
        $options = ['http' => ['method' => $post ? 'POST' : 'GET', 'timeout' => 15, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n" . ($post ? "Content-Type: application/x-www-form-urlencoded\r\n" : ''), 'content' => $post ? $body : '']];
        $raw = @file_get_contents($url, false, stream_context_create($options));
        if ($raw === false) throw new RuntimeException('Conexiunea cu Google nu a putut fi realizată.');
        $response = json_decode($raw, true);
        if (!is_array($response) || isset($response['error'])) {
            $message = is_array($response) ? (string) ($response['error_description'] ?? $response['error'] ?? '') : '';
            throw new RuntimeException($message !== '' ? $message : 'Răspuns Google invalid.');
        }
        return $response;
    }

    private function assertConfigured(): void
    {
        if (!$this->configured()) throw new RuntimeException('Autentificarea Google nu este configurată.');
    }
}
