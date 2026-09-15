<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $overviewUpdatedAt = CarbonImmutable::now(config('app.timezone'));
        $firstMonth = $overviewUpdatedAt->startOfMonth()->subMonths(5);
        $applicationCounts = Application::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalApplications = (int) $applicationCounts->sum();
        $pendingApplications = (int) $applicationCounts->get(Application::STATUS_SUBMITTED, 0)
            + (int) $applicationCounts->get(Application::STATUS_UNDER_REVIEW, 0);
        $scheduledInstallations = (int) $applicationCounts->get(Application::STATUS_SCHEDULED, 0);
        $installedApplications = (int) $applicationCounts->get(Application::STATUS_INSTALLED, 0)
            + (int) $applicationCounts->get(Application::STATUS_ACTIVATED, 0);
        $openTickets = Ticket::query()
            ->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])
            ->count();

        // Bounded SQL aggregation avoids loading individual applications and works
        // with SQLite and MySQL. Dates follow the application's storage timezone.
        $monthlyQuery = Application::query()
            ->where('created_at', '>=', $firstMonth)
            ->where('created_at', '<=', $overviewUpdatedAt);
        for ($index = 0; $index < 6; $index++) {
            $start = $firstMonth->addMonths($index);
            $monthlyQuery->selectRaw(
                "COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) AS month_{$index}",
                [$start->toDateTimeString(), $start->addMonth()->toDateTimeString()]
            );
        }
        $monthlyCounts = $monthlyQuery->first();
        $monthlyApplications = collect(range(0, 5))->map(function (int $index) use ($firstMonth, $monthlyCounts) {
            $month = $firstMonth->addMonths($index);

            return [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'count' => (int) $monthlyCounts->getAttribute("month_{$index}"),
            ];
        });
        $overviewTotal = $monthlyApplications->sum('count');
        $overviewMaximum = max(1, $monthlyApplications->max('count'));

        return view('admin.dashboard', compact(
            'totalApplications', 'pendingApplications', 'scheduledInstallations',
            'installedApplications', 'openTickets', 'monthlyApplications',
            'overviewTotal', 'overviewMaximum', 'overviewUpdatedAt'
        ));
    }
}
