<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AppNotificationSender;
use App\Support\CustomerEmailSender;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TicketAssignmentService
{
    public function assign(Ticket $ticket, ?int $actorId = null, string $source = 'automatic'): ?User
    {
        $ticket->refresh();

        if ($ticket->assigned_staff_id) {
            return $ticket->assignedStaff;
        }

        if ($ticket->urgency_review_required || ! $ticket->urgency || ! $ticket->isOpenForWork()) {
            $ticket->forceFill(['status' => Ticket::STATUS_QUEUED])->save();

            return null;
        }

        $technicians = $this->eligibleTechnicians();

        if ($technicians->isEmpty()) {
            $ticket->forceFill(['status' => Ticket::STATUS_QUEUED])->save();

            return null;
        }

        $technicianIds = $technicians->pluck('id');
        $technicians->load(['assignedTickets' => fn ($query) => $query
            ->whereIn('status', Ticket::activeWorkStatuses())
            ->orderBy('assigned_at')]);

        $busyInstallationTechnicianIds = $this->busyInstallationTechnicianIds($technicianIds);

        $available = $technicians
            ->reject(fn (User $technician) => in_array((int) $technician->id, $busyInstallationTechnicianIds, true))
            ->filter(fn (User $technician) => $technician->assignedTickets->count() < $technician->maximumActiveTicketWorkload())
            ->sort(function (User $left, User $right): int {
                $leftLoad = $left->assignedTickets->count();
                $rightLoad = $right->assignedTickets->count();
                $leftProgress = $left->assignedTickets->where('status', Ticket::STATUS_IN_PROGRESS)->count();
                $rightProgress = $right->assignedTickets->where('status', Ticket::STATUS_IN_PROGRESS)->count();
                $leftLast = $left->assignedTickets
                    ->max(fn (Ticket $ticket) => $ticket->assigned_at?->getTimestamp() ?? 0) ?? 0;
                $rightLast = $right->assignedTickets
                    ->max(fn (Ticket $ticket) => $ticket->assigned_at?->getTimestamp() ?? 0) ?? 0;

                return [$leftLoad, $leftProgress, $leftLast, $left->id]
                    <=> [$rightLoad, $rightProgress, $rightLast, $right->id];
            })
            ->values();

        /** @var User|null $technician */
        $technician = $available->first();

        if (! $technician) {
            $ticket->forceFill(['status' => Ticket::STATUS_QUEUED])->save();

            return null;
        }

        $ticket->forceFill([
            'assigned_staff_id' => $technician->id,
            'technician_id' => $technician->id,
            'status' => Ticket::STATUS_OPEN,
            'assigned_at' => now(),
            'assignment_source' => $source,
            'assignment_reason' => 'Automatically selected from the priority queue using current schedule and active workload.',
            'assignment_changed_by' => $actorId,
            'assignment_unavailable_since' => null,
        ])->save();

        $ticket->setRelation('assignedStaff', $technician);
        $ticket->setRelation('technician', $technician);

        $this->recordAssignmentEvent(
            $ticket,
            null,
            $technician->id,
            $actorId,
            $source,
            'Automatically selected from the priority queue using current schedule and active workload.'
        );

        AppNotificationSender::send(
            $technician,
            'New support ticket assigned',
            $ticket->publicReference().' ('.$ticket->displayUrgencyLabel().') was assigned to your queue.',
            route('technician.tickets.show', $ticket),
            'ticket_auto_assigned',
            ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
            $actorId
        );

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Support staff assigned',
                $technician->name.' (Technician) is assigned to '.$ticket->publicReference().'. Work will begin when the technician starts the ticket.',
                route('customer.tickets.show', $ticket),
                'ticket_staff_assigned',
                ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
                $actorId
            );

            CustomerEmailSender::sendTicketAssigned($ticket, $technician);
        }

        system_log(
            'Ticket Auto Assigned',
            $ticket->publicReference().' was assigned to '.$technician->name
                .' based on shift availability and active workload.'
        );

        return $technician;
    }

    public function dispatchQueued(int $limit = 100): int
    {
        $this->requeueUnavailableOpenAssignments();

        $assigned = 0;

        Ticket::query()
            ->whereNull('assigned_staff_id')
            ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
            ->where('urgency_review_required', false)
            ->priorityOrdered()
            ->limit($limit)
            ->get()
            ->each(function (Ticket $ticket) use (&$assigned): void {
                $technician = WorkflowTransaction::ticket(
                    $ticket,
                    fn (Ticket $current) => $this->assign($current)
                );

                if ($technician) {
                    $assigned++;
                }
            });

        return $assigned;
    }

    public function nextEligibleShiftAt(CarbonInterface|string|null $from = null): ?Carbon
    {
        $moment = $from instanceof CarbonInterface ? Carbon::instance($from)->copy() : Carbon::parse($from ?? now());
        $technicians = User::query()
            ->where('role', 'technician')
            ->whereNull('disabled_at')
            ->where(function ($query) {
                $query->whereNull('technician_status')
                    ->orWhereNotIn('technician_status', ['off_duty', 'on_leave']);
            })
            ->get();

        $next = null;
        foreach (range(0, 30) as $offset) {
            $date = $moment->copy()->startOfDay()->addDays($offset);
            foreach ($technicians as $technician) {
                if (! $technician->worksOnDate($date)) {
                    continue;
                }

                $shiftStart = Carbon::parse($date->toDateString().' '.$technician->technicianShiftStart());
                $shiftEnd = Carbon::parse($date->toDateString().' '.$technician->technicianShiftEnd());
                $candidate = $shiftStart;

                if ($date->isSameDay($moment)) {
                    if (TechnicianSchedule::isOnDutyAt($technician, $moment)) {
                        $candidate = $moment->copy();
                    } elseif ($moment->lt($shiftStart)) {
                        $candidate = $shiftStart;
                    } else {
                        $breakEnd = $technician->technicianBreakEnd();
                        $candidate = $breakEnd ? Carbon::parse($date->toDateString().' '.$breakEnd) : null;
                        if (! $candidate || $candidate->lte($moment) || $candidate->gte($shiftEnd)) {
                            continue;
                        }
                    }
                }

                if ($candidate->gte($moment) && ($next === null || $candidate->lt($next))) {
                    $next = $candidate;
                }
            }
            if ($next !== null) {
                break;
            }
        }

        return $next;
    }
    public function manualAssignee(int $id): User
    {
        $staff = User::find($id);

        if (! $staff || ! in_array($staff->role, ['admin', 'technician'], true) || $staff->isDisabled()) {
            throw ValidationException::withMessages([
                'assigned_staff_id' => 'Choose an active administrator or technician.',
            ]);
        }

        if ($staff->isTechnician() && $staff->technician_status === 'on_leave') {
            throw ValidationException::withMessages([
                'assigned_staff_id' => 'This technician is on leave and cannot receive a ticket.',
            ]);
        }

        return $staff;
    }

    public function manualAssignmentWarnings(User $staff, ?Ticket $ticket = null): array
    {
        if (! $staff->isTechnician()) {
            return [];
        }

        $warnings = [];

        if (! TechnicianSchedule::isOnDutyAt($staff, now())) {
            $warnings[] = 'The technician is currently outside their working schedule or break period.';
        }

        $activeLoad = $staff->assignedTickets()
            ->whereIn('status', Ticket::activeWorkStatuses())
            ->when($ticket, fn ($query) => $query->whereKeyNot($ticket->id))
            ->count();

        if ($activeLoad >= $staff->maximumActiveTicketWorkload()) {
            $warnings[] = 'The technician is already at their maximum active-ticket workload.';
        }

        if ($this->hasActiveInstallationNow($staff->id)) {
            $warnings[] = 'The technician is currently occupied by an installation.';
        }

        return $warnings;
    }

    public function requeueUnavailableOpenAssignments(): int
    {
        $requeued = 0;

        Ticket::query()
            ->where('status', Ticket::STATUS_OPEN)
            ->whereNotNull('assigned_staff_id')
            ->with('assignedStaff')
            ->get()
            ->each(function (Ticket $ticket) use (&$requeued): void {
                $changed = WorkflowTransaction::ticket($ticket, function (Ticket $current): bool {
                    return $this->requeueIfUnavailable($current);
                });

                if ($changed) {
                    $requeued++;
                }
            });

        return $requeued;
    }

    public function recordAssignmentEvent(
        Ticket $ticket,
        ?int $fromStaffId,
        ?int $toStaffId,
        ?int $actorId,
        string $source,
        string $reason
    ): void {
        $ticket->assignmentEvents()->create([
            'from_staff_id' => $fromStaffId,
            'to_staff_id' => $toStaffId,
            'actor_id' => $actorId,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    private function requeueIfUnavailable(Ticket $ticket): bool
    {
        $ticket->loadMissing('assignedStaff');
        $staff = $ticket->assignedStaff;

        if (! $staff || $staff->isDisabled()) {
            return $this->returnToQueue($ticket, 'Assigned staff account is unavailable.');
        }

        if ($staff->isAdmin()) {
            if ($ticket->assignment_unavailable_since) {
                $ticket->forceFill(['assignment_unavailable_since' => null])->save();
            }

            return false;
        }

        if ($staff->technician_status === 'on_leave') {
            return $this->returnToQueue($ticket, 'Assigned technician was placed on leave.');
        }

        $temporarilyUnavailable = ! TechnicianSchedule::isOnDutyAt($staff, now())
            || $this->hasActiveInstallationNow($staff->id);

        if (! $temporarilyUnavailable) {
            if ($ticket->assignment_unavailable_since) {
                $ticket->forceFill(['assignment_unavailable_since' => null])->save();
            }

            return false;
        }

        if (! $ticket->assignment_unavailable_since) {
            $ticket->forceFill(['assignment_unavailable_since' => now()])->save();

            return false;
        }

        $minutes = max(1, (int) config('ticket-routing.unavailable_requeue_minutes', 30));
        if ($ticket->assignment_unavailable_since->gt(now()->subMinutes($minutes))) {
            return false;
        }

        return $this->returnToQueue(
            $ticket,
            'Assigned technician remained unavailable for '.$minutes.' minutes.'
        );
    }

    private function returnToQueue(Ticket $ticket, string $reason): bool
    {
        $previous = $ticket->assignedStaff;
        $previousId = $ticket->assigned_staff_id;

        $ticket->forceFill([
            'assigned_staff_id' => null,
            'technician_id' => null,
            'status' => Ticket::STATUS_QUEUED,
            'assigned_at' => null,
            'assignment_source' => null,
            'assignment_reason' => $reason,
            'assignment_changed_by' => null,
            'assignment_unavailable_since' => null,
        ])->save();

        $this->recordAssignmentEvent($ticket, $previousId, null, null, 'automatic_requeue', $reason);

        if ($previous && ! $previous->isDisabled()) {
            AppNotificationSender::send(
                $previous,
                'Ticket returned to queue',
                $ticket->publicReference().' was returned to the support queue. '.$reason,
                $previous->isAdmin()
                    ? route('admin.tickets.show', $ticket)
                    : route('technician.tickets.index'),
                'ticket_requeued',
                ['ticket_id' => $ticket->id]
            );
        }

        if ($ticket->user) {
            AppNotificationSender::send(
                $ticket->user,
                'Support ticket returned to queue',
                $ticket->publicReference().' is waiting for the next available support staff member.',
                route('customer.tickets.show', $ticket),
                'ticket_requeued_customer',
                ['ticket_id' => $ticket->id]
            );
        }

        system_log('Ticket Returned to Queue', $ticket->publicReference().': '.$reason);

        return true;
    }

    private function eligibleTechnicians(): Collection
    {
        $now = now();

        return User::query()
            ->where('role', 'technician')
            ->whereNull('disabled_at')
            ->where(function ($query) {
                $query->whereNull('technician_status')
                    ->orWhereNotIn('technician_status', ['off_duty', 'on_leave']);
            })
            ->lockForUpdate()
            ->get()
            ->filter(fn (User $technician): bool => TechnicianSchedule::isOnDutyAt($technician, $now))
            ->values();
    }

    private function busyInstallationTechnicianIds(Collection $technicianIds): array
    {
        return Installation::query()
            ->whereIn('technician_id', $technicianIds)
            ->whereDate('installation_date', today())
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->get()
            ->filter(function (Installation $installation): bool {
                $start = $installation->scheduledStartAt();
                $end = $installation->scheduledEndAt();

                return $start && $end && now()->gte($start) && now()->lt($end);
            })
            ->pluck('technician_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function hasActiveInstallationNow(int $technicianId): bool
    {
        return $this->busyInstallationTechnicianIds(collect([$technicianId])) !== [];
    }
}
