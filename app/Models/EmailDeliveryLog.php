<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDeliveryLog extends Model
{
    protected $fillable = [
        'user_id',
        'notification_type',
        'recipient',
        'status',
        'attempts',
        'retry_status',
        'last_attempted_at',
        'sent_at',
        'failed_at',
        'error_message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'last_attempted_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
