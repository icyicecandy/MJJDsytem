@extends('layouts.app')

@section('title','Customer Dashboard')

@section('content')
<div class="telecom-page">
    <section class="telecom-hero">
        <h1>Welcome, {{ Auth::user()->name }}</h1>
        <p>Track your application progress, monitor your installation schedule, and stay updated on support activity from one clean service portal.</p>
        <div class="telecom-hero-actions">
            <a href="{{ route('customer.application.show') }}" class="btn">View Application</a>
            <a href="{{ route('customer.tickets.index', ['create_ticket' => 1]) }}" class="btn secondary" data-open-customer-ticket-modal>Report an Issue</a>
        </div>
    </section>

    <section class="metrics-grid">
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Application Status</div>
                    <div class="metric-value">{{ $latestApplication ? ucfirst(str_replace('_', ' ', $latestApplication->status)) : 'No application' }}</div>
                    <div class="metric-meta">Current service request stage</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Installation Schedule</div>
                    <div class="metric-value">@if($latestApplication && $latestApplication->installation) {{ \Carbon\Carbon::parse($latestApplication->installation->installation_date)->format('M d') }} @else - @endif</div>
                    <div class="metric-meta">@if($latestApplication && $latestApplication->installation) {{ $latestApplication->installation->installation_time ?? 'Time to be confirmed' }} @else No schedule yet @endif</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Live Application</div>
                    <div class="metric-value">{{ $activeApplication ? 'Active' : 'None' }}</div>
                    <div class="metric-meta">{{ $activeApplication ? 'You currently have one service request in progress.' : 'You can submit a new request when no active application is open.' }}</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2 3 7v6c0 5 3.8 8.7 9 9 5.2-.3 9-4 9-9V7l-9-5Z"></path><path d="m9 12 2 2 4-4"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Open Tickets</div>
                    <div class="metric-value">{{ $openTickets }}</div>
                    <div class="metric-meta">Support concerns awaiting closure</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></div>
            </div>
        </article>
    </section>

    <section class="panel-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></span>
                    <div>
                        <div class="panel-title">Recent Support Tickets</div>
                        <div class="panel-subtitle">Recent concerns and current service support activity</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                @if($tickets->count())
                    <table class="telecom-table">
                        <thead>
                            <tr><th>Tracking Number</th><th>Subject</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                                <tr>
                                    <td><span class="record-strong">{{ $ticket->publicReference() }}</span></td>
                                    <td>{{ $ticket->subject }}</td>
                                    <td><span class="badge {{ $ticket->status }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></td>
                                    <td><a href="{{ route('customer.tickets.show', $ticket) }}" class="btn secondary">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty-state">No support tickets yet. Use the quick issue report button whenever you need assistance.</div>
                @endif
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg></span>
                    <div>
                        <div class="panel-title">Quick Actions</div>
                        <div class="panel-subtitle">Navigate the core customer modules</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="quick-link-grid">
                    <a href="{{ route('customer.apply') }}" class="quick-link"><strong>Apply for Service</strong><span>Submit a new request if your previous application was rejected.</span></a>
                    <a href="{{ route('customer.tickets.index') }}" class="quick-link"><strong>Tickets</strong><span>Open a support conversation and view updates.</span></a>
                    <a href="{{ route('chat.index') }}" class="quick-link"><strong>Live Chat</strong><span>Connect with support staff or the AI assistant when staff are unavailable.</span></a>
                </div>
            </div>
        </div>
    </section>
</div>
@include('customer.tickets.partials.create-ticket-modal')
@endsection

