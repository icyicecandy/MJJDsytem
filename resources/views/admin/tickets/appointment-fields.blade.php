<div class="ticket-appointment-fields" data-ticket-appointment-fields>
    <input type="hidden" name="appointment_ticket_id" value="{{ $appointmentTicket?->id }}">
    <label for="{{ $appointmentPrefix }}Date">Appointment date</label>
    <input id="{{ $appointmentPrefix }}Date" name="appointment_date" type="date" min="{{ now()->toDateString() }}"
        value="{{ old('appointment_date', $appointmentTicket?->appointment_date?->format('Y-m-d')) }}">
    @error('appointment_date') <span class="error">{{ $message }}</span> @enderror
    <label for="{{ $appointmentPrefix }}Time">Appointment time</label>
    <select id="{{ $appointmentPrefix }}Time" name="appointment_time">
        <option value="">Select a one-hour slot</option>
        @foreach(\App\Models\Ticket::AVAILABLE_TIME_SLOTS as $slot)
            <option value="{{ $slot }}" @selected(old('appointment_time', $appointmentTicket?->scheduledStartAt()?->format('H:i')) === $slot)>
                {{ \Carbon\Carbon::parse($slot)->format('g:i A') }}
            </option>
        @endforeach
    </select>
    @error('appointment_time') <span class="error">{{ $message }}</span> @enderror
    <label for="{{ $appointmentPrefix }}Reason">Reason for appointment change</label>
    <textarea id="{{ $appointmentPrefix }}Reason" name="appointment_change_reason" rows="3" maxlength="1000">{{ old('appointment_change_reason') }}</textarea>
    @error('appointment_change_reason') <span class="error">{{ $message }}</span> @enderror
    <p class="ticket-sidebar-note">Required when changing or removing an existing appointment. Sent to the customer; keep internal notes out.</p>
    <p class="ticket-sidebar-note">Each assignment reserves one hour. Unavailable slots include installations and other tickets. Times use {{ config('app.timezone') }}.</p>
    <p class="ticket-sidebar-note" data-appointment-hint aria-live="polite"></p>
</div>
@once
    @push('styles')
        <style>.ticket-appointment-fields{display:grid;gap:.55rem;min-width:0}.ticket-appointment-fields input,.ticket-appointment-fields select,.ticket-appointment-fields textarea{width:100%;min-width:0;max-width:100%;box-sizing:border-box}.ticket-appointment-fields p{overflow-wrap:anywhere}</style>
    @endpush
    @push('scripts')
        <script type="application/json" id="ticketScheduleConfig">{!! json_encode(['bookings' => $scheduleBookings, 'today' => now()->toDateString(), 'time' => now()->format('H:i')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        <script src="{{ asset('js/ticket-appointments.js') }}?v={{ filemtime(public_path('js/ticket-appointments.js')) }}" defer></script>
    @endpush
@endonce
