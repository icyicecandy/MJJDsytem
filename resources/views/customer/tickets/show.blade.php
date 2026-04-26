@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
<div class="card">
    <h1>{{ $ticket->subject }}</h1>
    <p><strong>Tracking Number:</strong> {{ $ticket->publicReference() }}</p>
    <p><strong>Submitted At:</strong> {{ $ticket->created_at->displayDateTime('F d, Y h:i A') }}</p>
    <p><strong>Urgency:</strong> <span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></p>
    <p><strong>Status:</strong> <span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></p>
    <p><strong>Assigned Staff:</strong> {{ optional($ticket->technician)->name ?? 'Waiting for Assignment' }}</p>
    <p>{{ $ticket->description }}</p>

    @if($ticket->photoUrl())
        <div class="ticket-photo-block">
            <p><strong>Attached Photo:</strong></p>
            <img src="{{ $ticket->photoUrl() }}" alt="Ticket issue photo" class="ticket-photo-preview">
        </div>
    @endif
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

@if($ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
<div class="card">
    <form method="POST" action="{{ route('customer.tickets.reply', $ticket) }}">
        @csrf
        <label>Add a reply</label>
        <textarea name="message" rows="4" required></textarea>
        <button class="btn">Send</button>
    </form>
</div>
@endif

<div class="card">
    <a href="{{ route('customer.tickets.index') }}" class="btn secondary">Back</a>
</div>
@endsection
