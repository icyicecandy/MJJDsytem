<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'speed_mbps',
        'installation_fee',
        'description',
        'features',
        'badge',
        'is_featured',
        'is_active',
        'display_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'installation_fee' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('speed_mbps')->orderBy('name');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function displayName(): string
    {
        return $this->name.' '.$this->speed_mbps.' Mbps';
    }

    public function formattedInstallationFee(): string
    {
        return 'PHP '.$this->formattedFeeAmount();
    }

    public function formattedFeeAmount(): string
    {
        return rtrim(rtrim(number_format((float) $this->installation_fee, 2), '0'), '.');
    }
}
