@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
@php
    $isOpen = $ticket->isOpenForWork();
    $isInProgress = $ticket->status === \App\Models\Ticket::STATUS_IN_PROGRESS;
    $isCancellationRequested = $ticket->isCancellationRequested();
    $isResolved = $ticket->status === \App\Models\Ticket::STATUS_RESOLVED;
    $isClosed = $ticket->status === \App\Models\Ticket::STATUS_CLOSED;
    $isCancelled = $ticket->isCancelled();
    $isReadOnly = $ticket->isReadOnly();
    $assignedStaff = optional($ticket->technician)->name ?? 'Waiting for Assignment';
    $customerName = auth()->user()->name;
    $showReopenFields = $errors->has('reason') || old('reason');
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for your confirmation',
        \App\Models\Ticket::STATUS_CANCELLATION_REQUESTED => 'Cancellation request is waiting for admin review',
        \App\Models\Ticket::STATUS_CLOSED => 'Ticket closed',
        \App\Models\Ticket::STATUS_CANCELLED => 'Ticket cancelled',
        \App\Models\Ticket::STATUS_IN_PROGRESS => 'Support is working on this ticket',
        default => 'Waiting for support review',
    };
@endphp

<div class="ticket-detail-page">
    <div class="ticket-detail-backline">
        <a href="{{ route('customer.tickets.index') }}" class="btn secondary">Back to Tickets</a>
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
                        <p>Your conversation with MJJD Telecom support.</p>
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
                        <p>{{ $isReadOnly ? 'Replies are disabled because this ticket is closed or cancelled.' : 'Add more details or respond to support.' }}</p>
                    </div>
                </div>

                @if(! $isReadOnly)
                    <form method="POST" action="{{ route('customer.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form">
                        @csrf
                        <textarea name="message" rows="5" placeholder="Type your reply..."></textarea>
                        <label for="customer_ticket_attachment">Attachments <span class="field-badge optional">Optional</span></label>
                        <input id="customer_ticket_attachment" type="file" name="ticket_attachments[]" accept=".jpg,.jpeg,.png,.webp" multiple>
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
                        <dd>{{ $customerName }}</dd>
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
                        <dt>Response target ({{ config('app.timezone') }})</dt>
                        <dd>{{ $ticket->responseTargetLabel() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Status Actions</h2>
                @if($isOpen)
                    <form method="POST" action="{{ route('customer.tickets.cancel', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <button class="btn danger">Cancel Ticket</button>
                    </form>
                    <p class="ticket-sidebar-note">You can cancel this ticket because support work has not started yet.</p>
                @elseif($isInProgress)
                    <form method="POST" action="{{ route('customer.tickets.cancellation.request', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <button class="btn secondary">Request Cancellation</button>
                    </form>
                    <p class="ticket-sidebar-note">Support is already working on this ticket, so cancellation requires admin review.</p>
                @elseif($isCancellationRequested)
                    <p class="ticket-sidebar-note">Cancellation request is waiting for admin review.</p>
                @elseif($isResolved)
                    <form method="POST" action="{{ route('customer.tickets.close', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <button class="btn">Close Ticket</button>
                    </form>
                    <form method="POST" action="{{ route('customer.tickets.reopen', $ticket) }}" class="ticket-action-form ticket-reopen-form" data-ticket-reopen-form>
                        @csrf
                        @method('PATCH')
                        <div data-ticket-reopen-fields {{ $showReopenFields ? '' : 'hidden' }}>
                            <label for="reason">Reopen reason</label>
                            <textarea id="reason" name="reason" rows="4" placeholder="Tell us what still needs attention..." {{ $showReopenFields ? 'required' : '' }}>{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="error">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="button" class="btn secondary" data-ticket-reopen-toggle {{ $showReopenFields ? 'hidden' : '' }}>Reopen Ticket</button>
                        <button class="btn secondary" data-ticket-reopen-submit {{ $showReopenFields ? '' : 'hidden' }}>Submit Reopen Request</button>
                    </form>
                    <p class="ticket-sidebar-note">Close the ticket if the issue is solved, or reopen it if support still needs to work on it.</p>
                @elseif($isClosed || $isCancelled)
                    <p class="ticket-sidebar-note">This ticket is read-only.</p>
                @else
                    <p class="ticket-sidebar-note">Close becomes available after support marks the ticket resolved.</p>
                @endif
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Workflow</h2>
                <p class="ticket-sidebar-note">{{ $statusHelper }}.</p>
                <p class="ticket-sidebar-note">Open tickets can be cancelled before work starts. In-progress cancellation requests are reviewed by admin.</p>
            </section>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reopenForm = document.querySelector('[data-ticket-reopen-form]');
        if (!reopenForm) {
            return;
        }

        const fields = reopenForm.querySelector('[data-ticket-reopen-fields]');
        const textarea = reopenForm.querySelector('textarea[name="reason"]');
        const toggle = reopenForm.querySelector('[data-ticket-reopen-toggle]');
        const submit = reopenForm.querySelector('[data-ticket-reopen-submit]');

        if (toggle && fields && submit) {
            toggle.addEventListener('click', function () {
                fields.hidden = false;
                toggle.hidden = true;
                submit.hidden = false;
                if (textarea) {
                    textarea.required = true;
                    textarea.focus();
                }
            });
        }
    });
</script>
@endpush
