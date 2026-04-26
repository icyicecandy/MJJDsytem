@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
<div class="card">
    <h1>{{ $ticket->subject }}</h1>
    <p><strong>Tracking Number:</strong> {{ $ticket->publicReference() }}</p>
    <p><strong>Customer:</strong> {{ $ticket->user->name }}</p>
    <p><strong>Submitted At:</strong> {{ $ticket->created_at->displayDateTime('F d, Y h:i A') }}</p>
    <p><strong>Urgency:</strong> <span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></p>
    <p><strong>Status:</strong> <span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></p>
    <p><strong>Assigned To:</strong> {{ optional($ticket->technician)->name ?? 'Unassigned Queue' }}</p>
    <p>{{ $ticket->description }}</p>

    @if($ticket->photoUrl())
        <div class="ticket-photo-block">
            <p><strong>Attached Photo:</strong></p>
            <img src="{{ $ticket->photoUrl() }}" alt="Ticket issue photo" class="ticket-photo-preview">
        </div>
    @endif
</div>

<div class="card">
    <h2>Workflow</h2>
    <p class="text-muted">Admin handles queue placement, assignment, and closure. The assigned technician is responsible for marking progress and resolution.</p>
</div>

<div class="card">
    <h2>Conversation</h2>
    @forelse($ticket->messages as $msg)
        <div class="message">
            <div><strong>{{ $msg->user->name }}</strong> <em>{{ $msg->created_at->displayDateTime('M d, Y h:i A') }}</em></div>
            <p>{{ $msg->message }}</p>
        </div>
    @empty
        <p>No messages yet.</p>
    @endforelse
</div>

<div class="card inline-form">
    <form method="post" action="{{ route('admin.tickets.update', $ticket) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="admin_action" value="save">
        <select name="technician_id" class="small-input technician-load-select">
            <option value="">Unassigned Queue</option>
            @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" {{ $ticket->technician_id==$tech->id?'selected':'' }}>
                    {{ $tech->name }} ({{ $tech->active_ticket_load }} active)
                </option>
            @endforeach
        </select>
        <button class="btn">{{ $ticket->technician_id ? 'Update Assignment' : 'Assign Technician' }}</button>
    </form>

    @if($ticket->status === \App\Models\Ticket::STATUS_RESOLVED)
        <form method="post" action="{{ route('admin.tickets.update', $ticket) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="admin_action" value="close">
            <input type="hidden" name="technician_id" value="{{ $ticket->technician_id }}">
            <button class="btn secondary">Close Ticket</button>
        </form>
    @endif
</div>

@if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
<div class="card">
    <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}">
        @csrf
        <label>Your message</label>
        <textarea name="message" rows="4" required></textarea>
        <button class="btn">Send</button>
    </form>
</div>
@endif

<div class="card">
    <a href="{{ route('admin.tickets.index') }}" class="btn secondary">Back</a>
</div>
@endsection
