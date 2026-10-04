<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const TYPES = ['operations', 'applications', 'installations', 'tickets', 'customers', 'technicians'];

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $tab = $request->query('tab') === 'reports' || ($request->boolean('generate') && $filters['type'] !== 'operations')
            ? 'reports'
            : 'overview';
        $report = $this->buildReport($filters);
        $filterOptions = $this->filterOptions();
        $previewReady = $tab === 'overview' || $request->boolean('generate');

        if ($request->boolean('generate')) {
            system_log('Analytics Report Generated', ucfirst($filters['type']).' report previewed for '.$filters['from'].' through '.$filters['to'].$this->auditFilterSuffix($report['active_filters']).'.');
        }

        return view('admin.reports.index', compact('filters', 'report', 'tab', 'filterOptions', 'previewReady'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $report = $this->buildReport($filters, 10000);
        system_log('Analytics Report Exported', ucfirst($filters['type']).' CSV exported for '.$filters['from'].' through '.$filters['to'].$this->auditFilterSuffix($report['active_filters']).'.');
        $filename = 'mjjd-'.$filters['type'].'-'.$filters['from'].'-to-'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($report, $filters): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['MJJD Telecom '.ucfirst($filters['type']).' Report']);
            fputcsv($output, ['Period', $filters['from'].' to '.$filters['to']]);
            fputcsv($output, ['Generated', now()->format('F d, Y h:i A').' ('.config('app.timezone').')']);
            foreach ($report['active_filters'] as $filter) {
                fputcsv($output, ['Filter - '.$filter['label'], $filter['value']]);
            }
            fputcsv($output, []);
            fputcsv($output, array_values($report['columns']));
            foreach ($report['rows'] as $row) {
                fputcsv($output, array_map(fn (string $key) => $row[$key] ?? '', array_keys($report['columns'])));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function printApplication(Application $application): View
    {
        $application->load(['installation.technician']);
        $installation = $application->installation;

        return $this->detailView([
            'title' => 'Application Summary',
            'reference' => $application->reference_no ?: 'APP-'.$application->id,
            'status' => $this->label($application->status),
            'subtitle' => 'Important service application details for administrative hardcopy.',
            'back_url' => route('admin.applications.show', $application),
            'sections' => [
                $this->section('Application', [
                    'Reference number' => $application->reference_no ?: 'APP-'.$application->id,
                    'Status' => $this->label($application->status),
                    'Plan' => $application->plan_code,
                    'Submitted' => $application->created_at?->format('F d, Y h:i A'),
                    'Serviceability' => $this->label($application->serviceability_status),
                    'ID review' => $this->label($application->id_verification_status ?: 'pending_review'),
                ]),
                $this->section('Customer and Service Address', [
                    'Customer' => trim($application->first_name.' '.$application->last_name),
                    'Email' => $application->email,
                    'Mobile number' => $application->mobile_number,
                    'Service address' => $application->fullServiceAddress(),
                ]),
                $this->section('Installation', [
                    'Job number' => $installation?->displayJobReference(),
                    'Technician' => $installation?->technician?->name,
                    'Schedule' => $this->installationSchedule($installation),
                    'Status' => $installation?->displayStatusLabel(),
                    'Completed' => $installation?->completed_at?->format('F d, Y h:i A'),
                    'Activation date' => $installation?->activation_date?->format('F d, Y'),
                ]),
            ],
        ]);
    }

    public function printInstallation(Installation $installation): View
    {
        $installation->load(['application', 'technician']);
        $application = $installation->application;

        return $this->detailView([
            'title' => 'Installation Summary',
            'reference' => $installation->displayJobReference(),
            'status' => $installation->displayStatusLabel(),
            'subtitle' => 'Important installation and service details for field or administrative hardcopy.',
            'back_url' => route('admin.installations.index'),
            'sections' => [
                $this->section('Installation', [
                    'Job number' => $installation->displayJobReference(),
                    'Application reference' => $application?->reference_no,
                    'Status' => $installation->displayStatusLabel(),
                    'Schedule' => $this->installationSchedule($installation),
                    'Assigned technician' => $installation->technician?->name,
                    'Completed' => $installation->completed_at?->format('F d, Y h:i A'),
                    'Activation date' => $installation->activation_date?->format('F d, Y'),
                ]),
                $this->section('Customer and Service', [
                    'Customer' => $application ? trim($application->first_name.' '.$application->last_name) : null,
                    'Mobile number' => $application?->mobile_number,
                    'Email' => $application?->email,
                    'Plan' => $application?->plan_code,
                    'Service address' => $application?->fullServiceAddress(),
                ]),
                $this->section('Recorded Technical Details', $installation->technicalDetails()),
            ],
        ]);
    }

    public function printCustomer(User $customer): View
    {
        abort_unless($customer->isCustomer(), 404);
        $customer->loadCount([
            'applications',
            'tickets',
            'tickets as active_tickets_count' => fn ($query) => $query->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS]),
        ])->load(['application.installation.technician']);
        $application = $customer->application;
        $installation = $application?->installation;

        $backRoute = auth()->user()?->isTechnician()
            ? 'technician.customers.show'
            : 'admin.customers.show';

        return $this->detailView([
            'title' => 'Customer Summary',
            'reference' => $customer->displayCustomerRecordId(),
            'status' => $customer->isDisabled() ? 'Disabled' : 'Active',
            'subtitle' => 'Important customer, service, and support details for operational hardcopy.',
            'back_url' => route($backRoute, $customer),
            'sections' => [
                $this->section('Customer', [
                    'Customer ID' => $customer->displayCustomerRecordId(),
                    'Full name' => $customer->name,
                    'Email' => $customer->email ?: $application?->email,
                    'Mobile number' => $customer->phone ?: $application?->mobile_number,
                    'Account created' => $customer->created_at?->format('F d, Y h:i A'),
                    'Account status' => $customer->isDisabled() ? 'Disabled' : 'Active',
                ]),
                $this->section('Current Service', [
                    'Application reference' => $application?->reference_no,
                    'Application status' => $this->label($application?->status),
                    'Plan' => $application?->plan_code,
                    'Service address' => $application?->fullServiceAddress() ?: $customer->address,
                    'Installation status' => $installation?->displayStatusLabel(),
                    'Installation schedule' => $this->installationSchedule($installation),
                    'Assigned technician' => $installation?->technician?->name,
                ]),
                $this->section('Record Totals', [
                    'Applications' => $customer->applications_count,
                    'Support tickets' => $customer->tickets_count,
                    'Active support tickets' => $customer->active_tickets_count,
                ]),
            ],
        ]);
    }

    public function printTechnician(User $technician): View
    {
        abort_unless($technician->isTechnician(), 404);
        $technician->loadCount([
            'installations',
            'installations as scheduled_installations_count' => fn ($query) => $query->whereIn('status', Installation::activeSchedulingStatuses()),
            'installations as completed_installations_count' => fn ($query) => $query->whereIn('status', Installation::completedStatuses()),
            'assignedTickets',
            'assignedTickets as active_tickets_count' => fn ($query) => $query->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS]),
        ]);
        $nextInstallation = $technician->installations()
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereDate('installation_date', '>=', today())
            ->orderBy('installation_date')->orderBy('installation_time')->first();

        return $this->detailView([
            'title' => 'Technician Summary',
            'reference' => $technician->displayTechnicianRecordId(),
            'status' => $technician->isDisabled() ? 'Disabled' : $this->label($technician->technician_status ?: 'available'),
            'subtitle' => 'Important technician schedule and workload details for administrative hardcopy.',
            'back_url' => route('admin.technicians.show', $technician),
            'sections' => [
                $this->section('Technician', [
                    'Technician ID' => $technician->displayTechnicianRecordId(),
                    'Full name' => $technician->name,
                    'Email' => $technician->email,
                    'Mobile number' => $technician->phone,
                    'Employment type' => $technician->displayTechnicianEmploymentType(),
                    'Account status' => $technician->isDisabled() ? 'Disabled' : 'Active',
                    'Availability' => $this->label($technician->technician_status ?: 'available'),
                ]),
                $this->section('Schedule', [
                    'Working schedule' => $technician->displayTechnicianShift(),
                    'Maximum active tickets' => $technician->technician_max_active_tickets,
                    'Next installation' => $nextInstallation ? $nextInstallation->displayJobReference().' - '.$this->installationSchedule($nextInstallation) : 'No upcoming installation',
                ]),
                $this->section('Workload Totals', [
                    'All installations' => $technician->installations_count,
                    'Scheduled installations' => $technician->scheduled_installations_count,
                    'Completed installations' => $technician->completed_installations_count,
                    'All assigned tickets' => $technician->assigned_tickets_count,
                    'Active tickets' => $technician->active_tickets_count,
                ]),
            ],
        ]);
    }

    private function detailView(array $document): View
    {
        $document['generated_at'] = now();
        $document['generated_by'] = auth()->user()->name;

        return view('admin.reports.detail', compact('document'));
    }

    private function section(string $title, array $items): array
    {
        return [
            'title' => $title,
            'items' => collect($items)->map(fn ($value, $label) => [
                'label' => $label,
                'value' => filled($value) || $value === 0 ? (string) $value : 'Not recorded',
            ])->values()->all(),
        ];
    }

    private function installationSchedule(?Installation $installation): ?string
    {
        if (! $installation?->installation_date) {
            return null;
        }

        $time = $installation->installation_time
            ? Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i'))
            : 'Time not set';

        return $installation->installation_date->format('F d, Y').' at '.$time;
    }

    private function label(?string $value): string
    {
        return filled($value) ? ucfirst(str_replace('_', ' ', $value)) : 'Not recorded';
    }

    private function validatedFilters(Request $request): array
    {
        $defaults = [
            'type' => 'operations',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'status' => null,
            'technician' => null,
            'municipality' => null,
            'urgency' => null,
            'plan' => null,
        ];
        $values = array_merge($defaults, $request->only(array_keys($defaults)));
        $validated = validator($values, [
            'type' => ['required', Rule::in(self::TYPES)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:60'],
            'technician' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'technician']))],
            'municipality' => ['nullable', 'string', 'max:120'],
            'urgency' => ['nullable', Rule::in(Ticket::URGENCY_LEVELS)],
            'plan' => ['nullable', 'string', 'max:60'],
        ])->validate();

        $allowedStatuses = array_keys(self::statusOptions()[$validated['type']] ?? []);
        if (filled($validated['status']) && ! in_array($validated['status'], $allowedStatuses, true)) {
            validator(['status' => $validated['status']], [
                'status' => ['nullable', Rule::in($allowedStatuses)],
            ])->validate();
        }

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->endOfDay();

        $relevant = [
            'status' => ['applications', 'installations', 'tickets', 'customers', 'technicians'],
            'technician' => ['installations', 'tickets'],
            'municipality' => ['applications', 'installations', 'customers'],
            'urgency' => ['tickets'],
            'plan' => ['applications', 'customers'],
        ];
        foreach ($relevant as $filter => $types) {
            if (! in_array($validated['type'], $types, true)) {
                $validated[$filter] = null;
            }
        }

        return [
            'type' => $validated['type'],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'from_at' => $from,
            'to_at' => $to,
            'status' => $validated['status'] ?: null,
            'technician' => filled($validated['technician']) ? (int) $validated['technician'] : null,
            'municipality' => $validated['municipality'] ?: null,
            'urgency' => $validated['urgency'] ?: null,
            'plan' => $validated['plan'] ?: null,
        ];
    }

    private function buildReport(array $filters, int $limit = 1000): array
    {
        $from = $filters['from_at'];
        $to = $filters['to_at'];
        $applicationBase = Application::query()->whereBetween('created_at', [$from, $to]);
        $installationBase = Installation::query()->whereBetween('installation_date', [$filters['from'], $filters['to']]);
        $ticketBase = Ticket::query()->whereBetween('created_at', [$from, $to]);
        $customerBase = User::query()->where('role', 'customer')->whereBetween('created_at', [$from, $to]);

        $applicationCounts = (clone $applicationBase)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        $applicationQuery = clone $applicationBase;
        $installationQuery = clone $installationBase;
        $ticketQuery = clone $ticketBase;
        $customerQuery = clone $customerBase;
        $technicianQuery = User::query()->where('role', 'technician');

        $this->applyApplicationFilters($applicationQuery, $filters);
        $this->applyInstallationFilters($installationQuery, $filters);
        $this->applyTicketFilters($ticketQuery, $filters);
        $this->applyCustomerFilters($customerQuery, $filters);
        $this->applyTechnicianFilters($technicianQuery, $filters);

        $summary = $filters['type'] === 'operations'
            ? $this->comparisonSummary($from, $to)
            : $this->reportSummary($filters['type'], $applicationQuery, $installationQuery, $ticketQuery, $customerQuery, $technicianQuery);

        [$columns, $rows, $totalRows] = match ($filters['type']) {
            'applications' => $this->applicationRows($applicationQuery, $limit),
            'installations' => $this->installationRows($installationQuery, $limit),
            'tickets' => $this->ticketRows($ticketQuery, $limit),
            'customers' => $this->customerRows($customerQuery, $limit),
            'technicians' => $this->technicianRows($technicianQuery, $filters, $limit),
            default => $this->operationsRows($summary),
        };

        return [
            'title' => match ($filters['type']) {
                'applications' => 'Application Report',
                'installations' => 'Installation Report',
                'tickets' => 'Support Ticket Report',
                'customers' => 'Customer Report',
                'technicians' => 'Technician Workload Report',
                default => 'Operations Analytics Overview',
            },
            'summary' => $summary,
            'columns' => $columns,
            'rows' => $rows,
            'total_rows' => $totalRows,
            'truncated' => $totalRows > $limit,
            'monthly_activity' => $filters['type'] === 'operations' ? $this->monthlyActivity($from, $to) : collect(),
            'application_pipeline' => $filters['type'] === 'operations' ? collect([
                ['label' => 'Submitted', 'value' => (int) $applicationCounts->get(Application::STATUS_SUBMITTED, 0)],
                ['label' => 'Under review', 'value' => (int) $applicationCounts->get(Application::STATUS_UNDER_REVIEW, 0)],
                ['label' => 'Approved / scheduled', 'value' => (int) $applicationCounts->get(Application::STATUS_APPROVED, 0) + (int) $applicationCounts->get(Application::STATUS_SCHEDULED, 0)],
                ['label' => 'Installed / activated', 'value' => (int) $applicationCounts->get(Application::STATUS_INSTALLED, 0) + (int) $applicationCounts->get(Application::STATUS_ACTIVATED, 0)],
                ['label' => 'Rejected / cancelled', 'value' => (int) $applicationCounts->get(Application::STATUS_REJECTED, 0) + (int) $applicationCounts->get(Application::STATUS_CANCELLED, 0)],
            ]) : collect(),
            'recent_activity' => $filters['type'] === 'operations' ? $this->recentActivity($from, $to) : collect(),
            'previous_period' => $filters['type'] === 'operations' ? $this->previousPeriod($from, $to) : null,
            'active_filters' => $this->activeFilterLabels($filters),
            'query' => $this->filterQuery($filters),
            'generated_at' => now(),
        ];
    }

    private function comparisonSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $current = $this->operationsSummary($from, $to);
        $previous = $this->previousPeriod($from, $to);
        $previousValues = collect($this->operationsSummary($previous['from_at'], $previous['to_at']))->keyBy('key');

        return collect($current)->map(function (array $metric) use ($previousValues): array {
            $previousValue = (int) ($previousValues->get($metric['key'])['value'] ?? 0);
            $difference = (int) $metric['value'] - $previousValue;
            if ($previousValue === 0) {
                $changeLabel = $metric['value'] > 0 ? 'New activity' : 'No change';
                $direction = $metric['value'] > 0 ? 'up' : 'neutral';
            } else {
                $percent = (int) round(abs($difference) / $previousValue * 100);
                $changeLabel = $difference === 0 ? 'No change' : $percent.'% '.($difference > 0 ? 'more' : 'fewer');
                $direction = $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'neutral');
            }

            return $metric + ['previous_value' => $previousValue, 'change_label' => $changeLabel, 'change_direction' => $direction];
        })->all();
    }

    private function operationsSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $applications = Application::query()->whereBetween('created_at', [$from, $to])->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $installations = Installation::query()->whereBetween('installation_date', [$from->toDateString(), $to->toDateString()])->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $tickets = Ticket::query()->whereBetween('created_at', [$from, $to])->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            ['key' => 'customers', 'label' => 'Customers registered', 'value' => User::query()->where('role', 'customer')->whereBetween('created_at', [$from, $to])->count(), 'report_type' => 'customers'],
            ['key' => 'applications', 'label' => 'Applications submitted', 'value' => (int) $applications->sum(), 'report_type' => 'applications'],
            ['key' => 'applications_pending', 'label' => 'Applications pending review', 'value' => (int) $applications->get(Application::STATUS_SUBMITTED, 0) + (int) $applications->get(Application::STATUS_UNDER_REVIEW, 0), 'report_type' => 'applications', 'report_status' => 'pending_review'],
            ['key' => 'applications_activated', 'label' => 'Applications activated', 'value' => (int) $applications->get(Application::STATUS_ACTIVATED, 0), 'report_type' => 'applications', 'report_status' => Application::STATUS_ACTIVATED],
            ['key' => 'installations_scheduled', 'label' => 'Installations scheduled', 'value' => (int) $installations->get(Installation::STATUS_SCHEDULED, 0) + (int) $installations->get(Installation::STATUS_ASSIGNED, 0), 'report_type' => 'installations', 'report_status' => 'scheduled_assigned'],
            ['key' => 'installations_completed', 'label' => 'Installations completed', 'value' => (int) $installations->get(Installation::STATUS_COMPLETED, 0) + (int) $installations->get(Installation::STATUS_ACTIVATED, 0), 'report_type' => 'installations', 'report_status' => 'completed_activated'],
            ['key' => 'tickets', 'label' => 'Tickets submitted', 'value' => (int) $tickets->sum(), 'report_type' => 'tickets'],
            ['key' => 'tickets_open', 'label' => 'Tickets open or queued', 'value' => (int) $tickets->get(Ticket::STATUS_OPEN, 0) + (int) $tickets->get(Ticket::STATUS_QUEUED, 0), 'report_type' => 'tickets', 'report_status' => 'open_queued'],
            ['key' => 'tickets_completed', 'label' => 'Tickets resolved or closed', 'value' => (int) $tickets->get(Ticket::STATUS_RESOLVED, 0) + (int) $tickets->get(Ticket::STATUS_CLOSED, 0), 'report_type' => 'tickets', 'report_status' => 'resolved_closed'],
        ];
    }

    private function reportSummary(string $type, $applications, $installations, $tickets, $customers, $technicians): array
    {
        return match ($type) {
            'applications' => $this->statusSummary($applications, [
                ['label' => 'Matching applications', 'statuses' => null],
                ['label' => 'Pending review', 'statuses' => [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW]],
                ['label' => 'Approved / scheduled', 'statuses' => [Application::STATUS_APPROVED, Application::STATUS_SCHEDULED]],
                ['label' => 'Installed / activated', 'statuses' => [Application::STATUS_INSTALLED, Application::STATUS_ACTIVATED]],
                ['label' => 'Rejected / cancelled', 'statuses' => [Application::STATUS_REJECTED, Application::STATUS_CANCELLED]],
            ]),
            'installations' => $this->statusSummary($installations, [
                ['label' => 'Matching installations', 'statuses' => null],
                ['label' => 'Scheduled / assigned', 'statuses' => [Installation::STATUS_SCHEDULED, Installation::STATUS_ASSIGNED]],
                ['label' => 'In progress', 'statuses' => [Installation::STATUS_IN_PROGRESS]],
                ['label' => 'Completed / activated', 'statuses' => [Installation::STATUS_COMPLETED, Installation::STATUS_ACTIVATED]],
                ['label' => 'Needs reschedule / cancelled', 'statuses' => [Installation::STATUS_RESCHEDULE_REQUIRED, Installation::STATUS_CANCELLED]],
            ]),
            'tickets' => [
                ['label' => 'Matching tickets', 'value' => (clone $tickets)->count()],
                ['label' => 'Open / queued', 'value' => (clone $tickets)->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED])->count()],
                ['label' => 'In progress', 'value' => (clone $tickets)->where('status', Ticket::STATUS_IN_PROGRESS)->count()],
                ['label' => 'Resolved / closed', 'value' => (clone $tickets)->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count()],
                ['label' => 'High / critical urgency', 'value' => (clone $tickets)->whereIn('urgency', [Ticket::URGENCY_HIGH, Ticket::URGENCY_CRITICAL])->count()],
            ],
            'customers' => [
                ['label' => 'Matching customers', 'value' => (clone $customers)->count()],
                ['label' => 'Active accounts', 'value' => (clone $customers)->whereNull('disabled_at')->count()],
                ['label' => 'Disabled accounts', 'value' => (clone $customers)->whereNotNull('disabled_at')->count()],
                ['label' => 'With an application', 'value' => (clone $customers)->whereHas('application')->count()],
                ['label' => 'With active tickets', 'value' => (clone $customers)->whereHas('tickets', fn ($query) => $query->whereIn('status', Ticket::activeWorkStatuses()))->count()],
            ],
            'technicians' => [
                ['label' => 'Matching technicians', 'value' => (clone $technicians)->count()],
                ['label' => 'Available', 'value' => (clone $technicians)->whereNull('disabled_at')->where('technician_status', 'available')->count()],
                ['label' => 'Off duty', 'value' => (clone $technicians)->whereNull('disabled_at')->where('technician_status', 'off_duty')->count()],
                ['label' => 'On leave', 'value' => (clone $technicians)->whereNull('disabled_at')->where('technician_status', 'on_leave')->count()],
                ['label' => 'Disabled', 'value' => (clone $technicians)->whereNotNull('disabled_at')->count()],
            ],
            default => [],
        };
    }

    private function statusSummary($query, array $definitions): array
    {
        return collect($definitions)->map(fn (array $definition) => [
            'label' => $definition['label'],
            'value' => $definition['statuses'] === null
                ? (clone $query)->count()
                : (clone $query)->whereIn('status', $definition['statuses'])->count(),
        ])->all();
    }

    private function previousPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->startOfDay()->subDays($days - 1);

        return [
            'from' => $previousFrom->toDateString(),
            'to' => $previousTo->toDateString(),
            'from_at' => $previousFrom,
            'to_at' => $previousTo,
            'label' => $previousFrom->format('M d, Y').' - '.$previousTo->format('M d, Y'),
        ];
    }

    private function applyApplicationFilters($query, array $filters): void
    {
        $this->applyStatusFilter($query, 'applications', $filters['status']);
        $query->when($filters['municipality'], fn ($builder, $value) => $builder->where('municipality', $value));
        $query->when($filters['plan'], fn ($builder, $value) => $builder->where('plan_code', $value));
    }

    private function applyInstallationFilters($query, array $filters): void
    {
        $this->applyStatusFilter($query, 'installations', $filters['status']);
        $query->when($filters['technician'], fn ($builder, $value) => $builder->where('technician_id', $value));
        $query->when($filters['municipality'], fn ($builder, $value) => $builder->whereHas('application', fn ($application) => $application->where('municipality', $value)));
    }

    private function applyTicketFilters($query, array $filters): void
    {
        $this->applyStatusFilter($query, 'tickets', $filters['status']);
        $query->when($filters['technician'], fn ($builder, $value) => $builder->where('assigned_staff_id', $value));
        $query->when($filters['urgency'], fn ($builder, $value) => $builder->where('urgency', $value));
    }

    private function applyCustomerFilters($query, array $filters): void
    {
        if ($filters['status'] === 'without_application') {
            $query->whereDoesntHave('application');
        } elseif ($filters['status']) {
            $query->whereHas('application', function ($application) use ($filters): void {
                $this->applyStatusFilter($application, 'applications', $filters['status']);
            });
        }
        $query->when($filters['municipality'], fn ($builder, $value) => $builder->whereHas('application', fn ($application) => $application->where('municipality', $value)));
        $query->when($filters['plan'], fn ($builder, $value) => $builder->whereHas('application', fn ($application) => $application->where('plan_code', $value)));
    }

    private function applyTechnicianFilters($query, array $filters): void
    {
        if ($filters['status'] === 'disabled') {
            $query->whereNotNull('disabled_at');
        } elseif ($filters['status'] === 'active') {
            $query->whereNull('disabled_at');
        } elseif ($filters['status']) {
            $query->whereNull('disabled_at')->where('technician_status', $filters['status']);
        }
    }

    private function applyStatusFilter($query, string $type, ?string $status): void
    {
        if (! $status) {
            return;
        }
        $groups = [
            'applications' => [
                'pending_review' => [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW],
                'approved_scheduled' => [Application::STATUS_APPROVED, Application::STATUS_SCHEDULED],
                'installed_activated' => [Application::STATUS_INSTALLED, Application::STATUS_ACTIVATED],
                'rejected_cancelled' => [Application::STATUS_REJECTED, Application::STATUS_CANCELLED],
            ],
            'installations' => [
                'scheduled_assigned' => [Installation::STATUS_SCHEDULED, Installation::STATUS_ASSIGNED],
                'completed_activated' => [Installation::STATUS_COMPLETED, Installation::STATUS_ACTIVATED],
            ],
            'tickets' => [
                'open_queued' => [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED],
                'resolved_closed' => [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED],
            ],
        ];
        $group = $groups[$type][$status] ?? null;
        $group ? $query->whereIn('status', $group) : $query->where('status', $status);
    }

    private function operationsRows(array $summary): array
    {
        return [
            ['metric' => 'Metric', 'value' => 'Value'],
            collect($summary)->map(fn (array $item) => ['metric' => $item['label'], 'value' => $item['value']])->all(),
            count($summary),
        ];
    }

    private function applicationRows($query, int $limit): array
    {
        $total = (clone $query)->count();
        $rows = $query->latest()->limit($limit)->get()->map(fn (Application $application) => [
            'reference' => $application->reference_no ?: 'APP-'.$application->id,
            'customer' => trim($application->first_name.' '.$application->last_name),
            'plan' => $application->plan_code,
            'submitted' => $application->created_at->format('M d, Y h:i A'),
            'status' => ucfirst(str_replace('_', ' ', $application->status)),
        ])->all();

        return [['reference' => 'Reference', 'customer' => 'Customer', 'plan' => 'Plan', 'submitted' => 'Submitted', 'status' => 'Status'], $rows, $total];
    }

    private function installationRows($query, int $limit): array
    {
        $total = (clone $query)->count();
        $rows = $query->with(['application', 'technician'])->orderByDesc('installation_date')->limit($limit)->get()->map(fn (Installation $installation) => [
            'job' => $installation->displayJobReference(),
            'customer' => trim(($installation->application?->first_name ?? '').' '.($installation->application?->last_name ?? '')),
            'technician' => $installation->technician?->name ?? 'Unassigned',
            'schedule' => $installation->installation_date?->format('M d, Y').' '.($installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('g:i A') : ''),
            'status' => $installation->displayStatusLabel(),
        ])->all();

        return [['job' => 'Job Number', 'customer' => 'Customer', 'technician' => 'Technician', 'schedule' => 'Schedule', 'status' => 'Status'], $rows, $total];
    }

    private function ticketRows($query, int $limit): array
    {
        $total = (clone $query)->count();
        $rows = $query->with(['user', 'assignedStaff'])->latest()->limit($limit)->get()->map(fn (Ticket $ticket) => [
            'tracking' => $ticket->publicReference(),
            'customer' => $ticket->user?->name ?? 'Unknown',
            'subject' => $ticket->subject,
            'urgency' => $ticket->displayUrgencyLabel(),
            'assignee' => $ticket->assignedStaff?->name ?? 'Unassigned Queue',
            'submitted' => $ticket->created_at->format('M d, Y h:i A'),
            'status' => $ticket->isQueued() ? 'Queued' : $ticket->displayStatusLabel(),
        ])->all();

        return [['tracking' => 'Tracking Number', 'customer' => 'Customer', 'subject' => 'Subject', 'urgency' => 'Urgency', 'assignee' => 'Assigned Staff', 'submitted' => 'Submitted', 'status' => 'Status'], $rows, $total];
    }

    private function customerRows($query, int $limit): array
    {
        $total = (clone $query)->count();
        $rows = $query->with('application')->withCount([
            'tickets',
            'tickets as active_tickets' => fn ($ticketQuery) => $ticketQuery->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS]),
        ])->orderByDesc('created_at')->limit($limit)->get()->map(fn (User $customer) => [
            'customer_id' => $customer->displayCustomerRecordId(),
            'customer' => $customer->name,
            'contact' => $customer->phone ?: $customer->email,
            'application' => $customer->application?->reference_no ?? 'No application',
            'application_status' => $this->label($customer->application?->status),
            'tickets' => $customer->tickets_count,
            'active_tickets' => $customer->active_tickets,
            'registered' => $customer->created_at->format('M d, Y h:i A'),
        ])->all();

        return [[
            'customer_id' => 'Customer ID',
            'customer' => 'Customer',
            'contact' => 'Contact',
            'application' => 'Latest Application',
            'application_status' => 'Application Status',
            'tickets' => 'Tickets',
            'active_tickets' => 'Active Tickets',
            'registered' => 'Registered',
        ], $rows, $total];
    }

    private function technicianRows($query, array $filters, int $limit): array
    {
        $query->withCount([
                'installations as scheduled_installations' => fn ($query) => $query->whereBetween('installation_date', [$filters['from'], $filters['to']]),
                'installations as completed_installations' => fn ($query) => $query->whereBetween('installation_date', [$filters['from'], $filters['to']])->whereIn('status', Installation::completedStatuses()),
                'assignedTickets as assigned_tickets' => fn ($query) => $query->whereBetween('created_at', [$filters['from_at'], $filters['to_at']]),
                'assignedTickets as completed_tickets' => fn ($query) => $query->whereBetween('created_at', [$filters['from_at'], $filters['to_at']])->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED]),
            ]);
        $total = (clone $query)->count();
        $rows = $query->orderBy('name')->limit($limit)->get()->map(fn (User $technician) => [
            'technician' => $technician->name,
            'schedule' => $technician->displayTechnicianShift(),
            'installations' => $technician->scheduled_installations,
            'completed_installations' => $technician->completed_installations,
            'tickets' => $technician->assigned_tickets,
            'completed_tickets' => $technician->completed_tickets,
            'status' => $technician->isDisabled() ? 'Disabled' : ucfirst(str_replace('_', ' ', $technician->technician_status ?: 'available')),
        ])->all();

        return [['technician' => 'Technician', 'schedule' => 'Working Schedule', 'installations' => 'Installations', 'completed_installations' => 'Completed Installations', 'tickets' => 'Tickets', 'completed_tickets' => 'Resolved/Closed Tickets', 'status' => 'Status'], $rows, $total];
    }

    private function monthlyActivity(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $monthKey = fn ($value) => CarbonImmutable::parse($value)->format('Y-m');
        $applicationCounts = Application::query()->whereBetween('created_at', [$from, $to])->pluck('created_at')->countBy($monthKey);
        $installationCounts = Installation::query()->whereBetween('installation_date', [$from->toDateString(), $to->toDateString()])->pluck('installation_date')->countBy($monthKey);
        $ticketCounts = Ticket::query()->whereBetween('created_at', [$from, $to])->pluck('created_at')->countBy($monthKey);
        $customerCounts = User::query()->where('role', 'customer')->whereBetween('created_at', [$from, $to])->pluck('created_at')->countBy($monthKey);

        $months = collect();
        for ($month = $from->startOfMonth(); $month->lte($to->startOfMonth()) && $months->count() < 13; $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $months->push([
                'label' => $month->format('M Y'),
                'applications' => (int) $applicationCounts->get($key, 0),
                'installations' => (int) $installationCounts->get($key, 0),
                'tickets' => (int) $ticketCounts->get($key, 0),
                'customers' => (int) $customerCounts->get($key, 0),
            ]);
        }

        return $months;
    }

    private function recentActivity(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $applications = Application::query()->whereBetween('created_at', [$from, $to])->latest()->limit(5)->get()->map(fn (Application $application) => [
            'type' => 'Application',
            'reference' => $application->reference_no ?: 'APP-'.$application->id,
            'record' => trim($application->first_name.' '.$application->last_name),
            'status' => $this->label($application->status),
            'date' => $application->created_at,
        ]);
        $tickets = Ticket::query()->with('user')->whereBetween('created_at', [$from, $to])->latest()->limit(5)->get()->map(fn (Ticket $ticket) => [
            'type' => 'Ticket',
            'reference' => $ticket->publicReference(),
            'record' => $ticket->user?->name ?? $ticket->subject,
            'status' => $ticket->isQueued() ? 'Queued' : $ticket->displayStatusLabel(),
            'date' => $ticket->created_at,
        ]);
        $installations = Installation::query()->with('application')->whereBetween('installation_date', [$from->toDateString(), $to->toDateString()])->orderByDesc('installation_date')->limit(5)->get()->map(fn (Installation $installation) => [
            'type' => 'Installation',
            'reference' => $installation->displayJobReference(),
            'record' => trim(($installation->application?->first_name ?? '').' '.($installation->application?->last_name ?? '')),
            'status' => $installation->displayStatusLabel(),
            'date' => $installation->installation_date,
        ]);
        $customers = User::query()->where('role', 'customer')->whereBetween('created_at', [$from, $to])->latest()->limit(5)->get()->map(fn (User $customer) => [
            'type' => 'Customer',
            'reference' => $customer->displayCustomerRecordId(),
            'record' => $customer->name,
            'status' => $customer->isDisabled() ? 'Disabled' : 'Active',
            'date' => $customer->created_at,
        ]);

        return $applications->concat($tickets)->concat($installations)->concat($customers)
            ->sortByDesc('date')->take(8)->values()
            ->map(fn (array $item) => $item + ['date_label' => $item['date']?->format('M d, Y h:i A')]);
    }

    private function filterOptions(): array
    {
        return [
            'statuses_by_type' => self::statusOptions(),
            'staff' => User::query()->whereIn('role', ['admin', 'technician'])->whereNull('disabled_at')->orderBy('name')->get(['id', 'name', 'role'])->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name.' ('.ucfirst($user->role).')',
            ])->all(),
            'municipalities' => Application::query()->whereNotNull('municipality')->where('municipality', '!=', '')->distinct()->orderBy('municipality')->pluck('municipality')->values()->all(),
            'plans' => Application::query()->whereNotNull('plan_code')->where('plan_code', '!=', '')->distinct()->orderBy('plan_code')->pluck('plan_code')->values()->all(),
            'urgencies' => collect(Ticket::URGENCY_LEVELS)->mapWithKeys(fn ($value) => [$value => ucfirst($value)])->all(),
        ];
    }

    private static function statusOptions(): array
    {
        return [
            'operations' => [],
            'applications' => [
                'pending_review' => 'Pending review (submitted / under review)',
                'approved_scheduled' => 'Approved / scheduled',
                Application::STATUS_SUBMITTED => 'Submitted',
                Application::STATUS_UNDER_REVIEW => 'Under review',
                Application::STATUS_APPROVED => 'Approved',
                Application::STATUS_SCHEDULED => 'Scheduled',
                Application::STATUS_CANCELLATION_REQUESTED => 'Cancellation requested',
                'installed_activated' => 'Installed / activated',
                Application::STATUS_INSTALLED => 'Installed',
                Application::STATUS_ACTIVATED => 'Activated',
                'rejected_cancelled' => 'Rejected / cancelled',
                Application::STATUS_REJECTED => 'Rejected',
                Application::STATUS_CANCELLED => 'Cancelled',
            ],
            'installations' => [
                'scheduled_assigned' => 'Scheduled / assigned',
                Installation::STATUS_SCHEDULED => 'Scheduled',
                Installation::STATUS_ASSIGNED => 'Assigned',
                Installation::STATUS_IN_PROGRESS => 'In progress',
                Installation::STATUS_RESCHEDULE_REQUIRED => 'Reschedule required',
                'completed_activated' => 'Completed / activated',
                Installation::STATUS_COMPLETED => 'Completed',
                Installation::STATUS_ACTIVATED => 'Activated',
                Installation::STATUS_CANCELLED => 'Cancelled',
            ],
            'tickets' => [
                'open_queued' => 'Open / queued',
                Ticket::STATUS_OPEN => 'Open',
                Ticket::STATUS_QUEUED => 'Queued',
                Ticket::STATUS_IN_PROGRESS => 'In progress',
                Ticket::STATUS_CANCELLATION_REQUESTED => 'Cancellation requested',
                'resolved_closed' => 'Resolved / closed',
                Ticket::STATUS_RESOLVED => 'Resolved',
                Ticket::STATUS_CLOSED => 'Closed',
                Ticket::STATUS_CANCELLED => 'Cancelled',
            ],
            'customers' => [
                'without_application' => 'Without an application',
                'pending_review' => 'Application pending review',
                'approved_scheduled' => 'Application approved / scheduled',
                'installed_activated' => 'Application installed / activated',
                'rejected_cancelled' => 'Application rejected / cancelled',
            ],
            'technicians' => [
                'active' => 'All active accounts',
                'available' => 'Available',
                'off_duty' => 'Off duty',
                'on_leave' => 'On leave',
                'disabled' => 'Disabled',
            ],
        ];
    }

    private function activeFilterLabels(array $filters): array
    {
        $labels = [];
        if ($filters['status']) {
            $labels[] = [
                'label' => $filters['type'] === 'customers' ? 'Application status' : 'Status',
                'value' => self::statusOptions()[$filters['type']][$filters['status']] ?? $this->label($filters['status']),
            ];
        }
        if ($filters['technician']) {
            $staff = User::query()->find($filters['technician']);
            $labels[] = ['label' => 'Assigned staff', 'value' => $staff?->name ?? 'Unknown staff member'];
        }
        if ($filters['municipality']) {
            $labels[] = ['label' => 'Municipality', 'value' => $filters['municipality']];
        }
        if ($filters['urgency']) {
            $labels[] = ['label' => 'Urgency', 'value' => ucfirst($filters['urgency'])];
        }
        if ($filters['plan']) {
            $labels[] = ['label' => 'Plan', 'value' => $filters['plan']];
        }

        return $labels;
    }

    private function filterQuery(array $filters): array
    {
        return array_filter([
            'tab' => 'reports',
            'generate' => 1,
            'type' => $filters['type'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'technician' => $filters['technician'],
            'municipality' => $filters['municipality'],
            'urgency' => $filters['urgency'],
            'plan' => $filters['plan'],
        ], fn ($value) => filled($value));
    }

    private function auditFilterSuffix(array $activeFilters): string
    {
        if ($activeFilters === []) {
            return '';
        }

        return ' Filters: '.collect($activeFilters)->map(fn (array $filter) => $filter['label'].'='.$filter['value'])->implode(', ');
    }

}
