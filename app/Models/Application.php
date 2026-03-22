<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference_no',
        'first_name',
        'last_name',
        'email',
        'mobile_number',
        'region',
        'province',
        'municipality',
        'barangay',
        'street_landmark',
        'lat',
        'lng',
        'plan_code',
        'id_image_path',
        'status',
    ];


    protected function casts(): array
    {
        return [
            'agreed_to_terms' => 'boolean',
            'agreed_to_privacy' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }
    public function installation()
    {
        return $this->hasOne(Installation::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function fullServiceAddress(): string
    {
        return collect([
            $this->street_landmark,
            $this->barangay,
            $this->municipality,
            $this->province,
            $this->region,
        ])->filter()->implode(', ');
    }
}

