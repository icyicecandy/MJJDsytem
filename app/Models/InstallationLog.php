<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationLog extends Model
{
    protected $fillable = [
        'installation_id',
        'user_id',
        'notes',
    ];

    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
