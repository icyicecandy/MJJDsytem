<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AppNotificationSender;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OverdueTaskAlerts
{
    /** Return the number of new recipient alerts; dry runs never write. */
    public function run(bool $dryRun = false): int
    {
        $count = 0;

        $installationCandidates = Installation::query()
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereDate('installation_date', '<=', today())
            ->lazyById(200);

        foreach ($installationCandidates as $candidate) {
            $count += $this->notifyCandidate($candidate, $dryRun);
        }

        $ticketCandidates = Ticket::query()
            ->whereIn('status', Ticket::activeWorkStatuses())
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<=', now())
            ->lazyById(200);

        foreach ($ticketCandidates as $candidate) {
            $count += $this->notifyCandidate($candidate, $dryRun);
        }

        return $count;
    }

    private function notifyCandidate(Installation|Ticket $candidate, bool $dryRun): int
    {
        try {
            $action = fn ($current) => $this->notify($current, $dryRun);

            return $candidate instanceof Installation
                ? WorkflowTransaction::installation($candidate, $action)
                : WorkflowTransaction::ticket($candidate, $action);
        } catch (ModelNotFoundException) {
            return 0;
        }
    }

    private function notify(Installation|Ticket $task, bool $dryRun): int
    {
        $installation = $task instanceof Installation;
        $active = $installation ? Installation::activeSchedulingStatuses() : Ticket::activeWorkStatuses();

        if (! in_array($task->status, $active, true)) {
            return 0;
        }

        if ($installation && (
            in_array($task->application->status, [
                Application::STATUS_CANCELLED,
                Application::STATUS_CANCELLATION_REQUESTED,
            ], true)
            || $task->hasPendingCustomerRescheduleRequest()
        )) {
            return 0;
        }

        $deadline = $installation
            ? $task->scheduledEndAt()
            : $task->response_due_at;

        if (! $deadline || $deadline->gt(now())) {
            return 0;
        }

        $kind = $installation ? 'installation' : 'ticket';
        $reference = $installation ? $task->displayJobReference() : $task->publicReference();
        $start = $installation ? $task->scheduledStartAt() : $task->created_at;
        $users = User::query()
            ->whereNull('disabled_at')
            ->where(function ($query) use ($task, $installation) {
                $query->where('role', 'admin')
                    ->orWhere(function ($staffQuery) use ($task, $installation) {
                        $staffQuery->whereKey($installation ? $task->technician_id : $task->assigned_staff_id);
                    });
            })
            ->get();

        $count = 0;

        foreach ($users as $user) {
            $key = hash('sha256', implode('|', [
                $kind,
                $task->id,
                $deadline->format('Y-m-d H:i:s'),
                $installation ? $task->technician_id : $task->assigned_staff_id,
                $user->id,
            ]));

            if (DB::table('overdue_alert_deliveries')->where('delivery_key', $key)->exists()) {
                continue;
            }

            if (! $dryRun) {
                $admin = $user->role === 'admin';
                $url = $installation
                    ? ($admin
                        ? route('admin.applications.show', $task->application_id)
                        : route('technician.installations.show', $task))
                    : route($admin ? 'admin.tickets.show' : 'technician.tickets.show', $task);

                $message = $installation
                    ? $reference.' remains unfinished after its scheduled installation time on '
                        .$start->format('M d, Y g:i A')
                        .' ('.config('app.timezone').'). '
                        .($admin
                            ? 'Review progress and arrange rescheduling if needed.'
                            : 'Update your progress or contact an administrator if rescheduling is needed.')
                    : $reference.' remains unresolved after its '
                        .strtolower($task->displayUrgencyLabel()).' response target on '
                        .$deadline->format('M d, Y g:i A').' ('.config('app.timezone').'). '
                        .($admin
                            ? 'Review the priority queue and support workload.'
                            : 'Update the ticket or contact an administrator if support is needed.');

                DB::table('overdue_alert_deliveries')->insert([
                    'delivery_key' => $key,
                    'created_at' => now(),
                ]);

                AppNotificationSender::send(
                    $user,
                    ucfirst($kind).' overdue',
                    $message,
                    $url,
                    'task_overdue',
                    [
                        'task_type' => $kind,
                        'task_id' => $task->id,
                        'scheduled_start' => $start?->toIso8601String(),
                        'scheduled_end' => $deadline->toIso8601String(),
                        'technician_id' => $installation ? $task->technician_id : $task->assigned_staff_id,
                    ]
                );
            }

            $count++;
        }

        return $count;
    }
}
