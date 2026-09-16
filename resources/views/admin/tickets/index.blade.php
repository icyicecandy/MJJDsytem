@extends('layouts.app')

@section('title','Support Tickets')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Support Tickets</h1>
            <p>Review the queue, assign or reassign technicians, and close tickets after the assigned technician resolves them.</p>
        </div>
    </section>

    <section class="card ticket-queue-overview">
        <div class="ticket-queue-overview__head">
            <form method="GET" action="{{ route('admin.tickets.index') }}" class="toolbar-row">
                @if($selectedStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $selectedStatus }}">
                @endif
                <div class="toolbar-field grow">
                    <label for="admin_ticket_search">Search Tickets</label>
                    <input
                        id="admin_ticket_search"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Tracking number, customer name, or subject"
                    >
                </div>
                <div class="toolbar-actions">
                    <button type="submit" class="btn">Search</button>
                    @if($search !== '' || $selectedStatus !== 'all')
                        <a href="{{ route('admin.tickets.index') }}" class="btn secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="ticket-queue-overview__grid">
            <a href="{{ route('admin.tickets.index', array_filter(['status' => 'unassigned', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'unassigned' ? 'is-active' : '' }}">
                <span class="ticket-queue-metric__label">Unassigned</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['unassigned'] }}</strong>
                <span class="ticket-queue-metric__meta">Waiting for admin review or technician assignment</span>
            </a>
            <a href="{{ route('admin.tickets.index', array_filter(['status' => 'open', 'search' => $search])) }}" class="ticket-queue-metric {{ in_array($selectedStatus, ['open', 'queued'], true) ? 'is-active' : '' }}">
                <span class="ticket-queue-metric__label">Open</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['open'] }}</strong>
                <span class="ticket-queue-metric__meta">Assigned tickets waiting for technician work to begin</span>
            </a>
            <a href="{{ route('admin.tickets.index', array_filter(['status' => 'in_progress', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'in_progress' ? 'is-active' : '' }}">
                <span class="ticket-queue-metric__label">In Progress</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['in_progress'] }}</strong>
                <span class="ticket-queue-metric__meta">Currently being worked by assigned technicians</span>
            </a>
            <a href="{{ route('admin.tickets.index', array_filter(['status' => 'resolved', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'resolved' ? 'is-active' : '' }}">
                <span class="ticket-queue-metric__label">Resolved</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['resolved'] }}</strong>
                <span class="ticket-queue-metric__meta">Completed and waiting for final closure if needed</span>
            </a>
            <a href="{{ route('admin.tickets.index', array_filter(['status' => 'closed', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'closed' ? 'is-active' : '' }}">
                <span class="ticket-queue-metric__label">Closed</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['closed'] }}</strong>
                <span class="ticket-queue-metric__meta">Finished tickets kept for record history</span>
            </a>
            <a href="{{ route('admin.tickets.index', array_filter(['status' => 'cancelled', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'cancelled' ? 'is-active' : '' }}">
                <span class="ticket-queue-metric__label">Cancelled</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['cancelled'] }}</strong>
                <span class="ticket-queue-metric__meta">Cancelled tickets kept for history</span>
            </a>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable records table">
            <table class="telecom-table ticket-list-table ticket-list-table--admin">
                    <colgroup>
                        <col class="ticket-col-reference">
                        <col class="ticket-col-person">
                        <col class="ticket-col-subject">
                        <col class="ticket-col-urgency">
                        <col class="ticket-col-status">
                        <col class="ticket-col-person">
                        <col class="ticket-col-submitted">
                        <col class="ticket-col-action">
                    </colgroup>
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Urgency</th>
                        <th>Status</th>
                        <th>Technician</th>
                        <th>Submitted</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr>
                        <td><span class="record-strong record-reference">{{ $ticket->publicReference() }}</span></td>
                        <td><span class="record-strong">{{ $ticket->user->name }}</span></td>
                        <td>{{ $ticket->subject }}</td>
                        <td><span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></td>
                        <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                        <td>
                            @if($ticket->technician)
                                <span class="record-strong">{{ $ticket->technician->name }}</span>
                            @else
                                <span class="ticket-queue-pill">Unassigned Queue</span>
                            @endif
                        </td>
                        <td>{{ $ticket->created_at->displayDateTime() }}</td>
                        <td class="actions-cell">
                            <button
                                type="button"
                                class="btn secondary"
                                data-ticket-overlay-open
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-reference="{{ $ticket->publicReference() }}"
                                data-ticket-customer="{{ $ticket->user->name }}"
                                data-ticket-subject="{{ e($ticket->subject) }}"
                                data-ticket-status="{{ $ticket->displayStatusLabel() }}"
                                data-ticket-status-value="{{ $ticket->status }}"
                                data-ticket-urgency="{{ $ticket->displayUrgencyLabel() }}"
                                data-ticket-appointment-date="{{ $ticket->appointment_date?->format('Y-m-d') }}"
                                data-ticket-appointment-time="{{ $ticket->scheduledStartAt()?->format('H:i') }}"
                                data-ticket-technician-id="{{ $ticket->technician_id ?? '' }}"
                                data-ticket-technician-name="{{ optional($ticket->technician)->name ?? 'Unassigned Queue' }}"
                                data-ticket-update-url="{{ route('admin.tickets.update', $ticket) }}"
                                data-ticket-show-url="{{ route('admin.tickets.show', $ticket) }}"
                            >
                                View
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8"><div class="empty-state compact">No support tickets available.</div></td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="admin-ticket-modal" data-ticket-overlay aria-hidden="true">
    <div class="admin-ticket-modal__backdrop" data-ticket-overlay-close></div>
    <div class="admin-ticket-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="adminTicketOverlayTitle">
        <button type="button" class="admin-ticket-modal__close" data-ticket-overlay-close aria-label="Close assignment overlay">&times;</button>
        <div class="admin-ticket-modal__shell">
            <div class="admin-ticket-modal__intro">
                <h2 id="adminTicketOverlayTitle">Ticket Assignment</h2>
                <p>Assign or reassign the ticket from this overlay. Technician work-progress updates remain in the assigned technician workspace.</p>
            </div>

            <div class="admin-ticket-modal__summary">
                <div class="tracking-meta"><strong>Tracking Number</strong><span data-overlay-reference>-</span></div>
                <div class="tracking-meta"><strong>Customer</strong><span data-overlay-customer>-</span></div>
                <div class="tracking-meta"><strong>Subject</strong><span data-overlay-subject>-</span></div>
                <div class="tracking-meta"><strong>Urgency</strong><span data-overlay-urgency>-</span></div>
                <div class="tracking-meta"><strong>Status</strong><span data-overlay-status>-</span></div>
                <div class="tracking-meta"><strong>Assigned To</strong><span data-overlay-technician>-</span></div>
            </div>

            <form method="POST" class="admin-ticket-modal__form" data-overlay-assign-form data-ticket-appointment-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="admin_action" value="save">
                <label for="overlayTechnicianId">Assign Technician</label>
                <select id="overlayTechnicianId" name="technician_id" class="technician-load-select" data-overlay-technician-select>
                    <option value="">Unassigned Queue</option>
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">
                            {{ $tech->name }} ({{ $tech->active_ticket_load }} active)
                        </option>
                    @endforeach
                </select>
                @include('admin.tickets.appointment-fields', ['appointmentPrefix' => 'overlayAppointment', 'appointmentTicket' => null])
                <div class="admin-ticket-modal__actions">
                    <button type="submit" class="btn" data-overlay-assign-submit>Save Assignment</button>
                    <a href="#" class="btn secondary" data-overlay-detail-link>Open Full Ticket</a>
                </div>
            </form>

            <form method="POST" class="admin-ticket-modal__form admin-ticket-modal__close-form" data-overlay-close-form hidden>
                @csrf
                @method('PATCH')
                <input type="hidden" name="admin_action" value="close">
                <input type="hidden" name="technician_id" value="" data-overlay-close-technician-id>
                <div class="admin-ticket-modal__actions">
                    <button type="submit" class="btn secondary">Close Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const overlay = document.querySelector('[data-ticket-overlay]');
        if (!overlay) {
            return;
        }

        const assignForm = overlay.querySelector('[data-overlay-assign-form]');
        const closeForm = overlay.querySelector('[data-overlay-close-form]');
        const technicianSelect = overlay.querySelector('[data-overlay-technician-select]');
        const detailLink = overlay.querySelector('[data-overlay-detail-link]');
        const assignSubmit = overlay.querySelector('[data-overlay-assign-submit]');
        const closeTechnicianInput = overlay.querySelector('[data-overlay-close-technician-id]');

        const summaryTargets = {
            reference: overlay.querySelector('[data-overlay-reference]'),
            customer: overlay.querySelector('[data-overlay-customer]'),
            subject: overlay.querySelector('[data-overlay-subject]'),
            urgency: overlay.querySelector('[data-overlay-urgency]'),
            status: overlay.querySelector('[data-overlay-status]'),
            technician: overlay.querySelector('[data-overlay-technician]'),
        };

        const setSummary = (key, value) => {
            if (summaryTargets[key]) {
                summaryTargets[key].textContent = value || '-';
            }
        };

        const openOverlay = (button) => {
            assignForm.action = button.dataset.ticketUpdateUrl;
            technicianSelect.querySelector('[data-unavailable-technician]')?.remove();
            const selectedId = button.dataset.ticketTechnicianId || '';
            if (selectedId && !Array.from(technicianSelect.options).some(option => option.value === selectedId)) {
                const option = new Option('Current technician unavailable - choose another', selectedId);
                option.disabled = true; option.dataset.unavailableTechnician = 'true'; technicianSelect.add(option);
            }
            technicianSelect.value = selectedId;
            assignForm.querySelector('[name=appointment_ticket_id]').value = button.dataset.ticketId;
            assignForm.querySelector('[name=appointment_change_reason]').value = '';
            assignForm.querySelector('[name=appointment_date]').value = button.dataset.ticketAppointmentDate || '';
            assignForm.querySelector('[name=appointment_time]').value = button.dataset.ticketAppointmentTime || '';
            detailLink.href = button.dataset.ticketShowUrl || '#';
            closeTechnicianInput.value = button.dataset.ticketTechnicianId || '';

            setSummary('reference', button.dataset.ticketReference);
            setSummary('customer', button.dataset.ticketCustomer);
            setSummary('subject', button.dataset.ticketSubject);
            setSummary('urgency', button.dataset.ticketUrgency);
            setSummary('status', button.dataset.ticketStatus);
            setSummary('technician', button.dataset.ticketTechnicianName);

            assignSubmit.textContent = button.dataset.ticketTechnicianId ? 'Save Reassignment' : 'Assign Technician';

            const isReadOnly = button.dataset.ticketStatusValue === 'closed' || button.dataset.ticketStatusValue === 'cancelled';
            const isCancellationRequested = button.dataset.ticketStatusValue === 'cancellation_requested';
            const isResolved = button.dataset.ticketStatusValue === 'resolved';

            technicianSelect.disabled = isReadOnly || isCancellationRequested || isResolved;
            assignSubmit.hidden = isReadOnly || isCancellationRequested || isResolved;
            assignForm.dispatchEvent(new Event('appointment:refresh'));

            if (isResolved) {
                closeForm.hidden = false;
                closeForm.action = button.dataset.ticketUpdateUrl;
            } else {
                closeForm.hidden = true;
                closeForm.action = '';
            }

            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ticket-overlay-open');
        };

        window.addEventListener('load', () => {
            const previousId = @json(old('appointment_ticket_id'));
            const button = Array.from(document.querySelectorAll('[data-ticket-overlay-open]')).find(item => item.dataset.ticketId === String(previousId));
            if (!previousId || !button) return;
            openOverlay(button);
            technicianSelect.value = @json(old('technician_id', ''));
            assignForm.querySelector('[name=appointment_date]').value = @json(old('appointment_date', ''));
            assignForm.querySelector('[name=appointment_time]').value = @json(old('appointment_time', ''));
            assignForm.querySelector('[name=appointment_change_reason]').value = @json(old('appointment_change_reason', ''));
            assignForm.dispatchEvent(new Event('appointment:refresh'));
        });

        const closeOverlay = () => {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ticket-overlay-open');
        };

        document.querySelectorAll('[data-ticket-overlay-open]').forEach((button) => {
            button.addEventListener('click', () => openOverlay(button));
        });

        overlay.querySelectorAll('[data-ticket-overlay-close]').forEach((button) => {
            button.addEventListener('click', closeOverlay);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeOverlay();
            }
        });
    })();
</script>
@endpush
