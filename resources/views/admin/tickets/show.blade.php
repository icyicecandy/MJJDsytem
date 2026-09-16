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
    $assignedStaff = optional($ticket->technician)->name ?? 'Unassigned Queue';
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for customer confirmation',
        \App\Models\Ticket::STATUS_CANCELLATION_REQUESTED => 'Customer requested cancellation',
        \App\Models\Ticket::STATUS_CLOSED => $hasPendingReopenRequest ? 'Customer requested reopening' : 'Ticket closed',
        \App\Models\Ticket::STATUS_CANCELLED => 'Ticket cancelled',
        \App\Models\Ticket::STATUS_IN_PROGRESS => 'Technician is working on this ticket',
        default => 'Waiting for work to begin',
    };
@endphp

<div class="ticket-detail-page">
    <div class="ticket-detail-backline">
        <a href="{{ route('admin.tickets.index') }}" class="btn secondary">Back to Tickets</a>
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

                <p class="ticket-detail-description">{{ $ticket->description }}</p>

                <x-photo-gallery
                    :photos="$ticket->photoGalleryItems()"
                    title="Ticket photos"
                    empty="No ticket attachment uploaded."
                    button-text="View attachment"
                />
            </section>

            <section class="ticket-detail-card">
                <div class="ticket-detail-section-head">
                    <div>
                        <h2>Conversation</h2>
                        <p>Message history between the customer and support team.</p>
                    </div>
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
                                || str_starts_with($messageText, 'Admin rejected cancellation request');
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
                        <h2>Reply</h2>
                        <p>{{ $isReadOnly ? 'Replies are disabled because this ticket is closed or cancelled.' : 'Send an update to everyone on this ticket.' }}</p>
                    </div>
                </div>

                @if(! $isReadOnly)
                    <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form">
                        @csrf
                        <textarea name="message" rows="5" placeholder="Type your reply..."></textarea>
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
                <h2>Ticket Info</h2>
                <dl class="ticket-meta-list">
                    <div>
                        <dt>Customer</dt>
                        <dd>{{ $ticket->user->name }}</dd>
                    </div>
                    <div>
                        <dt>Submitted</dt>
                        <dd>{{ $ticket->created_at->displayDateTime('F d, Y h:i A') }}</dd>
                    </div>
                    <div>
                        <dt>Urgency</dt>
                        <dd><span class="badge urgency-{{ $ticket->displayUrgencyClass() }}">{{ $ticket->displayUrgencyLabel() }}</span></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></dd>
                    </div>
                    <div>
                        <dt>Assigned Staff</dt>
                        <dd>{{ $assignedStaff }}</dd>
                    </div>
                    <div>
                        <dt>Appointment ({{ config('app.timezone') }})</dt>
                        <dd>{{ $ticket->appointmentLabel() }}</dd>
                    </div>
                    @if(filled($ticket->appointment_change_reason))
                        <div>
                            <dt>Reason for appointment change</dt>
                            <dd style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $ticket->appointment_change_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Assignment</h2>
                @if(! $isReadOnly && ! $isCancellationRequested && ! $isResolved)
                    <form method="post" action="{{ route('admin.tickets.update', $ticket) }}" class="ticket-action-form" data-ticket-appointment-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="admin_action" value="save">
                        <label for="technician_id">Technician</label>
                        <select id="technician_id" name="technician_id" class="technician-load-select">
                            <option value="">Unassigned Queue</option>
                            @if($ticket->technician_id && ! $technicians->contains('id', $ticket->technician_id))
                                <option value="{{ $ticket->technician_id }}" selected disabled>Current technician unavailable - choose another</option>
                            @endif
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" @selected(old('technician_id', $ticket->technician_id) == $tech->id)>
                                    {{ $tech->name }} ({{ $tech->active_ticket_load }} active)
                                </option>
                            @endforeach
                        </select>
                        @error('technician_id') <span class="error">{{ $message }}</span> @enderror
                        @include('admin.tickets.appointment-fields', ['appointmentPrefix' => 'ticketAppointment', 'appointmentTicket' => $ticket])
                        <button class="btn">{{ $ticket->technician_id ? 'Update Assignment' : 'Assign Technician' }}</button>
                    </form>
                @else
                    <p class="ticket-sidebar-note">Assignment is locked for this ticket status.</p>
                @endif
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Status Actions</h2>
                @if($isCancellationRequested)
                    <div class="ticket-action-form">
                        <p class="ticket-sidebar-note">Customer requested cancellation. Review the request before any other status update.</p>
                        <form method="post" action="{{ route('admin.tickets.cancellation.approve', $ticket) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn danger">Approve Cancellation</button>
                        </form>
                        <form method="post" action="{{ route('admin.tickets.cancellation.reject', $ticket) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn secondary">Reject Cancellation</button>
                        </form>
                    </div>
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
                        <input type="hidden" name="technician_id" value="{{ $ticket->technician_id }}">
                        <button class="btn danger" {{ $isResolved ? '' : 'disabled' }}>Close Ticket</button>
                        @unless($isResolved)
                            <p class="ticket-sidebar-note">Close becomes available after the technician resolves the ticket.</p>
                        @endunless
                    </form>
                @else
                    <p class="ticket-sidebar-note">Ticket {{ $isCancelled ? 'cancelled' : 'closed' }}. No further status actions are available.</p>
                @endif
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Workflow</h2>
                <p class="ticket-sidebar-note">{{ $statusHelper }}.</p>
                <p class="ticket-sidebar-note">Admins manage assignment and final closure. Technicians handle start and resolution updates.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
