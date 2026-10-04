<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationIdVerificationEvent extends Model
{
    protected $fillable = [
        'application_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
