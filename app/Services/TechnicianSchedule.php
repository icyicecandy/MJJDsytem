<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TechnicianSchedule
{
    public const CONFLICT_MESSAGE = 'This technician already has an installation or ticket appointment during that time slot.';

    public static function eligibleTechnician(int $id): User
    {
        $technician = User::find($id);
        if (! $technician || ! $technician->isTechnician() || $technician->isDisabled()) {
            throw ValidationException::withMessages(['technician_id' => 'Choose an active technician. Disabled accounts cannot receive assignments.']);
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

    /** Caller must lock the target technician before checking and saving a booking. */
    public static function conflicts(int $technicianId, string $date, string $time, ?int $ignoreInstallationId = null, ?int $ignoreTicketId = null): bool
    {
        $start = Carbon::parse($date.' '.$time);
        $end = $start->copy()->addMinutes(Installation::SLOT_DURATION_MINUTES);
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
        ])->values()->all();
    }

    /** Shared source for conflict validation and displayed availability. */
    public static function bookings(?array $technicianIds = null, ?int $ignoreInstallationId = null, ?int $ignoreTicketId = null, bool $lock = false): Collection
    {
        $installations = Installation::query()->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereNotNull('technician_id')->whereNotNull('installation_date')->whereNotNull('installation_time')
            ->when($technicianIds !== null, fn ($q) => $q->whereIn('technician_id', $technicianIds))
            ->when($ignoreInstallationId, fn ($q) => $q->whereKeyNot($ignoreInstallationId));
        $tickets = Ticket::query()->whereIn('status', Ticket::ACTIVE_SCHEDULING_STATUSES)
            ->whereNotNull('technician_id')->whereNotNull('appointment_date')->whereNotNull('appointment_time')
            ->when($technicianIds !== null, fn ($q) => $q->whereIn('technician_id', $technicianIds))
            ->when($ignoreTicketId, fn ($q) => $q->whereKeyNot($ignoreTicketId));
        if ($lock) {
            $installations->lockForUpdate();
            $tickets->lockForUpdate();
        }
        return $installations->get()->toBase()->concat($tickets->get());
    }
}
