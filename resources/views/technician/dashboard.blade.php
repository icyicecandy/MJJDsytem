@extends('layouts.app')

@section('title','Technician Dashboard')

@push('styles')
<style>
    .today-workload-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
    }
    .today-workload-date {
        display: grid;
        gap: 0.15rem;
        text-align: right;
    }
    .today-workload-date strong {
        color: var(--color-primary);
        font-size: 0.95rem;
    }
    .today-workload-date span {
        color: var(--color-text-muted);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .today-workload-list {
        display: grid;
        gap: 0.2rem;
    }
    .today-workload-row {
        display: grid;
        grid-template-columns: minmax(90px, 0.7fr) minmax(160px, 1.1fr) minmax(180px, 1.4fr) minmax(145px, auto);
        align-items: center;
        gap: 0.9rem;
        padding: 0.9rem 0;
        border-bottom: 1px solid var(--color-border);
    }
    .today-workload-row:last-child {
        border-bottom: 0;
    }
    .today-workload-time,
    .today-workload-main,
    .today-workload-address,
    .today-workload-state {
        min-width: 0;
    }
    .today-workload-time {
        display: grid;
        gap: 0.16rem;
    }
    .today-workload-time strong {
        color: var(--color-primary);
        font-size: 1rem;
    }
    .today-workload-time span,
    .today-workload-address span {
        color: var(--color-text-muted);
        font-size: 0.84rem;
    }
    .today-workload-address span,
    .today-workload-address .record-muted {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .today-workload-state {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.55rem;
        white-space: nowrap;
    }
    .today-workload-state .btn {
        padding: 0.52rem 0.72rem;
        font-size: 0.84rem;
    }
    @media (max-width: 900px) {
        .today-workload-summary {
            align-items: flex-start;
            flex-direction: column;
        }
        .today-workload-date {
            text-align: left;
        }
        .today-workload-row {
            grid-template-columns: 1fr;
            gap: 0.45rem;
        }
        .today-workload-state {
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .today-workload-address span,
        .today-workload-address .record-muted {
            white-space: normal;
        }
    }
</style>
@endpush

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

    @if(($overdueTickets + $overdueInstallations) > 0)
        <section class="ui-operations-alert" role="status" aria-label="Overdue assigned work alert">
            <div class="ui-operations-alert__copy">
                <strong>{{ number_format($overdueTickets + $overdueInstallations) }} assigned {{ Str::plural('task', $overdueTickets + $overdueInstallations) }} overdue</strong>
                <span>Open the affected installation or ticket to update its progress.</span>
            </div>
            <a href="{{ $overdueTickets > 0 ? route('technician.tickets.index', ['overdue' => 1]) : route('technician.installations.index') }}" class="btn">Review work</a>
        </section>
    @endif

    <section class="metrics-grid">
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Assigned Installations</div><div class="metric-value">{{ $totalInstallations }}</div><div class="metric-meta">Total installation jobs assigned</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></div></div></article>
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Completed Jobs</div><div class="metric-value">{{ $completedInstallations }}</div><div class="metric-meta">Installations finished successfully</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"></path></svg></div></div></article>
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Scheduled Jobs</div><div class="metric-value">{{ $pendingInstallations }}</div><div class="metric-meta">Upcoming installation workload</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path></svg></div></div></article>
        <article class="metric-card"><div class="metric-head"><div><div class="metric-label">Open Tickets</div><div class="metric-value">{{ $assignedTickets }}</div><div class="metric-meta">Assigned service concerns still open</div></div><div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></div></div></article>
    </section>

    <section class="panel-grid panel-grid--single">
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
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Tickets table">
                    <table class="telecom-table" data-mobile-cards>
                        <thead><tr><th>Tracking Number</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                                <tr>
                                    <td><span class="record-strong record-reference">{{ $ticket->publicReference() }}</span></td>
                                    <td>{{ $ticket->subject }}</td>
                                    <td><span class="badge {{ $ticket->status }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></td>
                                    <td><a href="{{ route('technician.tickets.show', $ticket) }}" class="btn secondary">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @else
                    <div class="empty-state">No assigned tickets right now. New customer issues will appear here once assigned.</div>
                @endif
            </div>
        </div>

    </section>

    <div class="panel-card">
        <div class="panel-header">
            <div class="today-workload-summary">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"></path><path d="M7 15l3-3 2 2 5-5"></path></svg></span>
                    <div>
                        <div class="panel-title">Today's Workload</div>
                        <div class="panel-subtitle">Assigned installations scheduled for the current app date</div>
                    </div>
                </div>
                <div class="today-workload-date">
                    <strong>{{ $todayWorkloadDate->format('F d, Y') }}</strong>
                    <span>{{ $todayInstallations->count() }} {{ \Illuminate\Support\Str::plural('installation', $todayInstallations->count()) }}</span>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($todayInstallations->count())
                <div class="today-workload-list">
                    @foreach($todayInstallations as $installation)
                        @php
                            $application = $installation->application;
                            $customerName = $application
                                ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? ''))
                                : 'N/A';
                            $address = $application
                                ? collect([
                                    $application->barangay,
                                    $application->municipality,
                                    $application->province,
                                ])->filter()->implode(', ')
                                : null;
                            $scheduleTime = $installation->installation_time
                                ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i'))
                                : null;
                            $contactNumber = $application?->mobile_number;
                            $statusLabel = ucfirst(str_replace('_', ' ', $installation->status));
                        @endphp
                        <div class="today-workload-row">
                            <div class="today-workload-time">
                                <strong>{{ $scheduleTime ?? 'TBD' }}</strong>
                                <span>{{ $installation->displayJobReference() }}</span>
                            </div>
                            <div class="today-workload-main">
                                <div class="record-strong">{{ $customerName ?: 'N/A' }}</div>
                                <div class="record-muted">{{ $application?->plan_code ?: 'No plan recorded' }}</div>
                                <div class="record-muted">Contact: {{ $contactNumber ?: 'No contact number recorded' }}</div>
                            </div>
                            <div class="today-workload-address">
                                <span>{{ $address ?: 'Address not available' }}</span>
                            </div>
                            <div class="today-workload-state">
                                <span class="badge {{ $installation->status ?? 'info' }}">{{ $statusLabel }}</span>
                                <a href="{{ route('technician.installations.show', $installation) }}" class="btn secondary">
                                    {{ $installation->isFinalized() ? 'Review' : 'Open Job' }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">No assigned installations are scheduled for {{ $todayWorkloadDate->format('F d, Y') }}.</div>
            @endif
        </div>
    </div>
</div>
@endsection
