<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ApplicationIdentity
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_OTHER = 'other_government_id';

    public static function types(): array
    {
        return [
            'national_id' => 'Philippine National ID (PhilID, ePhilID, or Digital National ID)',
            'passport' => 'Philippine Passport',
            'drivers_license' => "Driver's License",
            'postal_id' => 'Postal ID',
            'umid' => 'Unified Multi-Purpose ID (UMID)',
            'prc_id' => 'PRC ID',
            'senior_citizen_id' => 'Senior Citizen ID',
            self::TYPE_OTHER => 'Other government-issued photo ID',
        ];
    }

    public static function typeLabel(?string $type, ?string $other = null): string
    {
        if ($type === self::TYPE_OTHER && filled($other)) {
            return trim($other).' (Other government-issued ID)';
        }

        return self::types()[$type] ?? 'Not specified — legacy application';
    }

    public static function normalizeNumber(?string $number): string
    {
        $normalized = mb_strtoupper(trim((string) $number), 'UTF-8');

        return preg_replace('/[^\p{L}\p{N}]/u', '', $normalized) ?? '';
    }

    public static function numberFingerprint(?string $number): ?string
    {
        $normalized = self::normalizeNumber($number);
        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    public static function uploadedFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    public static function storedFileHash(?string $path): ?string
    {
        $path = ApplicationIdUpload::path($path);
        if (! $path || ! ApplicationIdUpload::exists($path)) {
            return null;
        }

        $stream = Storage::disk(ApplicationIdUpload::disk($path))->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        try {
            return hash('sha256', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public static function maskedNumber(?string $number): string
    {
        $normalized = self::normalizeNumber($number);
        if ($normalized === '') {
            return 'Not recorded';
        }

        $visible = mb_substr($normalized, -4);

        return str_repeat('•', max(4, mb_strlen($normalized) - 4)).$visible;
    }
}
