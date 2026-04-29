<?php

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
        ->and($reply['answer'])->toBe('Hello! I\'m the MJJD Telecom assistant. I can help with common questions while our support team is unavailable or assisting other customers.');
});

test('programmed availability response is returned for support availability questions', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'is anyone available?');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('Our support team may be unavailable or assisting other customers right now. I can help with common questions while you wait, and a staff member can take over when available.');
});

test('programmed application response is returned for application questions', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'how to apply?');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('To apply, submit your contact information, service address, selected plan, and two valid ID photos: one photo of the ID placed on paper with three signatures, and one photo of the customer holding the same ID. The admin will review serviceability before scheduling installation.');
});

test('programmed ticket response is returned for service issue questions', function () {
    Http::preventStrayRequests();

    $customer = User::factory()->create(['role' => 'customer']);
    $reply = app(SupportAiService::class)->generateSupportReply($customer, 'no internet');

    expect($reply['source'])->toBe('programmed')
        ->and($reply['answer'])->toBe('You can submit a support ticket with your issue details. Our staff will review it and update the ticket status.');
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
