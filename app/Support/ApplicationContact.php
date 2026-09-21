<?php

namespace App\Support;

use App\Models\Application;
use Closure;

/** One contact identity policy for validation, persistence and migration audits. */
final class ApplicationContact
{
    public static function email(mixed $value): mixed
    {
        return is_string($value) ? mb_strtolower(trim($value)) : $value;
    }

    public static function phone(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Accept common visual separators, but never silently remove letters.
        $value = preg_replace('/[\s()\-]+/u', '', $value) ?? $value;
        if (preg_match('/^\+?639[0-9]{9}$/D', $value)) {
            return '0'.substr(ltrim($value, '+'), 2);
        }

        return $value;
    }

    public static function key(string $field, string $value): string
    {
        return $field === 'email' ? self::email($value) : ltrim(self::phone($value), '+');
    }

    public static function duplicate(string $field, string $value, ?int $ownerUserId = null): bool
    {
        return Application::query()->where($field.'_key', self::key($field, $value))
            ->when($ownerUserId, fn ($query) => $query->where('user_id', '!=', $ownerUserId))
            ->exists();
    }

    public static function message(string $field): string
    {
        return $field === 'email'
            ? 'This email address is already used in another application.'
            : 'This mobile number is already used in another application.';
    }

    public static function uniqueRule(string $field, ?int $ownerUserId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field, $ownerUserId) {
            if (is_string($value) && self::duplicate($field, $value, $ownerUserId)) {
                $fail(self::message($field));
            }
        };
    }
}
