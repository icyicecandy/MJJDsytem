<?php

namespace App\Models;

use App\Support\PublicUpload;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Installation extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESCHEDULE_REQUIRED = 'reschedule_required';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_SCHEDULING_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
    ];

    public const TECHNICIAN_STATUS_TRANSITIONS = [
        self::STATUS_SCHEDULED => [self::STATUS_IN_PROGRESS],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_RESCHEDULE_REQUIRED],
        self::STATUS_RESCHEDULE_REQUIRED => [],
        self::STATUS_COMPLETED => [self::STATUS_ACTIVATED],
        self::STATUS_ACTIVATED => [],
    ];

    public const AVAILABLE_TIME_SLOTS = [
        '08:00',
        '11:00',
        '14:00',
    ];

    public const SLOT_DURATION_MINUTES = 180;

    public const MAX_DAILY_INSTALLATIONS = 3;

    protected $fillable = [
        'application_id',
        'technician_id',
        'assigned_at',
        'installation_date',
        'installation_time',
        'status',
        'cancellation_previous_status',
        'stop_work_requested_at',
        'stop_work_reviewed_at',
        'stop_work_reviewed_by',
        'stopped_at',
        'stop_work_technician_notes',
        'stop_work_admin_notes',
        'completed_at',
        'activation_date',
        'activation_status',
        'technician_remarks',
        'reassignment_reason',
        'customer_reschedule_reason',
        'customer_rescheduled_at',
        'reschedule_requested_at',
        'reschedule_preferred_date',
        'reschedule_preferred_time',
        'reschedule_request_reason',
        'reschedule_reviewed_at',
        'reschedule_rejection_reason',
        'nap_code',
        'port_number',
        'equipment_serial_number',
        'cable_length',
        'installation_photo_path',
        'installation_photo_paths',
        'speedtest_photo_path',
        'nap_photo_path',
        'router_photo_path',
        'acceptance_report_photo_path',
        'cable_reel_photo_path',
        'customer_signature_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'stop_work_requested_at' => 'datetime',
            'stop_work_reviewed_at' => 'datetime',
            'stopped_at' => 'datetime',
            'customer_rescheduled_at' => 'datetime',
            'installation_date' => 'date',
            'reschedule_requested_at' => 'datetime',
            'reschedule_preferred_date' => 'date',
            'reschedule_reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'activation_date' => 'date',
            'installation_photo_paths' => 'array',
        ];
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function technician()
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function stopWorkReviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'stop_work_reviewed_by');
    }

    public function hasPendingStopWorkRequest(): bool
    {
        return filled($this->stop_work_requested_at) && blank($this->stop_work_reviewed_at);
    }

    public function logs()
    {
        return $this->hasMany(InstallationLog::class)->latest();
    }

    public function displayJobReference(): string
    {
        $year = $this->installation_date?->format('Y')
            ?? $this->created_at?->format('Y')
            ?? now()->format('Y');

        return sprintf('JOB-%s-%04d', $year, $this->id);
    }

    public function displayStatusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status ?? 'unknown'));
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isActivated(): bool
    {
        return $this->status === self::STATUS_ACTIVATED;
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, self::completedStatuses(), true);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isRescheduleRequired(): bool
    {
        return $this->status === self::STATUS_RESCHEDULE_REQUIRED;
    }

    public function customerRescheduleMessage(): string
    {
        return 'Your installation has been rescheduled to '.$this->installation_date?->format('F d, Y')
            .' at '.self::slotLabel(Carbon::parse($this->installation_time)->format('H:i'))
            .' ('.config('app.timezone').'). Reason: '.$this->customer_reschedule_reason;
    }

    public function canBeRescheduledByAdmin(): bool
    {
        return $this->status === self::STATUS_RESCHEDULE_REQUIRED
            || ($this->status === self::STATUS_SCHEDULED && $this->scheduledStartAt()?->lte(now()));
    }

    public function hasPendingCustomerRescheduleRequest(): bool
    {
        return filled($this->reschedule_requested_at) && blank($this->reschedule_reviewed_at);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TECHNICIAN_STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function scheduledStartAt(): ?Carbon
    {
        if (! $this->installation_date || ! $this->installation_time) {
            return null;
        }

        return Carbon::parse($this->installation_date->format('Y-m-d').' '.$this->installation_time);
    }

    public function scheduledEndAt(): ?Carbon
    {
        return $this->scheduledStartAt()?->copy()->addMinutes(self::SLOT_DURATION_MINUTES);
    }

    public static function slotLabel(string $time): string
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:i A');
    }

    public static function activeSchedulingStatuses(): array
    {
        return self::ACTIVE_SCHEDULING_STATUSES;
    }

    public static function completedStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_ACTIVATED,
        ];
    }

    public function recordDetails(): array
    {
        return [
            'Installation Status' => $this->displayStatusLabel(),
            'Assigned At' => $this->assigned_at?->displayDateTime('F d, Y h:i A'),
            'Scheduled Date' => $this->installation_date?->format('F d, Y'),
            'Scheduled Time' => $this->installation_time ? self::slotLabel(Carbon::parse($this->installation_time)->format('H:i')) : null,
            'Completed At' => $this->completed_at?->displayDateTime('F d, Y h:i A'),
            'Activation Date' => $this->activation_date?->format('F d, Y'),
        ];
    }

    public function technicalDetails(): array
    {
        return [
            'NAP Code' => $this->nap_code,
            'Port No.' => $this->port_number,
            'Equipment Serial No.' => $this->equipment_serial_number,
            'Cable Length' => $this->cable_length,
        ];
    }

    public function hasTechnicalDetails(): bool
    {
        return collect($this->technicalDetails())->filter(fn ($value) => filled($value))->isNotEmpty();
    }

    public function proofPhotos(): array
    {
        $installationPhotos = collect(PublicUpload::normalizePaths($this->installation_photo_paths))
            ->when(empty(PublicUpload::normalizePaths($this->installation_photo_paths)) && filled($this->installation_photo_path), fn ($photos) => $photos->push($this->installation_photo_path))
            ->values()
            ->mapWithKeys(fn ($path, $index) => ['Installation Photo '.($index + 1) => $path])
            ->all();

        return $installationPhotos + [
            'Speed Test Photo' => $this->speedtest_photo_path,
            'NAP Photo' => $this->nap_photo_path,
            'Router Photo' => $this->router_photo_path,
            'Acceptance Report Photo' => $this->acceptance_report_photo_path,
            'Cable Reel Photo' => $this->cable_reel_photo_path,
            'Customer Signature Photo' => $this->customer_signature_photo_path,
        ];
    }
}
