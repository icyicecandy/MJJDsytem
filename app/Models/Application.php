<?php

namespace App\Models;

use App\Support\ApplicationIdUpload;
use App\Support\ApplicationIdentity;
use App\Support\ApplicationContact;
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
        'valid_id_type',
        'valid_id_type_other',
        'valid_id_number',
        'valid_id_number_fingerprint',
        'id_image_hash',
        'duplicate_id_number_detected',
        'duplicate_id_image_detected',
        'id_verification_status',
        'id_verification_reason',
        'id_verified_by',
        'id_verified_at',
        'agreed_to_terms',
        'agreed_to_privacy',
        'consented_at',
        'terms_version',
        'privacy_version',
        'rejection_reason',
        'cancellation_reason',
        'cancellation_previous_status',
        'serviceability_status',
        'slot_availability_status',
        'assessment_notes',
        'assessed_by',
        'assessed_at',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (Application $application) {
            if ($application->isDirty('email')) {
                $application->email = ApplicationContact::email($application->email);
            }
            if ($application->isDirty('mobile_number')) {
                $application->mobile_number = ApplicationContact::phone($application->mobile_number);
            }
            $application->email_key = ApplicationContact::key('email', $application->email);
            $application->mobile_number_key = ApplicationContact::key('mobile_number', $application->mobile_number);
        });
    }

    protected function casts(): array
    {
        return [
            'agreed_to_terms' => 'boolean',
            'agreed_to_privacy' => 'boolean',
            'consented_at' => 'datetime',
            'assessed_at' => 'datetime',
            'valid_id_number' => 'encrypted',
            'duplicate_id_number_detected' => 'boolean',
            'duplicate_id_image_detected' => 'boolean',
            'id_verified_at' => 'datetime',
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

    public function idVerifier()
    {
        return $this->belongsTo(User::class, 'id_verified_by');
    }

    public function idVerificationEvents()
    {
        return $this->hasMany(ApplicationIdVerificationEvent::class)->latest();
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

    public function sequentialReference(): string
    {
        $year = $this->created_at?->format('Y') ?? now()->format('Y');

        return sprintf('APP-%s-%06d', $year, $this->getKey());
    }

    public function isTerminalForNewApplication(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_ACTIVATED,
        ], true);
    }

    public function idImageUrl(): ?string
    {
        return ApplicationIdUpload::exists($this->id_image_path) ? route('applications.id-photo', [$this, 'id']) : null;
    }

    public function idSelfieImageUrl(): ?string
    {
        return ApplicationIdUpload::exists($this->id_selfie_image_path) ? route('applications.id-photo', [$this, 'selfie']) : null;
    }

    public function validIdTypeLabel(): string
    {
        return ApplicationIdentity::typeLabel($this->valid_id_type, $this->valid_id_type_other);
    }

    public function maskedValidIdNumber(): string
    {
        return ApplicationIdentity::maskedNumber($this->valid_id_number);
    }

    public function hasPotentialIdentityDuplicate(): bool
    {
        return $this->duplicate_id_image_detected;
    }

    public function nextActionGuidance(): string
    {
        if (in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW], true)) {
            return match ($this->id_verification_status) {
                ApplicationIdentity::STATUS_PENDING => 'Verify the identity documents before approval.',
                ApplicationIdentity::STATUS_REJECTED => 'Waiting for the customer to submit replacement identity photos.',
                ApplicationIdentity::STATUS_VERIFIED => 'Confirm serviceability and slot availability, then approve or reject the application.',
                default => 'Review the application and complete identity verification before approval.',
            };
        }

        return match ($this->status) {
            self::STATUS_APPROVED => 'Assign a technician and select an available installation schedule.',
            self::STATUS_SCHEDULED => match (true) {
                $this->installation?->hasPendingCustomerRescheduleRequest() => 'Review the customer reschedule request.',
                $this->installation?->status === Installation::STATUS_RESCHEDULE_REQUIRED => 'Assign a new available installation schedule.',
                default => 'Waiting for the assigned technician to start the installation.',
            },
            self::STATUS_CANCELLATION_REQUESTED => 'Administrator must approve or reject the cancellation request.',
            self::STATUS_INSTALLED, 'completed' => 'Technician must confirm service activation.',
            self::STATUS_ACTIVATED => 'No further action is required. Service is activated.',
            self::STATUS_REJECTED => 'Application processing is complete. The customer may submit a new application.',
            self::STATUS_CANCELLED => 'No further action is required. The application is cancelled.',
            default => 'Review the current application status and recorded history.',
        };
    }

    public function nextActionTone(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVATED => 'success',
            self::STATUS_REJECTED, self::STATUS_CANCELLED => 'muted',
            self::STATUS_CANCELLATION_REQUESTED => 'warning',
            default => $this->id_verification_status === ApplicationIdentity::STATUS_REJECTED ? 'warning' : 'info',
        };
    }
}
