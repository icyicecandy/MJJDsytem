@extends('layouts.app')

@section('title', $ticket->publicReference())

@section('content')
@php
    $isResolved = $ticket->status === \App\Models\Ticket::STATUS_RESOLVED;
    $isClosed = $ticket->status === \App\Models\Ticket::STATUS_CLOSED;
    $assignedStaff = optional($ticket->technician)->name ?? 'Unassigned Queue';
    $statusHelper = match ($ticket->status) {
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for customer confirmation',
        \App\Models\Ticket::STATUS_CLOSED => 'Ticket closed',
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
                        <p>{{ $isClosed ? 'Replies are disabled because this ticket is closed.' : 'Send an update to everyone on this ticket.' }}</p>
                    </div>
                </div>

                @if(! $isClosed)
                    <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="ticket-reply-form">
                        @csrf
                        <textarea name="message" rows="5" placeholder="Type your reply..."></textarea>
                        <label for="admin_ticket_attachment">Attachment <span class="field-badge optional">Optional</span></label>
                        <input id="admin_ticket_attachment" type="file" name="ticket_attachment" accept=".jpg,.jpeg,.png,.webp">
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
                <h2>Assignment</h2>
                @if(! $isClosed)
                    <form method="post" action="{{ route('admin.tickets.update', $ticket) }}" class="ticket-action-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="admin_action" value="save">
                        <label for="technician_id">Technician</label>
                        <select id="technician_id" name="technician_id" class="technician-load-select">
                            <option value="">Unassigned Queue</option>
                            @foreach($technicians as $tech)
                                <option value="{{ $tech->id }}" {{ $ticket->technician_id == $tech->id ? 'selected' : '' }}>
                                    {{ $tech->name }} ({{ $tech->active_ticket_load }} active)
                                </option>
                            @endforeach
                        </select>
                        <button class="btn">{{ $ticket->technician_id ? 'Update Assignment' : 'Assign Technician' }}</button>
                    </form>
                @else
                    <p class="ticket-sidebar-note">Assignment is locked because this ticket is closed.</p>
                @endif
            </section>

            <section class="ticket-detail-card ticket-sidebar-card">
                <h2>Status Actions</h2>
                @if(! $isClosed)
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
                    <p class="ticket-sidebar-note">Ticket closed. No further status actions are available.</p>
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
