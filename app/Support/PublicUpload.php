<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PublicUpload
{
    public static function normalizePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if (preg_match('/^https?:\/\//i', $path)) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = $urlPath ? ltrim($urlPath, '/') : $path;
        }

        $path = ltrim($path, '/');
        $lowerPath = strtolower($path);

        foreach (['storage/app/public/', 'public/storage/'] as $anchor) {
            $position = strpos($lowerPath, $anchor);

            if ($position !== false) {
                $path = substr($path, $position + strlen($anchor));
                $lowerPath = strtolower($path);
            }
        }

        do {
            $previous = $path;

            foreach (['public/', 'storage/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $path = substr($path, strlen($prefix));
                }
            }
        } while ($path !== $previous);

        $path = preg_replace('#/+#', '/', $path);

        return $path ?: null;
    }

    public static function url(?string $path): ?string
    {
        $path = self::normalizePath($path);

        return $path ? Storage::url($path) : null;
    }

    public static function exists(?string $path): bool
    {
        $path = self::normalizePath($path);

        return $path ? Storage::disk('public')->exists($path) : false;
    }

    public static function urlIfExists(?string $path): ?string
    {
        $path = self::normalizePath($path);

        return self::exists($path) ? self::url($path) : null;
    }

    public static function isImage(?string $path): bool
    {
        $path = self::normalizePath($path);

        if (! $path) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    public static function attachmentMetadata(UploadedFile $file, string $path): array
    {
        return [
            'attachment_path' => self::normalizePath($path),
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType(),
        ];
    }
}
