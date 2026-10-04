@php
    use Illuminate\Support\Str;

    $isCustomer = $isCustomer ?? false;
    $statusLabel = $statusLabel ?? ($chatState['status_label'] ?? 'MJJD AI Assistant');
    $mode = $mode ?? ($chatState['mode'] ?? 'ai_active');
    $quickReplies = $quickReplies ?? [];
    $panelActions = $panelActions ?? null;
    $contextItems = $contextItems ?? [];
    $contextTitle = $contextTitle ?? null;
    $panelTitle = $panelTitle ?? ($isCustomer ? ($chatState['identity_name'] ?? 'MJJD AI Assistant') : $user->name);
    $panelMeta = $panelMeta ?? null;
    $canSendMessages = $canSendMessages ?? true;
    $composerNotice = $composerNotice ?? null;
    $backUrl = $backUrl ?? null;
    $showQuickReplies = $isCustomer && !empty($quickReplies) && in_array($mode, ['ai_active', 'waiting_for_human', 'staff_inactive'], true);
    $panelInitials = $isCustomer && Str::contains(Str::lower($panelTitle), 'ai')
        ? 'AI'
        : collect(preg_split('/\s+/', trim((string) $panelTitle)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    $statusLower = Str::lower($statusLabel);
    $statusTone = Str::contains($statusLower, ['attention', 'urgent'])
        ? 'chat-ui__badge--red'
        : (Str::contains($statusLower, ['waiting'])
            ? 'chat-ui__badge--gray'
            : (Str::contains($statusLower, ['assigned', 'connected'])
                ? 'chat-ui__badge--blue'
                : (Str::contains($statusLower, ['active'])
                    ? 'chat-ui__badge--yellow'
                    : (Str::contains($statusLower, ['closed', 'resolved']) ? 'chat-ui__badge--green' : 'chat-ui__badge--gray'))));
@endphp

<section class="chat-ui__panel">
    <header class="chat-ui__panel-head">
        <div class="chat-ui__panel-identity">
            @if($backUrl)
                <a href="{{ $backUrl }}" class="chat-ui__mobile-back" aria-label="Back to conversations">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                </a>
            @endif
            <span class="chat-ui__identity-avatar" aria-hidden="true">{{ $panelInitials ?: 'C' }}</span>
            <div>
                <h2>{{ $panelTitle }}</h2>
                @if($panelMeta)
                    <p>{{ $panelMeta }}</p>
                @endif
            </div>
        </div>

        <div class="chat-ui__panel-actions">
            <div class="chat-ui__panel-status">
                <span id="chat-connection-status" class="chat-ui__connection-status is-online" role="status" aria-live="polite"><span aria-hidden="true"></span>Connected</span>
                <span id="chat-status-badge" class="chat-ui__badge {{ $statusTone }}">{{ $statusLabel }}</span>
            </div>

            @if(! $isCustomer && !empty($contextItems))
                <button type="button" class="chat-ui__button chat-ui__button--secondary chat-ui__context-toggle" data-toggle-chat-context aria-expanded="true">Details</button>
            @endif

            @if(!empty($panelActions))
                {!! $panelActions !!}
            @endif
        </div>
    </header>

    <div class="chat-ui__panel-grid {{ $isCustomer ? 'is-customer' : 'is-staff' }}">
        <div class="chat-ui__thread-card">
            <div id="chat-window" class="chat-ui__messages" aria-label="Conversation messages" aria-live="polite">
                @if($messages->isEmpty())
                    <div class="chat-ui__system-banner">
                        @if(in_array($mode, ['assigned', 'active_human'], true))
                            A support staff member is assigned to this chat.
                        @elseif(in_array($mode, ['waiting_for_human', 'staff_inactive'], true))
                            Your message is in the queue for human support.
                        @else
                            Start the conversation and the AI assistant will respond first.
                        @endif
                    </div>
                @endif

                @foreach($messages as $msg)
                    @php
                        if ($msg->message_type === 'internal_note') {
                            continue;
                        }

                        $self = auth()->id() === $msg->from_user_id;
                        $bubbleClass = $msg->sender_type === 'system'
                            ? 'system'
                            : ($self
                                ? 'self'
                                : ($msg->sender_type === 'ai'
                                    ? 'ai'
                                    : ($msg->sender_type === 'customer' ? 'customer' : 'agent')));
                        $avatarLabel = $msg->sender_type === 'ai'
                            ? 'AI'
                            : collect(preg_split('/\s+/', trim($msg->senderLabel())))
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
                                ->implode('');
                        $attachmentItems = $msg->attachmentItems();
                    @endphp

                    <article class="chat-ui__message-row {{ $bubbleClass }}" data-message-id="{{ $msg->id }}">
                        @if($msg->sender_type === 'system')
                            <div class="chat-ui__system-banner">{{ $msg->message }}</div>
                        @else
                            @if(! $self)
                                <span class="chat-ui__avatar {{ $bubbleClass }}" aria-hidden="true">{{ $avatarLabel ?: 'U' }}</span>
                            @endif
                            <div class="chat-ui__message-bubble {{ $bubbleClass }}">
                                <div class="chat-ui__message-meta">
                                    <strong>{{ $msg->senderLabel() }}</strong>
                                    <span>{{ $msg->created_at->displayTime() }}</span>
                                </div>
                                <div class="chat-ui__message-text">{{ $msg->message }}</div>
                                @foreach($attachmentItems as $attachment)
                                    @if(! empty($attachment['url']))
                                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer" class="chat-ui__message-attachment">
                                            @if(! empty($attachment['is_image']))
                                                <img src="{{ $attachment['url'] }}" alt="{{ $attachment['original_name'] }}">
                                            @endif
                                            <span>{{ $attachment['original_name'] }}</span>
                                        </a>
                                    @elseif(! empty($attachment['missing']))
                                        <div class="chat-ui__message-attachment is-missing">
                                            <span>Uploaded file not found.</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($isCustomer)
                <div id="support-quick-replies" class="chat-ui__quick-replies {{ $showQuickReplies ? '' : 'is-hidden' }}">
                    @foreach($quickReplies as $quickReply)
                        <button type="button" class="chat-ui__quick-reply" data-quick-reply="{{ $quickReply }}">{{ $quickReply }}</button>
                    @endforeach
                </div>
            @endif

            @if($canSendMessages)
                <form id="chat-form" method="POST" action="{{ $sendRoute }}" enctype="multipart/form-data" class="chat-ui__composer">
                    @csrf
                    <div class="chat-ui__composer-fields">
                        <div class="chat-ui__composer-input">
                            <label for="chat_attachment" class="chat-ui__attachment-button" aria-label="Attach images" title="Attach images">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 1 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"></path></svg>
                                <span class="sr-only">Attach images</span>
                            </label>
                            <input id="chat_attachment" type="file" name="chat_attachments[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <label class="sr-only" for="chat_message">{{ $isCustomer ? 'Write your message' : 'Reply to the customer' }}</label>
                            <textarea
                                id="chat_message"
                                name="message"
                                rows="1"
                                data-chat-autogrow
                                placeholder="{{ $isCustomer ? 'Write your message...' : 'Reply to the customer...' }}"
                            ></textarea>
                        </div>
                        <div class="chat-ui__attachment-preview" data-chat-attachment-preview aria-live="polite"></div>
                        <div class="chat-ui__composer-toolbar" data-chat-attachment-meta hidden>
                            <span class="chat-ui__attachment-name" data-chat-attachment-name></span>
                        </div>
                    </div>
                    <button type="submit" class="chat-ui__button chat-ui__button--primary chat-ui__send-button">
                        <span>Send</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path></svg>
                    </button>
                </form>
            @elseif($composerNotice)
                <div class="chat-ui__composer-notice">{{ $composerNotice }}</div>
            @endif
        </div>

        @if(! $isCustomer && !empty($contextItems))
            <details class="chat-ui__context-card" open>
                <summary class="chat-ui__context-summary">
                    <h3>{{ $contextTitle ?: 'Customer details' }}</h3>
                    <span aria-hidden="true"></span>
                </summary>

                <div class="chat-ui__context-list">
                    @foreach($contextItems as $item)
                        @if(!empty($item['value']))
                            <div class="chat-ui__context-item {{ !empty($item['full']) ? 'is-full' : '' }}">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</section>