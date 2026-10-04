<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Installation;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TechnicianSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();
        $period = in_array($request->query('period'), ['today', 'week', '30days'], true)
            ? $request->query('period')
            : 'week';

        [$periodStart, $periodEnd, $periodLabel] = match ($period) {
            'today' => [$today, $today, 'Today - '.$today->format('M j, Y')],
            '30days' => [$today, $today->addDays(29), $today->format('M j').' - '.$today->addDays(29)->format('M j, Y')],
            default => [
                $today->startOfWeek(CarbonImmutable::MONDAY),
                $today->endOfWeek(CarbonImmutable::SUNDAY),
                $today->startOfWeek(CarbonImmutable::MONDAY)->format('M j').' - '.$today->endOfWeek(CarbonImmutable::SUNDAY)->format('M j, Y'),
            ],
        };

        $periodOptions = [
            'today' => 'Today',
            'week' => 'This Week',
            '30days' => 'Next 30 Days',
        ];

        $periodInstallations = Installation::query()
            ->with(['application', 'technician'])
            ->whereDate('installation_date', '>=', $periodStart->toDateString())
            ->whereDate('installation_date', '<=', $periodEnd->toDateString())
            ->where('status', '!=', Installation::STATUS_CANCELLED)
            ->orderBy('installation_date')
            ->orderBy('installation_time')
            ->get();

        $installationTotal = $periodInstallations->count();
        $installationPreview = $periodInstallations->take(12);
        $installationRemaining = max(0, $installationTotal - $installationPreview->count());
        $installationStatusCounts = $periodInstallations->countBy('status');

        $activeTicketCounts = Ticket::query()
            ->whereIn('status', Ticket::activeWorkStatuses())
            ->selectRaw('assigned_staff_id, COUNT(*) as total')
            ->groupBy('assigned_staff_id')
            ->pluck('total', 'assigned_staff_id');

        $technicians = User::query()
            ->technicians()
            ->orderBy('name')
            ->get();

        $installationsByTechnician = $periodInstallations->groupBy('technician_id');
        $technicianWorkloads = $technicians->map(function (User $technician) use (
            $now,
            $periodStart,
            $periodEnd,
            $installationsByTechnician,
            $activeTicketCounts
        ): array {
            $jobs = $installationsByTechnician->get($technician->id, collect());
            $workingDays = 0;
            for ($date = $periodStart; $date->lte($periodEnd); $date = $date->addDay()) {
                if ($technician->worksOnDate($date)) {
                    $workingDays++;
                }
            }

            $installationCapacity = max(1, $workingDays * Installation::MAX_DAILY_INSTALLATIONS);
            $activeTickets = (int) $activeTicketCounts->get($technician->id, 0);
            $ticketCapacity = $technician->maximumActiveTicketWorkload();
            $loadRatio = max(
                $jobs->count() / $installationCapacity,
                $activeTickets / max(1, $ticketCapacity)
            );

            $scheduleState = match (true) {
                $technician->isDisabled() => ['label' => 'Disabled', 'tone' => 'muted'],
                $technician->technician_status === 'on_leave' => ['label' => 'On leave', 'tone' => 'warning'],
                TechnicianSchedule::isOnDutyAt($technician, $now) => ['label' => 'On duty', 'tone' => 'success'],
                default => ['label' => 'Off duty', 'tone' => 'muted'],
            };

            $workloadState = match (true) {
                $technician->isDisabled() || $technician->technician_status === 'on_leave' => ['label' => 'Unavailable', 'tone' => 'muted'],
                $loadRatio >= 1 => ['label' => 'At capacity', 'tone' => 'danger'],
                $loadRatio >= .6 => ['label' => 'Moderate', 'tone' => 'warning'],
                default => ['label' => 'Available', 'tone' => 'success'],
            };

            $nextInstallation = $jobs
                ->filter(fn (Installation $installation) => $installation->scheduledStartAt()?->gte($now))
                ->sortBy(fn (Installation $installation) => $installation->scheduledStartAt()?->getTimestamp())
                ->first();

            $areas = $jobs->map(fn (Installation $installation) => collect([
                $installation->application?->barangay,
                $installation->application?->municipality,
            ])->filter()->implode(', '))->filter()->unique()->take(3)->values();

            return [
                'technician' => $technician,
                'schedule' => $scheduleState,
                'workload' => $workloadState,
                'installation_count' => $jobs->count(),
                'installation_capacity' => $installationCapacity,
                'active_tickets' => $activeTickets,
                'ticket_capacity' => $ticketCapacity,
                'load_percent' => min(100, (int) round($loadRatio * 100)),
                'next_installation' => $nextInstallation,
                'areas' => $areas,
            ];
        })->sortBy([
            fn (array $left, array $right) => ($left['schedule']['label'] === 'On duty' ? 0 : 1) <=> ($right['schedule']['label'] === 'On duty' ? 0 : 1),
            fn (array $left, array $right) => $left['load_percent'] <=> $right['load_percent'],
            fn (array $left, array $right) => strcasecmp($left['technician']->name, $right['technician']->name),
        ])->values();

        $pendingApplications = Application::query()
            ->whereIn('status', [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW])
            ->count();
        $unassignedTickets = Ticket::query()
            ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
            ->whereNull('assigned_staff_id')
            ->count();
        $openTickets = Ticket::query()
            ->where('status', Ticket::STATUS_OPEN)
            ->count();
        $cancellationRequests = Application::query()->where('status', Application::STATUS_CANCELLATION_REQUESTED)->count()
            + Ticket::query()->where('status', Ticket::STATUS_CANCELLATION_REQUESTED)->count()
            + Installation::query()->whereNotNull('stop_work_requested_at')->whereNull('stop_work_reviewed_at')->count();

        $attentionItems = collect([
            ['label' => 'Applications to review', 'count' => $pendingApplications, 'detail' => 'Submitted or under review', 'url' => route('admin.applications.index'), 'tone' => 'primary'],
            ['label' => 'Unassigned tickets', 'count' => $unassignedTickets, 'detail' => 'Waiting for staff ownership', 'url' => route('admin.tickets.index', ['status' => 'unassigned']), 'tone' => 'warning'],
            ['label' => 'Open tickets', 'count' => $openTickets, 'detail' => 'Assigned and waiting to start', 'url' => route('admin.tickets.index', ['status' => 'open']), 'tone' => 'primary'],
            ['label' => 'Cancellation requests', 'count' => $cancellationRequests, 'detail' => 'Admin decision required', 'url' => route('admin.applications.index', ['status' => Application::STATUS_CANCELLATION_REQUESTED]), 'tone' => 'neutral'],
        ]);


        $overdueInstallations = Installation::query()
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereDate('installation_date', '<=', $today->toDateString())
            ->get()
            ->filter(fn (Installation $installation) => $installation->scheduledEndAt()?->lte($now))
            ->count();
        $overdueTickets = Ticket::query()
            ->whereIn('status', Ticket::activeWorkStatuses())
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<=', $now)
            ->count();

        $recentActivity = SystemLog::query()
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'period', 'periodOptions', 'periodStart', 'periodEnd', 'periodLabel',
            'installationTotal', 'installationPreview', 'installationRemaining', 'installationStatusCounts',
            'technicianWorkloads', 'attentionItems', 'recentActivity',
            'overdueInstallations', 'overdueTickets', 'now'
        ));
    }
}