<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqResponse extends Model
{
    protected $fillable = [
        'question',
        'keywords',
        'answer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
