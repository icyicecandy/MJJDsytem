@extends('layouts.app')

@section('title','Technician Dashboard')

@section('content')
<div class="telecom-page">
    <section class="telecom-hero">
        <h1>Welcome, {{ Auth::user()->name }}</h1>
        <p>Track assigned field work, monitor completion performance, and respond to open service tickets from one streamlined technician workspace.</p>
        <div class="telecom-hero-actions">
            <a href="{{ route('technician.installations.index') }}" class="btn">View Installations</a>
            <a href="{{ route('technician.tickets.index') }}" class="btn secondary">Open Tickets</a>
        </div>
    </section>

    <section class="metrics-grid">
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Assigned Installations</div><div class="metric-value">{{ $totalInstallations }}</div><div class="metric-meta">Total installation jobs assigned</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></div></div></article>
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Completed Jobs</div><div class="metric-value">{{ $completedInstallations }}</div><div class="metric-meta">Installations finished successfully</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"></path></svg></div></div></article>
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Scheduled Jobs</div><div class="metric-value">{{ $pendingInstallations }}</div><div class="metric-meta">Upcoming installation workload</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path></svg></div></div></article>
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Open Tickets</div><div class="metric-value">{{ $assignedTickets }}</div><div class="metric-meta">Assigned service concerns still open</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></div></div></article>
    </section>

    <section class="panel-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></span>
                    <div>
                        <div class="panel-title">Assigned Tickets</div>
                        <div class="panel-subtitle">Current issue queue for your technician account</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                @if($tickets->count())
                    <table class="telecom-table">
                        <thead><tr><th>Tracking Number</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                                <tr>
                                    <td><span class="record-strong">{{ $ticket->publicReference() }}</span></td>
                                    <td>{{ $ticket->subject }}</td>
                                    <td><span class="badge {{ $ticket->status }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></td>
                                    <td><a href="{{ route('technician.tickets.show', $ticket) }}" class="btn secondary">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty-state">No assigned tickets right now. New customer issues will appear here once assigned.</div>
                @endif
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg></span>
                    <div>
                        <div class="panel-title">Quick Actions</div>
                        <div class="panel-subtitle">Open the core technician modules</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="quick-link-grid">
                    <a href="{{ route('technician.installations.index') }}" class="quick-link"><strong>Installations</strong><span>Review assigned jobs and complete installation records.</span></a>
                    <a href="{{ route('technician.tickets.index') }}" class="quick-link"><strong>Support Tickets</strong><span>Respond to assigned service concerns.</span></a>
                    <a href="{{ route('role.chat.index', ['role' => 'technician']) }}" class="quick-link"><strong>Live Chat</strong><span>Coordinate with support staff and customers.</span></a>
                </div>
            </div>
        </div>
    </section>

    <div class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"></path><path d="M7 15l3-3 2 2 5-5"></path></svg></span>
                <div>
                    <div class="panel-title">Today's Workload</div>
                    <div class="panel-subtitle">Today's assigned installations and support workload overview</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            <div class="chart" data-chart="today-schedule"></div>
        </div>
    </div>
</div>
@endsection




