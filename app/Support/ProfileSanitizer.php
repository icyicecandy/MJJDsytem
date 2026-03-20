<?php

namespace App\Support;

class ProfileSanitizer
{
    public static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }

    public static function username(?string $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
        $value = preg_replace('/[-_.]{2,}/', '-', $value) ?? $value;
        $value = trim($value, '._-');

        return $value === '' ? null : $value;
    }

    public static function phone(?string $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        $hasPlusPrefix = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        return $hasPlusPrefix ? '+' . $digits : $digits;
    }
}
