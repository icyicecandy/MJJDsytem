<x-app-layout>
    

    <div class="tracking-shell">
        <div class="tracking-card tracking-form-card">
            <h3>Application Lookup</h3>
            <p class="record-muted">Enter your application reference number and the contact information used when you applied.</p>

            <form method="POST" action="{{ route('track.search') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="record-strong">Application Reference Number</label>
                    <input type="text" name="reference_no" placeholder="APP-2026-4490" value="{{ old('reference_no') }}" required>
                    @error('reference_no')<p class="mt-1 text-muted" style="color:#b91c1c;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="record-strong">Email or Mobile Number</label>
                    <input type="text" name="contact" value="{{ old('contact') }}" required>
                    @error('contact')<p class="mt-1 text-muted" style="color:#b91c1c;">{{ $message }}</p>@enderror
                </div>
                <button class="btn" type="submit">Track Status</button>
            </form>
        </div>
    </div>
</x-app-layout>
