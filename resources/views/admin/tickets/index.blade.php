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
            <form method="GET" action="{{ route('admin.tickets.index') }}" class="toolbar-row ticket-filter-toolbar ticket-filter-toolbar--admin" data-mobile-filter>
                <div class="toolbar-field grow ticket-filter-search">
                    <label for="admin_ticket_search">Search Tickets</label>
                    <input
                        id="admin_ticket_search"
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Tracking number, customer name, or subject"
                    >
                </div>
                <div class="toolbar-field">
                    <label for="admin_ticket_status">Status</label>
                    <select id="admin_ticket_status" name="status">
                        <option value="all" @selected($selectedStatus === 'all')>All statuses</option>
                        <option value="unassigned" @selected($selectedStatus === 'unassigned')>Unassigned</option>
                        <option value="open" @selected(in_array($selectedStatus, ['open', 'queued'], true))>Open</option>
                        <option value="in_progress" @selected($selectedStatus === 'in_progress')>In progress</option>
                        <option value="resolved" @selected($selectedStatus === 'resolved')>Resolved</option>
                        <option value="closed" @selected($selectedStatus === 'closed')>Closed</option>
                        <option value="cancelled" @selected($selectedStatus === 'cancelled')>Cancelled</option>
                    </select>
                </div>
                <div class="toolbar-field">
                    <label for="admin_ticket_urgency">Urgency</label>
                    <select id="admin_ticket_urgency" name="urgency">
                        <option value="all">All urgency levels</option>
                        @foreach(\App\Models\Ticket::URGENCY_LEVELS as $urgency)
                            <option value="{{ $urgency }}" @selected($selectedUrgency === $urgency)>{{ ucfirst($urgency) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="toolbar-field">
                    <label for="admin_ticket_technician">Technician</label>
                    <select id="admin_ticket_technician" name="technician">
                        <option value="all">All technicians</option>
                        <option value="unassigned" @selected($selectedTechnician === 'unassigned')>Unassigned</option>
                        @foreach($filterTechnicians as $filterTechnician)
                            <option value="{{ $filterTechnician->id }}" @selected($selectedTechnician === (string) $filterTechnician->id)>
                                {{ $filterTechnician->name }}{{ $filterTechnician->disabled_at ? ' (Disabled)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="toolbar-actions">
                    <button type="submit" class="btn">Apply</button>
                    @if($search !== '' || $selectedStatus !== 'all' || $selectedUrgency !== 'all' || $selectedTechnician !== 'all')
                        <a href="{{ route('admin.tickets.index') }}" class="btn secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
        @php
            $queueFilterParams = array_filter([
                'search' => $search,
                'urgency' => $selectedUrgency,
                'technician' => $selectedTechnician,
            ], fn ($value) => $value !== '' && $value !== 'all');
        @endphp
        <nav class="ticket-queue-overview__grid" aria-label="Filter tickets by status">
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'unassigned'])) }}" class="ticket-queue-metric {{ $selectedStatus === 'unassigned' ? 'is-active' : '' }}" @if($selectedStatus === 'unassigned') aria-current="page" @endif>
                <span class="ticket-queue-metric__label">Unassigned</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['unassigned'] }}</strong>
            </a>
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'open'])) }}" class="ticket-queue-metric {{ in_array($selectedStatus, ['open', 'queued'], true) ? 'is-active' : '' }}" @if(in_array($selectedStatus, ['open', 'queued'], true)) aria-current="page" @endif>
                <span class="ticket-queue-metric__label">Open</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['open'] }}</strong>
            </a>
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'in_progress'])) }}" class="ticket-queue-metric {{ $selectedStatus === 'in_progress' ? 'is-active' : '' }}" @if($selectedStatus === 'in_progress') aria-current="page" @endif>
                <span class="ticket-queue-metric__label">In Progress</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['in_progress'] }}</strong>
            </a>
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'resolved'])) }}" class="ticket-queue-metric {{ $selectedStatus === 'resolved' ? 'is-active' : '' }}" @if($selectedStatus === 'resolved') aria-current="page" @endif>
                <span class="ticket-queue-metric__label">Resolved</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['resolved'] }}</strong>
            </a>
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'closed'])) }}" class="ticket-queue-metric {{ $selectedStatus === 'closed' ? 'is-active' : '' }}" @if($selectedStatus === 'closed') aria-current="page" @endif>
                <span class="ticket-queue-metric__label">Closed</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['closed'] }}</strong>
            </a>
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'cancelled'])) }}" class="ticket-queue-metric {{ $selectedStatus === 'cancelled' ? 'is-active' : '' }}" @if($selectedStatus === 'cancelled') aria-current="page" @endif>
                <span class="ticket-queue-metric__label">Cancelled</span>
                <strong class="ticket-queue-metric__value">{{ $queueSummary['cancelled'] }}</strong>
            </a>
        </nav>
    </section>

    <section class="card table-card ticket-table-card">
        <div class="table-wrap ticket-table-wrap" tabindex="0" role="region" aria-label="Support ticket records">
            <table class="telecom-table ticket-list-table ticket-list-table--admin" data-mobile-cards>
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
                        <th class="mobile-table-only">Response Target</th>
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
                        <td class="ticket-submitted-cell">
                            <span>{{ $ticket->created_at->displayDateTime('M d, Y') }}</span>
                            <small>{{ $ticket->created_at->displayTime() }}</small>
                        </td>
                        <td class="mobile-table-only">
                            {{ $ticket->responseTargetLabel() }}
                        </td>
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
                                data-ticket-urgency-value="{{ $ticket->urgency }}"
                                data-ticket-triage-required="{{ $ticket->urgency_review_required ? '1' : '0' }}"
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
                        <td colspan="9"><div class="empty-state compact">No support tickets available.</div></td>
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

            <form method="POST" class="admin-ticket-modal__form" data-overlay-assign-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="admin_action" value="save">
                <input type="hidden" name="ticket_id" value="" data-overlay-ticket-id>
                <label for="overlayUrgency">Urgency</label>
                <select id="overlayUrgency" name="urgency" data-overlay-urgency-select required>
                    @foreach(\App\Models\Ticket::URGENCY_LEVELS as $urgency)
                        <option value="{{ $urgency }}">{{ ucfirst($urgency) }}</option>
                    @endforeach
                </select>
                <p class="ticket-sidebar-note" data-overlay-triage-note hidden>This &ldquo;Other&rdquo; concern requires an admin urgency decision before automatic assignment.</p>
                <label for="overlayTechnicianId">Technician override <span class="field-badge optional">Optional</span></label>
                <select id="overlayTechnicianId" name="technician_id" class="technician-load-select" data-overlay-technician-select>
                    <option value="">Automatic priority queue</option>
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">
                            {{ $tech->name }} ({{ $tech->active_ticket_load }} active)
                        </option>
                    @endforeach
                </select>
                <div class="admin-ticket-modal__actions">
                    <button type="submit" class="btn" data-overlay-assign-submit>Save Priority & Assignment</button>
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
        const urgencySelect = overlay.querySelector('[data-overlay-urgency-select]');
        const triageNote = overlay.querySelector('[data-overlay-triage-note]');
        const ticketIdInput = overlay.querySelector('[data-overlay-ticket-id]');
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
            ticketIdInput.value = button.dataset.ticketId;
            urgencySelect.value = button.dataset.ticketUrgencyValue || 'medium';
            triageNote.hidden = button.dataset.ticketTriageRequired !== '1';
            detailLink.href = button.dataset.ticketShowUrl || '#';
            closeTechnicianInput.value = button.dataset.ticketTechnicianId || '';

            setSummary('reference', button.dataset.ticketReference);
            setSummary('customer', button.dataset.ticketCustomer);
            setSummary('subject', button.dataset.ticketSubject);
            setSummary('urgency', button.dataset.ticketUrgency);
            setSummary('status', button.dataset.ticketStatus);
            setSummary('technician', button.dataset.ticketTechnicianName);

            assignSubmit.textContent = button.dataset.ticketTechnicianId
                ? 'Save Priority & Reassignment'
                : 'Save Priority & Assignment';

            const isReadOnly = button.dataset.ticketStatusValue === 'closed' || button.dataset.ticketStatusValue === 'cancelled';
            const isCancellationRequested = button.dataset.ticketStatusValue === 'cancellation_requested';
            const isResolved = button.dataset.ticketStatusValue === 'resolved';

            technicianSelect.disabled = isReadOnly || isCancellationRequested || isResolved;
            urgencySelect.disabled = isReadOnly || isCancellationRequested || isResolved;
            assignSubmit.hidden = isReadOnly || isCancellationRequested || isResolved;
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
            const previousId = @json(old('ticket_id'));
            const button = Array.from(document.querySelectorAll('[data-ticket-overlay-open]')).find(item => item.dataset.ticketId === String(previousId));
            if (!previousId || !button) return;
            openOverlay(button);
            technicianSelect.value = @json(old('technician_id', ''));
            urgencySelect.value = @json(old('urgency', 'medium'));
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
