@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
<div class="card">
    <h1>{{ $ticket->subject }}</h1>
    <p><strong>Tracking Number:</strong> {{ $ticket->publicReference() }}</p>
    <p><strong>Customer:</strong> {{ $ticket->user->name }}</p>
    <p><strong>Status:</strong> <span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></p>
    <p><strong>Assigned To:</strong> {{ optional($ticket->technician)->name ?? 'Unassigned Queue' }}</p>
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

<div class="card inline-form">
    <form method="post" action="{{ route('admin.tickets.update', $ticket) }}">
        @csrf @method('PATCH')
        <select name="status" class="small-input">
            <option value="queued" {{ $ticket->status=='queued'?'selected':'' }}>Queued</option>
            <option value="open" {{ $ticket->status=='open'?'selected':'' }}>Open</option>
            <option value="in_progress" {{ $ticket->status=='in_progress'?'selected':'' }}>In Progress</option>
            <option value="resolved" {{ $ticket->status=='resolved'?'selected':'' }}>Resolved</option>
            <option value="closed" {{ $ticket->status=='closed'?'selected':'' }}>Closed</option>
        </select>
        <select name="technician_id" class="small-input">
            <option value="">Unassigned Queue</option>
            @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" {{ $ticket->technician_id==$tech->id?'selected':'' }}>{{ $tech->name }}</option>
            @endforeach
        </select>
        <button class="btn">Update</button>
    </form>
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
@endsection
