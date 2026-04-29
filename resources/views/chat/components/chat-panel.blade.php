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
    $showQuickReplies = $isCustomer && !empty($quickReplies) && in_array($mode, ['ai_active', 'waiting_for_human', 'staff_inactive'], true);
@endphp

<section class="chat-ui__panel">
    <div class="chat-ui__panel-head">
        <div>
            <h2>{{ $panelTitle }}</h2>
            @if($panelMeta)
                <p>{{ $panelMeta }}</p>
            @endif
        </div>

        <div class="chat-ui__panel-actions">
            <span id="chat-status-badge" class="chat-ui__badge {{ Str::contains(Str::lower($statusLabel), ['attention']) ? 'chat-ui__badge--red' : (Str::contains(Str::lower($statusLabel), ['waiting']) ? 'chat-ui__badge--gray' : (Str::contains(Str::lower($statusLabel), ['assigned', 'connected']) ? 'chat-ui__badge--blue' : (Str::contains(Str::lower($statusLabel), ['active']) ? 'chat-ui__badge--yellow' : (Str::contains(Str::lower($statusLabel), ['closed']) ? 'chat-ui__badge--green' : 'chat-ui__badge--gray')))) }}">{{ $statusLabel }}</span>

            @if(!empty($panelActions))
                {!! $panelActions !!}
            @endif
        </div>
    </div>

    <div class="chat-ui__panel-grid {{ $isCustomer ? 'is-customer' : 'is-staff' }}">
        <div class="chat-ui__thread-card">
            <div id="chat-window" class="chat-ui__messages">
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

                        $self = $msg->sender_type === 'customer' && auth()->id() === $msg->from_user_id;
                        $bubbleClass = $msg->message_type === 'internal_note'
                            ? 'internal-note'
                            : ($msg->sender_type === 'system'
                                ? 'system'
                                : ($self ? 'self' : ($msg->sender_type === 'ai' ? 'ai' : 'agent')));
                        $avatarLabel = $msg->sender_type === 'ai'
                            ? 'AI'
                            : Str::upper(Str::substr($msg->senderLabel(), 0, 1));
                        $attachmentPath = $msg->attachmentPath();
                        $attachmentUrl = $msg->attachmentUrl();
                        $attachmentMissing = $attachmentPath && ! $msg->attachmentExists();
                        $attachmentLabel = $msg->attachmentOriginalName() ?: 'View image';
                    @endphp

                    <article class="chat-ui__message-row {{ $bubbleClass }}">
                        @if($msg->sender_type === 'system')
                            <div class="chat-ui__system-banner">{{ $msg->message }}</div>
                        @else
                            @if($isCustomer && ! $self)
                                <span class="chat-ui__avatar {{ $bubbleClass }}">{{ $avatarLabel }}</span>
                            @endif
                            <div class="chat-ui__message-bubble {{ $bubbleClass }}">
                                <div class="chat-ui__message-meta">
                                    <strong>{{ $msg->senderLabel() }}</strong>
                                    <span>{{ $msg->created_at->displayTime() }}</span>
                                </div>
                                <div class="chat-ui__message-text">{{ $msg->message }}</div>
                                @if($attachmentUrl)
                                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="chat-ui__message-attachment">
                                        @if($msg->hasImageAttachment())
                                            <img src="{{ $attachmentUrl }}" alt="{{ $attachmentLabel }}">
                                        @endif
                                        <span>{{ $attachmentLabel }}</span>
                                    </a>
                                @elseif($attachmentMissing)
                                    <div class="chat-ui__message-attachment is-missing">
                                        <span>Uploaded file not found.</span>
                                    </div>
                                @endif
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
                        <textarea name="message" rows="2" placeholder="{{ $isCustomer ? 'Write your message' : 'Reply to the customer' }}"></textarea>
                        <div class="chat-ui__attachment-control">
                            <label for="chat_attachment" class="chat-ui__attachment-button">Attach image</label>
                            <input id="chat_attachment" type="file" name="chat_attachment" accept=".jpg,.jpeg,.png,.webp">
                            <span data-chat-attachment-name>No image selected</span>
                        </div>
                    </div>
                    <button type="submit" class="chat-ui__button chat-ui__button--primary">Send</button>
                </form>
            @elseif($composerNotice)
                <div class="chat-ui__composer-notice">{{ $composerNotice }}</div>
            @endif
        </div>

        @if(! $isCustomer && !empty($contextItems))
            <aside class="chat-ui__context-card">
                @if($contextTitle)
                    <h3>{{ $contextTitle }}</h3>
                @endif

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
            </aside>
        @endif
    </div>
</section>
