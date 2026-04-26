<x-app-layout>
    <div class="tracking-shell">
        <div class="tracking-card tracking-form-card">
            <h3>Ticket Lookup</h3>
            <p class="record-muted">Enter your public ticket tracking number and the customer email used when the ticket was submitted.</p>

            <form method="POST" action="{{ route('track.ticket.search') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="record-strong">Tracking Number</label>
                    <input type="text" name="reference_no" placeholder="Example: TKT-2026-0001" value="{{ old('reference_no') }}" required>
                    @error('reference_no')
                        <p class="mt-1 text-muted" style="color:#b91c1c;">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="record-strong">Customer Email</label>
                    <input type="email" name="contact" value="{{ old('contact') }}" required>
                    @error('contact')
                        <p class="mt-1 text-muted" style="color:#b91c1c;">{{ $message }}</p>
                    @enderror
                </div>
                <button class="btn" type="submit">Track Status</button>
            </form>
        </div>
    </div>
</x-app-layout>
