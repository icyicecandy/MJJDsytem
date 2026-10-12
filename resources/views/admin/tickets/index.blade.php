@extends('layouts.app')

@section('title','Support Tickets')

@section('content')
@php
    $queueAvailabilityMessage = 'No technician is currently on duty. Automatic assignment retries every five minutes.'
        .($nextAutomaticAssignmentAt
            ? ' Next eligible shift: '.$nextAutomaticAssignmentAt->format('M d, Y h:i A').' ('.config('app.timezone').').'
            : '');
@endphp<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Support Tickets</h1>
            <p>Review the priority queue, assign eligible support staff, take ownership when needed, and close resolved tickets.</p>
        </div>
    </section>

    <section class="card ticket-queue-overview">
        <div class="ticket-queue-overview__head">
            <form method="GET" action="{{ route('admin.tickets.index') }}" class="toolbar-row ticket-filter-toolbar ticket-filter-toolbar--admin" data-mobile-filter data-live-filter data-live-filter-target="#adminTicketResults">
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
                        <option value="unassigned" @selected($selectedStatus === 'unassigned')>Queued / awaiting staff</option>
                        <option value="open" @selected(in_array($selectedStatus, ['open', 'queued'], true))>Open</option>
                        <option value="in_progress" @selected($selectedStatus === 'in_progress')>In progress</option>
                        <option value="resolved" @selected($selectedStatus === 'resolved')>Resolved</option>
                        <option value="closed" @selected($selectedStatus === 'closed')>Closed</option>
                        <option value="cancelled" @selected($selectedStatus === 'cancelled')>Cancelled</option>
                    </select>
                </div>
                <div class="filter-date-range" role="group" aria-labelledby="ticket_date_range_label">
                    <span id="ticket_date_range_label" class="filter-date-range__label">Submitted date</span>
                    <div class="filter-date-range__fields">
                        <div class="toolbar-field"><label for="ticket_date_from">From</label><input id="ticket_date_from" type="date" name="date_from" value="{{ $dateFrom }}"></div>
                        <div class="toolbar-field"><label for="ticket_date_to">To</label><input id="ticket_date_to" type="date" name="date_to" value="{{ $dateTo }}"></div>
                    </div>
                </div>
                <label class="toolbar-field"><span>Action</span><select name="requires_action"><option value="0">All records</option><option value="1" @selected($requiresAction)>Requires action</option></select></label>
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
                    <label for="admin_ticket_technician">Support staff</label>
                    <select id="admin_ticket_technician" name="technician">
                        <option value="all">All support staff</option>
                        <option value="unassigned" @selected($selectedTechnician === 'unassigned')>Automatic queue</option>
                        @foreach($filterTechnicians as $filterTechnician)
                            <option value="{{ $filterTechnician->id }}" @selected($selectedTechnician === (string) $filterTechnician->id)>
                                {{ $filterTechnician->name }} - {{ ucfirst($filterTechnician->role) }}{{ $filterTechnician->disabled_at ? ' (Disabled)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="toolbar-actions">
                    <button type="submit" class="btn">Apply</button>
                    @if($search !== '' || $selectedStatus !== 'all' || $selectedUrgency !== 'all' || $selectedTechnician !== 'all' || filled($dateFrom) || filled($dateTo) || $requiresAction)
                        <a href="{{ route('admin.tickets.index') }}" class="btn secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
        <x-active-filter-summary :labels="[
            $selectedStatus !== 'all' ? 'Status: '.str_replace('_', ' ', ucfirst($selectedStatus)) : null,
            $search ? 'Search: '.$search : null,
            $selectedUrgency !== 'all' ? 'Urgency: '.ucfirst($selectedUrgency) : null,
            $selectedTechnician !== 'all' ? 'Assigned staff filter' : null,
            $dateFrom ? 'From: '.$dateFrom : null,
            $dateTo ? 'To: '.$dateTo : null,
            $requiresAction ? 'Requires action' : null,
        ]" :reset-url="route('admin.tickets.index')" />
        @php
            $queueFilterParams = array_filter([
                'search' => $search,
                'urgency' => $selectedUrgency,
                'technician' => $selectedTechnician,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'requires_action' => $requiresAction ? 1 : null,
            ], fn ($value) => $value !== '' && $value !== 'all');
        @endphp
        <nav class="ticket-queue-overview__grid" aria-label="Filter tickets by status" data-live-filter-sync="admin-ticket-queue">
            <a href="{{ route('admin.tickets.index', array_merge($queueFilterParams, ['status' => 'unassigned'])) }}" class="ticket-queue-metric {{ $selectedStatus === 'unassigned' ? 'is-active' : '' }}" @if($selectedStatus === 'unassigned') aria-current="page" @endif>
                <span class="ticket-queue-metric__label">Awaiting Staff</span>
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

    <section id="adminTicketResults" class="card table-card ticket-table-card" data-live-filter-results aria-busy="false">
        <div class="table-wrap ticket-table-wrap" tabindex="0" role="region" aria-label="Support ticket records">
            <table class="telecom-table ticket-list-table ticket-list-table--admin" data-mobile-cards>
                    <colgroup>
                        <col class="ticket-col-reference">
                        <col class="ticket-col-customer">
                        <col class="ticket-col-subject">
                        <col class="ticket-col-urgency">
                        <col class="ticket-col-status">
                        <col class="ticket-col-assignee">
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
                        <th>Assigned Staff</th>
                        <th>Submitted</th>
                        <th class="mobile-table-only">Response Target</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr @class(['ticket-row--queued' => $ticket->isQueued()])>
                        <td><span class="record-strong record-reference">{{ $ticket->publicReference() }}</span></td>
                        <td><span class="record-strong">{{ $ticket->user->name }}</span></td>
                        <td>{{ $ticket->subject }}</td>
                        <td><span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></td>
                        <td @class(['ticket-status-cell', 'is-queued' => $ticket->isQueued()])>
                            @if($ticket->isQueued())
                                <div class="ticket-queue-status" aria-label="{{ $ticket->displayStatusLabel() }}" title="{{ $ticket->displayStatusLabel() }}">
                                    <span class="ticket-queue-status__badge"><span aria-hidden="true"></span>Queued</span>
                                    <small>Waiting for on-duty technician</small>
                                </div>
                            @else
                                <span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span>
                            @endif
                        </td>
                        <td>
                            @if($ticket->assignedStaff)
                                <span class="record-strong">{{ $ticket->assignedStaff->name }}</span>
                                <small>{{ ucfirst($ticket->assignedStaff->role) }}</small>
                            @else
                                <div class="ticket-auto-queue" title="No technician is currently on duty. Automatic assignment remains active.">
                                    <span class="ticket-auto-queue__label"><span aria-hidden="true"></span>Automatic queue</span>
                                    @if($nextAutomaticAssignmentAt)
                                        <small class="ticket-auto-queue__schedule" title="{{ $nextAutomaticAssignmentAt->format('F d, Y h:i A') }} {{ config('app.timezone') }}">
                                            Next shift
                                            <strong>{{ $nextAutomaticAssignmentAt->format('M d, g:i A') }}</strong>
                                        </small>
                                    @else
                                        <small class="ticket-auto-queue__schedule">Checking staff availability every 5 minutes</small>
                                    @endif
                                </div>
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
                                data-ticket-status="{{ $ticket->isQueued() ? 'Queued' : $ticket->displayStatusLabel() }}"
                                data-ticket-status-value="{{ $ticket->status }}"
                                data-ticket-urgency="{{ $ticket->displayUrgencyLabel() }}"
                                data-ticket-urgency-value="{{ $ticket->urgency }}"
                                data-ticket-triage-required="{{ $ticket->urgency_review_required ? '1' : '0' }}"
                                data-ticket-staff-id="{{ $ticket->assigned_staff_id ?? '' }}"
                                data-ticket-staff-name="{{ optional($ticket->assignedStaff)->name ?? 'Automatic priority queue' }}"
                                data-ticket-update-url="{{ route('admin.tickets.update', $ticket) }}"
                                data-ticket-show-url="{{ route('admin.tickets.show', $ticket) }}"
                                data-ticket-queue-message="{{ $ticket->isQueued() ? $queueAvailabilityMessage : '' }}"
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
                <p>Assign the ticket to available support staff or take ownership yourself. Work starts only when the assignee selects Start Work.</p>
            </div>

            <div class="admin-ticket-modal__summary">
                <div class="tracking-meta"><strong>Tracking Number</strong><span data-overlay-reference>-</span></div>
                <div class="tracking-meta"><strong>Customer</strong><span data-overlay-customer>-</span></div>
                <div class="tracking-meta"><strong>Subject</strong><span data-overlay-subject>-</span></div>
                <div class="tracking-meta"><strong>Urgency</strong><span data-overlay-urgency>-</span></div>
                <div class="tracking-meta"><strong>Status</strong><span data-overlay-status>-</span></div>
                <div class="tracking-meta"><strong>Assigned To</strong><span data-overlay-staff>-</span></div>
            </div>
            <div class="ticket-queue-notice" data-overlay-queue-notice hidden></div>

            <form method="POST" class="admin-ticket-modal__form" data-overlay-assign-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="admin_action" value="save">
                <input type="hidden" name="ticket_id" value="" data-overlay-ticket-id>
                @if($errors->any() && old('ticket_id'))
                    <div class="ticket-assignment-error" role="alert">
                        <strong>The assignment was not saved.</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif
                <label for="overlayUrgency" class="admin-ticket-modal__field-label">Urgency</label>
                <select id="overlayUrgency" name="urgency" data-overlay-urgency-select required>
                    @foreach(\App\Models\Ticket::URGENCY_LEVELS as $urgency)
                        <option value="{{ $urgency }}">{{ ucfirst($urgency) }}</option>
                    @endforeach
                </select>
                <p class="ticket-sidebar-note" data-overlay-triage-note hidden>This &ldquo;Other&rdquo; concern requires an admin urgency decision before automatic assignment.</p>
                <label for="overlayAssignedStaffId" class="admin-ticket-modal__field-label">Support assignee <span class="field-badge optional">Automatic when empty</span></label>
                <select id="overlayAssignedStaffId" name="assigned_staff_id" class="technician-load-select" data-overlay-staff-select aria-describedby="ticketAssignmentHelp">
                    <option value="">Automatic priority queue</option>
                    @foreach($assignees as $staff)
                        @php($warnings = collect($staff->assignment_warnings ?? [])->implode(' '))
                        <option value="{{ $staff->id }}" data-assignment-warning="{{ $warnings }}">
                            {{ $staff->name }}
                            @if($staff->id === auth()->id()) (You) @endif
                            - {{ ucfirst($staff->role) }}
                            ({{ $staff->active_ticket_load }} active)
                            @if($warnings !== '') - Warning @endif
                        </option>
                    @endforeach
                </select>
                @error('assigned_staff_id') <span class="error">{{ $message }}</span> @enderror
                <p class="ticket-assignment-help" id="ticketAssignmentHelp">To reassign, choose a different staff member. The reason field will appear automatically.</p>

                <div class="ticket-assignment-reason" data-overlay-reason-group>
                    <label for="overlayAssignmentReason" class="admin-ticket-modal__field-label">Reason <span class="field-badge optional">Required for reassignment or takeover</span></label>
                    <textarea id="overlayAssignmentReason" name="assignment_reason" rows="3" maxlength="1000" data-overlay-assignment-reason placeholder="Briefly explain why the assignment is changing.">{{ old('assignment_reason') }}</textarea>
                    @error('assignment_reason') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="ticket-sidebar-note" data-overlay-warning-box hidden>
                    <strong>Assignment warning</strong>
                    <p data-overlay-warning-text></p>
                    <label class="ticket-confirmation-option">
                        <input type="checkbox" name="confirm_assignment_override" value="1" data-overlay-warning-confirm @checked(old('confirm_assignment_override'))>
                        <span>I reviewed this warning and want to continue.</span>
                    </label>
                </div>
                @error('confirm_assignment_override') <span class="error">{{ $message }}</span> @enderror

                <div class="ticket-sidebar-note" data-overlay-progress-box hidden>
                    <strong>Work is already in progress.</strong>
                    <label class="ticket-confirmation-option">
                        <input type="checkbox" name="confirm_in_progress_transfer" value="1" data-overlay-progress-confirm @checked(old('confirm_in_progress_transfer'))>
                        <span>I confirm this intentional transfer of active work.</span>
                    </label>
                </div>
                @error('confirm_in_progress_transfer') <span class="error">{{ $message }}</span> @enderror

                <div class="admin-ticket-modal__actions admin-ticket-modal__actions--assignment">
                    <button type="submit" class="btn" data-overlay-assign-submit>Save Ticket Changes</button>
                    <button type="submit" name="take_over_ticket" value="1" class="btn secondary" data-overlay-takeover data-current-admin="{{ auth()->id() }}">Take Over Ticket</button>
                    <a href="#" class="btn secondary admin-ticket-modal__detail-link" data-overlay-detail-link>Open Full Ticket</a>
                </div>
            </form>

            <form method="POST" class="admin-ticket-modal__form admin-ticket-modal__close-form" data-overlay-close-form hidden>
                @csrf
                @method('PATCH')
                <input type="hidden" name="admin_action" value="close">
                <input type="hidden" name="assigned_staff_id" value="" data-overlay-close-staff-id>
                <div class="admin-ticket-modal__actions">
                    <button type="submit" class="btn danger">Close Ticket</button>
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
        const staffSelect = overlay.querySelector('[data-overlay-staff-select]');
        const urgencySelect = overlay.querySelector('[data-overlay-urgency-select]');
        const triageNote = overlay.querySelector('[data-overlay-triage-note]');
        const ticketIdInput = overlay.querySelector('[data-overlay-ticket-id]');
        const detailLink = overlay.querySelector('[data-overlay-detail-link]');
        const assignSubmit = overlay.querySelector('[data-overlay-assign-submit]');
        const closeStaffInput = overlay.querySelector('[data-overlay-close-staff-id]');
        const assignmentReason = overlay.querySelector('[data-overlay-assignment-reason]');
        const reasonGroup = overlay.querySelector('[data-overlay-reason-group]');
        const warningBox = overlay.querySelector('[data-overlay-warning-box]');
        const warningText = overlay.querySelector('[data-overlay-warning-text]');
        const warningConfirm = overlay.querySelector('[data-overlay-warning-confirm]');
        const progressBox = overlay.querySelector('[data-overlay-progress-box]');
        const progressConfirm = overlay.querySelector('[data-overlay-progress-confirm]');
        const takeoverButton = overlay.querySelector('[data-overlay-takeover]');
        const queueNotice = overlay.querySelector('[data-overlay-queue-notice]');

        const summaryTargets = {
            reference: overlay.querySelector('[data-overlay-reference]'),
            customer: overlay.querySelector('[data-overlay-customer]'),
            subject: overlay.querySelector('[data-overlay-subject]'),
            urgency: overlay.querySelector('[data-overlay-urgency]'),
            status: overlay.querySelector('[data-overlay-status]'),
            staff: overlay.querySelector('[data-overlay-staff]'),
        };

        const setSummary = (key, value) => {
            if (summaryTargets[key]) {
                summaryTargets[key].textContent = value || '-';
            }
        };

        const openOverlay = (button) => {
            assignForm.action = button.dataset.ticketUpdateUrl;
            staffSelect.querySelector('[data-unavailable-staff]')?.remove();
            const selectedId = button.dataset.ticketStaffId || '';
            if (selectedId && !Array.from(staffSelect.options).some(option => option.value === selectedId)) {
                const option = new Option('Current staff member unavailable - choose another', selectedId);
                option.disabled = true; option.dataset.unavailableStaff = 'true'; staffSelect.add(option);
            }
            staffSelect.value = selectedId;
            assignForm.dataset.currentAssignee = selectedId;
            assignForm.dataset.ticketStatus = button.dataset.ticketStatusValue || '';
            ticketIdInput.value = button.dataset.ticketId;
            urgencySelect.value = button.dataset.ticketUrgencyValue || 'medium';
            triageNote.hidden = button.dataset.ticketTriageRequired !== '1';
            detailLink.href = button.dataset.ticketShowUrl || '#';
            closeStaffInput.value = button.dataset.ticketStaffId || '';

            setSummary('reference', button.dataset.ticketReference);
            setSummary('customer', button.dataset.ticketCustomer);
            setSummary('subject', button.dataset.ticketSubject);
            setSummary('urgency', button.dataset.ticketUrgency);
            setSummary('status', button.dataset.ticketStatus);
            setSummary('staff', button.dataset.ticketStaffName);
            queueNotice.textContent = button.dataset.ticketQueueMessage || '';
            queueNotice.hidden = !button.dataset.ticketQueueMessage;

            assignSubmit.textContent = 'Save Ticket Changes';


            const isReadOnly = button.dataset.ticketStatusValue === 'closed' || button.dataset.ticketStatusValue === 'cancelled';
            const isCancellationRequested = button.dataset.ticketStatusValue === 'cancellation_requested';
            const isResolved = button.dataset.ticketStatusValue === 'resolved';

            const assignmentLocked = isReadOnly || isCancellationRequested || isResolved;
            assignForm.dataset.assignmentLocked = assignmentLocked ? '1' : '0';
            staffSelect.disabled = assignmentLocked;
            urgencySelect.disabled = assignmentLocked;
            assignmentReason.disabled = assignmentLocked;
            assignSubmit.hidden = isReadOnly || isCancellationRequested || isResolved;
            if (isResolved) {
                closeForm.hidden = false;
                closeForm.action = button.dataset.ticketUpdateUrl;
            } else {
                closeForm.hidden = true;
                closeForm.action = '';
            }

            refreshAssignmentControls();
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ticket-overlay-open');
        };

        window.addEventListener('load', () => {
            const previousId = @json(old('ticket_id'));
            const button = Array.from(document.querySelectorAll('[data-ticket-overlay-open]')).find(item => item.dataset.ticketId === String(previousId));
            if (!previousId || !button) return;
            openOverlay(button);
            staffSelect.value = @json(old('assigned_staff_id', ''));
            assignmentReason.value = @json(old('assignment_reason', ''));
            refreshAssignmentControls();
            urgencySelect.value = @json(old('urgency', 'medium'));
        });

        const refreshAssignmentControls = () => {
            const currentAssignee = assignForm.dataset.currentAssignee || '';
            const changed = (staffSelect.value || '') !== currentAssignee;
            const selected = staffSelect.selectedOptions[0];
            const warning = changed ? (selected?.dataset.assignmentWarning || '') : '';
            const inProgress = assignForm.dataset.ticketStatus === 'in_progress';
            const assignmentLocked = assignForm.dataset.assignmentLocked === '1';

            assignSubmit.textContent = changed
                ? (currentAssignee ? 'Reassign Ticket' : 'Assign Ticket')
                : 'Save Ticket Changes';
            assignmentReason.required = changed && currentAssignee !== '';
            warningBox.hidden = warning === '';
            warningText.textContent = warning;
            warningConfirm.required = warning !== '';
            if (warning === '') warningConfirm.checked = false;

            progressBox.hidden = !(inProgress && changed);
            progressConfirm.required = inProgress && changed;
            if (!inProgress || !changed) progressConfirm.checked = false;

            const takeoverAvailable = !assignmentLocked
                && String(takeoverButton.dataset.currentAdmin || '') !== currentAssignee;
            reasonGroup.hidden = !(changed || takeoverAvailable);
            takeoverButton.hidden = !takeoverAvailable;
        };

        staffSelect.addEventListener('change', refreshAssignmentControls);
        takeoverButton.addEventListener('click', () => {
            staffSelect.value = takeoverButton.dataset.currentAdmin || '';
            refreshAssignmentControls();
            assignmentReason.focus();
        });

        const closeOverlay = () => {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ticket-overlay-open');
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-ticket-overlay-open]');
            if (button) openOverlay(button);
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
