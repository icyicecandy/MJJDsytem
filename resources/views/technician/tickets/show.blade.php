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
    $assignedStaff = optional($ticket->assignedStaff)->name ?? 'Unassigned Queue';
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for customer confirmation',
        \App\Models\Ticket::STATUS_CANCELLATION_REQUESTED => 'Customer requested cancellation. Waiting for admin decision',
        \App\Models\Ticket::STATUS_CLOSED => 'Ticket closed',
        \App\Models\Ticket::STATUS_CANCELLED => 'Ticket cancelled',
        \App\Models\Ticket::STATUS_IN_PROGRESS => 'Ready to resolve once the work is complete',
        default => 'Start work when you begin handling this ticket',
    };
@endphp

<div class="ticket-detail-page">
    <div class="ticket-detail-backline">
        <a href="{{ route('technician.tickets.index') }}" class="btn secondary">&larr; Back to Tickets</a>
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
                        <p>{{ $isReadOnly ? 'Replies are disabled because this ticket is closed or cancelled.' : 'Send a clear update to the customer.' }}</p>
                    </div>
                </div>

                @if(! $isReadOnly)
                    <form method="POST" action="{{ route('technician.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form" data-unsaved-warning>
                        @csrf
                        <textarea name="message" rows="3" placeholder="Write a clear update for the customer..."></textarea>
                        <label for="technician_ticket_attachment">Attachments <span class="field-badge optional">Optional</span></label>
                        <input id="technician_ticket_attachment" type="file" name="ticket_attachments[]" accept=".jpg,.jpeg,.png,.webp" multiple>
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
                </dl>
            </section>

            <section class="ticket-detail-card ticket-sidebar-card ticket-status-card">
                <h2>Work status</h2>
                <p class="ticket-workflow-state">{{ $statusHelper }}.</p>
                @if($isOpen)
                    <form method="POST" action="{{ route('technician.tickets.start', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <button class="btn">Start Work</button>
                    </form>
                @elseif($isInProgress)
                    <form method="POST" action="{{ route('technician.tickets.resolve', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <label for="resolution_notes">Resolution notes</label>
                        <textarea id="resolution_notes" name="resolution_notes" rows="4" required placeholder="Summarize the fix...">{{ old('resolution_notes') }}</textarea>
                        @error('resolution_notes')
                            <p class="error">{{ $message }}</p>
                        @enderror
                        <button class="btn" data-submit-once>Mark as Resolved</button>
                    </form>
                @elseif($isCancellationRequested)
                    <p class="ticket-sidebar-note">Customer requested cancellation. Waiting for admin decision.</p>
                @elseif($isResolved)
                    <p class="ticket-sidebar-note">No technician action is available for a resolved ticket.</p>
                @else
                    <p class="ticket-sidebar-note">Ticket {{ $isCancelled ? 'cancelled' : 'closed' }}. No work actions are available.</p>
                @endif
            </section>

        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form [data-submit-once]').forEach(function (button) {
            button.form.addEventListener('submit', function () {
                button.disabled = true;
                button.textContent = 'Saving...';
            });
        });
    });
</script>
@endpush
