<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayUser(): string
    {
        $role = ucfirst($this->user_role ?: 'System');

        if (! $this->user) {
            return $role;
        }

        return $role . ' (' . $this->user->name . ')';
    }
}
