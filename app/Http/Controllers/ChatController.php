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

        if ($me->isCustomer()) {
            $partner = $this->supportChat->resolveCustomerPartner($me);
            $messages = $this->supportChat->conversationMessages($me, $partner);
            $chatState = $this->supportChat->conversationState($me);

            return view('chat.conversation', [
                'user' => $partner ?? $me,
                'messages' => $messages,
                'chatState' => $chatState,
                'quickReplies' => $chatState['quick_replies'],
                'customer' => $me,
                'activeConversationUser' => $partner,
            ]);
        }

        $conversations = $this->supportChat->supportConversationList($me);

        return view('chat.index', compact('conversations'));
    }

    public function conversation(mixed $roleOrUser, ?User $user = null)
    {
        $this->guardRoleRoute();
        $user = $this->resolveConversationUser($roleOrUser, $user);

        $me = Auth::user();
        $this->supportChat->markPresence($me);

        if ($me->isCustomer()) {
            $partner = $this->supportChat->resolveCustomerPartner($me) ?? $user;
            $messages = $this->supportChat->conversationMessages($me, $partner);
            $chatState = $this->supportChat->conversationState($me);

            return view('chat.conversation', [
                'user' => $partner,
                'messages' => $messages,
                'chatState' => $chatState,
                'quickReplies' => $chatState['quick_replies'],
                'customer' => $me,
                'activeConversationUser' => $partner,
            ]);
        }

        abort_unless($user->isCustomer(), 404);
        abort_unless($this->supportChat->canStaffViewConversation($me, $user), 403);

        $messages = $this->supportChat->conversationMessages($user, $me);
        $chatState = $this->supportChat->conversationState($user);
        $conversations = $this->supportChat->supportConversationList($me);
        $assignableStaff = $me->isAdmin() ? $this->supportChat->availableAssigneesForCustomer($user) : collect();

        return view('chat.conversation', [
            'user' => $user,
            'messages' => $messages,
            'chatState' => $chatState,
            'quickReplies' => $chatState['quick_replies'],
            'conversations' => $conversations,
            'assignableStaff' => $assignableStaff,
            'customer' => $user,
            'activeConversationUser' => $user,
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
                'messages' => $result['messages']->map(fn ($msg) => [
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
                    'created_at' => $msg->created_at->format('M d, h:i A'),
                ])->values(),
                'chat_state' => $result['state'],
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
}
