<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSetting extends Model
{
    protected $fillable = [
        'business_name',
        'phone',
        'support_email',
        'office_address',
        'business_hours',
        'facebook_url',
        'map_url',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'business_name' => 'MJJD Telecom',
            'phone' => '0985 075 7176',
            'support_email' => 'support@mjjdtelecom.com',
            'office_address' => 'Dumlao St. Dusoc, Bayambang, Pangasinan',
            'business_hours' => 'Mon - Fri: 8:00 AM - 5:00 PM',
        ]);
    }
}
