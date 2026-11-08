@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
@php
    $isResolved = $ticket->status === \App\Models\Ticket::STATUS_RESOLVED;
    $isClosed = $ticket->status === \App\Models\Ticket::STATUS_CLOSED;
    $isCancellationRequested = $ticket->isCancellationRequested();
    $isCancelled = $ticket->isCancelled();
    $isReadOnly = $ticket->isReadOnly();
    $hasPendingReopenRequest = $ticket->hasPendingReopenRequest();
    $assignedStaff = optional($ticket->assignedStaff)->name ?? optional($ticket->technician)->name ?? 'Unassigned Queue';
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for customer confirmation',
        \App\Models\Ticket::STATUS_CANCELLATION_REQUESTED => 'Customer requested cancellation',
        \App\Models\Ticket::STATUS_CLOSED => $hasPendingReopenRequest ? 'Customer requested reopening' : 'Ticket closed',
        \App\Models\Ticket::STATUS_CANCELLED => 'Ticket cancelled',
        \App\Models\Ticket::STATUS_IN_PROGRESS => 'Assigned support staff is working on this ticket',
        default => 'Waiting for work to begin',
    };
@endphp

<div class="ticket-detail-page">
    <div class="ticket-detail-backline">
        <a href="{{ route('admin.tickets.index') }}" class="btn secondary">&larr; Back to Tickets</a>
    </div>

    <div class="ticket-detail-layout">
        <main class="ticket-detail-main">
            <section class="ticket-detail-card ticket-detail-hero">
                <div class="ticket-detail-hero__top">
                    <div>
                        <p class="ticket-detail-eyebrow">{{ $ticket->publicReference() }}</p>
                        <h1>{{ $ticket->subject }}</h1>
                    </div>
                    <span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span>
                </div>

                <div class="ticket-detail-hero__meta">
                    <span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span>
                    <span>Assigned to {{ $assignedStaff }}</span>
                </div>

                @if($ticket->isQueued())
                    <div class="ticket-queue-notice ticket-queue-notice--detail" role="status">
                        <strong>No technician is currently on duty.</strong>
                        <span>Automatic assignment retries every five minutes.</span>
                        @if($nextAutomaticAssignmentAt)
                            <span>Next eligible shift: {{ $nextAutomaticAssignmentAt->format('F d, Y h:i A') }} ({{ config('app.timezone') }}).</span>
                        @endif
                    </div>
                @endif

                <p class="ticket-detail-description">{{ $ticket->description }}</p>

                <x-photo-gallery
                    :photos="$ticket->photoGalleryItems()"
                    title="Ticket photos"
                    empty="No ticket attachment uploaded."
                    button-text="View attachment"
                />
            </section>

            <x-next-action :message="$ticket->nextActionGuidance()" :tone="$ticket->nextActionTone()" />

            <section class="ticket-detail-card ticket-conversation-card">
                <div class="ticket-detail-section-head">
                    <div>
                        <h2>Conversation</h2>
                        <p>Customer messages and workflow updates in chronological order.</p>
                    </div>
                    <span class="ticket-section-count">{{ $ticket->messages->count() }} {{ $ticket->messages->count() === 1 ? 'update' : 'updates' }}</span>
                </div>

                <div class="ticket-timeline">
                    @forelse($ticket->messages as $msg)
                        @php
                            $messageText = (string) $msg->message;
                            $isCustomerMessage = optional($msg->user)->id === $ticket->user_id;
                            $isSystemMessage = str_starts_with($messageText, 'Technician started work')
                                || str_starts_with($messageText, 'Technician marked this ticket as resolved')
                                || str_starts_with($messageText, 'Ticket closed by')
                                || str_starts_with($messageText, 'Ticket reopen requested by')
                                || str_starts_with($messageText, 'Ticket reopen request approved by')
                                || str_starts_with($messageText, 'Ticket reopened by')
                                || str_starts_with($messageText, 'Customer cancelled this ticket')
                                || str_starts_with($messageText, 'Customer requested ticket cancellation')
                                || str_starts_with($messageText, 'Admin approved ticket cancellation')
                                || str_starts_with($messageText, 'Admin rejected cancellation request')
                                || str_starts_with($messageText, 'Ticket queue updated')
                                || str_starts_with($messageText, 'Administrator started work')
                                || str_starts_with($messageText, 'Administrator marked this ticket as resolved');
                            $messageClass = $isSystemMessage ? 'is-system' : ($isCustomerMessage ? 'is-customer' : 'is-staff');
                        @endphp
                        <article class="ticket-message-row {{ $messageClass }}">
                            <div class="ticket-message-bubble">
                                <div class="ticket-message-meta">
                                    <strong>{{ optional($msg->user)->name ?? 'System' }}</strong>
                                    <span>{{ $msg->created_at->displayDateTime('M d, Y h:i A') }}</span>
                                </div>
                                <p>{{ $messageText }}</p>
                                @if($msg->attachmentPaths())
                                    <x-photo-gallery
                                        :photos="$msg->attachmentGalleryItems()"
                                        title="Message attachments"
                                        button-text="View attachment"
                                        class="ticket-message-attachment"
                                    />
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="ticket-empty-state">No messages yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="ticket-detail-card ticket-reply-card">
                <div class="ticket-detail-section-head">
                    <div>
                        <h2>Reply to ticket</h2>
                        <p>{{ $isReadOnly ? 'Replies are disabled because this ticket is closed or cancelled.' : 'Send an update to everyone on this ticket.' }}</p>
                    </div>
                </div>

                @if(! $isReadOnly)
                    <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form" data-unsaved-warning data-protect-form>
                        @csrf
                        <textarea name="message" rows="3" maxlength="4000" placeholder="Write a clear update for the customer..."></textarea>
                        <label for="admin_ticket_attachment">Attachments <span class="field-badge optional">Optional</span></label>
                        <input id="admin_ticket_attachment" type="file" name="ticket_attachments[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                        @error('ticket_attachment')
                            <p class="error">{{ $message }}</p>
                        @enderror
                        @error('ticket_attachments')
                            <p class="error">{{ $message }}</p>
                        @enderror
                        @error('ticket_attachments.*')
                            <p class="error">{{ $message }}</p>
                        @enderror
                        <div class="ticket-form-actions">
                            <button class="btn">Send Reply</button>
                        </div>
                    </form>
                @else
                    <div class="ticket-reply-disabled">
                        <p class="ticket-sidebar-note">This ticket is read-only.</p>
                    </div>
                @endif
            </section>
        </main>

        <aside class="ticket-detail-sidebar">
            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Ticket details</h2>
                <dl class="ticket-meta-list ticket-meta-list--compact">
                    <div>
                        <dt>Customer</dt>
                        <dd>{{ $ticket->user->name }}</dd>
                    </div>
                    <div>
                        <dt>Submitted</dt>
                        <dd>{{ $ticket->created_at->displayDateTime('F d, Y h:i A') }}</dd>
                    </div>

                    <div>
                        <dt>Response target ({{ config('app.timezone') }})</dt>
                        <dd>{{ $ticket->responseTargetLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Assignment source</dt>
                        <dd>{{ $ticket->assignment_source ? ucfirst(str_replace('_', ' ', $ticket->assignment_source)) : 'Waiting in priority queue' }}</dd>
                    </div>
                </dl>
            </section>

            @if(! $isReadOnly && ! $isCancellationRequested && ! $isResolved)
            <section class="ticket-detail-card ticket-sidebar-card ticket-assignment-card">
                <h2>Support Assignment</h2>
                    <form method="post" action="{{ route('admin.tickets.update', $ticket) }}" class="ticket-action-form" data-staff-assignment-form data-current-assignee="{{ $ticket->assigned_staff_id ?? '' }}" data-ticket-status="{{ $ticket->status }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="admin_action" value="save">

                        <label for="ticket_urgency">Urgency</label>
                        <select id="ticket_urgency" name="urgency" required>
                            @foreach(\App\Models\Ticket::URGENCY_LEVELS as $urgency)
                                <option value="{{ $urgency }}" @selected(old('urgency', $ticket->urgency) === $urgency)>{{ ucfirst($urgency) }}</option>
                            @endforeach
                        </select>
                        @if($ticket->urgency_review_required)
                            <p class="ticket-sidebar-note">Admin urgency review is required because the customer selected &ldquo;Other Technical Concern.&rdquo;</p>
                        @endif
                        @error('urgency') <span class="error">{{ $message }}</span> @enderror

                        <label for="assigned_staff_id">Support assignee <span class="field-badge optional">Automatic when empty</span></label>
                        <select id="assigned_staff_id" name="assigned_staff_id" class="technician-load-select" data-staff-select>
                            <option value="">Automatic priority queue</option>
                            @if($ticket->assigned_staff_id && ! $assignees->contains('id', $ticket->assigned_staff_id))
                                <option value="{{ $ticket->assigned_staff_id }}" selected disabled>Current staff member unavailable - choose another</option>
                            @endif
                            @foreach($assignees as $staff)
                                @php($warnings = collect($staff->assignment_warnings ?? [])->implode(' '))
                                <option
                                    value="{{ $staff->id }}"
                                    data-staff-role="{{ $staff->role }}"
                                    data-assignment-warning="{{ $warnings }}"
                                    @selected(old('assigned_staff_id', $ticket->assigned_staff_id) == $staff->id)
                                >
                                    {{ $staff->name }}
                                    @if($staff->id === auth()->id()) (You) @endif
                                    - {{ ucfirst($staff->role) }}
                                    ({{ $staff->active_ticket_load }} active)
                                    @if($warnings !== '') - Warning @endif
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_staff_id') <span class="error">{{ $message }}</span> @enderror


                        <div data-assignment-reason-wrap>
                            <label for="assignment_reason">Reassignment or takeover reason <span class="field-badge optional" data-reason-badge>Required when assignment changes</span></label>
                            <textarea id="assignment_reason" name="assignment_reason" rows="3" maxlength="1000" placeholder="Example: Technician is unavailable; admin is taking over.">{{ old('assignment_reason') }}</textarea>
                            @error('assignment_reason') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="ticket-sidebar-note" data-assignment-warning-box hidden>
                            <strong>Assignment warning</strong>
                            <p data-assignment-warning-text></p>
                            <label class="ticket-confirmation-option">
                                <input type="checkbox" name="confirm_assignment_override" value="1" data-assignment-override @checked(old('confirm_assignment_override'))>
                                <span>I reviewed the schedule or workload warning and want to continue.</span>
                            </label>
                        </div>
                        @error('confirm_assignment_override') <span class="error">{{ $message }}</span> @enderror

                        <div class="ticket-sidebar-note" data-progress-transfer-box hidden>
                            <strong>Work is already in progress.</strong>
                            <label class="ticket-confirmation-option">
                                <input type="checkbox" name="confirm_in_progress_transfer" value="1" data-progress-transfer @checked(old('confirm_in_progress_transfer'))>
                                <span>I confirm this intentional transfer of active work.</span>
                            </label>
                        </div>
                        @error('confirm_in_progress_transfer') <span class="error">{{ $message }}</span> @enderror

                        <div class="admin-ticket-actions">
                            <button class="btn">Save Ticket Changes</button>
                            @if((int) $ticket->assigned_staff_id !== (int) auth()->id())
                                <button type="submit" name="take_over_ticket" value="1" class="btn secondary" data-take-over-ticket data-current-admin="{{ auth()->id() }}">Take Over Ticket</button>
                            @endif
                        </div>
                    </form>
            </section>
            @endif

            <section class="ticket-detail-card ticket-sidebar-card ticket-status-card">
                <h2>Status Actions</h2>
                <p class="ticket-workflow-state">{{ $statusHelper }}.</p>
                @if($isCancellationRequested)
                    <div class="ticket-action-form">
                        <p class="ticket-sidebar-note">Customer requested cancellation. Review the request before any other status update.</p>
                        <form method="post" action="{{ route('admin.tickets.cancellation.approve', $ticket) }}" data-protect-form>
                            @csrf
                            @method('PATCH')
                            <button class="btn danger" data-confirm="Approve this cancellation request and stop further ticket work?">Approve Cancellation</button>
                        </form>
                        <form method="post" action="{{ route('admin.tickets.cancellation.reject', $ticket) }}" data-protect-form>
                            @csrf
                            @method('PATCH')
                            <button class="btn secondary" data-confirm="Reject this cancellation request and return the ticket to its previous work state?">Reject Cancellation</button>
                        </form>
                    </div>
                @elseif((int) $ticket->assigned_staff_id === (int) auth()->id() && $ticket->isOpenForWork())
                    <form method="post" action="{{ route('admin.tickets.start', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <p class="ticket-sidebar-note">You own this ticket. Start work when you are ready to handle it.</p>
                        <button class="btn">Start Work</button>
                    </form>
                @elseif((int) $ticket->assigned_staff_id === (int) auth()->id() && $ticket->status === \App\Models\Ticket::STATUS_IN_PROGRESS)
                    <form method="post" action="{{ route('admin.tickets.resolve', $ticket) }}" class="ticket-action-form" data-protect-form data-warn-unsaved>
                        @csrf
                        @method('PATCH')
                        <label for="admin_resolution_notes">Resolution notes</label>
                        <textarea id="admin_resolution_notes" name="resolution_notes" rows="4" required>{{ old('resolution_notes') }}</textarea>
                        @error('resolution_notes') <span class="error">{{ $message }}</span> @enderror
                        <button class="btn" data-confirm="Mark this ticket as resolved with the recorded resolution note?">Mark Resolved</button>
                    </form>
                @elseif($hasPendingReopenRequest)
                    <div class="ticket-action-form">
                        <p class="ticket-sidebar-note"><strong>Reopen reason:</strong> {{ $ticket->reopen_reason }}</p>
                        <form method="post" action="{{ route('admin.tickets.reopen-request.approve', $ticket) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn">Accept Reopen Request</button>
                        </form>
                    </div>
                @elseif(! $isReadOnly)
                    <form method="post" action="{{ route('admin.tickets.update', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="admin_action" value="close">
                        <input type="hidden" name="assigned_staff_id" value="{{ $ticket->assigned_staff_id }}">
                        <button class="btn danger" {{ $isResolved ? '' : 'disabled' }}>Close Ticket</button>
                        @unless($isResolved)
                            <p class="ticket-sidebar-note">Close becomes available after the technician resolves the ticket.</p>
                        @endunless
                    </form>
                @else
                    <p class="ticket-sidebar-note">Ticket {{ $isCancelled ? 'cancelled' : 'closed' }}. No further status actions are available.</p>
                @endif
            </section>

            <details class="ticket-detail-card ticket-sidebar-card ticket-history-card">
                <summary>
                    <span>Assignment history</span>
                    <span class="ticket-history-count">{{ $ticket->assignmentEvents->count() }}</span>
                </summary>
                <div class="ticket-history-list">
                @forelse($ticket->assignmentEvents as $event)
                    <div class="ticket-sidebar-note">
                        <strong>{{ $event->fromStaff?->name ?? 'Queue' }} &rarr; {{ $event->toStaff?->name ?? 'Queue' }}</strong>
                        <p>{{ $event->reason }}</p>
                        <small>
                            {{ $event->created_at->displayDateTime('M d, Y h:i A') }}
                            @if($event->actor) &middot; by {{ $event->actor->name }} @endif
                        </small>
                    </div>
                @empty
                    <p class="ticket-sidebar-note">No assignment changes recorded yet.</p>
                @endforelse
                </div>
            </details>

        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-staff-assignment-form]').forEach(function (form) {
            const select = form.querySelector('[data-staff-select]');
            const reason = form.querySelector('[name="assignment_reason"]');
            const warningBox = form.querySelector('[data-assignment-warning-box]');
            const warningText = form.querySelector('[data-assignment-warning-text]');
            const warningConfirm = form.querySelector('[data-assignment-override]');
            const progressBox = form.querySelector('[data-progress-transfer-box]');
            const progressConfirm = form.querySelector('[data-progress-transfer]');
            const takeover = form.querySelector('[data-take-over-ticket]');
            const currentAssignee = form.dataset.currentAssignee || '';
            const inProgress = form.dataset.ticketStatus === 'in_progress';

            const refreshAssignmentControls = function () {
                const selected = select?.selectedOptions[0];
                const changed = (select?.value || '') !== currentAssignee;
                const warning = changed ? (selected?.dataset.assignmentWarning || '') : '';

                if (reason) {
                    reason.required = changed && currentAssignee !== '';
                }

                if (warningBox) warningBox.hidden = warning === '';
                if (warningText) warningText.textContent = warning;
                if (warningConfirm) {
                    warningConfirm.required = warning !== '';
                    if (warning === '') warningConfirm.checked = false;
                }

                if (progressBox) progressBox.hidden = !(inProgress && changed);
                if (progressConfirm) {
                    progressConfirm.required = inProgress && changed;
                    if (!inProgress || !changed) progressConfirm.checked = false;
                }
            };

            select?.addEventListener('change', refreshAssignmentControls);
            takeover?.addEventListener('click', function () {
                select.value = takeover.dataset.currentAdmin || '';
                refreshAssignmentControls();
                reason?.focus();
            });

            refreshAssignmentControls();
        });
    });
</script>
@endpush
