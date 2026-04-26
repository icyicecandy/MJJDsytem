<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Installation extends Model
{
    public const ACTIVE_SCHEDULING_STATUSES = ['scheduled', 'assigned', 'in_progress'];
    public const TECHNICIAN_STATUS_TRANSITIONS = [
        'scheduled' => ['in_progress'],
        'in_progress' => ['completed'],
        'completed' => [],
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

    public const SLOT_DURATION_MINUTES = 60;

    protected $fillable = [
        'application_id',
        'technician_id',
        'assigned_at',
        'installation_date',
        'installation_time',
        'status',
        'completed_at',
        'technician_remarks',
        'installation_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'installation_date' => 'date',
            'completed_at' => 'datetime',
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
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
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

        return Carbon::parse($this->installation_date->format('Y-m-d') . ' ' . $this->installation_time);
    }

    public function scheduledEndAt(): ?Carbon
    {
        return $this->scheduledStartAt()?->copy()->addMinutes(self::SLOT_DURATION_MINUTES);
    }

    public static function activeSchedulingStatuses(): array
    {
        return self::ACTIVE_SCHEDULING_STATUSES;
    }

    public function recordDetails(): array
    {
        return [
            'Installation Status' => $this->displayStatusLabel(),
            'Assigned At' => $this->assigned_at?->displayDateTime('F d, Y h:i A'),
            'Scheduled Date' => $this->installation_date?->format('F d, Y'),
            'Scheduled Time' => $this->installation_time ? \Carbon\Carbon::parse($this->installation_time)->format('h:i A') : null,
            'Completed At' => $this->completed_at?->displayDateTime('F d, Y h:i A'),
        ];
    }

    public function proofPhotos(): array
    {
        return [
            'Installation Photo' => $this->installation_photo_path,
        ];
    }
}
