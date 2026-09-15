@extends('layouts.app')

@section('title','Admin Dashboard')

@push('styles')
<style>
    .operations-overview__heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
    .operations-overview__heading h2 { margin: 0; font-size: 1rem; }
    .operations-overview__period, .operations-overview__note { margin: 0.5rem 0 1rem; color: var(--color-text-muted); font-size: 0.85rem; }
    .operations-overview__total { margin: 0 0 1rem; color: var(--color-primary); }
    .operations-overview__total strong { font-size: 1.6rem; font-variant-numeric: tabular-nums; }
    .operations-overview__chart { list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; }
    .operations-overview__row { display: grid; grid-template-columns: 5rem minmax(0, 1fr) minmax(2rem, max-content); align-items: center; gap: 0.65rem; font-size: 0.85rem; }
    .operations-overview__track { height: 0.85rem; background: #e8eef8; border-radius: 0.4rem; overflow: hidden; }
    .operations-overview__bar { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2563eb, #0891b2); }
    .operations-overview__count { text-align: right; font-variant-numeric: tabular-nums; }
    .operations-overview__note { margin: 1rem 0 0; }
    .operations-overview__empty { padding: 0.75rem; border-radius: 0.5rem; background: #f1f5f9; color: var(--color-text-muted); }
</style>
@endpush

@section('content')
@php
    $icon = fn($svg) => $svg;
@endphp

<div class="telecom-page">
    <section class="telecom-hero">
        <h1>Welcome, {{ Auth::user()->name }}</h1>
        <p>Monitor applications, installation activity, and service tickets from one telecom operations dashboard.</p>
        <div class="telecom-chip-row">
            <span class="telecom-chip">Applications Pipeline</span>
            <span class="telecom-chip">Installation Monitoring</span>
            <span class="telecom-chip">Support Operations</span>
        </div>
    </section>

    <section class="metrics-grid">
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Total Applications</div>
                    <div class="metric-value">{{ $totalApplications }}</div>
                    <div class="metric-meta">All submitted service requests</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h8"></path><path d="M8 9h2"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Pending Review</div>
                    <div class="metric-value">{{ $pendingApplications }}</div>
                    <div class="metric-meta">Submitted or under review</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Scheduled Installations</div>
                    <div class="metric-value">{{ $scheduledInstallations }}</div>
                    <div class="metric-meta">Applications awaiting installation</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Installed Applications</div>
                    <div class="metric-value">{{ $installedApplications }}</div>
                    <div class="metric-meta">Completed installations</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2 3 7v6c0 5 3.8 8.7 9 9 5.2-.3 9-4 9-9V7l-9-5Z"></path><path d="m9 12 2 2 4-4"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Open Tickets</div>
                    <div class="metric-value">{{ $openTickets }}</div>
                    <div class="metric-meta">Assigned and unassigned; not yet started</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></div>
            </div>
        </article>
    </section>

    <section class="panel-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"></path><path d="M7 15l3-3 2 2 5-5"></path></svg></span>
                    <div>
                        <div class="panel-title">Operations Overview</div>
                        <div class="panel-subtitle">Monthly application submissions</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <section class="operations-overview" aria-labelledby="operationsChartTitle">
                    <div class="operations-overview__heading">
                        <h2 id="operationsChartTitle">Applications over the last 6 months</h2>
                        <a href="{{ route('admin.dashboard') }}" class="btn secondary" data-overview-refresh>Refresh</a>
                    </div>
                    <p class="operations-overview__period">{{ $monthlyApplications->first()['label'] }} &ndash; {{ $monthlyApplications->last()['label'] }}</p>
                    <p class="operations-overview__total"><strong>{{ number_format($overviewTotal) }}</strong> applications in this period</p>
                    @if($overviewTotal === 0)
                        <p class="operations-overview__empty">No applications submitted in this period.</p>
                    @endif
                    <ol class="operations-overview__chart" aria-label="Monthly application counts">
                        @foreach($monthlyApplications as $month)
                            <li class="operations-overview__row" data-month="{{ $month['month'] }}" data-count="{{ $month['count'] }}">
                                <span>{{ $month['label'] }}</span>
                                <span class="operations-overview__track" aria-hidden="true">
                                    <span class="operations-overview__bar" style="width: {{ round($month['count'] / $overviewMaximum * 100, 2) }}%"></span>
                                </span>
                                <strong class="operations-overview__count">{{ number_format($month['count']) }}</strong>
                            </li>
                        @endforeach
                    </ol>
                    <p class="operations-overview__note">All application statuses included. Current month is partial.</p>
                    <p class="operations-overview__note">Updated {{ $overviewUpdatedAt->format('M d, Y h:i A') }} ({{ config('app.timezone') }}). Select Refresh for the latest totals.</p>
                </section>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg></span>
                    <div>
                        <div class="panel-title">Quick Actions</div>
                        <div class="panel-subtitle">Open the main admin modules</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="quick-link-grid">
                    <a href="{{ route('admin.applications.index') }}" class="quick-link"><strong>Applications</strong><span>Review, approve, and schedule requests.</span></a>
                    <a href="{{ route('admin.installations.index') }}" class="quick-link"><strong>Installations</strong><span>Track field schedules and completions.</span></a>
                    <a href="{{ route('admin.customers.index') }}" class="quick-link"><strong>Customers</strong><span>Review customer details, service status, and support history.</span></a>
                    <a href="{{ route('admin.technicians.index') }}" class="quick-link"><strong>Technicians</strong><span>Review technician details, workload, and upcoming schedules.</span></a>
                    <a href="{{ route('admin.tickets.index') }}" class="quick-link"><strong>Tickets</strong><span>Manage customer issues and assignments.</span></a>
                    <a href="{{ route('admin.system-logs.index') }}" class="quick-link"><strong>System Logs</strong><span>View the latest recorded system activity.</span></a>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection


