@php
    $subjectOptions = \App\Models\Ticket::subjectOptions();
    $ticketModalActive = request()->query('create_ticket') === '1'
        || $errors->has('subject')
        || $errors->has('description')
        || $errors->has('ticket_photo')
        || $errors->has('ticket_photos');
@endphp

<div class="customer-ticket-modal{{ $ticketModalActive ? ' is-open' : '' }}" id="customerTicketModal" aria-hidden="{{ $ticketModalActive ? 'false' : 'true' }}">
    <div class="customer-ticket-modal__backdrop" data-customer-ticket-close></div>
    <div class="customer-ticket-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="customerTicketModalTitle">
        <button type="button" class="customer-ticket-modal__close" aria-label="Close ticket form" data-customer-ticket-close>&times;</button>
        <div class="customer-ticket-modal__shell">
            <div class="customer-ticket-modal__intro">
                <span class="application-status-kicker">Support Ticket</span>
                <h2 id="customerTicketModalTitle">Create New Ticket</h2>
                <p>Describe the issue, choose the closest subject, and attach a photo if it helps show the problem more clearly.</p>
            </div>

            <form method="POST" action="{{ route('customer.tickets.store') }}" enctype="multipart/form-data" class="customer-ticket-modal__form">
                @csrf

                <div class="customer-ticket-modal__field">
                    <label for="customer_ticket_subject">Subject</label>
                    <select id="customer_ticket_subject" name="subject" required data-ticket-subject>
                        <option value="">Select Ticket Subject</option>
                        @foreach($subjectOptions as $key => $option)
                            <option
                                value="{{ $key }}"
                                data-urgency="{{ $option['urgency'] }}"
                                data-requires-triage="{{ ($option['requires_admin_triage'] ?? false) ? '1' : '0' }}"
                                {{ old('subject') === $key ? 'selected' : '' }}
                            >
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('subject')
                        <p class="customer-ticket-modal__error">{{ $message }}</p>
                    @enderror
                </div>

                <p class="customer-ticket-modal__hint" id="customerTicketUrgencyHint">
                    Urgency will be identified automatically after you choose the ticket subject.
                </p>

                <div class="customer-ticket-modal__field">
                    <label for="customer_ticket_description">Description</label>
                    <textarea id="customer_ticket_description" name="description" rows="5" required maxlength="4000" placeholder="Describe the issue, what happened, and any details that can help support review it faster.">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="customer-ticket-modal__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="customer-ticket-modal__field">
                    <label for="customer_ticket_photo">Photos <span class="field-badge optional">Optional</span></label>
                    <input id="customer_ticket_photo" type="file" name="ticket_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                    <p class="customer-ticket-modal__helper">Attach one or more images if they help show the issue more clearly.</p>
                    @error('ticket_photo')
                        <p class="customer-ticket-modal__error">{{ $message }}</p>
                    @enderror
                    @error('ticket_photos')
                        <p class="customer-ticket-modal__error">{{ $message }}</p>
                    @enderror
                    @error('ticket_photos.*')
                        <p class="customer-ticket-modal__error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="customer-ticket-modal__actions">
                    <button type="submit" class="btn">Submit Ticket</button>
                    <button type="button" class="btn secondary" data-customer-ticket-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('customerTicketModal');

            if (!modal) {
                return;
            }

            const body = document.body;
            const subjectSelect = modal.querySelector('[data-ticket-subject]');
            const urgencyHint = document.getElementById('customerTicketUrgencyHint');

            const renderUrgencyHint = function () {
                if (!subjectSelect || !urgencyHint) {
                    return;
                }

                const selected = subjectSelect.options[subjectSelect.selectedIndex];
                const urgency = selected?.dataset?.urgency;
                const requiresTriage = selected?.dataset?.requiresTriage === '1';

                if (requiresTriage) {
                    urgencyHint.textContent = 'An administrator will review this concern and set its urgency before assignment.';
                    return;
                }

                if (!urgency) {
                    urgencyHint.textContent = 'Urgency will be identified automatically after you choose the ticket subject.';
                    return;
                }

                urgencyHint.textContent = 'Detected urgency: ' + urgency.replace('_', ' ').replace(/\b\w/g, function (letter) {
                    return letter.toUpperCase();
                });
            };

            const openModal = function () {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                body.classList.add('ticket-overlay-open');
                renderUrgencyHint();

                const firstField = modal.querySelector('select, textarea, input:not([type="hidden"])');
                if (firstField) {
                    window.setTimeout(function () { firstField.focus(); }, 30);
                }
            };

            const closeModal = function () {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                body.classList.remove('ticket-overlay-open');
            };

            document.querySelectorAll('[data-open-customer-ticket-modal]').forEach(function (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    openModal();
                });
            });

            modal.querySelectorAll('[data-customer-ticket-close]').forEach(function (trigger) {
                trigger.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            if (subjectSelect) {
                subjectSelect.addEventListener('change', renderUrgencyHint);
                renderUrgencyHint();
            }

            if (modal.classList.contains('is-open')) {
                body.classList.add('ticket-overlay-open');
            }
        });
    </script>
@endonce
