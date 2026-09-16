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
        foreach ([Installation::class, Ticket::class] as $model) {
            $dateColumn = $model === Installation::class ? 'installation_date' : 'appointment_date';
            $statuses = $model === Installation::class
                ? Installation::activeSchedulingStatuses()
                : [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS];

            foreach ($model::query()->whereIn('status', $statuses)
                ->whereDate($dateColumn, '<=', today())->lazyById(200) as $candidate) {
                try {
                    // Re-read under the same locks used by completion/rescheduling.
                    // The delivery marker and notification commit together.
                    $action = fn ($current) => $this->notify($current, $dryRun);
                    $count += $candidate instanceof Installation
                        ? WorkflowTransaction::installation($candidate, $action)
                        : WorkflowTransaction::ticket($candidate, $action);
                } catch (ModelNotFoundException) {
                    // A task removed since the candidate scan no longer needs an alert.
                }
            }
        }

        return $count;
    }

    private function notify(Installation|Ticket $task, bool $dryRun): int
    {
        $installation = $task instanceof Installation;
        $active = $installation ? Installation::activeSchedulingStatuses()
            : [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS];
        if (! in_array($task->status, $active, true)) {
            return 0;
        }
        if ($installation && (in_array($task->application->status, [Application::STATUS_CANCELLED, Application::STATUS_CANCELLATION_REQUESTED], true)
            || $task->hasPendingCustomerRescheduleRequest())) {
            return 0;
        }
        $end = $task->scheduledEndAt();
        if (! $end || $end->gt(now())) {
            return 0;
        }

        $kind = $installation ? 'installation' : 'ticket';
        $reference = $installation ? $task->displayJobReference() : $task->publicReference();
        $start = $task->scheduledStartAt();
        $users = User::query()->whereNull('disabled_at')->where(function ($query) use ($task) {
            $query->where('role', 'admin')->orWhere(function ($query) use ($task) {
                $query->where('role', 'technician')->whereKey($task->technician_id);
            });
        })->get();

        $count = 0;
        foreach ($users as $user) {
            $key = hash('sha256', implode('|', [$kind, $task->id, $start->format('Y-m-d H:i:s'), $task->technician_id, $user->id]));
            // Task locking serializes competing workers. The primary key additionally
            // enforces uniqueness, independent of notification read/delete actions.
            if (DB::table('overdue_alert_deliveries')->where('delivery_key', $key)->exists()) {
                continue;
            }
            if (! $dryRun) {
                $admin = $user->role === 'admin';
                $url = $installation
                    ? ($admin ? route('admin.applications.show', $task->application_id) : route('technician.installations.show', $task))
                    : route($admin ? 'admin.tickets.show' : 'technician.tickets.show', $task);
                $message = $reference.' remains unfinished after its scheduled slot on '
                    .$start->format('M d, Y g:i A').' - '.$end->format('g:i A').' ('.config('app.timezone').'). '
                    .($admin ? 'Review progress and arrange rescheduling if needed.' : 'Update your progress or contact an administrator if rescheduling is needed.');
                DB::table('overdue_alert_deliveries')->insert(['delivery_key' => $key, 'created_at' => now()]);
                AppNotificationSender::send($user, ucfirst($kind).' overdue', $message, $url, 'task_overdue', [
                    'task_type' => $kind, 'task_id' => $task->id,
                    'scheduled_start' => $start->toIso8601String(), 'scheduled_end' => $end->toIso8601String(),
                    'technician_id' => $task->technician_id,
                ]);
            }
            $count++;
        }

        return $count;
    }
}
