@extends('layouts.app')

@section('title', 'Support Chat')

@section('content')
@php
    use Illuminate\Support\Str;

    $me = auth()->user();
    $isCustomer = $me->role === 'customer';
    $isRoleChat = ! $isCustomer;
    $search = $search ?? '';
    $scope = $scope ?? '';
    $sendRoute = $isRoleChat ? route('role.chat.send', ['role' => $me->role, 'user' => $user]) : route('chat.send', $user);
    $snapshotRoute = $isRoleChat ? route('role.chat.snapshot', ['role' => $me->role, 'user' => $user, 'search' => $search, 'scope' => $scope]) : route('chat.snapshot', ['user' => $user]);
    $takeoverRoute = $isRoleChat ? route('role.chat.takeover', ['role' => $me->role, 'user' => $user]) : null;
    $assignRoute = $isRoleChat && $me->isAdmin() ? route('role.chat.assign', ['role' => $me->role, 'user' => $user]) : null;
    $availabilityRoute = $isRoleChat ? route('role.chat.availability', ['role' => $me->role]) : null;
    $noteRoute = $isRoleChat ? route('role.chat.note', ['role' => $me->role, 'user' => $user]) : null;
    $closeRoute = $isRoleChat ? route('role.chat.close', ['role' => $me->role, 'user' => $user]) : null;
    $reopenRoute = $isRoleChat ? route('role.chat.reopen', ['role' => $me->role, 'user' => $user]) : null;
    $mode = $chatState['mode'] ?? 'ai_active';
    $statusLabel = $isCustomer
        ? ($chatState['customer_status_label'] ?? $chatState['status_label'] ?? 'AI assistant is helping')
        : ($chatState['status_label'] ?? 'AI assistant is helping');
    $humanModes = ['assigned', 'active_human'];
    $currentAssignedStaffId = optional($chatState['assigned_staff'])->id;
    $assignableStaff = $assignableStaff ?? collect();
    $conversations = $conversations ?? collect();
    $staffStatus = $isRoleChat ? $me->chatAvailabilityLabel() : null;
    $canAdminAssignToSelf = $isRoleChat && $me->isAdmin() && ($currentAssignedStaffId !== $me->id || ! in_array($mode, $humanModes, true));
    $canTechnicianTakeOver = $isRoleChat && $me->isTechnician() && ! in_array($mode, $humanModes, true);
    $canSendMessages = $isCustomer || ($isRoleChat && $currentAssignedStaffId === $me->id && in_array($mode, $humanModes, true));
    $composerNotice = null;
    if (! $isCustomer && ! $canSendMessages) {
        $composerNotice = $me->isAdmin()
            ? 'Assign this conversation to yourself before replying.'
            : 'Only the assigned active support staff member can reply to this conversation.';
    }

    $pageTitle = $isCustomer ? 'Live chat' : ($me->isAdmin() ? 'Support queue' : 'Assigned conversations');
    $pageSubtitle = $isCustomer
        ? 'Chat with MJJD support from one thread. If no staff member is available, the AI assistant will respond first.'
        : ($me->isAdmin()
            ? 'Review the queue, reassign conversations, and manage active support work.'
            : 'Stay focused on your assigned customer conversations and respond quickly.');

    $headerActions = null;
    if ($isRoleChat) {
        $headerActions = '<form method="POST" action="' . $availabilityRoute . '">'
            . '<span class="chat-ui__button chat-ui__button--secondary" style="pointer-events:none;">' . e($staffStatus) . '</span>'
            . csrf_field()
            . '<input type="hidden" name="chat_available" value="' . ($me->chat_available ? '0' : '1') . '">'
            . '<button type="submit" class="chat-ui__button ' . ($me->chat_available ? 'chat-ui__button--secondary' : 'chat-ui__button--primary') . '">'
            . ($me->chat_available ? 'Set away' : 'Set available')
            . '</button></form>';

        if ($canTechnicianTakeOver) {
            $headerActions .= '<form method="POST" action="' . $takeoverRoute . '">'
                . csrf_field()
                . '<button type="submit" class="chat-ui__button chat-ui__button--secondary">'
                . ($me->isAdmin() ? 'Assign to me' : 'Take over')
                . '</button></form>';
        }
    }

    $panelActions = '';
    $reassignModal = '';
    if ($isRoleChat && $me->isAdmin() && $assignRoute) {
        if ($canAdminAssignToSelf) {
            $panelActions .= '<form method="POST" action="' . $assignRoute . '">'
                . csrf_field()
                . '<input type="hidden" name="assigned_staff_id" value="' . e($me->id) . '">'
                . '<button type="submit" class="chat-ui__button chat-ui__button--primary" data-assign-to-me="' . e($me->id) . '">Assign to me</button>'
                . '</form>';
        }

        $panelActions .= '<button type="button" class="chat-ui__button chat-ui__button--secondary" data-open-reassign-modal>Reassign</button>';

        $modalStaff = $assignableStaff->keyBy('id');
        if (! $modalStaff->has($me->id)) {
            $modalStaff->put($me->id, $me);
        }

        $reassignModal = '<div class="chat-ui__modal" id="reassignModal" aria-hidden="true">'
            . '<div class="chat-ui__modal-backdrop" data-close-reassign-modal></div>'
            . '<div class="chat-ui__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reassignModalTitle">'
            . '<div class="chat-ui__modal-head">'
            . '<div><h3 id="reassignModalTitle">Reassign conversation</h3><p>Select who should handle this customer conversation.</p></div>'
            . '<button type="button" class="chat-ui__modal-close" data-close-reassign-modal aria-label="Close reassignment modal">&times;</button>'
            . '</div>'
            . '<div class="chat-ui__modal-body">'
            . '<form method="POST" action="' . $assignRoute . '" class="chat-ui__filters">'
            . csrf_field()
            . '<label for="assigned_staff_id">Assign to</label>'
            . '<select name="assigned_staff_id" id="assigned_staff_id" required>'
            . '<option value="" disabled' . ($currentAssignedStaffId ? '' : ' selected') . '>Select support staff</option>';
        foreach ($modalStaff as $staffOption) {
            $selected = $currentAssignedStaffId === $staffOption->id ? ' selected' : '';
            $reassignModal .= '<option value="' . e($staffOption->id) . '"' . $selected . '>' . e($staffOption->name . ' (' . ucfirst($staffOption->role) . ')') . '</option>';
        }
        $reassignModal .= '</select>'
            . '<div class="chat-ui__modal-actions">'
            . '<button class="chat-ui__button chat-ui__button--primary" type="submit">Save reassignment</button>'
            . '<button type="button" class="chat-ui__button chat-ui__button--secondary" data-close-reassign-modal>Cancel</button>'
            . '</div>'
            . '</form>'
            . '<form method="POST" action="' . $assignRoute . '">'
            . csrf_field()
            . '<input type="hidden" name="assigned_staff_id" value="' . e($me->id) . '">'
            . '<button type="submit" class="chat-ui__button chat-ui__button--secondary" data-assign-to-me="' . e($me->id) . '">Assign to me</button>'
            . '</form>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    if ($isRoleChat) {
        $panelActions .= '<form method="POST" action="' . ($mode === 'closed' ? $reopenRoute : $closeRoute) . '">'
            . csrf_field()
            . '<button type="submit" class="chat-ui__button chat-ui__button--secondary">'
            . ($mode === 'closed' ? 'Reopen' : 'Close')
            . '</button></form>';
    }

    $contextItems = $isCustomer ? [] : array_values(array_filter([
        ['label' => 'Customer', 'value' => $customerContext['customer_name'] ?? $customer->name],
        ['label' => 'Email', 'value' => $customerContext['customer_email'] ?? null],
        ['label' => 'Phone', 'value' => $customerContext['customer_phone'] ?? null],
        ['label' => 'Application', 'value' => $customerContext['application_reference'] ?? null],
        ['label' => 'Application status', 'value' => $customerContext['application_status'] ?? null],
        ['label' => 'Plan', 'value' => $customerContext['application_plan'] ?? null],
        ['label' => 'Ticket', 'value' => $customerContext['ticket_reference'] ?? null],
        ['label' => 'Ticket status', 'value' => $customerContext['ticket_status'] ?? null],
        ['label' => 'Assigned technician', 'value' => $customerContext['assigned_technician'] ?? null],
        ['label' => 'Address', 'value' => $customerContext['service_address'] ?? null, 'full' => true],
        ['label' => 'Latest ticket topic', 'value' => $customerContext['ticket_subject'] ?? null, 'full' => true],
    ], fn (array $item) => filled($item['value'] ?? null)));

    $sidebarContent = null;
    if ($isRoleChat) {
        $sidebarContent = view('chat.components.conversation-list', [
            'viewerRole' => $me->role,
            'sidebarTitle' => $me->isAdmin() ? 'Customer queue' : 'My chats',
            'sidebarMeta' => $conversations->count() . ' active conversation' . ($conversations->count() === 1 ? '' : 's'),
            'conversations' => $conversations,
            'activeConversationId' => $user->id,
            'filterAction' => route('role.chat.index', ['role' => $me->role]),
            'search' => $search,
            'scope' => $scope,
            'showFilters' => true,
            'showScopeFilter' => $me->isAdmin(),
            'emptyTitle' => $me->isAdmin() ? 'No queued conversations' : 'No assigned conversations',
            'emptyMessage' => $me->isAdmin() ? 'Customer chats will appear here when someone starts a conversation.' : 'New customer chats assigned to you will appear here.',
        ])->render();
    }

    $panelContent = view('chat.components.chat-panel', [
        'role' => $me->role,
        'user' => $user,
        'messages' => $messages,
        'chatState' => $chatState,
        'mode' => $mode,
        'statusLabel' => $statusLabel,
        'sendRoute' => $sendRoute,
        'isCustomer' => $isCustomer,
        'quickReplies' => $quickReplies,
        'panelTitle' => $isCustomer ? ($chatState['identity_name'] ?? 'MJJD AI Assistant') : $user->name,
        'panelMeta' => $isCustomer
            ? 'A single thread for AI and live support.'
            : ($me->isAdmin() ? 'Review the thread and route it to the right staff member.' : 'Respond directly to the customer from this thread.'),
        'panelActions' => $panelActions,
        'contextItems' => $contextItems,
        'contextTitle' => $isCustomer ? null : 'Customer context',
        'noteRoute' => $noteRoute,
        'showNoteForm' => $isRoleChat,
        'canSendMessages' => $canSendMessages,
        'composerNotice' => $composerNotice,
    ])->render();
@endphp

@include('chat.layouts.chat-layout', [
    'role' => $me->role,
    'eyebrow' => $isCustomer ? 'Customer' : ucfirst($me->role),
    'title' => $pageTitle,
    'subtitle' => $pageSubtitle,
    'headerActions' => $headerActions,
    'sidebarContent' => $sidebarContent,
    'panelContent' => $panelContent,
])
{!! $reassignModal !!}
@endsection

@section('scripts')
<script>
    (function () {
        const chatWindow = document.getElementById('chat-window');
        const form = document.getElementById('chat-form');
        const conversationList = document.getElementById('chat-conversation-list');
        const quickRepliesWrap = document.getElementById('support-quick-replies');
        const statusBadge = document.getElementById('chat-status-badge');
        const reassignModal = document.getElementById('reassignModal');
        const reassignOpeners = document.querySelectorAll('[data-open-reassign-modal]');
        const reassignClosers = document.querySelectorAll('[data-close-reassign-modal]');
        const assignedStaffSelect = document.getElementById('assigned_staff_id');
        const assignToMeButtons = document.querySelectorAll('[data-assign-to-me]');
        const textarea = form ? form.querySelector('textarea[name="message"]') : null;
        const attachmentInput = form ? form.querySelector('input[type="file"]') : null;
        const attachmentName = form ? form.querySelector('[data-chat-attachment-name]') : null;
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        const meId = {{ auth()->id() }};
        const isCustomer = {{ $isCustomer ? 'true' : 'false' }};
        const viewerRole = @json($me->role);
        const snapshotUrl = @json($snapshotRoute);
        let pollTimer = null;
        let lastSignature = '';
        let isSending = false;

        if (chatWindow) {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        function openReassignModal() {
            if (!reassignModal) return;
            reassignModal.classList.add('is-open');
            reassignModal.setAttribute('aria-hidden', 'false');
        }

        function closeReassignModal() {
            if (!reassignModal) return;
            reassignModal.classList.remove('is-open');
            reassignModal.setAttribute('aria-hidden', 'true');
        }

        reassignOpeners.forEach((button) => {
            button.addEventListener('click', openReassignModal);
        });

        reassignClosers.forEach((button) => {
            button.addEventListener('click', closeReassignModal);
        });

        assignToMeButtons.forEach((button) => {
            button.addEventListener('click', function () {
                if (assignedStaffSelect) {
                    assignedStaffSelect.value = this.dataset.assignToMe;
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeReassignModal();
            }
        });

        if (quickRepliesWrap) {
            quickRepliesWrap.addEventListener('click', function (event) {
                const button = event.target.closest('[data-quick-reply]');
                if (!button || !textarea) return;

                textarea.value = button.dataset.quickReply;
                submitMessage();
            });
        }

        function setSendingState(sending) {
            isSending = sending;
            if (submitButton) {
                submitButton.disabled = sending;
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function attachmentHtml(message) {
            let attachments = Array.isArray(message.attachments) ? message.attachments : [];

            if (!attachments.length && message.attachment_path) {
                attachments = [{
                    path: message.attachment_path,
                    url: message.attachment_url,
                    original_name: message.attachment_original_name || 'View image',
                    is_image: message.attachment_is_image,
                    missing: message.attachment_missing || !message.attachment_url,
                }];
            }

            if (!attachments.length) {
                return '';
            }

            return attachments.map((attachment) => {
                if (attachment.missing || !attachment.url) {
                    return `
                        <div class="chat-ui__message-attachment is-missing">
                            <span>Uploaded file not found.</span>
                        </div>
                    `;
                }

                const label = escapeHtml(attachment.original_name || 'View image');
                const url = escapeHtml(attachment.url);
                const image = attachment.is_image
                    ? `<img src="${url}" alt="${label}">`
                    : '';

                return `
                    <a href="${url}" target="_blank" rel="noopener noreferrer" class="chat-ui__message-attachment">
                        ${image}
                        <span>${label}</span>
                    </a>
                `;
            }).join('');
        }

        function appendMessage(message) {
            if (!chatWindow) return;

            if (message.sender_type === 'system') {
                const row = document.createElement('article');
                row.className = 'chat-ui__message-row system';
                row.dataset.messageId = message.id;
                row.innerHTML = '<div class="chat-ui__system-banner">' + escapeHtml(message.message) + '</div>';
                chatWindow.appendChild(row);
                chatWindow.scrollTop = chatWindow.scrollHeight;
                return;
            }

            const bubbleClass = message.sender_type === 'customer' && message.from_user_id === meId
                ? 'self'
                : (message.sender_type === 'ai' ? 'ai' : 'agent');

            const row = document.createElement('article');
            row.className = 'chat-ui__message-row ' + bubbleClass;
            row.dataset.messageId = message.id;
            row.innerHTML = `
                <div class="chat-ui__message-bubble ${bubbleClass}">
                    <div class="chat-ui__message-meta">
                        <strong>${escapeHtml(message.sender_label)}</strong>
                        <span>${escapeHtml(message.created_at)}</span>
                    </div>
                    <div class="chat-ui__message-text">${escapeHtml(message.message)}</div>
                    ${attachmentHtml(message)}
                </div>
            `;
            chatWindow.appendChild(row);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        function badgeClass(statusLabel) {
            const normalized = String(statusLabel || '').toLowerCase();

            if (normalized.includes('waiting')) return 'chat-ui__badge--gray';
            if (normalized.includes('assigned') || normalized.includes('connected')) return 'chat-ui__badge--blue';
            if (normalized.includes('active') || normalized.includes('progress')) return 'chat-ui__badge--yellow';
            if (normalized.includes('closed') || normalized.includes('resolved')) return 'chat-ui__badge--green';
            if (normalized.includes('attention')) return 'chat-ui__badge--red';
            if (normalized.includes('urgent')) return 'chat-ui__badge--red';

            return 'chat-ui__badge--gray';
        }

        function renderMessage(message) {
            if (message.sender_type === 'system') {
                return `
                    <article class="chat-ui__message-row system" data-message-id="${message.id}">
                        <div class="chat-ui__system-banner">${escapeHtml(message.message)}</div>
                    </article>
                `;
            }

            const bubbleClass = message.sender_type === 'customer' && message.from_user_id === meId
                ? 'self'
                : (message.sender_type === 'ai' ? 'ai' : 'agent');

            return `
                <article class="chat-ui__message-row ${bubbleClass}" data-message-id="${message.id}">
                    <div class="chat-ui__message-bubble ${bubbleClass}">
                        <div class="chat-ui__message-meta">
                            <strong>${escapeHtml(message.sender_label)}</strong>
                            <span>${escapeHtml(message.created_at)}</span>
                        </div>
                        <div class="chat-ui__message-text">${escapeHtml(message.message)}</div>
                        ${attachmentHtml(message)}
                    </div>
                </article>
            `;
        }

        function renderMessages(messages) {
            if (!chatWindow) return;

            const nearBottom = (chatWindow.scrollHeight - chatWindow.scrollTop - chatWindow.clientHeight) < 120;
            const signature = JSON.stringify((messages || []).map((message) => [message.id, message.message, message.attachment_path, message.attachments, message.created_at]));

            if (signature === lastSignature) {
                return;
            }

            chatWindow.innerHTML = (messages || []).map(renderMessage).join('');
            lastSignature = signature;

            if (nearBottom) {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }
        }

        function renderConversationList(conversations) {
            if (!conversationList || !Array.isArray(conversations)) return;

            conversationList.innerHTML = conversations.map((conversation) => {
                const statusClass = badgeClass(conversation.status_label);
                const primaryText = viewerRole === 'admin'
                    ? (conversation.assigned_staff_name ? `Assigned to ${escapeHtml(conversation.assigned_staff_name)}` : 'Unassigned')
                    : escapeHtml(conversation.last_activity || 'Recent activity');
                const secondaryText = escapeHtml(conversation.queue_age || conversation.application_reference || 'Recent');
                const submeta = viewerRole === 'admin'
                    ? `
                        <div class="chat-ui__conversation-submeta" data-conversation-submeta>
                            ${conversation.application_reference ? `<span>${escapeHtml(conversation.application_reference)}</span>` : ''}
                            ${conversation.open_ticket_count ? `<span>${conversation.open_ticket_count} open ticket${conversation.open_ticket_count === 1 ? '' : 's'}</span>` : ''}
                        </div>
                    `
                    : '';

                return `
                    <a href="${escapeHtml(conversation.url)}" class="chat-ui__conversation-item ${conversation.is_active ? 'is-active' : ''}" data-conversation-item data-customer-id="${conversation.customer_id}">
                        <div class="chat-ui__conversation-top">
                            <strong>${escapeHtml(conversation.customer_name)}</strong>
                            <span class="chat-ui__badge ${statusClass}" data-conversation-status>${escapeHtml(conversation.status_label)}</span>
                        </div>
                        <p class="chat-ui__conversation-preview" data-conversation-preview>${escapeHtml(conversation.latest_message || '')}</p>
                        <div class="chat-ui__conversation-meta">
                            <span data-conversation-meta-primary>${primaryText}</span>
                            <span data-conversation-meta-secondary>${secondaryText}</span>
                        </div>
                        ${submeta}
                    </a>
                `;
            }).join('');
        }

        function renderQuickReplies(chatState) {
            if (!isCustomer || !quickRepliesWrap || !chatState) return;

            const replies = Array.isArray(chatState.quick_replies) ? chatState.quick_replies : [];
            const shouldShow = replies.length > 0 && ['ai_active', 'waiting_for_human', 'staff_inactive'].includes(chatState.mode);
            const signature = replies.join('\u001f');

            if (quickRepliesWrap.dataset.quickRepliesSignature !== signature) {
                quickRepliesWrap.innerHTML = replies.map((reply) => {
                    const label = escapeHtml(reply);
                    return `<button type="button" class="chat-ui__quick-reply" data-quick-reply="${label}">${label}</button>`;
                }).join('');
                quickRepliesWrap.dataset.quickRepliesSignature = signature;
            }

            quickRepliesWrap.classList.toggle('is-hidden', !shouldShow);
        }

        function updateChatState(chatState) {
            if (!chatState || !statusBadge) return;

            statusBadge.textContent = chatState.status_label || statusBadge.textContent;
            statusBadge.className = 'chat-ui__badge ' + badgeClass(chatState.status_label);

            if (assignedStaffSelect) {
                assignedStaffSelect.value = chatState.assigned_staff_id ? String(chatState.assigned_staff_id) : '';
            }

            renderQuickReplies(chatState);
        }

        function pollSnapshot() {
            if (!snapshotUrl) return;

            fetch(snapshotUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
                .then((response) => response.ok ? response.json() : null)
                .then((payload) => {
                    if (!payload) return;
                    renderMessages(payload.messages || []);
                    updateChatState(payload.chat_state || null);
                    renderConversationList(payload.conversations || null);
                })
                .catch(() => {});
        }

        function submitMessage() {
            if (!form || !textarea || isSending) return;

            const message = textarea.value.trim();
            const attachments = attachmentInput && attachmentInput.files ? Array.from(attachmentInput.files) : [];
            if (!message && !attachments.length) return;

            setSendingState(true);
            const formData = new FormData(form);
            formData.set('message', message);

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then((response) => response.json())
                .then((payload) => {
                    textarea.value = '';
                    if (attachmentInput) {
                        attachmentInput.value = '';
                    }
                    updateAttachmentName();
                    (payload.messages || []).forEach(appendMessage);
                    lastSignature = '';
                    updateChatState(payload.chat_state || null);
                    pollSnapshot();
                })
                .catch((error) => console.error(error))
                .finally(() => {
                    setSendingState(false);
                    textarea.focus();
                });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitMessage();
            });
        }

        if (textarea) {
            textarea.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                    event.preventDefault();
                    submitMessage();
                }
            });
        }

        function updateAttachmentName() {
            if (!attachmentInput || !attachmentName) return;

            const files = attachmentInput.files ? Array.from(attachmentInput.files) : [];
            attachmentName.textContent = files.length
                ? files.map((file) => file.name).join(', ')
                : 'No images selected';
        }

        if (attachmentInput) {
            attachmentInput.addEventListener('change', updateAttachmentName);
            updateAttachmentName();
        }

        if (window.Echo) {
            window.Echo.private(`chat.${meId}`)
                .listen('ChatMessageSent', (event) => {
                    const message = event.message;
                    if (!message || !chatWindow) return;
                    if (form && message.from_user_id === meId) return;
                    appendMessage(message);
                    lastSignature = '';
                    pollSnapshot();
                });
        }

        pollSnapshot();
        pollTimer = window.setInterval(pollSnapshot, 3000);
        window.addEventListener('beforeunload', function () {
            if (pollTimer) {
                window.clearInterval(pollTimer);
            }
        });
    })();
</script>
@endsection
