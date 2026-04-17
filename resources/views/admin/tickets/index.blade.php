@extends('layouts.app')

@section('title','Support Tickets')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Support Tickets</h1>
            <p>Review the FIFO support queue, assign technicians, and keep ticket resolution work aligned from one admin queue.</p>
        </div>
    </section>

    @if(session('success'))
        <section class="notice-card">
            {{ session('success') }}
        </section>
    @endif

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table admin-ticket-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Technician</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr>
                        <td><span class="record-strong">{{ $ticket->publicReference() }}</span></td>
                        <td><span class="record-strong">{{ $ticket->user->name }}</span></td>
                        <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="record-strong">{{ $ticket->subject }}</a></td>
                        <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                        <td>{{ optional($ticket->technician)->name ?? 'Unassigned Queue' }}</td>
                        <td class="actions-cell">
                            <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="admin-ticket-actions">
                                @csrf
                                @method('PATCH')
                                <select name="status">
                                    <option value="queued" {{ $ticket->status=='queued'?'selected':'' }}>Queued</option>
                                    <option value="open" {{ $ticket->status=='open'?'selected':'' }}>Open</option>
                                    <option value="in_progress" {{ $ticket->status=='in_progress'?'selected':'' }}>In Progress</option>
                                    <option value="resolved" {{ $ticket->status=='resolved'?'selected':'' }}>Resolved</option>
                                    <option value="closed" {{ $ticket->status=='closed'?'selected':'' }}>Closed</option>
                                </select>

                                <select name="technician_id">
                                    <option value="">Unassigned Queue</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->id }}" {{ $ticket->technician_id==$tech->id?'selected':'' }}>{{ $tech->name }}</option>
                                    @endforeach
                                </select>

                                <button class="btn secondary">Update</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6"><div class="empty-state compact">No support tickets available.</div></td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
