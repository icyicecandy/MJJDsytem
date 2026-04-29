<?php

namespace App\Models;

use App\Support\PublicUpload;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

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
        'id_selfie_image_path',
        'agreed_to_terms',
        'agreed_to_privacy',
        'consented_at',
        'terms_version',
        'privacy_version',
        'rejection_reason',
        'serviceability_status',
        'slot_availability_status',
        'assessment_notes',
        'assessed_by',
        'assessed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'agreed_to_terms' => 'boolean',
            'agreed_to_privacy' => 'boolean',
            'consented_at' => 'datetime',
            'assessed_at' => 'datetime',
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

    public function assessor()
    {
        return $this->belongsTo(\App\Models\User::class, 'assessed_by');
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

    public function isTerminalForNewApplication(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function idImageUrl(): ?string
    {
        return PublicUpload::urlIfExists($this->id_image_path);
    }

    public function idSelfieImageUrl(): ?string
    {
        return PublicUpload::urlIfExists($this->id_selfie_image_path);
    }
}
