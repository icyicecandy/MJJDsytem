<?php

namespace App\Models;

use App\Support\PublicUpload;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachment_path',
        'attachment_paths',
    ];

    protected function casts(): array
    {
        return [
            'attachment_paths' => 'array',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachmentUrl(): ?string
    {
        return PublicUpload::urlIfExists($this->attachment_path);
    }

    public function attachmentPaths(): array
    {
        $paths = PublicUpload::normalizePaths($this->attachment_paths);

        if (empty($paths) && filled($this->attachment_path)) {
            $paths[] = $this->attachment_path;
        }

        return $paths;
    }

    public function attachmentGalleryItems(): array
    {
        return collect($this->attachmentPaths())
            ->mapWithKeys(fn ($path, $index) => ['Message attachment ' . ($index + 1) => $path])
            ->all();
    }

    public function attachmentExists(): bool
    {
        return PublicUpload::exists($this->attachment_path);
    }

    public function hasImageAttachment(): bool
    {
        return $this->attachmentExists() && PublicUpload::isImage($this->attachment_path);
    }
}
