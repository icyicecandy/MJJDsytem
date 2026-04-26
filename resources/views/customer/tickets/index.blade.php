@extends('layouts.app')

@section('title','My Support Tickets')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>My Support Tickets</h1>
            <p>Track your reported issues, review queue or assignment updates, and open each support thread from one list.</p>
        </div>
    </section>

    <section class="card toolbar-card">
        <div class="toolbar-actions">
            <a href="{{ route('customer.tickets.index', ['create_ticket' => 1]) }}" class="btn" data-open-customer-ticket-modal>Create New Ticket</a>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table">
                <thead>
                    <tr><th>Tracking Number</th><th>Subject</th><th>Urgency</th><th>Status</th><th>Assigned Staff</th><th>Submitted</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr>
                        <td><span class="record-strong">{{ $ticket->publicReference() }}</span></td>
                        <td>{{ $ticket->subject }}</td>
                        <td><span class="badge ticket-urgency-text urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></td>
                        <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                        <td>{{ optional($ticket->technician)->name ?? 'Waiting for Assignment' }}</td>
                        <td>{{ $ticket->created_at->displayDateTime('m/d/y h:i A') }}</td>
                        <td><a href="{{ route('customer.tickets.show', $ticket) }}" class="btn secondary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state compact">No tickets yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@include('customer.tickets.partials.create-ticket-modal')
@endsection
