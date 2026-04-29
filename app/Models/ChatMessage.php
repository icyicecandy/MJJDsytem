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
        return PublicUpload::normalizePath($this->metadata['attachment_path'] ?? null);
    }

    public function attachmentUrl(): ?string
    {
        return PublicUpload::url($this->attachmentPath());
    }

    public function attachmentOriginalName(): ?string
    {
        return $this->metadata['attachment_original_name'] ?? null;
    }

    public function hasImageAttachment(): bool
    {
        return PublicUpload::isImage($this->attachmentPath());
    }
}
