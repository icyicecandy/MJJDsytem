<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Support\PublicUpload;
use Illuminate\Support\Facades\Schema;

class Ticket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const URGENCY_LOW = 'low';
    public const URGENCY_MEDIUM = 'medium';
    public const URGENCY_HIGH = 'high';
    public const URGENCY_CRITICAL = 'critical';

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
            'urgency' => self::URGENCY_MEDIUM,
        ],
    ];

    protected $fillable = [
        'user_id',
        'technician_id',
        'tracking_number',
        'subject',
        'description',
        'status',
        'urgency',
        'photo_path',
    ];

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

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function scopeQueueOrdered($query)
    {
        return $query
            ->orderByRaw("CASE WHEN technician_id IS NULL AND status IN ('open', 'queued') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN technician_id IS NULL AND status IN ('open', 'queued') THEN created_at END ASC")
            ->latest('updated_at');
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
        return $this->status !== self::STATUS_CLOSED;
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

    public static function resolveUrgencyForSubject(string $subjectKeyOrLabel): string
    {
        if (isset(self::SUBJECT_CATALOG[$subjectKeyOrLabel])) {
            return self::SUBJECT_CATALOG[$subjectKeyOrLabel]['urgency'];
        }

        $matched = collect(self::SUBJECT_CATALOG)
            ->firstWhere('label', $subjectKeyOrLabel);

        return $matched['urgency'] ?? self::URGENCY_MEDIUM;
    }

    public function displayUrgencyLabel(): string
    {
        return ucfirst($this->urgency ?: self::resolveUrgencyForSubject($this->subject));
    }

    public function displayUrgencyClass(): string
    {
        return $this->urgency ?: self::resolveUrgencyForSubject($this->subject);
    }

    public function photoUrl(): ?string
    {
        return PublicUpload::urlIfExists($this->photo_path);
    }

    public function syncAssignmentStatus(?int $technicianId = null, ?string $status = null): array
    {
        $technicianId = $technicianId ?: null;
        $status = $status ?: $this->status;

        if ($technicianId === null) {
            if ($status === self::STATUS_IN_PROGRESS) {
                $status = self::STATUS_OPEN;
            }

            if ($status === self::STATUS_QUEUED) {
                $status = self::STATUS_OPEN;
            }
        } elseif ($status === self::STATUS_QUEUED) {
            $status = self::STATUS_OPEN;
        }

        return [
            'technician_id' => $technicianId,
            'status' => $status,
        ];
    }
}
