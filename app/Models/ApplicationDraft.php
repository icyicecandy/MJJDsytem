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

    public function photoUrl(): ?string
    {
        return $this->hasPhoto() ? route('customer.apply.draft.photo', ['v' => $this->token]) : null;
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
                    $path = $draft->photo_path;
                    $draft->delete();
                    return ['path' => $path];
                });
                if ($removed !== null) {
                    static::deletePhoto($removed['path']);
                    $count++;
                }
            }
        });
        return $count;
    }
}
