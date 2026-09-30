<?php

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        $id = (int) Session::get('user_id', 0);
        if (!$id || !Database::available()) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT id, email, first_name, last_name, phone, customer_type, company_name, company_vat_id, company_registration_number, company_address, role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function check(): bool { return self::user() !== null; }
    public static function isAdmin(): bool { return (self::user()['role'] ?? '') === 'admin'; }

    public static function loginById(int $userId): void
    {
        session_regenerate_id(true);
        Session::put('user_id', $userId);
    }

    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        self::loginById((int) $user['id']);
        return true;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        session_regenerate_id(true);
    }
}
