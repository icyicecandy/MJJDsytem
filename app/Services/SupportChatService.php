<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SupportChatService
{
    public function __construct(protected SupportAiService $ai)
    {
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
    }

    public function setAvailability(User $user, bool $available): void
    {
        $user->forceFill([
            'chat_available' => $available,
            'is_online' => $available,
            'last_seen' => now(),
        ])->save();
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
        $threshold = Carbon::now()->subMinutes(5);

        return User::query()
            ->whereIn('role', ['admin', 'technician'])
            ->where('chat_available', true)
            ->where(function ($query) use ($threshold) {
                $query->where('is_online', true)
                    ->orWhere('last_seen', '>=', $threshold);
            })
            ->orderBy('name')
            ->get();
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
            ->filter(fn (ChatMessage $message) => $message->assigned_staff_id !== null && in_array($message->conversation_status, ['assigned', 'active_human'], true))
            ->countBy(fn (ChatMessage $message) => $message->assigned_staff_id);
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
        $available = $this->availableSupportUsers();

        if ($available->isEmpty()) {
            return null;
        }

        $latest = $this->latestConversationMessage($customer);
        if ($latest?->assigned_staff_id) {
            $currentAssignee = $available->firstWhere('id', $latest->assigned_staff_id);
            if ($currentAssignee && in_array($latest->conversation_status, ['assigned', 'active_human'], true)) {
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
        if ($latest?->assignedStaff) {
            return $latest->assignedStaff;
        }

        return $this->resolvePreferredStaff($customer);
    }

    public function conversationMessages(User $customer, ?User $partner = null): Collection
    {
        $messages = ChatMessage::query()
            ->with(['fromUser', 'toUser', 'assignedStaff', 'customer'])
            ->where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->get();

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
        $mode = $latest?->conversation_status ?? ($this->resolvePreferredStaff($customer) ? 'assigned' : 'ai_active');

        if ($mode === 'assigned' && ! $assignedStaff) {
            $assignedStaff = $this->resolvePreferredStaff($customer);
        }

        $identityName = match ($mode) {
            'assigned', 'active_human' => $assignedStaff?->name ? 'Connected to: ' . $assignedStaff->name : 'Connected to: Support Agent',
            'waiting_for_human' => 'Waiting for Human Support',
            'closed' => 'Conversation Closed',
            default => 'MJJD AI Assistant',
        };

        $identityRole = match ($mode) {
            'assigned' => ucfirst($assignedStaff?->role ?? 'agent') . ' Assigned',
            'active_human' => ucfirst($assignedStaff?->role ?? 'agent'),
            'waiting_for_human' => 'Queue Status',
            'closed' => 'Closed',
            default => 'AI Assistant',
        };

        $statusLabel = match ($mode) {
            'assigned' => 'Connected to Assigned Staff',
            'active_human' => 'Human Support Active',
            'waiting_for_human' => 'Waiting for Human Support',
            'closed' => 'Conversation Closed',
            default => 'MJJD AI Assistant',
        };

        $statusClass = match ($mode) {
            'active_human' => 'success',
            'assigned' => 'info',
            'waiting_for_human' => 'warning',
            'closed' => 'secondary',
            default => 'info',
        };

        return [
            'mode' => $mode,
            'identity_name' => $identityName,
            'identity_role' => $identityRole,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'assigned_staff' => $assignedStaff,
            'available_staff_count' => $availableStaff->count(),
            'quick_replies' => $mode === 'ai_active' ? $this->quickReplies() : [],
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

            return [
                'customer' => $customer,
                'latest_message' => $message,
                'status_label' => $state['status_label'],
                'status_class' => $state['status_class'],
                'assigned_staff' => $state['assigned_staff'],
                'is_mine' => $message->assigned_staff_id === $staff->id,
            ];
        })->sortByDesc(fn (array $item) => optional($item['latest_message'])->created_at)->values();
    }

    public function canStaffViewConversation(User $staff, User $customer): bool
    {
        if ($staff->isAdmin()) {
            return true;
        }

        $latest = $this->latestConversationMessage($customer);

        return $latest?->assigned_staff_id === $staff->id;
    }

    public function canTechnicianTakeOver(User $technician, User $customer): bool
    {
        if (! $technician->isTechnician() || ! $technician->chat_available) {
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
        return $this->createSystemMessage(
            customer: $customer,
            message: 'A support agent is now available. You are being connected to ' . $staff->name . '.',
            assignedStaff: $staff,
            status: $activate ? 'active_human' : 'assigned',
            handoffStatus: $actor->id === $staff->id ? 'transferred_to_human' : 'assigned_to_human'
        );
    }

    public function handleCustomerMessage(User $customer, string $message): array
    {
        $latest = $this->latestConversationMessage($customer);
        $preferredStaff = $this->resolvePreferredStaff($customer);
        $created = collect();

        if ($preferredStaff && (($latest?->assigned_staff_id !== $preferredStaff->id) || ! in_array($latest?->conversation_status, ['assigned', 'active_human'], true))) {
            $created->push($this->assignConversation($preferredStaff, $customer, $preferredStaff));
        }

        $customerMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $preferredStaff?->id,
            'from_user_id' => $customer->id,
            'to_user_id' => $preferredStaff?->id,
            'message' => $message,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'conversation_status' => $preferredStaff ? 'assigned' : 'ai_active',
            'handoff_status' => $preferredStaff ? 'assigned_to_human' : 'ai_active',
        ]);
        $created->push($customerMessage);
        event(new ChatMessageSent($customerMessage));

        if ($preferredStaff) {
            return ['messages' => $created, 'state' => $this->conversationState($customer)];
        }

        $ai = $this->ai->generateSupportReply($customer, $message);
        $aiStatus = $ai['source'] === 'fallback' ? 'waiting_for_human' : 'ai_active';

        $aiMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => null,
            'from_user_id' => null,
            'to_user_id' => $customer->id,
            'message' => $ai['answer'],
            'sender_type' => 'ai',
            'message_type' => 'text',
            'conversation_status' => $aiStatus,
            'handoff_status' => $aiStatus,
            'metadata' => $ai['metadata'] ?? null,
        ]);

        $created->push($aiMessage);
        event(new ChatMessageSent($aiMessage));

        return ['messages' => $created, 'state' => $this->conversationState($customer)];
    }

    public function handleStaffMessage(User $staff, User $customer, string $message): array
    {
        $created = collect();
        $latest = $this->latestConversationMessage($customer);

        if ($staff->isTechnician()) {
            abort_unless($latest?->assigned_staff_id === $staff->id, 403);
        }

        if (! $latest || $latest->assigned_staff_id !== $staff->id || ! in_array($latest->conversation_status, ['assigned', 'active_human'], true)) {
            $created->push($this->createSystemMessage(
                customer: $customer,
                message: 'You are now connected with ' . $staff->name . '.',
                assignedStaff: $staff,
                status: 'assigned',
                handoffStatus: 'transferred_to_human'
            ));
        }

        $agentMessage = ChatMessage::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'from_user_id' => $staff->id,
            'to_user_id' => $customer->id,
            'message' => $message,
            'sender_type' => 'agent',
            'message_type' => 'text',
            'conversation_status' => 'active_human',
            'handoff_status' => 'human_active',
        ]);

        $created->push($agentMessage);
        event(new ChatMessageSent($agentMessage));

        return ['messages' => $created, 'state' => $this->conversationState($customer)];
    }

    public function takeOver(User $staff, User $customer): ChatMessage
    {
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

