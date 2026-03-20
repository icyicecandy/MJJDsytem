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

    @if(session('success'))
        <section class="notice-card">
            {{ session('success') }}
        </section>
    @endif

    <section class="card toolbar-card">
        <div class="toolbar-actions">
            <a href="{{ route('customer.tickets.create') }}" class="btn">Create New Ticket</a>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table">
                <thead>
                    <tr><th>Subject</th><th>Status</th><th>Assigned Staff</th><th>Created</th></tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr>
                        <td><a href="{{ route('customer.tickets.show', $ticket) }}" class="record-strong">{{ $ticket->subject }}</a></td>
                        <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                        <td>{{ optional($ticket->technician)->name ?? 'Waiting for Assignment' }}</td>
                        <td>{{ $ticket->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4"><div class="empty-state compact">No tickets yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
