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

    public static function normalizePaths(mixed $paths): array
    {
        if (is_string($paths)) {
            $trimmed = trim($paths);
            $decoded = str_starts_with($trimmed, '[') ? json_decode($trimmed, true) : null;
            $paths = is_array($decoded) ? $decoded : [$paths];
        }

        if (! is_array($paths)) {
            return [];
        }

        return collect($paths)
            ->map(fn ($path) => self::normalizePath(is_array($path) ? ($path['path'] ?? $path['attachment_path'] ?? null) : $path))
            ->filter()
            ->values()
            ->all();
    }

    public static function storeFiles(iterable $files, string $directory): array
    {
        return collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => self::normalizePath($file->store($directory, 'public')))
            ->filter()
            ->values()
            ->all();
    }

    public static function flattenFiles(mixed ...$inputs): array
    {
        return collect($inputs)
            ->flatMap(function ($input) {
                if ($input instanceof UploadedFile) {
                    return [$input];
                }

                if (is_array($input)) {
                    return $input;
                }

                return [];
            })
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values()
            ->all();
    }

    public static function attachmentMetadataMany(iterable $files, string $directory): ?array
    {
        $attachments = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($directory) {
                $metadata = self::attachmentMetadata($file, $file->store($directory, 'public'));

                return [
                    'path' => $metadata['attachment_path'],
                    'original_name' => $metadata['attachment_original_name'],
                    'mime' => $metadata['attachment_mime'],
                ];
            })
            ->filter(fn (array $item) => filled($item['path']))
            ->values();

        if ($attachments->isEmpty()) {
            return null;
        }

        $first = $attachments->first();

        return [
            'attachment_path' => $first['path'],
            'attachment_original_name' => $first['original_name'],
            'attachment_mime' => $first['mime'],
            'attachments' => $attachments->all(),
        ];
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
