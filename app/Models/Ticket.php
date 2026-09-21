<?php

namespace App\Models;

use App\Support\PublicUpload;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Ticket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const URGENCY_LOW = 'low';

    public const URGENCY_MEDIUM = 'medium';

    public const URGENCY_HIGH = 'high';

    public const URGENCY_CRITICAL = 'critical';

    public const URGENCY_LEVELS = [
        self::URGENCY_CRITICAL,
        self::URGENCY_HIGH,
        self::URGENCY_MEDIUM,
        self::URGENCY_LOW,
    ];

    public const AVAILABLE_TIME_SLOTS = [
        '08:00',
        '09:00',
        '10:00',
        '11:00',
        '13:00',
        '14:00',
        '15:00',
        '16:00',
    ];

    public const APPOINTMENT_DURATION_MINUTES = 60;

    public const SUBJECT_CATALOG = [
        'no_internet_connection' => [
            'label' => 'No Internet Connection',
            'urgency' => self::URGENCY_HIGH,
        ],
        'slow_or_intermittent_connection' => [
            'label' => 'Slow or Intermittent Connection',
            'urgency' => self::URGENCY_MEDIUM,
        ],
        'service_outage_in_area' => [
            'label' => 'Service Outage in Area',
            'urgency' => self::URGENCY_CRITICAL,
        ],
        'damaged_line_or_equipment' => [
            'label' => 'Damaged Line or Equipment',
            'urgency' => self::URGENCY_HIGH,
        ],
        'installation_follow_up' => [
            'label' => 'Installation Follow-up',
            'urgency' => self::URGENCY_MEDIUM,
        ],
        'new_or_another_installation_request' => [
            'label' => 'Request for New/Another Installation',
            'urgency' => self::URGENCY_MEDIUM,
        ],
        'account_access_issue' => [
            'label' => 'Account Access or Login Issue',
            'urgency' => self::URGENCY_MEDIUM,
        ],
        'relocation_request' => [
            'label' => 'Relocation Request',
            'urgency' => self::URGENCY_LOW,
        ],
        'plan_upgrade_downgrade' => [
            'label' => 'Plan Upgrade or Downgrade',
            'urgency' => self::URGENCY_LOW,
        ],
        'other_technical_concern' => [
            'label' => 'Other Technical Concern',
            'urgency' => null,
            'requires_admin_triage' => true,
        ],
    ];

    protected $fillable = [
        'user_id',
        'technician_id',
        'assigned_at',
        'assignment_source',
        'response_due_at',
        'appointment_date',
        'appointment_time',
        'appointment_change_reason',
        'appointment_changed_at',
        'tracking_number',
        'subject',
        'description',
        'status',
        'urgency',
        'urgency_review_required',
        'triaged_at',
        'triaged_by',
        'photo_path',
        'photo_paths',
        'reopen_requested_at',
        'reopen_reason',
        'reopen_reviewed_at',
        'reopen_reviewed_by',
    ];

    public const ACTIVE_SCHEDULING_STATUSES = [
        self::STATUS_OPEN, self::STATUS_QUEUED, self::STATUS_IN_PROGRESS, self::STATUS_CANCELLATION_REQUESTED,
    ];

    public function scheduledStartAt(): ?\Carbon\Carbon
    {
        return $this->appointment_date && $this->appointment_time
            ? \Carbon\Carbon::parse($this->appointment_date->format('Y-m-d').' '.$this->appointment_time)
            : null;
    }

    public function scheduledEndAt(): ?\Carbon\Carbon
    {
        return $this->scheduledStartAt()?->addMinutes(self::APPOINTMENT_DURATION_MINUTES);
    }

    public function appointmentLabel(): string
    {
        return $this->scheduledStartAt()
            ? $this->scheduledStartAt()->format('M d, Y g:i A').' - '.$this->scheduledEndAt()->format('g:i A')
            : 'Not scheduled';
    }

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'appointment_changed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'response_due_at' => 'datetime',
            'urgency_review_required' => 'boolean',
            'triaged_at' => 'datetime',
            'reopen_requested_at' => 'datetime',
            'reopen_reviewed_at' => 'datetime',
            'photo_paths' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket): void {
            if ($ticket->tracking_number) {
                return;
            }

            $ticket->forceFill([
                'tracking_number' => self::generateTrackingNumber($ticket->id, $ticket->created_at),
            ])->saveQuietly();
        });
    }

    public static function generateTrackingNumber(int $ticketId, CarbonInterface|string|null $createdAt = null): string
    {
        $timestamp = $createdAt instanceof CarbonInterface
            ? $createdAt
            : ($createdAt ? now()->parse($createdAt) : now());

        return sprintf('TKT-%s-%04d', $timestamp->format('Y'), $ticketId);
    }

    public function publicReference(): string
    {
        return $this->tracking_number ?: self::generateTrackingNumber($this->id, $this->created_at);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function reopenReviewer()
    {
        return $this->belongsTo(User::class, 'reopen_reviewed_by');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function scopePriorityOrdered($query)
    {
        return $query
            ->orderByRaw("CASE urgency WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->oldest('created_at');
    }

    public function scopeQueueOrdered($query)
    {
        return $query
            ->orderByRaw("CASE WHEN technician_id IS NULL AND status IN ('open', 'queued') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status IN ('open', 'queued') THEN 1 ELSE 2 END")
            ->priorityOrdered();
    }

    public function scopeAssignedTo($query, User $user)
    {
        return $query->where('technician_id', $user->id);
    }

    public function isQueued(): bool
    {
        return $this->technician_id === null && in_array($this->status, [self::STATUS_OPEN, self::STATUS_QUEUED], true);
    }

    public function displayStatusLabel(): string
    {
        if ($this->hasPendingReopenRequest()) {
            return 'Reopen Requested';
        }

        return $this->status === self::STATUS_QUEUED
            ? 'Open'
            : ucfirst(str_replace('_', ' ', $this->status));
    }

    public function displayStatusClass(): string
    {
        return $this->status === self::STATUS_QUEUED ? self::STATUS_OPEN : $this->status;
    }

    public function isOpenForWork(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_QUEUED], true);
    }

    public function canReceiveReplies(): bool
    {
        return ! $this->isReadOnly();
    }

    public function isCancellationRequested(): bool
    {
        return $this->status === self::STATUS_CANCELLATION_REQUESTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReadOnly(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function hasPendingReopenRequest(): bool
    {
        return $this->status === self::STATUS_CLOSED
            && filled($this->reopen_requested_at)
            && blank($this->reopen_reviewed_at);
    }

    public function lifecycleAttributes(array $attributes): array
    {
        $table = $this->getTable();

        return collect($attributes)
            ->filter(fn ($value, string $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    public static function subjectOptions(): array
    {
        return self::SUBJECT_CATALOG;
    }

    public static function subjectKeys(): array
    {
        return array_keys(self::SUBJECT_CATALOG);
    }

    public static function resolveSubjectLabel(string $subjectKeyOrLabel): string
    {
        return self::SUBJECT_CATALOG[$subjectKeyOrLabel]['label'] ?? $subjectKeyOrLabel;
    }

    public static function resolveUrgencyForSubject(string $subjectKeyOrLabel): ?string
    {
        if (array_key_exists($subjectKeyOrLabel, self::SUBJECT_CATALOG)) {
            return self::SUBJECT_CATALOG[$subjectKeyOrLabel]['urgency'];
        }

        $matched = collect(self::SUBJECT_CATALOG)
            ->firstWhere('label', $subjectKeyOrLabel);

        return $matched ? $matched['urgency'] : self::URGENCY_MEDIUM;
    }

    public static function subjectRequiresAdminTriage(string $subjectKeyOrLabel): bool
    {
        if (array_key_exists($subjectKeyOrLabel, self::SUBJECT_CATALOG)) {
            return (bool) (self::SUBJECT_CATALOG[$subjectKeyOrLabel]['requires_admin_triage'] ?? false);
        }

        $matched = collect(self::SUBJECT_CATALOG)
            ->firstWhere('label', $subjectKeyOrLabel);

        return (bool) ($matched['requires_admin_triage'] ?? false);
    }

    public static function activeWorkStatuses(): array
    {
        return [self::STATUS_OPEN, self::STATUS_QUEUED, self::STATUS_IN_PROGRESS];
    }

    public static function responseDueAtForUrgency(?string $urgency, CarbonInterface|string|null $from = null): ?Carbon
    {
        if (! $urgency) {
            return null;
        }

        $hours = (int) config('ticket-routing.response_hours.'.$urgency, 8);
        $start = $from instanceof CarbonInterface
            ? Carbon::instance($from)
            : ($from ? Carbon::parse($from) : now());

        return $start->copy()->addHours($hours);
    }

    public function displayUrgencyLabel(): string
    {
        if ($this->urgency_review_required) {
            return 'Pending Review';
        }

        return ucfirst($this->urgency ?: self::URGENCY_MEDIUM);
    }

    public function displayUrgencyClass(): string
    {
        return $this->urgency_review_required
            ? 'medium'
            : ($this->urgency ?: self::URGENCY_MEDIUM);
    }

    public function responseTargetLabel(): string
    {
        return $this->response_due_at
            ? $this->response_due_at->displayDateTime('M d, Y h:i A')
            : 'Pending urgency review';
    }

    public function photoUrl(): ?string
    {
        return PublicUpload::urlIfExists($this->photo_path);
    }

    public function photoPaths(): array
    {
        $paths = PublicUpload::normalizePaths($this->photo_paths);

        if (empty($paths) && filled($this->photo_path)) {
            $paths[] = $this->photo_path;
        }

        return $paths;
    }

    public function photoGalleryItems(): array
    {
        return collect($this->photoPaths())
            ->mapWithKeys(fn ($path, $index) => ['Ticket photo '.($index + 1) => $path])
            ->all();
    }

    public function syncAssignmentStatus(?int $technicianId = null, ?string $status = null): array
    {
        $technicianId = $technicianId ?: null;
        $status = $status ?: $this->status;

        if ($technicianId === null && in_array($status, [
            self::STATUS_OPEN,
            self::STATUS_QUEUED,
            self::STATUS_IN_PROGRESS,
        ], true)) {
            $status = self::STATUS_QUEUED;
        } elseif ($status === self::STATUS_QUEUED) {
            $status = self::STATUS_OPEN;
        }

        return [
            'technician_id' => $technicianId,
            'status' => $status,
        ];
    }
}
