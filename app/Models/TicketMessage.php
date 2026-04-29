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
    ];

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
        return PublicUpload::url($this->attachment_path);
    }

    public function hasImageAttachment(): bool
    {
        return PublicUpload::isImage($this->attachment_path);
    }
}
