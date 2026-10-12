<?php

use App\Models\AppNotification;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\SupportAiService;
use App\Services\SupportChatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('services.support_chat.presence_window_seconds', 90);
    config()->set('services.support_chat.max_active_conversations_per_staff', 1);
});
function makeSupportUser(string $role, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => $role,
        'chat_available' => true,
        'is_online' => true,
        'last_seen' => now(),
    ], $attributes));
}

test('shared chat composer starts compact and exposes automatic growth behavior', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('chat.index', absolute: false))
        ->assertOk()
        ->assertSee('data-chat-autogrow', false)
        ->assertSee('rows="1"', false)
        ->assertSee('data-chat-attachment-meta', false)
        ->assertSee('function resizeComposer()', false)
        ->assertSee("textarea.addEventListener('input', resizeComposer)", false);

    $css = file_get_contents(public_path('css/chat.css'));

    expect($css)
        ->toContain('body.layout.is-application-page .chat-ui .chat-ui__composer .chat-ui__composer-input textarea[data-chat-autogrow]')
        ->toContain('min-height: 3rem;')
        ->toContain('min-width: 0;')
        ->toContain('.chat-ui__send-button {')
        ->toContain('align-self: center;')
        ->toContain('.chat-ui__panel-head {')
        ->toContain('flex: 0 0 auto;')
        ->toContain('background: rgba(11, 24, 48, 0.98);')
        ->toContain('.chat-ui__panel-grid {')
        ->toContain('grid-template-rows: minmax(0, 1fr);')
        ->toContain('body.layout.is-application-page .chat-ui .chat-ui__composer {')
        ->toContain('position: relative;')
        ->toContain('inset: auto;');

    $responsiveCss = file_get_contents(public_path('css/responsive.css'));

    expect($responsiveCss)
        ->toContain('body.layout .chat-ui .chat-ui__thread-card {')
        ->toContain('height: 100%;')
        ->toContain('max-height: 100%;')
        ->not->toContain('height: clamp(22rem, calc(100dvh - 13rem), 40rem);');
});

test('shared customer queue keeps filters fixed while the conversation list scrolls', function () {
    $css = file_get_contents(public_path('css/chat.css'));

    expect($css)
        ->toContain('.chat-ui__sidebar-shell {')
        ->toContain('position: relative;')
        ->toContain('.chat-ui__sidebar {')
        ->toContain('position: absolute;')
        ->toContain('.chat-ui__conversation-list {')
        ->toContain('flex: 1 1 0;')
        ->toContain('overflow-y: auto;')
        ->toContain('overscroll-behavior-y: contain;')
        ->toContain('scrollbar-gutter: stable;')
        ->toContain('.chat-ui__sidebar-shell[open] .chat-ui__sidebar');
});

test('customer details disclosure uses directional chevrons instead of show and hide text', function () {
    $css = file_get_contents(public_path('css/chat.css'));

    expect($css)
        ->toContain(".chat-ui__context-card[open] .chat-ui__context-summary > span::before")
        ->toContain("content: '<';")
        ->toContain(".chat-ui__context-card:not([open]) .chat-ui__context-summary > span::before")
        ->toContain("content: '>';")
        ->not->toContain("content: 'Hide';")
        ->not->toContain("content: 'Show';");
});

test('no staff online sends customer to ai assistant', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $result = app(SupportChatService::class)->handleCustomerMessage($customer, 'What internet plans do you offer?');
    $latest = app(SupportChatService::class)->latestConversationMessage($customer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->conversation_status)->toBe('ai_active')
        ->and($result['state']['mode'])->toBe('ai_active')
        ->and($result['state']['customer_status_label'])->toBe('AI assistant is helping')
        ->and($result['messages']->contains(fn ($message) => $message->sender_type === 'system' && str_contains($message->message, 'MJJD AI Assistant will assist for now')))->toBeTrue();
});

test('one available technician gets assigned to the customer conversation', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');

    $result = app(SupportChatService::class)->handleCustomerMessage($customer, 'I need help with my service.');
    $latest = app(SupportChatService::class)->latestConversationMessage($customer);

    expect($latest->assigned_staff_id)->toBe($technician->id)
        ->and($latest->conversation_status)->toBe('assigned')
        ->and($result['state']['mode'])->toBe('assigned')
        ->and($result['state']['assigned_staff']->id)->toBe($technician->id);
});

test('customer chat messages create one refreshed unread sidebar notification for assigned staff', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'First message');
    $firstNotification = AppNotification::query()
        ->where('user_id', $technician->id)
        ->where('type', 'chat_message_staff')
        ->firstOrFail();

    $service->handleCustomerMessage($customer, 'Second message');

    expect(AppNotification::query()
        ->where('user_id', $technician->id)
        ->where('type', 'chat_message_staff')
        ->whereNull('read_at')
        ->count())->toBe(1)
        ->and($firstNotification->refresh()->data['message_id'])->not->toBeNull()
        ->and($firstNotification->sidebarSectionFor($technician))->toBe('chat');
});

test('staff chat replies create a customer live chat sidebar notification', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'I need help.');
    $service->handleStaffMessage($technician, $customer, 'I can help you.');

    $notification = AppNotification::query()
        ->where('user_id', $customer->id)
        ->where('type', 'chat_message_customer')
        ->firstOrFail();

    expect($notification->sidebarSectionFor($customer))->toBe('chat')
        ->and($notification->read_at)->toBeNull();
});

test('chat image attachment is stored and rendered for assigned support', function () {
    Storage::fake('public');

    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');

    $response = $this
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->actingAs($customer)
        ->post(route('chat.send', ['user' => $customer], false), [
            'message' => 'Please check this photo.',
            'chat_attachment' => UploadedFile::fake()->image('signal.png'),
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('messages.1.attachment_is_image', true);

    $message = ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('sender_type', 'customer')
        ->latest('id')
        ->firstOrFail();

    expect($message->attachmentPath())->toStartWith('chat-attachments/')
        ->and($message->attachmentUrl())->toContain('/storage/chat-attachments/');

    Storage::disk('public')->assertExists($message->attachmentPath());

    $this
        ->actingAs($technician)
        ->get(route('role.chat.conversation', ['role' => 'technician', 'user' => $customer], false))
        ->assertOk()
        ->assertSee('/storage/chat-attachments/', false)
        ->assertSee('chat-ui__message-attachment', false);
});

test('staff becoming available auto assigns the oldest unassigned conversation once', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'chat_available' => false,
        'is_online' => false,
        'last_seen' => null,
    ]);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'hi');
    expect($service->latestConversationMessage($customer)->assigned_staff_id)->toBeNull();

    $service->setAvailability($technician, true);
    $latest = $service->latestConversationMessage($customer);

    expect($latest->assigned_staff_id)->toBe($technician->id)
        ->and($latest->conversation_status)->toBe('assigned');

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('message', 'You are now connected to ' . $technician->name)
        ->count())->toBe(1);
});

test('staff can be marked away without becoming available for chat assignment', function () {
    $technician = makeSupportUser('technician');
    $customer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->setAvailability($technician, false);
    $technician->refresh();

    expect($technician->chatAvailabilityState())->toBe('away')
        ->and($technician->chatAvailabilityLabel())->toBe('Away')
        ->and($technician->is_online)->toBeTrue()
        ->and($technician->chat_available)->toBeFalse()
        ->and($technician->isEffectivelyChatAvailable())->toBeFalse();

    $result = $service->handleCustomerMessage($customer, 'I need help with my service.');
    $latest = $service->latestConversationMessage($customer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->assigned_staff_id)->toBeNull();
});

test('customer polling auto assigns an unassigned ai conversation when staff is available', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'chat_available' => false,
        'is_online' => false,
        'last_seen' => null,
    ]);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'What internet plans do you offer?');
    $technician->forceFill([
        'chat_available' => true,
        'is_online' => true,
        'last_seen' => now(),
    ])->save();

    $response = $this
        ->actingAs($customer)
        ->getJson(route('chat.snapshot', ['user' => $customer], absolute: false));

    $response
        ->assertOk()
        ->assertJsonPath('chat_state.status_label', 'Connected to support');

    $latest = $service->latestConversationMessage($customer);

    expect($latest->assigned_staff_id)->toBe($technician->id)
        ->and($latest->conversation_status)->toBe('assigned');
});

test('customer messages after auto assignment do not create reassignment loops or duplicate connection messages', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'chat_available' => false,
        'is_online' => false,
        'last_seen' => null,
    ]);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'hi');
    $service->setAvailability($technician, true);
    $service->handleCustomerMessage($customer, 'I still need help');
    $service->handleCustomerMessage($customer, 'following up');

    $latest = $service->latestConversationMessage($customer);

    expect($latest->assigned_staff_id)->toBe($technician->id)
        ->and($latest->conversation_status)->toBe('assigned');

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('message', 'You are now connected to ' . $technician->name)
        ->count())->toBe(1);
});

test('busy staff do not receive new auto assignments and ai keeps assisting the queued customer', function () {
    $busyTechnician = makeSupportUser('technician');
    $existingCustomer = User::factory()->create(['role' => 'customer']);
    $queuedCustomer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->assignConversation($busyTechnician, $existingCustomer, $busyTechnician, activate: true);

    $result = $service->handleCustomerMessage($queuedCustomer, 'How do I track my application?');
    $latest = $service->latestConversationMessage($queuedCustomer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->assigned_staff_id)->toBeNull()
        ->and($latest->conversation_status)->toBe('ai_active')
        ->and($result['state']['mode'])->toBe('ai_active')
        ->and($result['state']['customer_status_label'])->toBe('AI assistant is helping')
        ->and($result['state']['quick_replies'])->not->toBeEmpty();
});

test('unassigned waiting conversations show ai assistant and quick replies', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    ChatMessage::create([
        'customer_id' => $customer->id,
        'assigned_staff_id' => null,
        'from_user_id' => $customer->id,
        'to_user_id' => null,
        'message' => 'Can someone help me?',
        'sender_type' => 'customer',
        'message_type' => 'text',
        'conversation_status' => 'waiting_for_human',
        'handoff_status' => 'waiting_for_human',
    ]);

    $state = app(SupportChatService::class)->conversationState($customer);

    expect($state['assigned_staff'])->toBeNull()
        ->and($state['mode'])->toBe('ai_active')
        ->and($state['identity_name'])->toBe('MJJD AI Assistant')
        ->and($state['customer_status_label'])->toBe('AI assistant is helping')
        ->and($state['quick_replies'])->not->toBeEmpty();
});

test('inactive assigned conversations show ai assistant and quick replies before next customer message', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'I need help with my account.');

    $technician->forceFill([
        'last_seen' => now()->subSeconds(180),
        'is_online' => false,
    ])->save();

    $state = $service->conversationState($customer);

    expect($state['assigned_staff'])->toBeNull()
        ->and($state['mode'])->toBe('ai_active')
        ->and($state['identity_name'])->toBe('MJJD AI Assistant')
        ->and($state['customer_status_label'])->toBe('AI assistant is helping')
        ->and($state['quick_replies'])->not->toBeEmpty();
});

test('inactive assigned technician falls back to ai assistance', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'I need help with my account.');
    makeSupportUser('admin');

    $technician->forceFill([
        'last_seen' => now()->subSeconds(180),
        'is_online' => false,
    ])->save();

    $result = $service->handleCustomerMessage($customer, 'How long does installation take?');
    $latest = $service->latestConversationMessage($customer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->assigned_staff_id)->toBeNull()
        ->and($latest->conversation_status)->toBe('ai_active')
        ->and($result['state']['mode'])->toBe('ai_active')
        ->and($result['state']['customer_status_label'])->toBe('AI assistant is helping')
        ->and($result['messages']->contains(fn ($message) => $message->sender_type === 'system' && str_contains($message->message, 'MJJD AI Assistant will assist for now')))->toBeTrue();

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('handoff_status', 'ai_assisting')
        ->count())->toBe(1);
});

test('admin can reassign a conversation and only the new technician sees it', function () {
    $admin = makeSupportUser('admin');
    $oldTechnician = makeSupportUser('technician');
    $newTechnician = makeSupportUser('technician');
    $customer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->assignConversation($admin, $customer, $oldTechnician);

    $response = $this
        ->actingAs($admin)
        ->post(route('role.chat.assign', ['role' => 'admin', 'user' => $customer], absolute: false), [
            'assigned_staff_id' => $newTechnician->id,
        ]);

    $response->assertRedirect();

    $this->actingAs($newTechnician)
        ->get(route('role.chat.index', ['role' => 'technician'], absolute: false))
        ->assertOk()
        ->assertSee($customer->name);

    $this->actingAs($oldTechnician)
        ->get(route('role.chat.index', ['role' => 'technician'], absolute: false))
        ->assertOk()
        ->assertDontSee($customer->name);

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('message', 'like', '%reassigned and you are now connected to support ' . $newTechnician->name . '%')
        ->exists())->toBeTrue();
});

test('admin must assign conversation to self before replying', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'chat_available' => false,
        'is_online' => false,
        'last_seen' => null,
    ]);
    $customer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'I need help with my connection.');

    $this
        ->actingAs($admin)
        ->post(route('role.chat.send', ['role' => 'admin', 'user' => $customer], false), [
            'message' => 'I can help.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($admin)
        ->get(route('role.chat.conversation', ['role' => 'admin', 'user' => $customer], false))
        ->assertOk()
        ->assertSee('Assign this conversation to yourself before replying.')
        ->assertSee('Assign to me');

    $this
        ->actingAs($admin)
        ->post(route('role.chat.assign', ['role' => 'admin', 'user' => $customer], false), [
            'assigned_staff_id' => $admin->id,
        ])
        ->assertRedirect();

    $latest = $service->latestConversationMessage($customer);

    expect($admin->refresh()->isEffectivelyChatAvailable())->toBeTrue()
        ->and($latest->assigned_staff_id)->toBe($admin->id)
        ->and($latest->conversation_status)->toBe('assigned');

    $this
        ->actingAs($admin)
        ->post(route('role.chat.send', ['role' => 'admin', 'user' => $customer], false), [
            'message' => 'I am now assigned to this chat.',
        ])
        ->assertRedirect();

    $latest = $service->latestConversationMessage($customer);

    expect($latest->from_user_id)->toBe($admin->id)
        ->and($latest->assigned_staff_id)->toBe($admin->id)
        ->and($latest->conversation_status)->toBe('active_human');
});

test('logged out technician does not receive new auto assigned chat', function () {
    $technician = makeSupportUser('technician');
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($technician)->post('/logout')->assertRedirect('/');

    $technician->refresh();

    expect($technician->is_online)->toBeFalse()
        ->and($technician->chat_available)->toBeFalse()
        ->and($technician->last_seen)->toBeNull()
        ->and($technician->chatAvailabilityState())->toBe('offline')
        ->and($technician->chatAvailabilityLabel())->toBe('Offline');

    $result = app(SupportChatService::class)->handleCustomerMessage($customer, 'What internet plans do you offer?');
    $latest = app(SupportChatService::class)->latestConversationMessage($customer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->assigned_staff_id)->toBeNull()
        ->and($latest->conversation_status)->toBe('ai_active')
        ->and($result['state']['customer_status_label'])->toBe('AI assistant is helping');
});

test('closed conversation does not preserve stale staff assignment for a new customer message', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');
    $service = app(SupportChatService::class);

    $service->assignConversation($technician, $customer, $technician);
    $service->closeConversation($technician, $customer);

    $technician->forceFill([
        'is_online' => false,
        'chat_available' => false,
        'last_seen' => null,
    ])->save();

    $result = $service->handleCustomerMessage($customer, 'I have a new support concern.');
    $latest = $service->latestConversationMessage($customer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->assigned_staff_id)->toBeNull()
        ->and($latest->conversation_status)->toBe('ai_active');
});

test('programmed greeting response is returned before external ai fallback', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'hi');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('Hello! I can help with applications, installation schedules, support tickets, account concerns, and service questions. What do you need help with?');
});

test('programmed availability response is returned for support availability questions', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'is anyone available?');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('Support staff may be unavailable or assisting other customers. I can help with common questions while you wait, and a staff member can take over when available.');
});

test('programmed application response is returned for application questions', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'how to apply?');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('You can submit a service application from the Apply page. The form will guide you through the required customer information, service address, plan, and identity documents.');
});

test('programmed ticket response is returned for service issue questions', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'no internet');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('Check that the modem or router has power and all cables are securely connected. Restart the router once. If service does not return, create a support ticket for investigation.');
});

test('expanded programmed matcher recognizes support intents without changing quick replies', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $ai = app(SupportAiService::class);
    $cases = [
        'why was my application rejected?' => 'rejected_application',
        'can I apply again?' => 'reapply',
        'the scan id says it cannot read id' => 'ocr_id_reading',
        'how do I upload id picture?' => 'id_upload',
        'what documents do I need?' => 'application_requirements',
        'it says I already applied' => 'duplicate_application',
        'how do I track my application?' => 'application_status',
        'I need a new connection' => 'apply_for_service',
        'is the service available in my area?' => 'coverage_availability',
        'the installer is not here' => 'technician_late',
        'why is there no technician available?' => 'no_technician_available',
        'can I change schedule?' => 'reschedule_installation',
        'who is assigned to install it?' => 'technician_assigned',
        'when is my installation date?' => 'installation_schedule',
        'my ticket is not assigned' => 'ticket_queued',
        'what is my ticket status?' => 'ticket_status',
        'my internet keeps disconnecting' => 'intermittent_connection',
        'the router has a red light' => 'router_issue',
        'the connection is buffering' => 'slow_internet',
        'I forgot my password' => 'forgot_password',
        'I cannot login' => 'login_problem',
        'where can I update profile?' => 'update_profile',
        'there is nobody online' => 'no_staff_online',
        'are you a bot?' => 'ai_limitations',
        'please transfer chat to another staff member' => 'chat_transfer',
        'this is an urgent issue' => 'emergency_urgent',
        'I want to cancel installation' => 'cancellation',
    ];

    foreach ($cases as $message => $intent) {
        $reply = $ai->generateSupportReply($customer, $message);
        expect($reply['source'])->toBe('programmed')
            ->and($reply['metadata']['intent'])->toBe($intent);
    }

    expect($ai->quickReplies())->toBe([
        'What internet plans do you offer?',
        'How long does installation take?',
        'How do I track my application?',
        'How do I file a support ticket?',
        'What areas do you serve?',
    ]);
});

test('short keywords use phrase boundaries instead of matching inside other words', function () {
    Http::fake(fn () => throw new RuntimeException('Ollama unavailable'));

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'this question is outside the supported topics');

    expect($reply['metadata']['intent'] ?? null)->not->toBe('greeting');
});

test('unknown questions fall back to the default support guidance when external ai is unavailable', function () {
    Http::fake(fn () => throw new RuntimeException('Ollama unavailable'));

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'what is your refund matrix?');

    expect($reply['source'])->toBe('fallback')
        ->and($reply['answer'])->toBe('I\'m not fully sure about that yet, but I can help with applications, installation schedules, tracking, tickets, and available plans. A staff member can also take over when available.');
});

test('customer replies do not auto reassign a conversation after admin takes over', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'hi');
    $admin = makeSupportUser('admin');
    $technician = makeSupportUser('technician');
    $service->takeOver($admin, $customer);
    $service->handleStaffMessage($admin, $customer, 'hello');

    $result = $service->handleCustomerMessage($customer, 'I still need help');
    $latest = $service->latestConversationMessage($customer);

    expect($latest->assigned_staff_id)->toBe($admin->id)
        ->and($latest->conversation_status)->toBe('active_human')
        ->and($result['state']['assigned_staff']->id)->toBe($admin->id);

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('message', 'You are now connected with ' . $admin->name . '.')
        ->count())->toBe(1);

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('assigned_staff_id', $technician->id)
        ->exists())->toBeFalse();
});

test('manual reassignment to technician stays locked on later customer replies', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'hi');
    $admin = makeSupportUser('admin');
    $technician = makeSupportUser('technician');
    $service->takeOver($admin, $customer);
    $service->assignConversation($admin, $customer, $technician);
    $service->handleStaffMessage($technician, $customer, 'I can help with that.');

    $service->handleCustomerMessage($customer, 'thanks, I have another question');
    $service->handleCustomerMessage($customer, 'following up again');

    $latest = $service->latestConversationMessage($customer);

    expect($latest->assigned_staff_id)->toBe($technician->id)
        ->and($latest->conversation_status)->toBe('active_human');

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('message', 'like', '%reassigned and you are now connected to support ' . $technician->name . '%')
        ->exists())->toBeTrue();
});

test('taking over twice with the same staff member does not create duplicate connection messages', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'hello');
    $admin = makeSupportUser('admin');
    $service->takeOver($admin, $customer);
    $service->takeOver($admin, $customer);

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('message', 'You are now connected with ' . $admin->name . '.')
        ->count())->toBe(1);
});
