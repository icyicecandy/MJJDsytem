<x-app-layout>
    <x-slot name="header">
        <h2>Ticket Tracking Result</h2>
        <p>Tracking Number: {{ $ticket->publicReference() }}</p>
    </x-slot>

    <div class="tracking-shell">
        <div class="tracking-card">
            <h3>Current Status</h3>
            <p><span class="badge {{ $ticket->status }}">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</span></p>
            <div class="tracking-grid mt-1">
                <div class="tracking-meta"><strong>Tracking Number</strong>{{ $ticket->publicReference() }}</div>
                <div class="tracking-meta"><strong>Subject</strong>{{ $ticket->subject }}</div>
                <div class="tracking-meta"><strong>Created</strong>{{ $ticket->created_at->displayDateTime() }}</div>
                <div class="tracking-meta"><strong>Latest Update</strong>{{ $ticket->updated_at->displayDateTime() }}</div>
            </div>
        </div>

        <div class="tracking-card">
            <h3>Tracking Notes</h3>
            <div class="empty-state">Internal staff assignment and ticket conversation details are not shown on public tracking.</div>
        </div>

        <a href="{{ route('track.ticket.form') }}" class="btn secondary">Track Another Ticket</a>
    </div>
</x-app-layout>
