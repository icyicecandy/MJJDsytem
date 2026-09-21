<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AppNotificationSender;
use Illuminate\Support\Collection;

final class TicketAssignmentService
{
    public function assign(Ticket $ticket, ?int $actorId = null, string $source = 'automatic'): ?User
    {
        $ticket->refresh();

        if ($ticket->technician_id) {
            return $ticket->technician;
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

        $busyInstallationTechnicianIds = Installation::query()
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
            'technician_id' => $technician->id,
            'status' => Ticket::STATUS_OPEN,
            'assigned_at' => now(),
            'assignment_source' => $source,
        ])->save();

        $ticket->setRelation('technician', $technician);

        AppNotificationSender::send(
            $technician,
            'New support ticket assigned',
            $ticket->publicReference().' ('.$ticket->displayUrgencyLabel().') was assigned to your queue.',
            route('technician.tickets.show', $ticket),
            'ticket_auto_assigned',
            ['ticket_id' => $ticket->id, 'tracking_number' => $ticket->publicReference()],
            $actorId
        );

        system_log(
            'Ticket Auto Assigned',
            $ticket->publicReference().' was assigned to '.$technician->name
                .' based on shift availability and active workload.'
        );

        return $technician;
    }

    public function dispatchQueued(int $limit = 100): int
    {
        $assigned = 0;

        Ticket::query()
            ->whereNull('technician_id')
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
}
