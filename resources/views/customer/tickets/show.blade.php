@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
@php
    $isResolved = $ticket->status === \App\Models\Ticket::STATUS_RESOLVED;
    $isClosed = $ticket->status === \App\Models\Ticket::STATUS_CLOSED;
    $assignedStaff = optional($ticket->technician)->name ?? 'Waiting for Assignment';
    $customerName = auth()->user()->name;
    $showReopenFields = $errors->has('reason') || old('reason');
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for your confirmation',
        \App\Models\Ticket::STATUS_CLOSED => $ticket->hasPendingReopenRequest() ? 'Reopen request waiting for admin review' : 'Ticket closed',
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
                        <p>{{ $isClosed ? 'Replies are disabled because this ticket is closed.' : 'Add more details or respond to support.' }}</p>
                    </div>
                </div>

                @if(! $isClosed)
                    <form method="POST" action="{{ route('customer.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form">
                        @csrf
                        <textarea name="message" rows="5" placeholder="Type your reply..."></textarea>
                        <label for="customer_ticket_attachment">Attachment <span class="field-badge optional">Optional</span></label>
                        <input id="customer_ticket_attachment" type="file" name="ticket_attachment" accept=".jpg,.jpeg,.png,.webp">
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
                </dl>
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Status Actions</h2>
                @if($isResolved)
                    <form method="POST" action="{{ route('customer.tickets.close', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <button class="btn">Close Ticket</button>
                    </form>
                    <p class="ticket-sidebar-note">If the issue is solved, close the ticket. Reopen requests are available after a ticket is closed.</p>
                @elseif($isClosed && $ticket->hasPendingReopenRequest())
                    <p class="ticket-sidebar-note">Your reopen request is waiting for admin review.</p>
                    <p class="ticket-sidebar-note"><strong>Reason:</strong> {{ $ticket->reopen_reason }}</p>
                @elseif($isClosed)
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
                @else
                    <p class="ticket-sidebar-note">Close becomes available after support marks the ticket resolved.</p>
                @endif
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Workflow</h2>
                <p class="ticket-sidebar-note">{{ $statusHelper }}.</p>
                <p class="ticket-sidebar-note">When the issue is resolved, close the ticket. If a closed ticket needs more work, submit a reopen reason for admin review.</p>
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
