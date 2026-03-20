@extends('layouts.app')

@section('title','Support Chat')

@section('content')
@php
    $isCustomer = auth()->user()->role === 'customer';
    $isRoleChat = ! $isCustomer;
    $sendRoute = $isRoleChat ? route('role.chat.send', ['role' => auth()->user()->role, 'user' => $user]) : route('chat.send', $user);
    $takeoverRoute = $isRoleChat ? route('role.chat.takeover', ['role' => auth()->user()->role, 'user' => $user]) : null;
    $assignRoute = $isRoleChat && auth()->user()->isAdmin() ? route('role.chat.assign', ['role' => auth()->user()->role, 'user' => $user]) : null;
    $availabilityRoute = $isRoleChat ? route('role.chat.availability', ['role' => auth()->user()->role]) : null;
    $mode = $chatState['mode'] ?? 'ai_active';
    $statusLabel = $chatState['status_label'] ?? 'MJJD AI Assistant';
    $statusClass = $chatState['status_class'] ?? 'info';
    $humanModes = ['assigned', 'active_human'];
    $assignableStaff = $assignableStaff ?? collect();
    $canAdminTakeOver = $isRoleChat && auth()->user()->isAdmin() && (optional($chatState['assigned_staff'])->id !== auth()->id() || ! in_array($mode, $humanModes, true));
@endphp

<div class="support-chat-page {{ $isCustomer ? 'support-chat-page-customer' : 'support-chat-page-staff' }}">
    <section class="card support-chat-header-card support-chat-hero-card">
        <div class="support-chat-hero-copy">
            <span class="application-status-kicker">{{ $isCustomer ? 'Live Support' : 'Support Workspace' }}</span>
            <h1>{{ $isCustomer ? 'MJJD Support Chat' : ($user->name . ' Conversation') }}</h1>
            <p>
                @if($isCustomer)
                    @if(in_array($mode, $humanModes, true))
                        You are currently connected to a human support staff member.
                    @elseif($mode === 'waiting_for_human')
                        MJJD AI could not fully resolve the concern. Your conversation is waiting in the human support queue.
                    @else
                        You are currently chatting with the MJJD AI Assistant until a human support staff member is assigned.
                    @endif
                @else
                    {{ auth()->user()->isAdmin() ? 'Track AI handling, oversee queue status, and reassign support without leaving this conversation view.' : 'Focus on this assigned customer thread, reply quickly, and keep the issue moving toward resolution.' }}
                @endif
            </p>
            <div class="support-hero-stat-row">
                <span class="support-hero-stat">
                    <strong>{{ $statusLabel }}</strong>
                    <span>Current conversation state</span>
                </span>
                <span class="support-hero-stat">
                    <strong>{{ $isCustomer ? ($chatState['identity_name'] ?? 'MJJD AI Assistant') : $user->name }}</strong>
                    <span>{{ $isCustomer ? 'Support identity' : 'Customer in focus' }}</span>
                </span>
            </div>
        </div>
        <div class="support-chat-header-actions">
            <div class="support-identity-card">
                <strong>{{ $chatState['identity_name'] ?? 'MJJD AI Assistant' }}</strong>
                <span>{{ $chatState['identity_role'] ?? 'AI Assistant' }}</span>
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            @if($isRoleChat)
                <div class="support-header-buttons">
                    <form method="POST" action="{{ $availabilityRoute }}">
                        @csrf
                        <input type="hidden" name="chat_available" value="{{ auth()->user()->chat_available ? 0 : 1 }}">
                        <button type="submit" class="btn secondary">{{ auth()->user()->chat_available ? 'Set Unavailable' : 'Set Available' }}</button>
                    </form>
                    @if($canAdminTakeOver)
                        <form method="POST" action="{{ $takeoverRoute }}">
                            @csrf
                            <button type="submit" class="btn">Assign to Me</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <div class="support-chat-layout {{ $isCustomer ? 'support-chat-layout-customer' : 'support-chat-layout-staff' }}">
        @if($isRoleChat)
            <aside class="support-conversation-sidebar card">
                <div class="support-sidebar-head">
                    <div>
                        <h3>{{ auth()->user()->isAdmin() ? 'Customer Conversations' : 'Assigned Conversations' }}</h3>
                        <p>{{ auth()->user()->isAdmin() ? 'Jump between customer threads and rebalance support when needed.' : 'Stay focused on the queue assigned to you.' }}</p>
                    </div>
                    <span class="badge info">{{ $conversations->count() }}</span>
                </div>
                <div class="support-conversation-list">
                    @forelse($conversations as $conversation)
                        @php
                            $customerItem = $conversation['customer'];
                            $latest = $conversation['latest_message'];
                        @endphp
                        <a href="{{ route('role.chat.conversation', ['role' => auth()->user()->role, 'user' => $customerItem]) }}" class="support-conversation-item {{ $customerItem->id === $user->id ? 'active' : '' }}">
                            <div class="support-conversation-top">
                                <strong>{{ $customerItem->name }}</strong>
                                <span class="badge {{ $conversation['status_class'] }}">{{ $conversation['status_label'] }}</span>
                            </div>
                            <div class="support-conversation-meta">
                                <span>{{ $conversation['assigned_staff']?->name ? 'Connected to: ' . $conversation['assigned_staff']->name : 'Waiting for human support' }}</span>
                                @if($conversation['is_mine'])
                                    <span class="badge success">Mine</span>
                                @endif
                            </div>
                            <p>{{ \Illuminate\Support\Str::limit($latest->message, 92) }}</p>
                        </a>
                    @empty
                        <div class="support-sidebar-empty">
                            <div class="support-sidebar-empty-icon">...</div>
                            <strong>{{ auth()->user()->isAdmin() ? 'No customer conversations yet' : 'No assigned conversations yet' }}</strong>
                            <p>{{ auth()->user()->isAdmin() ? 'Customer threads will appear here once a new support conversation starts.' : 'When a thread is assigned to you, it will appear here automatically.' }}</p>
                        </div>
                    @endforelse
                </div>
            </aside>
        @endif

        <section class="support-chat-main card {{ $isCustomer ? 'support-chat-main-customer' : 'support-chat-main-staff' }}">
            <div class="support-chat-panel-head">
                <div>
                    <h2>{{ $isCustomer ? ($chatState['identity_name'] ?? 'MJJD AI Assistant') : $user->name }}</h2>
                    <p>
                        @if($isCustomer)
                            {{ $statusLabel }}
                        @else
                            Customer support thread with {{ $user->name }}.
                        @endif
                    </p>
                </div>
                <div class="support-chat-panel-actions">
                    @if($isRoleChat)
                        <div class="support-chip">{{ $chatState['assigned_staff'] ? 'Connected to: ' . $chatState['assigned_staff']->name : 'Waiting for human support' }}</div>
                        @if(auth()->user()->isAdmin() && $assignRoute && $assignableStaff->isNotEmpty())
                            <form method="POST" action="{{ $assignRoute }}" class="support-assignment-form">
                                @csrf
                                <select name="assigned_staff_id">
                                    @foreach($assignableStaff as $staffOption)
                                        <option value="{{ $staffOption->id }}" {{ optional($chatState['assigned_staff'])->id === $staffOption->id ? 'selected' : '' }}>{{ $staffOption->name }} ({{ ucfirst($staffOption->role) }})</option>
                                    @endforeach
                                </select>
                                <button class="btn secondary" type="submit">Reassign</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            <div id="chat-window" class="support-chat-window">
                @if($messages->isEmpty())
                    <div class="support-system-banner">
                        @if(in_array($mode, $humanModes, true))
                            A human support staff member is assigned to this conversation.
                        @elseif($mode === 'waiting_for_human')
                            Your message has been received. Please wait while human support becomes available.
                        @else
                            Hello, I am the MJJD AI Assistant. I can help answer common support questions while our staff are offline.
                        @endif
                    </div>
                @endif

                @foreach($messages as $msg)
                    @php
                        $self = $msg->sender_type === 'customer' && auth()->user()->id === $msg->from_user_id;
                        $bubbleClass = $msg->sender_type === 'system' ? 'system' : ($self ? 'self' : ($msg->sender_type === 'ai' ? 'ai' : 'agent'));
                    @endphp
                    <article class="support-message-row {{ $bubbleClass }}">
                        @if($msg->sender_type === 'system')
                            <div class="support-system-banner">{{ $msg->message }}</div>
                        @else
                            <div class="support-message-bubble {{ $bubbleClass }}">
                                <div class="support-message-meta">
                                    <strong>{{ $msg->senderLabel() }}</strong>
                                    <span>{{ $msg->created_at->format('M d, h:i A') }}</span>
                                </div>
                                <div class="support-message-text">{{ $msg->message }}</div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($isCustomer && !empty($quickReplies) && $mode === 'ai_active')
                <div class="support-quick-replies" id="support-quick-replies">
                    @foreach($quickReplies as $quickReply)
                        <button type="button" class="support-quick-btn" data-quick-reply="{{ $quickReply }}">{{ $quickReply }}</button>
                    @endforeach
                </div>
            @endif

            <form id="chat-form" method="POST" action="{{ $sendRoute }}" class="support-chat-composer">
                @csrf
                <textarea name="message" rows="2" placeholder="Type your message..." required></textarea>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </section>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const chatWindow = document.getElementById('chat-window');
        const form = document.getElementById('chat-form');
        const quickButtons = document.querySelectorAll('[data-quick-reply]');
        const quickRepliesWrap = document.getElementById('support-quick-replies');
        const meId = {{ auth()->id() }};
        const isCustomer = {{ $isCustomer ? 'true' : 'false' }};

        if (chatWindow) {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        quickButtons.forEach((button) => {
            button.addEventListener('click', function () {
                const textarea = form.querySelector('textarea[name="message"]');
                textarea.value = this.dataset.quickReply;
                textarea.focus();
            });
        });

        function appendMessage(message) {
            if (!chatWindow) return;

            if (message.sender_type === 'system') {
                const row = document.createElement('article');
                row.className = 'support-message-row system';
                row.innerHTML = '<div class="support-system-banner">' + message.message + '</div>';
                chatWindow.appendChild(row);
                chatWindow.scrollTop = chatWindow.scrollHeight;
                return;
            }

            const bubbleClass = message.sender_type === 'customer' && message.from_user_id === meId
                ? 'self'
                : (message.sender_type === 'ai' ? 'ai' : 'agent');

            const row = document.createElement('article');
            row.className = 'support-message-row ' + bubbleClass;
            row.innerHTML = `
                <div class="support-message-bubble ${bubbleClass}">
                    <div class="support-message-meta">
                        <strong>${message.sender_label}</strong>
                        <span>${message.created_at}</span>
                    </div>
                    <div class="support-message-text">${message.message}</div>
                </div>
            `;
            chatWindow.appendChild(row);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const textarea = form.querySelector('textarea[name="message"]');
                const message = textarea.value.trim();
                if (!message) return;

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ message }),
                })
                    .then((response) => response.json())
                    .then((payload) => {
                        textarea.value = '';
                        (payload.messages || []).forEach(appendMessage);
                        if (isCustomer && payload.chat_state && payload.chat_state.mode !== 'ai_active' && quickRepliesWrap) {
                            quickRepliesWrap.classList.add('is-hidden');
                        }
                    })
                    .catch((error) => console.error(error));
            });
        }

        if (window.Echo) {
            window.Echo.private(`chat.${meId}`)
                .listen('ChatMessageSent', (event) => {
                    const message = event.message;
                    if (!message || !chatWindow) return;
                    if (form && message.from_user_id === meId) return;
                    appendMessage(message);
                });
        }
    })();
</script>
@endsection

