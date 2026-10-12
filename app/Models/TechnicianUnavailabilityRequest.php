<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TechnicianUnavailabilityRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CLARIFICATION = 'clarification_requested';
    public const STATUS_REJECTED = 'rejected';

    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_CLARIFICATION,
    ];

    public const CATEGORIES = [
        'emergency' => 'Emergency',
        'medical' => 'Medical concern',
        'personal' => 'Personal matter',
        'official_business' => 'Official business',
        'planned_leave' => 'Planned leave',
        'other' => 'Other',
    ];

    protected $fillable = [
        'technician_id',
        'requested_start_at',
        'requested_end_at',
        'category',
        'reason',
        'evidence_path',
        'evidence_original_name',
        'status',
        'approved_start_at',
        'approved_end_at',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_start_at' => 'datetime',
            'requested_end_at' => 'datetime',
            'approved_start_at' => 'datetime',
            'approved_end_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    public function effectiveStartAt(): Carbon
    {
        return Carbon::parse($this->status === self::STATUS_APPROVED && $this->approved_start_at
            ? $this->approved_start_at
            : $this->requested_start_at);
    }

    public function effectiveEndAt(): Carbon
    {
        return Carbon::parse($this->status === self::STATUS_APPROVED && $this->approved_end_at
            ? $this->approved_end_at
            : $this->requested_end_at);
    }

    public function blocksPeriod(CarbonInterface|string $start, CarbonInterface|string $end): bool
    {
        if (! in_array($this->status, self::BLOCKING_STATUSES, true)) {
            return false;
        }

        $periodStart = Carbon::parse($start);
        $periodEnd = Carbon::parse($end);

        return $periodStart->lt($this->effectiveEndAt()) && $periodEnd->gt($this->effectiveStartAt());
    }

    public static function blocksPeriodFor(int $technicianId, CarbonInterface|string $start, CarbonInterface|string $end, ?int $ignoreRequestId = null): bool
    {
        return self::query()
            ->where('technician_id', $technicianId)
            ->blocking()
            ->when($ignoreRequestId, fn (Builder $query) => $query->whereKeyNot($ignoreRequestId))
            ->get()
            ->contains(fn (self $request) => $request->blocksPeriod($start, $end));
    }

    public function affectedInstallations(CarbonInterface|string|null $periodStart = null, CarbonInterface|string|null $periodEnd = null): Collection
    {
        $start = $periodStart ? Carbon::parse($periodStart) : $this->effectiveStartAt();
        $end = $periodEnd ? Carbon::parse($periodEnd) : $this->effectiveEndAt();

        return Installation::query()
            ->with(['application.user', 'technician'])
            ->where('technician_id', $this->technician_id)
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereDate('installation_date', '>=', $start->copy()->subDay()->toDateString())
            ->whereDate('installation_date', '<=', $end->copy()->addDay()->toDateString())
            ->orderBy('installation_date')
            ->orderBy('installation_time')
            ->get()
            ->filter(function (Installation $installation) use ($start, $end): bool {
                $jobStart = $installation->scheduledStartAt();
                $jobEnd = $installation->scheduledEndAt();

                return $jobStart && $jobEnd && $jobStart->lt($end) && $jobEnd->gt($start);
            })
            ->values();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CLARIFICATION => 'Clarification requested',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function reference(): string
    {
        return sprintf('UNAV-%s-%04d', $this->created_at?->format('Y') ?? now()->format('Y'), $this->id);
    }
}
