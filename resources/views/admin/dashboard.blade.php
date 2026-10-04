@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
@php
    $scheduledCount = (int) $installationStatusCounts->get(\App\Models\Installation::STATUS_SCHEDULED, 0)
        + (int) $installationStatusCounts->get(\App\Models\Installation::STATUS_ASSIGNED, 0);
    $inProgressCount = (int) $installationStatusCounts->get(\App\Models\Installation::STATUS_IN_PROGRESS, 0);
    $completedCount = (int) $installationStatusCounts->get(\App\Models\Installation::STATUS_COMPLETED, 0)
        + (int) $installationStatusCounts->get(\App\Models\Installation::STATUS_ACTIVATED, 0);
@endphp

<div class="telecom-page admin-workload-dashboard">
    <section class="telecom-hero admin-workload-hero">
        <div>
            <span class="admin-workload-eyebrow">Operations command center</span>
            <h1>Welcome, {{ Auth::user()->name }}</h1>
            <p>See what needs attention now, who is carrying the workload, and which installations are scheduled during the selected period.</p>
        </div>
        <div class="telecom-hero-actions">
            <a class="btn secondary" href="{{ route('admin.reports.index') }}">Open Analytics</a>
        </div>
    </section>

    @if(($overdueTickets + $overdueInstallations) > 0)
        <section class="ui-operations-alert" role="status" aria-label="Overdue work alert">
            <div class="ui-operations-alert__copy">
                <strong>{{ number_format($overdueTickets + $overdueInstallations) }} overdue {{ Str::plural('task', $overdueTickets + $overdueInstallations) }} need attention</strong>
                <span>{{ number_format($overdueInstallations) }} installations and {{ number_format($overdueTickets) }} tickets are past their service target.</span>
            </div>
            <a href="{{ $overdueTickets > 0 ? route('admin.tickets.index', ['overdue' => 1]) : route('admin.installations.index') }}" class="btn">Review work</a>
        </section>
    @endif

    <section class="admin-workload-toolbar" aria-label="Dashboard period">
        <div>
            <span class="admin-workload-eyebrow">Current workload</span>
            <h2>{{ $periodLabel }}</h2>
            <p>Installation schedules use {{ config('app.timezone') }} time.</p>
        </div>
        <nav class="admin-workload-periods" aria-label="Choose workload period">
            @foreach($periodOptions as $value => $label)
                <a href="{{ route('admin.dashboard', ['period' => $value]) }}"
                   class="{{ $period === $value ? 'is-active' : '' }}"
                   @if($period === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <section class="admin-attention-grid" aria-label="Admin attention items">
        @foreach($attentionItems as $item)
            <a class="admin-attention-card is-{{ $item['tone'] }}" href="{{ $item['url'] }}">
                <span>{{ $item['label'] }}</span>
                <strong>{{ number_format($item['count']) }}</strong>
                <small>{{ $item['detail'] }}</small>
                <em>Review <span aria-hidden="true">&rarr;</span></em>
            </a>
        @endforeach
    </section>

    <section class="admin-workload-main-grid">
        <article class="panel-card admin-schedule-panel">
            <header class="admin-section-heading">
                <div>
                    <span class="admin-workload-eyebrow">Installation schedule</span>
                    <h2>{{ number_format($installationTotal) }} {{ Str::plural('installation', $installationTotal) }} in this period</h2>
                    <p>Customer, assigned technician, time, and service location.</p>
                </div>
                <a class="btn secondary" href="{{ route('admin.installations.index') }}">View all</a>
            </header>

            <div class="admin-schedule-summary" aria-label="Installation status summary">
                <span><strong>{{ number_format($scheduledCount) }}</strong> Scheduled</span>
                <span><strong>{{ number_format($inProgressCount) }}</strong> In progress</span>
                <span><strong>{{ number_format($completedCount) }}</strong> Completed</span>
            </div>

            <div class="admin-schedule-list">
                @forelse($installationPreview as $installation)
                    @php
                        $application = $installation->application;
                        $customerName = trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')) ?: 'Customer unavailable';
                        $location = collect([$application?->barangay, $application?->municipality])->filter()->implode(', ') ?: 'Location unavailable';
                        $isLate = $installation->scheduledEndAt()?->isPast() && in_array($installation->status, \App\Models\Installation::activeSchedulingStatuses(), true);
                    @endphp
                    <a class="admin-schedule-item" href="{{ $application ? route('admin.applications.show', $application) : route('admin.installations.index') }}">
                        <time datetime="{{ $installation->installation_date?->toDateString() }}">
                            <strong>{{ $installation->installation_date?->format('D') }}</strong>
                            <span>{{ $installation->installation_date?->format('M j') }}</span>
                        </time>
                        <div class="admin-schedule-copy">
                            <div class="admin-schedule-title">
                                <strong>{{ $installation->displayJobReference() }}</strong>
                                <span class="admin-status-pill is-{{ $isLate ? 'danger' : $installation->status }}">{{ $isLate ? 'Overdue' : $installation->displayStatusLabel() }}</span>
                            </div>
                            <span>{{ $customerName }}</span>
                            <small>{{ $location }}</small>
                        </div>
                        <div class="admin-schedule-assignment">
                            <strong>{{ $installation->installation_time ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i')) : 'Time pending' }}</strong>
                            <span>{{ $installation->technician?->name ?? 'Unassigned' }}</span>
                            <small>{{ $application?->lat !== null && $application?->lng !== null ? 'Map pin available' : 'Map pin needs review' }}</small>
                        </div>
                    </a>
                @empty
                    <div class="admin-empty-state">
                        <strong>No installations in this period</strong>
                        <p>Choose another period or open Installations to schedule approved applications.</p>
                    </div>
                @endforelse
            </div>

            @if($installationRemaining > 0)
                <a class="admin-list-footer" href="{{ route('admin.installations.index') }}">
                    View {{ number_format($installationRemaining) }} more scheduled {{ Str::plural('installation', $installationRemaining) }}
                </a>
            @endif
        </article>

    </section>

    <section class="panel-card admin-technician-panel" aria-labelledby="technicianWorkloadTitle">
        <header class="admin-section-heading">
            <div>
                <span class="admin-workload-eyebrow">Technician workload</span>
                <h2 id="technicianWorkloadTitle">Capacity and assignments for {{ strtolower($periodOptions[$period]) }}</h2>
                <p>Installation capacity follows each technician schedule; ticket capacity follows the configured active-ticket limit.</p>
            </div>
            <a class="btn secondary" href="{{ route('admin.technicians.index') }}">Manage technicians</a>
        </header>

        <div class="admin-technician-list">
            @forelse($technicianWorkloads as $workload)
                @php($technician = $workload['technician'])
                <a class="admin-technician-row" href="{{ route('admin.technicians.show', $technician) }}">
                    <span class="admin-technician-avatar">{{ $technician->initials }}</span>
                    <span class="admin-technician-identity">
                        <strong>{{ $technician->name }}</strong>
                        <small>{{ $technician->displayTechnicianShift() }}</small>
                        <span class="admin-status-pill is-{{ $workload['schedule']['tone'] }}">{{ $workload['schedule']['label'] }}</span>
                    </span>
                    <span class="admin-technician-load">
                        <span><strong>{{ $workload['installation_count'] }}</strong> / {{ $workload['installation_capacity'] }} installations</span>
                        <span class="admin-load-track"><i style="width: {{ $workload['load_percent'] }}%"></i></span>
                        <small class="is-{{ $workload['workload']['tone'] }}">{{ $workload['workload']['label'] }}</small>
                    </span>
                    <span class="admin-technician-tickets">
                        <strong>{{ $workload['active_tickets'] }} / {{ $workload['ticket_capacity'] }}</strong>
                        <small>Active tickets</small>
                    </span>
                    <span class="admin-technician-next">
                        @if($workload['next_installation'])
                            <strong>{{ $workload['next_installation']->installation_date?->format('M j') }} &middot; {{ \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($workload['next_installation']->installation_time)->format('H:i')) }}</strong>
                            <small>{{ $workload['areas']->implode(', ') ?: 'Location pending' }}</small>
                        @else
                            <strong>No upcoming installation</strong>
                            <small>{{ $workload['areas']->implode(', ') ?: 'No assigned area in this period' }}</small>
                        @endif
                    </span>
                    <span class="admin-row-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @empty
                <div class="admin-empty-state">
                    <strong>No technician accounts found</strong>
                    <p>Create a technician account before assigning installations or support tickets.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="panel-card admin-activity-panel" aria-labelledby="recentActivityTitle">
        <header class="admin-section-heading">
            <div>
                <span class="admin-workload-eyebrow">Recent activity</span>
                <h2 id="recentActivityTitle">Latest operational updates</h2>
                <p>The five newest events recorded by the system.</p>
            </div>
            <a class="btn secondary" href="{{ route('admin.system-logs.index') }}">View system logs</a>
        </header>
        <div class="admin-activity-list">
            @forelse($recentActivity as $log)
                <div class="admin-activity-row">
                    <span class="admin-activity-dot" aria-hidden="true"></span>
                    <span>
                        <strong>{{ $log->action }}</strong>
                        <small>{{ Str::limit($log->description, 140) }}</small>
                    </span>
                    <span class="admin-activity-meta">
                        <strong>{{ $log->displayUser() }}</strong>
                        <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->diffForHumans() }}</time>
                    </span>
                </div>
            @empty
                <div class="admin-empty-state">
                    <strong>No activity recorded yet</strong>
                    <p>Important application, installation, ticket, and account events will appear here.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection