<?php

namespace App\Core;

final class Validator
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field => $label) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field] = "$label este obligatoriu.";
            }
        }
        return $errors;
    }

    public static function email(?string $value): bool { return (bool) filter_var($value, FILTER_VALIDATE_EMAIL); }
}
