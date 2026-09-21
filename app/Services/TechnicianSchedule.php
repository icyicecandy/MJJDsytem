<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TechnicianSchedule
{
    public const CONFLICT_MESSAGE = 'This technician already has an installation or ticket appointment during that time slot.';

    public const WEEKDAY_MESSAGE = 'Installations can only be scheduled from Monday through Friday.';

    public const WORKDAY_MESSAGE = 'This technician is not scheduled to work on the selected day.';

    public const SHIFT_MESSAGE = 'The selected installation time is outside this technician\'s working hours.';

    public const BREAK_MESSAGE = 'The selected installation time overlaps this technician\'s break period.';

    public const DAILY_LIMIT_MESSAGE = 'This technician already has the maximum number of installations for that day.';

    public static function eligibleTechnician(int $id): User
    {
        $technician = User::find($id);
        if (! $technician || ! $technician->isTechnician() || $technician->isDisabled()) {
            throw ValidationException::withMessages(['technician_id' => 'Choose an active technician. Disabled accounts cannot receive assignments.']);
        }

        if ($technician->technician_status === 'on_leave') {
            throw ValidationException::withMessages(['technician_id' => 'This technician is currently marked as on leave.']);
        }

        return $technician;
    }

    public static function assertFuture(string $date, string $time, string $dateField = 'installation_date', string $timeField = 'installation_time'): void
    {
        $start = Carbon::parse($date.' '.$time);
        if ($start->lte(now())) {
            throw ValidationException::withMessages([
                $start->toDateString() < now()->toDateString() ? $dateField : $timeField => 'Choose an appointment date and time in the future.',
            ]);
        }
    }

    public static function assertInstallationWindow(
        string $date,
        string $time,
        string $dateField = 'installation_date',
        string $timeField = 'installation_time'
    ): void {
        self::assertFuture($date, $time, $dateField, $timeField);

        $start = Carbon::parse($date.' '.$time);
        if ($start->isWeekend()) {
            throw ValidationException::withMessages([$dateField => self::WEEKDAY_MESSAGE]);
        }

        if (! in_array($start->format('H:i'), Installation::AVAILABLE_TIME_SLOTS, true)) {
            throw ValidationException::withMessages([
                $timeField => 'Choose one of the available installation times.',
            ]);
        }
    }

    public static function assertInstallationAvailable(
        User $technician,
        Application $application,
        string $date,
        string $time,
        ?int $ignoreInstallationId = null,
        string $dateField = 'installation_date',
        string $timeField = 'installation_time'
    ): void {
        self::assertInstallationWindow($date, $time, $dateField, $timeField);

        $issue = self::installationAvailabilityIssue(
            $technician,
            $application,
            $date,
            $time,
            $ignoreInstallationId,
            $dateField,
            $timeField
        );

        if ($issue !== null) {
            throw ValidationException::withMessages([$issue['field'] => $issue['message']]);
        }
    }

    public static function installationAvailabilityIssue(
        User $technician,
        Application $application,
        string $date,
        string $time,
        ?int $ignoreInstallationId = null,
        string $dateField = 'installation_date',
        string $timeField = 'installation_time',
        ?Collection $bookings = null,
        ?Collection $dailyInstallations = null
    ): ?array {
        $start = Carbon::parse($date.' '.$time);
        $end = $start->copy()->addMinutes(Installation::SLOT_DURATION_MINUTES);

        if (! $technician->worksOnDate($date)) {
            return ['field' => $dateField, 'message' => self::WORKDAY_MESSAGE];
        }

        $shiftStart = Carbon::parse($date.' '.$technician->technicianShiftStart());
        $shiftEnd = Carbon::parse($date.' '.$technician->technicianShiftEnd());
        if ($start->lt($shiftStart) || $end->gt($shiftEnd)) {
            return ['field' => $timeField, 'message' => self::SHIFT_MESSAGE];
        }

        if (self::overlapsBreak($technician, $start, $end)) {
            return ['field' => $timeField, 'message' => self::BREAK_MESSAGE];
        }

        $hasConflict = $bookings === null
            ? self::conflicts($technician->id, $date, $time, $ignoreInstallationId)
            : $bookings
                ->where('technician_id', $technician->id)
                ->contains(function ($booking) use ($start, $end) {
                    $existingStart = $booking->scheduledStartAt();
                    $existingEnd = $booking->scheduledEndAt();

                    return $existingStart && $existingEnd
                        && $start->lt($existingEnd)
                        && $end->gt($existingStart);
                });

        if ($hasConflict) {
            return ['field' => $timeField, 'message' => self::CONFLICT_MESSAGE];
        }

        $dailyCount = $dailyInstallations === null
            ? Installation::query()
                ->where('technician_id', $technician->id)
                ->whereDate('installation_date', $start->toDateString())
                ->whereIn('status', Installation::activeSchedulingStatuses())
                ->when($ignoreInstallationId, fn ($query) => $query->whereKeyNot($ignoreInstallationId))
                ->count()
            : $dailyInstallations
                ->where('technician_id', $technician->id)
                ->filter(fn (Installation $installation) => $installation->installation_date?->format('Y-m-d') === $start->toDateString())
                ->count();

        if ($dailyCount >= Installation::MAX_DAILY_INSTALLATIONS) {
            return ['field' => $timeField, 'message' => self::DAILY_LIMIT_MESSAGE];
        }

        $routeIssue = self::routeLimitIssue(
            $technician->id,
            $application,
            $date,
            $time,
            $ignoreInstallationId,
            $dailyInstallations
        );

        if ($routeIssue !== null) {
            return ['field' => $timeField, 'message' => $routeIssue];
        }

        return null;
    }

    public static function availableTechniciansForInstallation(
        Collection $technicians,
        Application $application,
        string $date,
        string $time,
        ?int $ignoreInstallationId = null,
        ?Collection $bookings = null,
        ?Collection $dailyInstallations = null
    ): Collection {
        return $technicians
            ->filter(fn (User $technician) => ! $technician->isDisabled()
                && $technician->technician_status !== 'on_leave'
                && self::installationAvailabilityIssue(
                    $technician,
                    $application,
                    $date,
                    $time,
                    $ignoreInstallationId,
                    bookings: $bookings,
                    dailyInstallations: $dailyInstallations
                ) === null)
            ->values();
    }

    public static function installationAvailabilityGrid(
        Collection $technicians,
        Application $application,
        \Carbon\CarbonInterface|string $startDate,
        \Carbon\CarbonInterface|string $endDate,
        ?int $ignoreInstallationId = null,
        ?string $excludedDate = null,
        ?string $excludedTime = null
    ): array {
        $technicianIds = $technicians->pluck('id')->map(fn ($id) => (int) $id)->values();
        if ($technicianIds->isEmpty()) {
            return [];
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $bookings = self::bookings($technicianIds->all(), $ignoreInstallationId);
        $dailyInstallations = Installation::query()
            ->with('application')
            ->whereIn('technician_id', $technicianIds)
            ->whereBetween('installation_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->when($ignoreInstallationId, fn ($query) => $query->whereKeyNot($ignoreInstallationId))
            ->get();

        $availability = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $dateKey = $date->toDateString();
            $slots = collect(Installation::AVAILABLE_TIME_SLOTS)
                ->map(function (string $slot) use (
                    $technicians,
                    $application,
                    $dateKey,
                    $ignoreInstallationId,
                    $excludedDate,
                    $excludedTime,
                    $bookings,
                    $dailyInstallations
                ) {
                    if ($dateKey === $excludedDate && $slot === $excludedTime) {
                        return null;
                    }

                    if (Carbon::parse($dateKey.' '.$slot)->lte(now())) {
                        return null;
                    }

                    $availableTechnicians = self::availableTechniciansForInstallation(
                        $technicians,
                        $application,
                        $dateKey,
                        $slot,
                        $ignoreInstallationId,
                        $bookings,
                        $dailyInstallations
                    );

                    if ($availableTechnicians->isEmpty()) {
                        return null;
                    }

                    return [
                        'value' => $slot,
                        'label' => Installation::slotLabel($slot),
                        'available_count' => $availableTechnicians->count(),
                        'technician_ids' => $availableTechnicians->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if ($slots !== []) {
                $availability[$dateKey] = [
                    'label' => $date->format('F j, Y'),
                    'slots' => $slots,
                ];
            }
        }

        return $availability;
    }

    /** Caller must lock the target technician before checking and saving a booking. */
    public static function conflicts(
        int $technicianId,
        string $date,
        string $time,
        ?int $ignoreInstallationId = null,
        ?int $ignoreTicketId = null,
        int $durationMinutes = Installation::SLOT_DURATION_MINUTES
    ): bool {
        $start = Carbon::parse($date.' '.$time);
        $end = $start->copy()->addMinutes($durationMinutes);

        return self::bookings([$technicianId], $ignoreInstallationId, $ignoreTicketId, true)
            ->contains(fn ($booking) => $start->lt($booking->scheduledEndAt()) && $end->gt($booking->scheduledStartAt()));
    }

    public static function calendar(): array
    {
        return self::bookings()->map(fn ($booking) => [
            'kind' => $booking instanceof Ticket ? 'ticket' : 'installation',
            'id' => $booking->id,
            'technician_id' => $booking->technician_id,
            'date' => $booking->scheduledStartAt()->format('Y-m-d'),
            'time' => $booking->scheduledStartAt()->format('H:i'),
            'end_time' => $booking->scheduledEndAt()->format('H:i'),
        ])->values()->all();
    }

    /** Shared source for conflict validation and displayed availability. */
    public static function bookings(?array $technicianIds = null, ?int $ignoreInstallationId = null, ?int $ignoreTicketId = null, bool $lock = false): Collection
    {
        $installations = Installation::query()->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereNotNull('technician_id')->whereNotNull('installation_date')->whereNotNull('installation_time')
            ->when($technicianIds !== null, fn ($q) => $q->whereIn('technician_id', $technicianIds))
            ->when($ignoreInstallationId, fn ($q) => $q->whereKeyNot($ignoreInstallationId));
        if ($lock) {
            $installations->lockForUpdate();
        }

        // Ticket work is queue based. Legacy appointment columns remain for
        // migration safety but no longer reserve calendar time.
        return $installations->get()->toBase();
    }

    private static function routeLimitIssue(
        int $technicianId,
        Application $application,
        string $date,
        string $time,
        ?int $ignoreInstallationId = null,
        ?Collection $dailyInstallations = null
    ): ?string {
        if ($application->lat === null || $application->lng === null) {
            return null;
        }

        $maximumDistance = (float) config('technician-scheduling.max_consecutive_route_distance_km', 30);
        if ($maximumDistance <= 0) {
            return null;
        }

        $installations = $dailyInstallations === null
            ? Installation::query()
                ->with('application')
                ->where('technician_id', $technicianId)
                ->whereDate('installation_date', Carbon::parse($date)->toDateString())
                ->whereIn('status', Installation::activeSchedulingStatuses())
                ->when($ignoreInstallationId, fn ($query) => $query->whereKeyNot($ignoreInstallationId))
                ->get()
            : $dailyInstallations
                ->where('technician_id', $technicianId)
                ->filter(fn (Installation $installation) => $installation->installation_date?->format('Y-m-d') === Carbon::parse($date)->toDateString());

        $jobs = $installations
            ->filter(fn (Installation $installation) => $installation->application?->lat !== null
                && $installation->application?->lng !== null)
            ->map(fn (Installation $installation) => [
                'candidate' => false,
                'time' => $installation->scheduledStartAt(),
                'lat' => (float) $installation->application->lat,
                'lng' => (float) $installation->application->lng,
                'label' => $installation->displayJobReference(),
            ]);

        $jobs->push([
            'candidate' => true,
            'time' => Carbon::parse($date.' '.$time),
            'lat' => (float) $application->lat,
            'lng' => (float) $application->lng,
            'label' => $application->reference_no,
        ]);

        $ordered = $jobs->sortBy(fn (array $job) => $job['time']->getTimestamp())->values();
        $candidateIndex = $ordered->search(fn (array $job) => $job['candidate']);

        foreach ([$candidateIndex - 1, $candidateIndex + 1] as $neighborIndex) {
            $neighbor = $ordered->get($neighborIndex);
            if (! $neighbor) {
                continue;
            }

            $distance = self::distanceKilometers(
                (float) $application->lat,
                (float) $application->lng,
                $neighbor['lat'],
                $neighbor['lng']
            );

            if ($distance > $maximumDistance) {
                return sprintf(
                    'This route is approximately %.1f km from the technician\'s adjacent job (%s), above the %.0f km scheduling limit.',
                    $distance,
                    $neighbor['label'],
                    $maximumDistance
                );
            }
        }

        return null;
    }

    public static function isOnDutyAt(User $technician, \Carbon\CarbonInterface|string $moment): bool
    {
        $at = Carbon::parse($moment);
        if ($technician->isDisabled()
            || in_array($technician->technician_status, ['off_duty', 'on_leave'], true)
            || ! $technician->worksOnDate($at)) {
            return false;
        }

        $shiftStart = Carbon::parse($at->toDateString().' '.$technician->technicianShiftStart());
        $shiftEnd = Carbon::parse($at->toDateString().' '.$technician->technicianShiftEnd());
        if ($at->lt($shiftStart) || $at->gte($shiftEnd)) {
            return false;
        }

        return ! self::overlapsBreak($technician, $at, $at->copy()->addMinute());
    }

    public static function overlapsBreak(User $technician, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        $breakStartValue = $technician->technicianBreakStart();
        $breakEndValue = $technician->technicianBreakEnd();
        if (! $breakStartValue || ! $breakEndValue) {
            return false;
        }

        $breakStart = Carbon::parse($start->toDateString().' '.$breakStartValue);
        $breakEnd = Carbon::parse($start->toDateString().' '.$breakEndValue);

        return $start->lt($breakEnd) && $end->gt($breakStart);
    }

    public static function distanceKilometers(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371;
        $latitudeDelta = deg2rad($toLat - $fromLat);
        $longitudeDelta = deg2rad($toLng - $fromLng);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
