<?php

namespace App\Models;

use App\Support\PublicUpload;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'customer_id',
        'assigned_staff_id',
        'from_user_id',
        'to_user_id',
        'message',
        'sender_type',
        'message_type',
        'conversation_status',
        'handoff_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function senderLabel(): string
    {
        return match ($this->sender_type) {
            'ai' => 'MJJD AI Assistant',
            'system' => 'System',
            'customer' => optional($this->fromUser)->name ?? 'Customer',
            'agent' => optional($this->fromUser)->name ?? 'Support Agent',
            default => optional($this->fromUser)->name ?? 'Support',
        };
    }

    public function attachmentPath(): ?string
    {
        return $this->attachmentItems()[0]['path'] ?? null;
    }

    public function attachmentUrl(): ?string
    {
        return PublicUpload::urlIfExists($this->attachmentPath());
    }

    public function attachmentExists(): bool
    {
        return PublicUpload::exists($this->attachmentPath());
    }

    public function attachmentOriginalName(): ?string
    {
        return $this->attachmentItems()[0]['original_name'] ?? null;
    }

    public function hasImageAttachment(): bool
    {
        return $this->attachmentExists() && PublicUpload::isImage($this->attachmentPath());
    }

    public function attachmentItems(): array
    {
        $metadata = $this->metadata ?? [];
        $attachments = $metadata['attachments'] ?? null;

        if (! is_array($attachments) || empty($attachments)) {
            $attachments = filled($metadata['attachment_path'] ?? null)
                ? [[
                    'path' => $metadata['attachment_path'],
                    'original_name' => $metadata['attachment_original_name'] ?? null,
                    'mime' => $metadata['attachment_mime'] ?? null,
                ]]
                : [];
        }

        return collect($attachments)
            ->map(function (array $item) {
                $path = PublicUpload::normalizePath($item['path'] ?? $item['attachment_path'] ?? null);

                if (! $path) {
                    return null;
                }

                $url = PublicUpload::urlIfExists($path);

                return [
                    'path' => $path,
                    'url' => $url,
                    'original_name' => $item['original_name'] ?? $item['attachment_original_name'] ?? basename($path),
                    'mime' => $item['mime'] ?? $item['attachment_mime'] ?? null,
                    'is_image' => PublicUpload::isImage($path),
                    'missing' => ! $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
