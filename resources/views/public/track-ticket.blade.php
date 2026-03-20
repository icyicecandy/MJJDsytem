<x-app-layout>
    <x-slot name="header">
        <h2>Track Ticket</h2>
        <p>Check ticket progress using the current ticket ID and the customer email used on the account.</p>
    </x-slot>

    <div class="tracking-shell">
        <div class="tracking-card tracking-form-card">
            <h3>Ticket Lookup</h3>
            <p class="record-muted">This project currently uses the ticket ID as the public ticket reference.</p>

            <form method="POST" action="{{ route('track.ticket.search') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="record-strong">Ticket ID</label>
                    <input type="text" name="reference_no" placeholder="Example: 12" value="{{ old('reference_no') }}" required>
                    @error('reference_no')<p class="mt-1 text-muted" style="color:#b91c1c;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="record-strong">Customer Email</label>
                    <input type="email" name="contact" value="{{ old('contact') }}" required>
                    @error('contact')<p class="mt-1 text-muted" style="color:#b91c1c;">{{ $message }}</p>@enderror
                </div>
                <button class="btn" type="submit">Track Status</button>
            </form>
        </div>
    </div>
</x-app-layout>
