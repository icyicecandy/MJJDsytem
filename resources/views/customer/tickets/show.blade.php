@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
<div class="card">
    <h1>{{ $ticket->subject }}</h1>
    <p><strong>Tracking Number:</strong> {{ $ticket->publicReference() }}</p>
    <p><strong>Status:</strong> <span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></p>
    <p><strong>Assigned Staff:</strong> {{ optional($ticket->technician)->name ?? 'Waiting for Assignment' }}</p>
    <p><strong>Created:</strong> {{ $ticket->created_at->format('M d, Y') }}</p>
    <p>{{ $ticket->description }}</p>
</div>

<div class="card">
    <h2>Conversation</h2>
    @forelse($ticket->messages as $msg)
        <div class="message">
            <div><strong>{{ $msg->user->name }}</strong> <em>{{ $msg->created_at->format('M d, Y H:i') }}</em></div>
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
@endsection
