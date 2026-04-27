<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SupportChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(protected SupportChatService $supportChat)
    {
    }

    protected function guardRoleRoute(): void
    {
        $routeRole = request()->route('role');

        if ($routeRole !== null && $routeRole !== auth()->user()->role) {
            abort(404);
        }
    }

    protected function resolveConversationUser(mixed $roleOrUser, ?User $user = null): User
    {
        if ($roleOrUser instanceof User) {
            return $roleOrUser;
        }

        if ($user instanceof User) {
            return $user;
        }

        abort(404);
    }

    public function index()
    {
        $this->guardRoleRoute();

        $me = auth()->user();
        $this->supportChat->markPresence($me);
        $search = trim((string) request()->query('search', ''));
        $scope = trim((string) request()->query('scope', ''));

        if ($me->isCustomer()) {
            $this->supportChat->autoAssignCustomerConversation($me);
            $partner = $this->supportChat->resolveCustomerPartner($me);
            $messages = $this->supportChat->conversationMessages($me, $me, $partner);
            $chatState = $this->supportChat->conversationState($me);
            $customerContext = $this->supportChat->customerSupportContext($me);

            return view('chat.conversation', [
                'user' => $partner ?? $me,
                'messages' => $messages,
                'chatState' => $chatState,
                'quickReplies' => $chatState['quick_replies'],
                'customer' => $me,
                'activeConversationUser' => $partner,
                'customerContext' => $customerContext,
            ]);
        }

        $conversations = $this->supportChat->filteredSupportConversationList($me, $search, $scope);
        $conversationOverview = $this->supportChat->conversationOverview($conversations);

        return view('chat.index', compact('conversations', 'conversationOverview', 'search', 'scope'));
    }

    public function conversation(mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $user = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        $this->supportChat->markPresence($me);

        if ($me->isCustomer()) {
            $this->supportChat->autoAssignCustomerConversation($me);
            $partner = $this->supportChat->resolveCustomerPartner($me) ?? $user;
            $messages = $this->supportChat->conversationMessages($me, $me, $partner);
            $chatState = $this->supportChat->conversationState($me);
            $customerContext = $this->supportChat->customerSupportContext($me);

            return view('chat.conversation', [
                'user' => $partner,
                'messages' => $messages,
                'chatState' => $chatState,
                'quickReplies' => $chatState['quick_replies'],
                'customer' => $me,
                'activeConversationUser' => $partner,
                'customerContext' => $customerContext,
            ]);
        }

        abort_unless($user->isCustomer(), 404);
        abort_unless($this->supportChat->canStaffViewConversation($me, $user), 403);

        $messages = $this->supportChat->conversationMessages($user, $me);
        $chatState = $this->supportChat->conversationState($user);
        $search = trim((string) request()->query('search', ''));
        $scope = trim((string) request()->query('scope', ''));
        $conversations = $this->supportChat->filteredSupportConversationList($me, $search, $scope);
        $assignableStaff = $me->isAdmin() ? $this->supportChat->availableAssigneesForCustomer($user) : collect();
        $conversationOverview = $this->supportChat->conversationOverview($conversations);
        $customerContext = $this->supportChat->customerSupportContext($user);

        return view('chat.conversation', [
            'user' => $user,
            'messages' => $messages,
            'chatState' => $chatState,
            'quickReplies' => $chatState['quick_replies'],
            'conversations' => $conversations,
            'assignableStaff' => $assignableStaff,
            'customer' => $user,
            'activeConversationUser' => $user,
            'conversationOverview' => $conversationOverview,
            'customerContext' => $customerContext,
            'search' => $search,
            'scope' => $scope,
        ]);
    }

    public function snapshot(mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $user = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        $this->supportChat->markPresence($me);

        if ($me->isCustomer()) {
            $this->supportChat->autoAssignCustomerConversation($me);
            $partner = $this->supportChat->resolveCustomerPartner($me) ?? $user;
            $messages = $this->supportChat->conversationMessages($me, $me, $partner);
            $chatState = $this->supportChat->conversationState($me);

            return response()->json([
                'messages' => $this->serializeMessages($messages),
                'chat_state' => $this->serializeChatState($chatState),
            ]);
        }

        abort_unless($user->isCustomer(), 404);
        abort_unless($this->supportChat->canStaffViewConversation($me, $user), 403);

        $messages = $this->supportChat->conversationMessages($user, $me);
        $chatState = $this->supportChat->conversationState($user);
        $search = trim((string) request()->query('search', ''));
        $scope = trim((string) request()->query('scope', ''));
        $conversations = $this->supportChat->filteredSupportConversationList($me, $search, $scope);

        return response()->json([
            'messages' => $this->serializeMessages($messages),
            'chat_state' => $this->serializeChatState($chatState),
            'conversations' => $this->serializeConversations($conversations, $me, $user, $search, $scope),
        ]);
    }

    public function send(Request $request, mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $user = $this->resolveConversationUser($roleOrUser, $user);

        $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $me = Auth::user();
        $this->supportChat->markPresence($me);

        if (! $me->isCustomer()) {
            abort_unless($this->supportChat->canStaffViewConversation($me, $user), 403);
        }

        $result = $me->isCustomer()
            ? $this->supportChat->handleCustomerMessage($me, $request->message)
            : $this->supportChat->handleStaffMessage($me, $user, $request->message);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'ok',
                'messages' => $this->serializeMessages($result['messages']),
                'chat_state' => $this->serializeChatState($result['state']),
            ]);
        }

        return back();
    }

    public function takeOver(mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $user = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        abort_unless($me->isAdmin() || $me->isTechnician(), 403);
        abort_unless($user->isCustomer(), 404);

        if ($me->isTechnician()) {
            abort_unless($this->supportChat->canTechnicianTakeOver($me, $user), 403);
        }

        $this->supportChat->markPresence($me);
        $this->supportChat->takeOver($me, $user);

        return back()->with('success', 'Conversation assigned to ' . $me->name . '.');
    }

    public function assign(Request $request, mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $customer = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        abort_unless($me->isAdmin(), 403);
        abort_unless($customer->isCustomer(), 404);

        $validated = $request->validate([
            'assigned_staff_id' => ['required', 'exists:users,id'],
        ]);

        $staff = User::query()
            ->whereIn('role', ['admin', 'technician'])
            ->where('id', $validated['assigned_staff_id'])
            ->firstOrFail();

        if (! $staff->isEffectivelyChatAvailable()) {
            return back()->withErrors([
                'assigned_staff_id' => 'Selected staff member is not currently available for chat.',
            ]);
        }

        $this->supportChat->markPresence($me);
        $this->supportChat->assignConversation($me, $customer, $staff);

        return back()->with('success', 'Conversation reassigned to ' . $staff->name . '.');
    }

    public function updateAvailability(Request $request)
    {
        $this->guardRoleRoute();

        $me = Auth::user();
        abort_unless($me->isAdmin() || $me->isTechnician(), 403);

        $validated = $request->validate([
            'chat_available' => ['required', 'boolean'],
        ]);

        $this->supportChat->setAvailability($me, (bool) $validated['chat_available']);

        return back()->with('success', 'Chat availability updated.');
    }

    public function addNote(Request $request, mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $customer = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        abort_unless($me->isAdmin() || $me->isTechnician(), 403);
        abort_unless($customer->isCustomer(), 404);
        abort_unless($this->supportChat->canStaffViewConversation($me, $customer), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $this->supportChat->markPresence($me);
        $this->supportChat->addInternalNote($me, $customer, $validated['message']);

        return back()->with('success', 'Internal note added.');
    }

    public function closeConversation(mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $customer = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        abort_unless($me->isAdmin() || $me->isTechnician(), 403);
        abort_unless($customer->isCustomer(), 404);
        abort_unless($this->supportChat->canStaffViewConversation($me, $customer), 403);

        $this->supportChat->markPresence($me);
        $this->supportChat->closeConversation($me, $customer);

        return back()->with('success', 'Conversation closed.');
    }

    public function reopenConversation(mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $customer = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        abort_unless($me->isAdmin() || $me->isTechnician(), 403);
        abort_unless($customer->isCustomer(), 404);
        abort_unless($this->supportChat->canStaffViewConversation($me, $customer), 403);

        $this->supportChat->markPresence($me);
        $this->supportChat->reopenConversation($me, $customer);

        return back()->with('success', 'Conversation reopened.');
    }

    protected function serializeMessages($messages)
    {
        return $messages->map(fn ($msg) => [
            'id' => $msg->id,
            'message' => $msg->message,
            'sender_type' => $msg->sender_type,
            'message_type' => $msg->message_type,
            'sender_label' => $msg->senderLabel(),
            'sender_role' => optional($msg->fromUser)->role,
            'conversation_status' => $msg->conversation_status,
            'assigned_staff_id' => $msg->assigned_staff_id,
            'assigned_staff_name' => optional($msg->assignedStaff)->name,
            'from_user_id' => $msg->from_user_id,
            'created_at' => $msg->created_at->displayTime(),
        ])->values();
    }

    protected function serializeChatState(array $chatState): array
    {
        $viewer = Auth::user();

        return [
            'mode' => $chatState['mode'] ?? null,
            'status_label' => $viewer?->isCustomer()
                ? ($chatState['customer_status_label'] ?? $chatState['status_label'] ?? null)
                : ($chatState['status_label'] ?? null),
            'identity_name' => $chatState['identity_name'] ?? null,
            'identity_role' => $chatState['identity_role'] ?? null,
            'assigned_staff_name' => optional($chatState['assigned_staff'] ?? null)->name,
            'quick_replies' => $chatState['quick_replies'] ?? [],
        ];
    }

    protected function serializeConversations($conversations, User $viewer, User $activeUser, string $search, string $scope)
    {
        return $conversations->map(function (array $conversation) use ($viewer, $activeUser, $search, $scope) {
            $customer = $conversation['customer'];
            $latest = $conversation['latest_message'];

            return [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'status_label' => $conversation['status_label'],
                'status_class' => $conversation['status_class'],
                'assigned_staff_name' => $conversation['assigned_staff']?->name,
                'is_mine' => $conversation['is_mine'] ?? false,
                'last_activity' => $conversation['last_activity'] ?? null,
                'queue_age' => $conversation['queue_age'] ?? null,
                'application_reference' => $conversation['application_reference'] ?? null,
                'open_ticket_count' => $conversation['open_ticket_count'] ?? 0,
                'latest_message' => $latest?->message,
                'is_active' => $customer->id === $activeUser->id,
                'url' => route('role.chat.conversation', [
                    'role' => $viewer->role,
                    'user' => $customer,
                    'search' => $search,
                    'scope' => $scope,
                ]),
            ];
        })->values();
    }
}
