<?php

namespace App\Support;

use App\Services\WorkflowTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ApplicationIdUpload
{
    public static function store(UploadedFile $file): string
    {
        $path = $file->store('application-ids/private', 'local');
        if (! $path) {
            throw new \RuntimeException('The ID photo could not be saved. Please try again.');
        }
        WorkflowTransaction::trackUpload($path, 'local');

        return $path;
    }

    public static function path(?string $path): ?string
    {
        $path = PublicUpload::normalizePath($path);
        if (! $path || ! str_starts_with($path, 'application-ids/') || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }

    public static function disk(string $path): string
    {
        // Existing uploads stay readable; new ID photos are never stored publicly.
        return str_starts_with($path, 'application-ids/private/') ? 'local' : 'public';
    }

    public static function exists(?string $path): bool
    {
        $path = self::path($path);

        return $path !== null && Storage::disk(self::disk($path))->exists($path);
    }
}
