<x-app-layout>
    <div class="tech-page">
        <section class="card tech-page-header">
            <div>
                <h1>My Assigned Tickets</h1>
                <p>Work through only the support issues assigned to you, reply to customers, and resolve completed concerns cleanly.</p>
            </div>
        </section>

        <section class="card ticket-queue-overview">
            <div class="ticket-queue-overview__head">
                <form method="GET" action="{{ route('technician.tickets.index') }}" class="toolbar-row">
                    @if($selectedStatus !== 'all')
                        <input type="hidden" name="status" value="{{ $selectedStatus }}">
                    @endif
                    <div class="toolbar-field grow">
                        <label for="technician_ticket_search">Search Tickets</label>
                        <input
                            id="technician_ticket_search"
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Tracking number, customer name, or subject"
                        >
                    </div>
                    <div class="toolbar-actions">
                        <button type="submit" class="btn">Search</button>
                        @if($search !== '' || $selectedStatus !== 'all')
                            <a href="{{ route('technician.tickets.index') }}" class="btn secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="ticket-queue-overview__grid">
                <a href="{{ route('technician.tickets.index', array_filter(['status' => 'open', 'search' => $search])) }}" class="ticket-queue-metric {{ in_array($selectedStatus, ['open', 'queued'], true) ? 'is-active' : '' }}">
                    <span class="ticket-queue-metric__label">Open</span>
                    <strong class="ticket-queue-metric__value">{{ $queueSummary['open'] }}</strong>
                    <span class="ticket-queue-metric__meta">Assigned tickets waiting for work to begin</span>
                </a>
                <a href="{{ route('technician.tickets.index', array_filter(['status' => 'in_progress', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'in_progress' ? 'is-active' : '' }}">
                    <span class="ticket-queue-metric__label">In Progress</span>
                    <strong class="ticket-queue-metric__value">{{ $queueSummary['in_progress'] }}</strong>
                    <span class="ticket-queue-metric__meta">Tickets currently being worked on</span>
                </a>
                <a href="{{ route('technician.tickets.index', array_filter(['status' => 'resolved', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'resolved' ? 'is-active' : '' }}">
                    <span class="ticket-queue-metric__label">Resolved</span>
                    <strong class="ticket-queue-metric__value">{{ $queueSummary['resolved'] }}</strong>
                    <span class="ticket-queue-metric__meta">Completed tickets waiting for final closure</span>
                </a>
                <a href="{{ route('technician.tickets.index', array_filter(['status' => 'closed', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'closed' ? 'is-active' : '' }}">
                    <span class="ticket-queue-metric__label">Closed</span>
                    <strong class="ticket-queue-metric__value">{{ $queueSummary['closed'] }}</strong>
                    <span class="ticket-queue-metric__meta">Finished tickets kept for record history</span>
                </a>
                <a href="{{ route('technician.tickets.index', array_filter(['status' => 'cancelled', 'search' => $search])) }}" class="ticket-queue-metric {{ $selectedStatus === 'cancelled' ? 'is-active' : '' }}">
                    <span class="ticket-queue-metric__label">Cancelled</span>
                    <strong class="ticket-queue-metric__value">{{ $queueSummary['cancelled'] }}</strong>
                    <span class="ticket-queue-metric__meta">Cancelled tickets kept for history</span>
                </a>
            </div>
        </section>

        <section class="card table-card">
            <div class="table-wrap">
                <table class="telecom-table admin-ticket-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                        <tr>
                            <td><span class="record-strong">{{ $ticket->publicReference() }}</span></td>
                            <td>
                                <span class="record-strong">{{ $ticket->user->name }}</span>
                            </td>
                            <td>{{ $ticket->subject }}</td>
                            <td><span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></td>
                            <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                            <td>{{ $ticket->created_at->displayDateTime() }}</td>
                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="btn secondary"
                                    data-tech-ticket-overlay-open
                                    data-ticket-reference="{{ $ticket->publicReference() }}"
                                    data-ticket-customer="{{ $ticket->user->name }}"
                                    data-ticket-subject="{{ e($ticket->subject) }}"
                                    data-ticket-urgency="{{ $ticket->displayUrgencyLabel() }}"
                                    data-ticket-status="{{ $ticket->displayStatusLabel() }}"
                                    data-ticket-status-value="{{ $ticket->status }}"
                                    data-ticket-description="{{ e($ticket->description) }}"
                                    data-ticket-submitted="{{ $ticket->created_at->displayDateTime() }}"
                                    data-ticket-show-url="{{ route('technician.tickets.show', $ticket) }}"
                                    data-ticket-start-url="{{ route('technician.tickets.start', $ticket) }}"
                                    data-ticket-resolve-url="{{ route('technician.tickets.resolve', $ticket) }}"
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7"><div class="empty-state compact">No assigned tickets.</div></td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="admin-ticket-modal" data-tech-ticket-overlay aria-hidden="true">
        <div class="admin-ticket-modal__backdrop" data-tech-ticket-overlay-close></div>
        <div class="admin-ticket-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="techTicketOverlayTitle">
            <button type="button" class="admin-ticket-modal__close" data-tech-ticket-overlay-close aria-label="Close ticket overlay">&times;</button>
            <div class="admin-ticket-modal__shell">
                <div class="admin-ticket-modal__intro">
                    <h2 id="techTicketOverlayTitle">Ticket Details</h2>
                    <p>Review the assigned ticket details, then resolve it when the issue has been handled.</p>
                </div>

                <div class="admin-ticket-modal__summary">
                    <div class="tracking-meta"><strong>Tracking Number</strong><span data-tech-ticket-reference>-</span></div>
                    <div class="tracking-meta"><strong>Customer</strong><span data-tech-ticket-customer>-</span></div>
                    <div class="tracking-meta"><strong>Subject</strong><span data-tech-ticket-subject>-</span></div>
                    <div class="tracking-meta"><strong>Urgency</strong><span data-tech-ticket-urgency>-</span></div>
                    <div class="tracking-meta"><strong>Status</strong><span data-tech-ticket-status>-</span></div>
                    <div class="tracking-meta"><strong>Submitted</strong><span data-tech-ticket-submitted>-</span></div>
                    <div class="tracking-meta tech-ticket-modal__description">
                        <strong>Description</strong>
                        <span data-tech-ticket-description>-</span>
                    </div>
                </div>

                <div class="admin-ticket-modal__actions">
                    <form method="POST" data-tech-ticket-work-form>
                        @csrf
                        @method('PATCH')
                        <div data-tech-ticket-resolution-wrap hidden>
                            <label>Resolution notes</label>
                            <textarea name="resolution_notes" rows="3" data-tech-ticket-resolution-notes></textarea>
                        </div>
                        <button type="submit" class="btn" data-tech-ticket-work-button>Start Work</button>
                    </form>
                    <a href="#" class="btn secondary" data-tech-ticket-detail-link>Open Full Ticket</a>
                    <span class="record-muted" data-tech-ticket-no-action hidden>This ticket has no available technician action.</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.querySelector('[data-tech-ticket-overlay]');
            if (!overlay) {
                return;
            }

            const workForm = overlay.querySelector('[data-tech-ticket-work-form]');
            const workButton = overlay.querySelector('[data-tech-ticket-work-button]');
            const resolutionWrap = overlay.querySelector('[data-tech-ticket-resolution-wrap]');
            const resolutionNotes = overlay.querySelector('[data-tech-ticket-resolution-notes]');
            const detailLink = overlay.querySelector('[data-tech-ticket-detail-link]');
            const noAction = overlay.querySelector('[data-tech-ticket-no-action]');

            const summaryTargets = {
                reference: overlay.querySelector('[data-tech-ticket-reference]'),
                customer: overlay.querySelector('[data-tech-ticket-customer]'),
                subject: overlay.querySelector('[data-tech-ticket-subject]'),
                urgency: overlay.querySelector('[data-tech-ticket-urgency]'),
                status: overlay.querySelector('[data-tech-ticket-status]'),
                submitted: overlay.querySelector('[data-tech-ticket-submitted]'),
                description: overlay.querySelector('[data-tech-ticket-description]'),
            };

            const setSummary = function (key, value) {
                if (summaryTargets[key]) {
                    summaryTargets[key].textContent = value || '-';
                }
            };

            const openOverlay = function (button) {
                setSummary('reference', button.dataset.ticketReference);
                setSummary('customer', button.dataset.ticketCustomer);
                setSummary('subject', button.dataset.ticketSubject);
                setSummary('urgency', button.dataset.ticketUrgency);
                setSummary('status', button.dataset.ticketStatus);
                setSummary('submitted', button.dataset.ticketSubmitted);
                setSummary('description', button.dataset.ticketDescription);

                if (detailLink) {
                    detailLink.href = button.dataset.ticketShowUrl || '#';
                }

                const status = button.dataset.ticketStatusValue;
                const canStart = status === 'open' || status === 'queued';
                const canResolve = status === 'in_progress';
                const isCancellationRequested = status === 'cancellation_requested';
                if (workForm) {
                    workForm.hidden = !canStart && !canResolve;
                    workForm.action = canStart
                        ? (button.dataset.ticketStartUrl || '')
                        : (canResolve ? (button.dataset.ticketResolveUrl || '') : '');
                }
                if (noAction) {
                    noAction.hidden = canStart || canResolve;
                    noAction.textContent = isCancellationRequested
                        ? 'Customer requested cancellation. Waiting for admin decision.'
                        : 'This ticket has no available technician action.';
                }
                if (workButton) {
                    workButton.disabled = !canStart && !canResolve;
                    workButton.textContent = canStart ? 'Start Work' : 'Mark as Resolved';
                }
                if (resolutionWrap) {
                    resolutionWrap.hidden = !canResolve;
                }
                if (resolutionNotes) {
                    resolutionNotes.required = canResolve;
                    resolutionNotes.value = '';
                }

                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ticket-overlay-open');
            };

            const closeOverlay = function () {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('ticket-overlay-open');
            };

            document.querySelectorAll('[data-tech-ticket-overlay-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openOverlay(button);
                });
            });

            overlay.querySelectorAll('[data-tech-ticket-overlay-close]').forEach(function (button) {
                button.addEventListener('click', closeOverlay);
            });

            if (workForm && workButton) {
                workForm.addEventListener('submit', function () {
                    workButton.disabled = true;
                    workButton.textContent = 'Saving...';
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                    closeOverlay();
                }
            });
        });
    </script>
</x-app-layout>
