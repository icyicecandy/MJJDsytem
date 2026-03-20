<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'activated_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
