@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
@php
    $isOpen = $ticket->isOpenForWork();
    $isInProgress = $ticket->status === \App\Models\Ticket::STATUS_IN_PROGRESS;
    $isResolved = $ticket->status === \App\Models\Ticket::STATUS_RESOLVED;
    $isClosed = $ticket->status === \App\Models\Ticket::STATUS_CLOSED;
    $assignedStaff = optional($ticket->technician)->name ?? 'Unassigned Queue';
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for customer confirmation',
        \App\Models\Ticket::STATUS_CLOSED => 'Ticket closed',
        \App\Models\Ticket::STATUS_IN_PROGRESS => 'Ready to resolve once the work is complete',
        default => 'Start work when you begin handling this ticket',
    };
@endphp

<div class="ticket-detail-page">
    <div class="ticket-detail-backline">
        <a href="{{ route('technician.tickets.index') }}" class="btn secondary">Back to Tickets</a>
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

                <x-upload-preview
                    :path="$ticket->photo_path"
                    label="Ticket photo"
                    empty="No ticket attachment uploaded."
                    button-text="View attachment"
                    image-alt="Ticket issue photo"
                />
            </section>

            <section class="ticket-detail-card">
                <div class="ticket-detail-section-head">
                    <div>
                        <h2>Conversation</h2>
                        <p>Keep customer updates and internal workflow events in one timeline.</p>
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
                                || str_starts_with($messageText, 'Ticket reopened by');
                            $messageClass = $isSystemMessage ? 'is-system' : ($isCustomerMessage ? 'is-customer' : 'is-staff');
                        @endphp
                        <article class="ticket-message-row {{ $messageClass }}">
                            <div class="ticket-message-bubble">
                                <div class="ticket-message-meta">
                                    <strong>{{ optional($msg->user)->name ?? 'System' }}</strong>
                                    <span>{{ $msg->created_at->displayDateTime('M d, Y h:i A') }}</span>
                                </div>
                                <p>{{ $messageText }}</p>
                                <x-upload-preview
                                    :path="$msg->attachment_path"
                                    label="Message attachment"
                                    button-text="View attachment"
                                    :show-empty="false"
                                    class="ticket-message-attachment"
                                />
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
                        <p>{{ $isClosed ? 'Replies are disabled because this ticket is closed.' : 'Send a clear update to the customer.' }}</p>
                    </div>
                </div>

                @if(! $isClosed)
                    <form method="POST" action="{{ route('technician.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form">
                        @csrf
                        <textarea name="message" rows="5" placeholder="Type your reply..."></textarea>
                        <label for="technician_ticket_attachment">Attachment <span class="field-badge optional">Optional</span></label>
                        <input id="technician_ticket_attachment" type="file" name="ticket_attachment" accept=".jpg,.jpeg,.png,.webp">
                        @error('ticket_attachment')
                            <p class="error">{{ $message }}</p>
                        @enderror
                        <div class="ticket-form-actions">
                            <button class="btn">Send Reply</button>
                        </div>
                    </form>
                @else
                    <div class="ticket-reply-disabled">
                        <textarea rows="4" disabled placeholder="Ticket closed"></textarea>
                        <div class="ticket-form-actions">
                            <button class="btn" disabled>Send Reply</button>
                        </div>
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
                </dl>
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Work Action</h2>
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
                @elseif($isResolved)
                    <p class="ticket-sidebar-note">No technician action is available for a resolved ticket.</p>
                @else
                    <p class="ticket-sidebar-note">Ticket closed. No work actions are available.</p>
                @endif
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Workflow</h2>
                <p class="ticket-sidebar-note">{{ $statusHelper }}.</p>
                <p class="ticket-sidebar-note">Only start tickets when work begins, then resolve with concise notes once the issue is handled.</p>
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
