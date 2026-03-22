@extends('layouts.app')

@section('title','Admin Dashboard')

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
                    <div class="metric-meta">Upcoming field jobs</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Active Subscriptions</div>
                    <div class="metric-value">{{ $activeSubscriptions ?? 0 }}</div>
                    <div class="metric-meta">Customers currently in service</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Open Tickets</div>
                    <div class="metric-value">{{ $openTickets }}</div>
                    <div class="metric-meta">Issues awaiting action</div>
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
                        <div class="panel-subtitle">Core service metrics for daily monitoring</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="chart" data-chart="monthly-applications"></div>
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
                    <a href="{{ route('admin.subscriptions.index') }}" class="quick-link"><strong>Subscriptions</strong><span>Check active and expiring service plans.</span></a>
                    <a href="{{ route('admin.tickets.index') }}" class="quick-link"><strong>Tickets</strong><span>Manage customer issues and assignments.</span></a>
                </div>
            </div>
        </div>
    </section>

    <div class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"></path><path d="M7 15l3-3 2 2 5-5"></path></svg></span>
                <div>
                    <div class="panel-title">Ticket Status Analytics</div>
                    <div class="panel-subtitle">Visual placeholder for service support status distribution</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            <div class="chart" data-chart="ticket-status"></div>
        </div>
    </div>
</div>
@endsection



