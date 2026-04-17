<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Ticket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'technician_id',
        'tracking_number',
        'subject',
        'description',
        'status',
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
        return $this->isQueued()
            ? 'Queued'
            : ucfirst(str_replace('_', ' ', $this->status));
    }

    public function displayStatusClass(): string
    {
        return $this->isQueued() ? 'warning' : $this->status;
    }

    public function syncAssignmentStatus(?int $technicianId = null, ?string $status = null): array
    {
        $technicianId = $technicianId ?: null;
        $status = $status ?: $this->status;

        if ($technicianId === null) {
            if (in_array($status, [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED], true)) {
                $status = self::STATUS_QUEUED;
            }

            if ($status === self::STATUS_OPEN) {
                $status = self::STATUS_QUEUED;
            }
        } elseif (in_array($status, [self::STATUS_OPEN, self::STATUS_QUEUED], true)) {
            $status = self::STATUS_IN_PROGRESS;
        }

        return [
            'technician_id' => $technicianId,
            'status' => $status,
        ];
    }
}
