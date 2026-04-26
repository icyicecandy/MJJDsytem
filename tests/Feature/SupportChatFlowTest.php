<?php

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\SupportAiService;
use App\Services\SupportChatService;
use Illuminate\Support\Facades\Http;

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
        ->and($result['state']['customer_status_label'])->toBe('AI assistant is helping');
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

test('busy staff do not receive new auto assignments and ai answers while queued', function () {
    $busyTechnician = makeSupportUser('technician');
    $existingCustomer = User::factory()->create(['role' => 'customer']);
    $queuedCustomer = User::factory()->create(['role' => 'customer']);
    $service = app(SupportChatService::class);

    $service->assignConversation($busyTechnician, $existingCustomer, $busyTechnician, activate: true);

    $result = $service->handleCustomerMessage($queuedCustomer, 'How do I track my application?');
    $latest = $service->latestConversationMessage($queuedCustomer);

    expect($result['messages']->last()->sender_type)->toBe('ai')
        ->and($latest->assigned_staff_id)->toBeNull()
        ->and($latest->conversation_status)->toBe('waiting_for_human')
        ->and($result['state']['mode'])->toBe('waiting_for_human')
        ->and($result['state']['customer_status_label'])->toBe('Waiting for support');
});

test('inactive assigned technician keeps assignment and marks conversation for attention', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = makeSupportUser('technician');
    $service = app(SupportChatService::class);

    $service->handleCustomerMessage($customer, 'I need help with my account.');

    $technician->forceFill([
        'last_seen' => now()->subSeconds(180),
        'is_online' => false,
    ])->save();

    $result = $service->handleCustomerMessage($customer, 'How long does installation take?');
    $latest = $service->latestConversationMessage($customer);

    expect($result['messages']->last()->sender_type)->toBe('customer')
        ->and($latest->assigned_staff_id)->toBe($technician->id)
        ->and($latest->conversation_status)->toBe('staff_inactive')
        ->and($result['state']['mode'])->toBe('staff_inactive')
        ->and($result['state']['status_label'])->toBe('Needs attention')
        ->and($result['state']['customer_status_label'])->toBe('Waiting for support');

    expect(ChatMessage::query()
        ->where('customer_id', $customer->id)
        ->where('message_type', 'status')
        ->where('conversation_status', 'staff_inactive')
        ->exists())->toBeTrue();
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
});

test('logged out technician does not receive new auto assigned chat', function () {
    $technician = makeSupportUser('technician');
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($technician)->post('/logout')->assertRedirect('/');

    $technician->refresh();

    expect($technician->is_online)->toBeFalse()
        ->and($technician->chat_available)->toBeFalse()
        ->and($technician->last_seen)->toBeNull();

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
        ->and($reply['answer'])->toBe('Hello! I’m the MJJD Telecom assistant. I can help with common questions while our support team is unavailable or assisting other customers.');
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
        ->and($reply['answer'])->toBe('To apply, submit your service application form with your contact information, service address, selected plan, and a valid ID. The admin will review serviceability before scheduling installation.');
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
        ->and($reply['answer'])->toBe('I’m not fully sure about that yet, but I can help with applications, installation schedules, tracking, tickets, and available plans. A staff member can also take over when available.');
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
        ->where('message', 'A support agent is now available. You are being connected to ' . $technician->name . '.')
        ->count())->toBe(1);
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
