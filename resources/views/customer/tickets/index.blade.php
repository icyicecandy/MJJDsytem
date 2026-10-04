@extends('layouts.app')

@section('title','My Support Tickets')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>My Support Tickets</h1>
            <p>Track your reported issues, review queue or assignment updates, and open each support thread from one list.</p>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('customer.tickets.index', ['create_ticket' => 1]) }}" class="btn" data-open-customer-ticket-modal>Create New Ticket</a>
        </div>
    </section>

    <section class="card toolbar-card">
        <form method="GET" action="{{ route('customer.tickets.index') }}" class="toolbar-row customer-ticket-filter-toolbar" data-mobile-filter data-live-filter data-live-filter-target="#customerTicketResults">
            <div class="toolbar-field grow customer-ticket-search-field">
                <label for="customer_ticket_search">Search</label>
                <input
                    id="customer_ticket_search"
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Tracking number, subject, description, or assigned staff"
                >
            </div>

            <div class="toolbar-field customer-ticket-status-field">
                <label for="customer_ticket_status">Status</label>
                <select name="status" id="customer_ticket_status">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="toolbar-actions">
                <button type="submit" class="btn">Filter</button>
                @if($search !== '' || $selectedStatus !== '')
                    <a href="{{ route('customer.tickets.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <section id="customerTicketResults" class="card table-card" data-live-filter-results aria-busy="false">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable records table">
            <table class="telecom-table ticket-list-table ticket-list-table--customer" data-mobile-cards>
                    <colgroup>
                        <col class="ticket-col-reference">
                        <col class="ticket-col-subject">
                        <col class="ticket-col-urgency">
                        <col class="ticket-col-status">
                        <col class="ticket-col-person">
                        <col class="ticket-col-submitted">
                        <col class="ticket-col-action">
                    </colgroup>
                <thead>
                    <tr><th>Tracking Number</th><th>Subject</th><th>Urgency</th><th>Status</th><th>Assigned Staff</th><th>Submitted</th><th class="mobile-table-only">Response Target</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr>
                        <td><span class="record-strong record-reference">{{ $ticket->publicReference() }}</span></td>
                        <td>{{ $ticket->subject }}</td>
                        <td><span class="badge ticket-urgency-text urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></td>
                        <td class="customer-ticket-status-cell">
                            <span class="badge {{ $ticket->displayStatusClass() }}">
                                {{ $ticket->isQueued() ? 'Queued' : $ticket->displayStatusLabel() }}
                            </span>
                        </td>
                        <td>
                            @if($ticket->assignedStaff)
                                <span class="record-strong">{{ $ticket->assignedStaff->name }}</span>
                            @else
                                <span class="record-muted">Not yet assigned</span>
                            @endif
                        </td>
                        <td>{{ $ticket->created_at->displayDateTime('m/d/y h:i A') }}</td>
                        <td class="mobile-table-only">
                            {{ $ticket->responseTargetLabel() }}
                        </td>
                        <td><a href="{{ route('customer.tickets.show', $ticket) }}" class="btn secondary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8"><div class="empty-state compact">No tickets yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@include('customer.tickets.partials.create-ticket-modal')
@endsection
