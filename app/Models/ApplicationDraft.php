<?php

namespace App\Models;

use App\Services\WorkflowTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ApplicationDraft extends Model
{
    public const FIELDS = [
        'first_name', 'last_name', 'email', 'mobile_number', 'region', 'province',
        'municipality', 'barangay', 'street_landmark', 'lat', 'lng', 'plan_code',
        'valid_id_type', 'valid_id_type_other',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'step' => 'integer', 'expires_at' => 'datetime'];
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path && str_starts_with($this->photo_path, 'application-ids/private/')
            && ! str_contains($this->photo_path, '..') && Storage::disk('local')->exists($this->photo_path);
    }

    public function hasIdImage(): bool
    {
        return $this->id_image_path && str_starts_with($this->id_image_path, 'application-ids/private/')
            && ! str_contains($this->id_image_path, '..') && Storage::disk('local')->exists($this->id_image_path);
    }

    public function photoUrl(): ?string
    {
        return $this->hasPhoto() ? route('customer.apply.draft.photo', ['photo' => 'selfie', 'v' => $this->token]) : null;
    }

    public function idImageUrl(): ?string
    {
        return $this->hasIdImage() ? route('customer.apply.draft.photo', ['photo' => 'id', 'v' => $this->token]) : null;
    }

    public static function deletePhoto(?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'application-ids/private/') || str_contains($path, '..')) return;
        try {
            if (! Storage::disk('local')->delete($path)) {
                logger()->warning('Could not remove an obsolete draft photo.', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function pruneExpired(): int
    {
        $count = 0;
        static::query()->where('expires_at', '<=', now())->select(['id', 'user_id'])->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $removed = WorkflowTransaction::run(function () use ($row) {
                    // Use the same lock order as save, discard and final submission.
                    User::query()->whereKey($row->user_id)->lockForUpdate()->first();
                    $draft = static::query()->whereKey($row->id)->lockForUpdate()->first();
                    if (! $draft || $draft->expires_at->isFuture()) return null;
                    $paths = [$draft->photo_path, $draft->id_image_path];
                    $draft->delete();
                    return $paths;
                });
                if ($removed !== null) {
                    foreach ($removed as $path) static::deletePhoto($path);
                    $count++;
                }
            }
        });
        return $count;
    }
}
