<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\PublicUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupportChatService
{
    public function __construct(protected SupportAiService $ai)
    {
    }

    protected function presenceThreshold(): Carbon
    {
        return Carbon::now()->subSeconds((int) config('services.support_chat.presence_window_seconds', 120));
    }

    protected function maxActiveConversationsPerStaff(): int
    {
        return max(1, (int) config('services.support_chat.max_active_conversations_per_staff', 1));
    }

    public function markPresence(User $user): void
    {
        $attributes = ['last_seen' => now()];

        if ($user->isAdmin() || $user->isTechnician()) {
            $attributes['is_online'] = true;
            if ($user->chat_available === null) {
                $attributes['chat_available'] = true;
            }
        }

        $user->forceFill($attributes)->save();

        if (($user->isAdmin() || $user->isTechnician()) && $user->isEffectivelyChatAvailable()) {
            $this->autoAssignOldestUnassignedConversation($user);
        }
    }

    public function setAvailability(User $user, bool $available): void
    {
        $user->forceFill([
            'chat_available' => $available,
            'is_online' => true,
            'last_seen' => now(),
        ])->save();

        if ($available && $user->isEffectivelyChatAvailable()) {
            $this->autoAssignOldestUnassignedConversation($user);
        }
    }

    public function quickReplies(): array
    {
        return $this->ai->quickReplies();
    }

    public function supportUsers(): Collection
    {
        return User::query()
            ->whereIn('role', ['admin', 'technician'])
            ->orderByRaw("FIELD(role, 'admin', 'technician')")
            ->orderBy('name')
            ->get();
    }

    public function availableSupportUsers(): Collection
    {
        return User::query()
            ->whereIn('role', ['admin', 'technician'])
            ->where('is_online', true)
            ->where('chat_available', true)
            ->whereNotNull('last_seen')
            ->where('last_seen', '>=', $this->presenceThreshold())
            ->orderBy('name')
            ->get();
    }

    protected function activeAssignmentStatuses(): array
    {
        return ['assigned', 'active_human', 'staff_inactive'];
    }

    protected function activeHumanConversationStatuses(): array
    {
        return ['assigned', 'active_human', 'staff_inactive'];
    }

    protected function autoAssignableConversationStatuses(): array
    {
        return ['ai_active', 'ai_assisting', 'waiting_for_human'];
    }

    protected function shouldRetainAssignment(?ChatMessage $latest): bool
    {
        return $latest?->assigned_staff_id !== null
            && in_array($latest->conversation_status, $this->activeAssignmentStatuses(), true);
    }

    protected function shouldAutoAssignCustomerConversation(?ChatMessage $latest): bool
    {
        if (! $latest) {
            return true;
        }

        return $latest->assigned_staff_id === null
            && in_array($latest->conversation_status, $this->autoAssignableConversationStatuses(), true);
    }

    protected function isExistingAutoAssignableConversation(?ChatMessage $latest): bool
    {
        return $latest !== null
            && $latest->assigned_staff_id === null
            && in_array($latest->conversation_status, $this->autoAssignableConversationStatuses(), true);
    }

    protected function isEligibleAutoAssignmentStaff(User $staff, ?Collection $activeCounts = null): bool
    {
        return ($staff->isAdmin() || $staff->isTechnician())
            && $staff->isEffectivelyChatAvailable()
            && ! $this->isStaffBusy($staff, $activeCounts);
    }

    protected function shouldCreateConnectionMessage(?ChatMessage $latest, User $staff): bool
    {
        if (! $latest) {
            return true;
        }

        return $latest->assigned_staff_id !== $staff->id
            || ! in_array($latest->conversation_status, ['assigned', 'active_human'], true);
    }

    protected function shouldCreateAiAssistanceNotice(?ChatMessage $latest, string $conversationStatus): bool
    {
        return in_array($conversationStatus, ['ai_active', 'waiting_for_human'], true)
            && (
                ! $latest
                || $latest->assigned_staff_id !== null
                || ! in_array($latest->conversation_status, ['ai_active', 'waiting_for_human'], true)
            );
    }

    protected function shouldSwitchInactiveConversationToAi(?ChatMessage $latest): bool
    {
        return $this->assignedStaffIsInactive($latest);
    }

    protected function autoAssignableSupportUsers(): Collection
    {
        $activeCounts = $this->activeConversationCounts();

        return $this->availableSupportUsers()
            ->reject(fn (User $staff) => $this->isStaffBusy($staff, $activeCounts))
            ->values();
    }

    public function autoAssignOldestUnassignedConversation(?User $preferredStaff = null): ?ChatMessage
    {
        if ($preferredStaff && ! $this->isEligibleAutoAssignmentStaff($preferredStaff)) {
            return null;
        }

        $candidate = $this->latestConversationMessages()
            ->filter(fn (ChatMessage $message) => $this->isExistingAutoAssignableConversation($message))
            ->sortBy(fn (ChatMessage $message) => $message->created_at?->getTimestamp() ?? 0)
            ->first();

        if (! $candidate?->customer) {
            return null;
        }

        return $this->autoAssignCustomerConversation($candidate->customer, $preferredStaff);
    }

    public function autoAssignCustomerConversation(User $customer, ?User $preferredStaff = null): ?ChatMessage
    {
        return DB::transaction(function () use ($customer, $preferredStaff) {
            $latest = ChatMessage::query()
                ->with(['assignedStaff', 'fromUser'])
                ->where('customer_id', $customer->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $this->isExistingAutoAssignableConversation($latest)) {
                return null;
            }

            $activeCounts = $this->activeConversationCounts();

            if ($preferredStaff) {
                if (! $this->isEligibleAutoAssignmentStaff($preferredStaff, $activeCounts)) {
                    return null;
                }

                return $this->assignConversation($preferredStaff, $customer, $preferredStaff);
            }

            $staff = $this->resolvePreferredStaff($customer);
            if (! $staff || ! $this->isEligibleAutoAssignmentStaff($staff, $activeCounts)) {
                return null;
            }

            return $this->assignConversation($staff, $customer, $staff);
        });
    }

    protected function latestConversationMessages(): Collection
    {
        return ChatMessage::query()
            ->with(['customer', 'assignedStaff', 'fromUser'])
            ->whereNotNull('customer_id')
            ->latest('id')
            ->get()
            ->groupBy('customer_id')
            ->map(fn (Collection $messages) => $messages->first())
            ->filter();
    }

    protected function activeConversationCounts(): Collection
    {
        return $this->latestConversationMessages()
            ->filter(fn (ChatMessage $message) => $message->assigned_staff_id !== null && in_array($message->conversation_status, $this->activeHumanConversationStatuses(), true))
            ->countBy(fn (ChatMessage $message) => $message->assigned_staff_id);
    }

    protected function isStaffBusy(User $staff, ?Collection $activeCounts = null): bool
    {
        $activeCounts ??= $this->activeConversationCounts();

        return (int) $activeCounts->get($staff->id, 0) >= $this->maxActiveConversationsPerStaff();
    }

    protected function assignedStaffIsInactive(?ChatMessage $latest): bool
    {
        return $latest?->assignedStaff !== null
            && $this->shouldRetainAssignment($latest)
            && ! $latest->assignedStaff->isEffectivelyChatAvailable();
    }

    protected function waitingStatus(?ChatMessage $latest = null): string
    {
        if ($this->assignedStaffIsInactive($latest)) {
            return 'staff_inactive';
        }

        if ($this->autoAssignableSupportUsers()->isEmpty()) {
            return 'ai_active';
        }

        return 'waiting_for_human';
    }

    protected function preferredTechnicianId(User $customer): ?int
    {
        $installationTechnicianId = optional(optional($customer->application)->installation)->technician_id;
        if ($installationTechnicianId) {
            return (int) $installationTechnicianId;
        }

        $ticketTechnicianId = $customer->tickets()
            ->whereNotNull('technician_id')
            ->whereIn('status', ['open', 'queued', 'in_progress'])
            ->latest('id')
            ->value('technician_id');

        return $ticketTechnicianId ? (int) $ticketTechnicianId : null;
    }

    public function latestConversationMessage(User $customer): ?ChatMessage
    {
        return ChatMessage::query()
            ->with(['assignedStaff', 'fromUser'])
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();
    }

    public function resolvePreferredStaff(User $customer): ?User
    {
        $available = $this->autoAssignableSupportUsers();

        if ($available->isEmpty()) {
            return null;
        }

        $latest = $this->latestConversationMessage($customer);
        if ($this->shouldRetainAssignment($latest)) {
            $currentAssignee = $available->firstWhere('id', $latest->assigned_staff_id);
            if ($currentAssignee) {
                return $currentAssignee;
            }
        }

        $preferredTechnicianId = $this->preferredTechnicianId($customer);
        if ($preferredTechnicianId) {
            $preferredTechnician = $available->firstWhere('id', $preferredTechnicianId);
            if ($preferredTechnician) {
                return $preferredTechnician;
            }
        }

        $activeCounts = $this->activeConversationCounts();

        return $available
            ->sortBy(function (User $staff) use ($activeCounts) {
                return [
                    (int) $activeCounts->get($staff->id, 0),
                    $staff->isTechnician() ? 0 : 1,
                    strtolower($staff->name),
                ];
            })
            ->first();
    }

    public function availableAssigneesForCustomer(User $customer): Collection
    {
        $preferredTechnicianId = $this->preferredTechnicianId($customer);
        $activeCounts = $this->activeConversationCounts();

        return $this->availableSupportUsers()
            ->sortBy(function (User $staff) use ($preferredTechnicianId, $activeCounts) {
                return [
                    $staff->id === $preferredTechnicianId ? 0 : 1,
                    (int) $activeCounts->get($staff->id, 0),
                    $staff->isTechnician() ? 0 : 1,
                    strtolower($staff->name),
                ];
            })
            ->values();
    }

    public function resolveCustomerPartner(User $customer): ?User
    {
        $latest = $this->latestConversationMessage($customer);
        if ($this->shouldRetainAssignment($latest) && $latest?->assignedStaff) {
            return $latest->assignedStaff;
        }

        return $this->resolvePreferredStaff($customer);
    }

    public function conversationMessages(User $customer, User $viewer, ?User $partner = null): Collection
    {
        $messages = ChatMessage::query()
            ->with(['fromUser', 'toUser', 'assignedStaff', 'customer'])
            ->where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->get();

        if ($viewer->isCustomer()) {
            $messages = $messages->reject(fn (ChatMessage $message) => $message->message_type === 'internal_note')->values();
        }

        if ($messages->isNotEmpty()) {
            return $messages;
        }

        if (! $partner) {
            return collect();
        }

        return ChatMessage::query()
            ->with(['fromUser', 'toUser'])
            ->where(function ($q) use ($customer, $partner) {
                $q->where('from_user_id', $customer->id)->where('to_user_id', $partner->id);
            })
            ->orWhere(function ($q) use ($customer, $partner) {
                $q->where('from_user_id', $partner->id)->where('to_user_id', $customer->id);
            })
            ->orderBy('created_at')
            ->get();
    }

    public function conversationState(User $customer): array
    {
        $latest = $this->latestConversationMessage($customer);
        $availableStaff = $this->availableSupportUsers();
        $assignedStaff = $latest?->assignedStaff;
        $autoAssignableStaff = $this->autoAssignableSupportUsers();
        $mode = $latest?->conversation_status ?? ($autoAssignableStaff->isNotEmpty() ? 'assigned' : $this->waitingStatus($latest));

        if ($this->shouldSwitchInactiveConversationToAi($latest) && $mode !== 'closed') {
            $mode = 'ai_active';
            $assignedStaff = null;
        }

        if ($mode === 'waiting_for_human' && ! $assignedStaff) {
            $mode = 'ai_active';
        }

        if ($mode === 'assigned' && ! $assignedStaff) {
            $assignedStaff = $this->resolvePreferredStaff($customer);
        }

        $customerStatusLabel = match ($mode) {
            'assigned', 'active_human' => 'Connected to support',
            'waiting_for_human', 'staff_inactive' => 'Waiting for support',
            default => 'AI assistant is helping',
        };

        $identityName = match ($mode) {
            'assigned', 'active_human' => $assignedStaff?->name ? 'Connected to: ' . $assignedStaff->name : 'Connected to: Support Agent',
            'waiting_for_human' => 'Support queue',
            'staff_inactive' => 'Support follow-up needed',
            'closed' => 'Conversation Closed',
            default => 'MJJD AI Assistant',
        };

        $identityRole = match ($mode) {
            'assigned' => ucfirst($assignedStaff?->role ?? 'agent') . ' Assigned',
            'active_human' => ucfirst($assignedStaff?->role ?? 'agent'),
            'waiting_for_human' => 'Queue Status',
            'staff_inactive' => 'Needs Attention',
            'closed' => 'Closed',
            default => 'AI Assistant',
        };

        $statusLabel = match ($mode) {
            'assigned', 'active_human' => 'Connected to support',
            'waiting_for_human' => 'Waiting for support',
            'staff_inactive' => 'Needs attention',
            'closed' => 'Conversation Closed',
            default => 'AI assistant is helping',
        };

        $statusClass = match ($mode) {
            'active_human' => 'success',
            'assigned' => 'info',
            'staff_inactive' => 'warning',
            'waiting_for_human' => 'warning',
            'closed' => 'secondary',
            default => 'info',
        };

        return [
            'mode' => $mode,
            'identity_name' => $identityName,
            'identity_role' => $identityRole,
            'status_label' => $statusLabel,
            'customer_status_label' => $customerStatusLabel,
            'status_class' => $statusClass,
            'assigned_staff' => $assignedStaff,
            'available_staff_count' => $availableStaff->count(),
            'quick_replies' => in_array($mode, ['ai_active', 'waiting_for_human', 'staff_inactive'], true) ? $this->quickReplies() : [],
            'latest_message' => $latest,
        ];
    }

    public function supportConversationList(User $staff): Collection
    {
        $latestMessages = $this->latestConversationMessages();

        if ($staff->isTechnician()) {
            $latestMessages = $latestMessages->filter(fn (ChatMessage $message) => $message->assigned_staff_id === $staff->id);
        }

        return $latestMessages->map(function (ChatMessage $message) use ($staff) {
            $customer = $message->customer;
            $state = $this->conversationState($customer);
            $application = $customer->application;
            $openTicketCount = $customer->tickets()
                ->whereIn('status', ['open', 'queued', 'in_progress'])
                ->count();

            return [
                'customer' => $customer,
                'latest_message' => $message,
                'status_label' => $state['status_label'],
                'status_class' => $state['status_class'],
                'assigned_staff' => $state['assigned_staff'],
                'is_mine' => $message->assigned_staff_id === $staff->id,
                'last_activity' => $message->created_at?->displayRelative(),
                'queue_age' => $this->queueAgeLabel($message),
                'application_reference' => $application?->reference_no,
                'application_status' => $application?->status ? ucfirst(str_replace('_', ' ', $application->status)) : null,
                'open_ticket_count' => $openTicketCount,
            ];
        })->sortByDesc(fn (array $item) => optional($item['latest_message'])->created_at)->values();
    }

    public function filteredSupportConversationList(User $staff, ?string $search = null, ?string $scope = null): Collection
    {
        $conversations = $this->supportConversationList($staff);

        if ($scope) {
            $conversations = $conversations->filter(function (array $conversation) use ($scope) {
                return match ($scope) {
                    'waiting' => in_array($conversation['status_label'], ['Waiting for support', 'Needs attention'], true),
                    'mine' => $conversation['is_mine'] === true,
                    'human' => $conversation['status_label'] === 'Connected to support',
                    'closed' => $conversation['status_label'] === 'Conversation Closed',
                    default => true,
                };
            })->values();
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);

            $conversations = $conversations->filter(function (array $conversation) use ($needle) {
                $haystacks = [
                    $conversation['customer']?->name,
                    $conversation['customer']?->email,
                    $conversation['application_reference'],
                    $conversation['application_status'],
                    $conversation['latest_message']?->message,
                    $conversation['assigned_staff']?->name,
                ];

                return collect($haystacks)
                    ->filter()
                    ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $needle));
            })->values();
        }

        return $conversations;
    }

    public function conversationOverview(Collection $conversations): array
    {
        return [
            'total' => $conversations->count(),
            'waiting' => $conversations->whereIn('status_label', ['Waiting for support', 'Needs attention'])->count(),
            'assigned' => $conversations->where('status_label', 'Connected to support')->count(),
            'mine' => $conversations->where('is_mine', true)->count(),
        ];
    }

    public function customerSupportContext(User $customer): array
    {
        $application = $customer->application?->loadMissing('installation.technician');
        $openTicket = $customer->tickets()
            ->whereIn('status', ['open', 'queued', 'in_progress'])
            ->latest('id')
            ->first();

        return [
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'application_reference' => $application?->reference_no,
            'application_status' => $application?->status ? ucfirst(str_replace('_', ' ', $application->status)) : null,
            'application_plan' => $application?->plan_code,
            'service_address' => $application?->fullServiceAddress(),
            'ticket_reference' => $openTicket?->publicReference(),
            'ticket_subject' => $openTicket?->subject,
            'ticket_status' => $openTicket?->status ? ucfirst(str_replace('_', ' ', $openTicket->status)) : null,
            'assigned_technician' => $application?->installation?->technician?->name,
        ];
    }

    protected function queueAgeLabel(?ChatMessage $message): ?string
    {
        if (! $message?->created_at) {
            return null;
        }

        $minutes = max(1, now()->diffInMinutes($message->created_at));

        if ($minutes < 60) {
            return $minutes . ' min in queue';
        }

        $hours = now()->diffInHours($message->created_at);
        if ($hours < 24) {
            return $hours . ' hr in queue';
        }

        return now()->diffInDays($message->created_at) . ' day in queue';
    }

    public function canStaffViewConversation(User $staff, User $customer): bool
    {
        if ($staff->isAdmin()) {
            return true;
        }

        $latest = $this->latestConversationMessage($customer);

        return $latest?->assigned_staff_id === $staff->id;
    }

    public function canStaffReply(User $staff, User $customer): bool
    {
        $latest = $this->latestConversationMessage($customer);

        return $latest?->assigned_staff_id === $staff->id
            && in_array($latest->conversation_status, ['assigned', 'active_human'], true);
    }

    public function canTechnicianTakeOver(User $technician, User $customer): bool
    {
        if (! $technician->isTechnician() || ! $technician->isEffectivelyChatAvailable()) {
            return false;
        }

        $latest = $this->latestConversationMessage($customer);

        if (! $latest) {
            return true;
        }

        if ($latest->assigned_staff_id !== null && $latest->assigned_staff_id !== $technician->id) {
            return false;
        }

        return $latest->assigned_staff_id === null
            && in_array($latest->conversation_status, ['ai_active', 'waiting_for_human'], true);
    }

    public function assignConversation(User $actor, User $customer, User $staff, bool $activate = false): ChatMessage
    {
        $latest = $this->latestConversationMessage($customer);

        if (! $this->shouldCreateConnectionMessage($latest, $staff)) {
            return $latest;
        }

        $message = $latest?->assigned_staff_id && (int) $latest->assigned_staff_id !== (int) $staff->id
            ? 'This conversation has been reassigned and you are now connected to support ' . $staff->name . '.'
            : 'You are now connected to ' . $staff->name;

        return $this->createSystemMessage(
            customer: $customer,
            message: $message,
            assignedStaff: $staff,
            status: $activate ? 'active_human' : 'assigned',
            handoffStatus: $actor->id === $staff->id ? 'transferred_to_human' : 'assigned_to_human'
        );
    }

    protected function storeChatAttachment(?UploadedFile $attachment): ?array
    {
        if (! $attachment) {
            return null;
        }

        return PublicUpload::attachmentMetadata(
            $attachment,
            $attachment->store('chat-attachments', 'public')
        );
    }

    public function handleCustomerMessage(User $customer, string $message, ?UploadedFile $attachment = null): array
    {
        $latest = $this->latestConversationMessage($customer);
        $preferredStaff = null;
        $conversationStatus = 'waiting_for_human';
        $assignedStaffId = null;
        $message = trim($message);
        $displayMessage = $message !== '' ? $message : 'Image attachment';
        $attachmentMetadata = $this->storeChatAttachment($attachment);

        if ($this->shouldSwitchInactiveConversationToAi($latest)) {
            $conversationStatus = 'ai_active';
        } elseif ($this->assignedStaffIsInactive($latest)) {
            $assignedStaffId = $latest?->assigned_staff_id;
            $preferredStaff = $latest?->assignedStaff;
            $conversationStatus = 'staff_inactive';
        } elseif ($this->shouldRetainAssignment($latest)) {
            $assignedStaffId = $latest?->assigned_staff_id;
            $preferredStaff = $latest?->assignedStaff;
            $conversationStatus = $latest?->conversation_status === 'active_human' ? 'active_human' : 'assigned';
        } elseif ($this->shouldAutoAssignCustomerConversation($latest)) {
            $preferredStaff = $this->resolvePreferredStaff($customer);
            $assignedStaffId = $preferredStaff?->id;
            $conversationStatus = $preferredStaff ? 'assigned' : $this->waitingStatus($latest);
        } else {
            $conversationStatus = $this->waitingStatus($latest);
        }

        $created = collect();

        if ($conversationStatus === 'staff_inactive' && $latest?->conversation_status !== 'staff_inactive') {
            $created->push($this->createSystemMessage(
                customer: $customer,
                message: 'Your assigned support staff is currently inactive. The conversation is still assigned and may need reassignment.',
                assignedStaff: $latest?->assignedStaff,
                status: 'staff_inactive',
                handoffStatus: 'staff_inactive'
            ));
        }

        if ($preferredStaff
            && ! $this->shouldRetainAssignment($latest)
            && (($latest?->assigned_staff_id !== $preferredStaff->id) || ! in_array($latest?->conversation_status, ['assigned', 'active_human'], true))) {
            $created->push($this->assignConversation($preferredStaff, $customer, $preferredStaff));
        }

        $customerMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $assignedStaffId,
            'from_user_id' => $customer->id,
            'to_user_id' => $preferredStaff?->id,
            'message' => $displayMessage,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'conversation_status' => $conversationStatus,
            'handoff_status' => $preferredStaff ? 'assigned_to_human' : $conversationStatus,
            'metadata' => $attachmentMetadata,
        ]);
        $created->push($customerMessage);
        event(new ChatMessageSent($customerMessage));

        if ($assignedStaffId !== null) {
            return ['messages' => $created, 'state' => $this->conversationState($customer)];
        }

        if ($this->shouldCreateAiAssistanceNotice($latest, $conversationStatus)) {
            $created->push($this->createSystemMessage(
                customer: $customer,
                message: 'No support staff are available right now. MJJD AI Assistant will assist for now while you wait.',
                assignedStaff: null,
                status: $conversationStatus,
                handoffStatus: 'ai_assisting'
            ));
        }

        $aiPrompt = $message !== '' ? $message : 'The customer uploaded an image attachment.';
        $ai = $this->ai->generateSupportReply($customer, $aiPrompt);
        $responseStatus = $conversationStatus === 'staff_inactive'
            ? 'staff_inactive'
            : 'ai_active';

        $aiMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $assignedStaffId,
            'from_user_id' => null,
            'to_user_id' => $customer->id,
            'message' => $ai['answer'],
            'sender_type' => 'ai',
            'message_type' => 'text',
            'conversation_status' => $responseStatus,
            'handoff_status' => $responseStatus,
            'metadata' => $ai['metadata'] ?? null,
        ]);

        $created->push($aiMessage);
        event(new ChatMessageSent($aiMessage));

        return ['messages' => $created, 'state' => $this->conversationState($customer)];
    }

    public function handleStaffMessage(User $staff, User $customer, string $message, ?UploadedFile $attachment = null): array
    {
        $created = collect();

        abort_unless($this->canStaffReply($staff, $customer), 403);

        $message = trim($message);
        $displayMessage = $message !== '' ? $message : 'Image attachment';
        $attachmentMetadata = $this->storeChatAttachment($attachment);

        $agentMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'from_user_id' => $staff->id,
            'to_user_id' => $customer->id,
            'message' => $displayMessage,
            'sender_type' => 'agent',
            'message_type' => 'text',
            'conversation_status' => 'active_human',
            'handoff_status' => 'human_active',
            'metadata' => $attachmentMetadata,
        ]);

        $created->push($agentMessage);
        event(new ChatMessageSent($agentMessage));

        return ['messages' => $created, 'state' => $this->conversationState($customer)];
    }

    public function addInternalNote(User $staff, User $customer, string $message): ChatMessage
    {
        $note = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $this->latestConversationMessage($customer)?->assigned_staff_id,
            'from_user_id' => $staff->id,
            'to_user_id' => null,
            'message' => $message,
            'sender_type' => 'agent',
            'message_type' => 'internal_note',
            'conversation_status' => $this->latestConversationMessage($customer)?->conversation_status ?? 'waiting_for_human',
            'handoff_status' => 'internal_note',
        ]);

        event(new ChatMessageSent($note));

        return $note;
    }

    public function closeConversation(User $staff, User $customer): ChatMessage
    {
        return $this->createSystemMessage(
            customer: $customer,
            message: 'This support conversation has been marked as closed by ' . $staff->name . '.',
            assignedStaff: $this->latestConversationMessage($customer)?->assignedStaff,
            status: 'closed',
            handoffStatus: 'closed'
        );
    }

    public function reopenConversation(User $staff, User $customer): ChatMessage
    {
        $latest = $this->latestConversationMessage($customer);
        $assignedStaff = $latest?->assignedStaff;
        $status = $assignedStaff ? 'assigned' : 'waiting_for_human';

        return $this->createSystemMessage(
            customer: $customer,
            message: 'This support conversation has been reopened by ' . $staff->name . '.',
            assignedStaff: $assignedStaff,
            status: $status,
            handoffStatus: 'reopened'
        );
    }

    public function takeOver(User $staff, User $customer): ChatMessage
    {
        $latest = $this->latestConversationMessage($customer);

        if (! $this->shouldCreateConnectionMessage($latest, $staff)) {
            return $latest;
        }

        return $this->createSystemMessage(
            customer: $customer,
            message: 'You are now connected with ' . $staff->name . '.',
            assignedStaff: $staff,
            status: 'assigned',
            handoffStatus: 'transferred_to_human'
        );
    }

    protected function createSystemMessage(User $customer, string $message, ?User $assignedStaff, string $status, ?string $handoffStatus = null): ChatMessage
    {
        $systemMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $assignedStaff?->id,
            'from_user_id' => null,
            'to_user_id' => $customer->id,
            'message' => $message,
            'sender_type' => 'system',
            'message_type' => 'status',
            'conversation_status' => $status,
            'handoff_status' => $handoffStatus,
        ]);

        event(new ChatMessageSent($systemMessage));

        return $systemMessage;
    }
}

