<x-app-layout>
    <x-slot name="header">
        <h2>New Support Ticket</h2>
    </x-slot>

    <div class="p-6">
        <div class="card">
            <h3 class="title">Submit a Ticket</h3>
            <form method="POST" action="{{ route('customer.tickets.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="ticket-subject">Subject</label>
                <select id="ticket-subject" name="subject" required data-ticket-subject>
                    <option value="">Select Ticket Subject</option>
                    @foreach($subjectOptions as $key => $option)
                        <option
                            value="{{ $key }}"
                            data-urgency="{{ $option['urgency'] }}"
                            {{ old('subject') === $key ? 'selected' : '' }}
                        >
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>

                <p class="text-muted mt-1" id="ticket-urgency-hint">
                    Urgency will be identified automatically after you choose the ticket subject.
                </p>

                <label>Description</label>
                <textarea name="description" rows="5" required>{{ old('description') }}</textarea>

                <label for="ticket_photo">Photos <span class="field-badge optional">Optional</span></label>
                <input id="ticket_photo" type="file" name="ticket_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                <p class="text-muted mt-1">Attach one or more images if they help show the issue more clearly.</p>

                <button type="submit" class="btn">Create Ticket</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const subjectSelect = document.querySelector('[data-ticket-subject]');
            const urgencyHint = document.getElementById('ticket-urgency-hint');

            if (!subjectSelect || !urgencyHint) {
                return;
            }

            const renderUrgencyHint = () => {
                const selected = subjectSelect.options[subjectSelect.selectedIndex];
                const urgency = selected?.dataset?.urgency;

                if (!urgency) {
                    urgencyHint.textContent = 'Urgency will be identified automatically after you choose the ticket subject.';
                    return;
                }

                urgencyHint.textContent = 'Detected urgency: ' + urgency.replace('_', ' ').replace(/\b\w/g, function (letter) {
                    return letter.toUpperCase();
                });
            };

            subjectSelect.addEventListener('change', renderUrgencyHint);
            renderUrgencyHint();
        });
    </script>
</x-app-layout>
