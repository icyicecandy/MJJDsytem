<?php

use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeTicketUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

function makeTicket(array $attributes = []): Ticket
{
    $customer = $attributes['customer'] ?? makeTicketUser('customer');
    unset($attributes['customer']);

    return Ticket::create(array_merge([
        'user_id' => $customer->id,
        'subject' => 'No Internet Connection',
        'description' => 'Connection is down.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ], $attributes));
}

test('customer ticket waits in the priority queue when no technician is on shift', function () {
    Storage::fake('public');

    $customer = makeTicketUser('customer');

    $this
        ->actingAs($customer)
        ->post(route('customer.tickets.store', absolute: false), [
            'subject' => 'no_internet_connection',
            'description' => 'Internet connection is down.',
            'ticket_photo' => UploadedFile::fake()->image('ticket.png'),
        ])
        ->assertRedirect(route('customer.tickets.index', absolute: false));

    $ticket = Ticket::firstOrFail();

    expect($ticket->user_id)->toBe($customer->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_QUEUED)
        ->and($ticket->response_due_at)->not->toBeNull()
        ->and($ticket->photo_path)->toStartWith('ticket-attachments/');

    Storage::disk('public')->assertExists($ticket->photo_path);
});

test('ticket reply attachment is stored on the public disk and visible in detail', function () {
    Storage::fake('public');

    $customer = makeTicketUser('customer');
    $ticket = makeTicket(['customer' => $customer]);

    $this
        ->actingAs($customer)
        ->post(route('customer.tickets.reply', $ticket, false), [
            'message' => 'Here is the issue photo.',
            'ticket_attachments' => [
                UploadedFile::fake()->image('reply-a.png'),
                UploadedFile::fake()->image('reply-b.png'),
            ],
        ])
        ->assertRedirect();

    $message = $ticket->messages()->latest('id')->firstOrFail();

    expect($message->attachment_path)->toStartWith('ticket-attachments/')
        ->and($message->attachmentUrl())->toContain('/storage/ticket-attachments/')
        ->and($message->attachmentPaths())->toHaveCount(2);

    foreach ($message->attachmentPaths() as $path) {
        Storage::disk('public')->assertExists($path);
    }

    $this->actingAs($customer)
        ->get(route('customer.tickets.show', $ticket, false))
        ->assertOk()
        ->assertSee('/storage/ticket-attachments/', false)
        ->assertSee('View attachment');
});

test('customer creates ticket for new or another installation request', function () {
    $customer = makeTicketUser('customer');

    $this
        ->actingAs($customer)
        ->post(route('customer.tickets.store', absolute: false), [
            'subject' => 'new_or_another_installation_request',
            'description' => 'I need another installation at a different location.',
        ])
        ->assertRedirect(route('customer.tickets.index', absolute: false));

    $ticket = Ticket::firstOrFail();

    expect($ticket->subject)->toBe('Request for New/Another Installation')
        ->and($ticket->urgency)->toBe(Ticket::URGENCY_MEDIUM)
        ->and($ticket->status)->toBe(Ticket::STATUS_QUEUED)
        ->and($ticket->response_due_at)->not->toBeNull();
});

test('customer can search and filter support tickets', function () {
    $customer = makeTicketUser('customer');
    $technician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Fiber Agent',
    ]);

    $openTicket = makeTicket([
        'customer' => $customer,
        'subject' => 'No Internet Connection',
        'status' => Ticket::STATUS_OPEN,
    ]);
    $queuedTicket = makeTicket([
        'customer' => $customer,
        'subject' => 'Queued Follow-up',
        'status' => Ticket::STATUS_QUEUED,
    ]);
    $inProgressTicket = makeTicket([
        'customer' => $customer,
        'subject' => 'Router Signal Trouble',
        'status' => Ticket::STATUS_IN_PROGRESS,
        'technician_id' => $technician->id,
    ]);
    $resolvedTicket = makeTicket([
        'customer' => $customer,
        'subject' => 'Resolved Concern',
        'status' => Ticket::STATUS_RESOLVED,
    ]);
    $closedTicket = makeTicket([
        'customer' => $customer,
        'subject' => 'Closed Concern',
        'status' => Ticket::STATUS_CLOSED,
    ]);

    $this
        ->actingAs($customer)
        ->get(route('customer.tickets.index', ['status' => Ticket::STATUS_OPEN], false))
        ->assertOk()
        ->assertSee($openTicket->publicReference())
        ->assertSee($queuedTicket->publicReference())
        ->assertDontSee($inProgressTicket->publicReference())
        ->assertDontSee($resolvedTicket->publicReference())
        ->assertDontSee($closedTicket->publicReference());

    $this
        ->actingAs($customer)
        ->get(route('customer.tickets.index', ['search' => 'Fiber Agent'], false))
        ->assertOk()
        ->assertSee($inProgressTicket->publicReference())
        ->assertDontSee($openTicket->publicReference());
});

test('technician starts assigned open ticket', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket(['technician_id' => $technician->id, 'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '09:00']);

    $this
        ->actingAs($technician)
        ->patch(route('technician.tickets.start', $ticket, false))
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(Ticket::STATUS_IN_PROGRESS)
        ->and($ticket->messages()->where('message', 'Technician started work on this ticket.')->exists())->toBeTrue();
});

test('technician resolves in progress ticket with notes', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_IN_PROGRESS,
    ]);

    $this
        ->actingAs($technician)
        ->patch(route('technician.tickets.resolve', $ticket, false), [
            'resolution_notes' => 'Replaced damaged connector and restored signal.',
        ])
        ->assertRedirect(route('technician.tickets.index', absolute: false));

    $ticket->refresh();

    expect($ticket->status)->toBe(Ticket::STATUS_RESOLVED)
        ->and($ticket->messages()->where('message', 'like', '%Replaced damaged connector%')->exists())->toBeTrue();
});

test('technician ticket resolve success banner is rendered once', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_IN_PROGRESS,
    ]);

    $response = $this
        ->followingRedirects()
        ->actingAs($technician)
        ->patch(route('technician.tickets.resolve', $ticket, false), [
            'resolution_notes' => 'Restarted the ONU and verified the restored signal.',
        ]);

    $response->assertOk();

    expect(substr_count($response->getContent(), 'Ticket marked as resolved.'))->toBe(1);
});

test('technician cannot resolve open ticket before starting work', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket(['technician_id' => $technician->id]);

    $this
        ->actingAs($technician)
        ->patch(route('technician.tickets.resolve', $ticket, false), [
            'resolution_notes' => 'Done.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Start work on this ticket before marking it as resolved.');

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_OPEN);
});

test('admin assignment does not start work automatically', function () {
    $admin = makeTicketUser('admin');
    $technician = makeTicketUser('technician');
    $ticket = makeTicket();

    $this
        ->actingAs($admin)
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'technician_id' => $technician->id,
        ])
        ->assertRedirect(route('admin.tickets.index', absolute: false));

    $ticket->refresh();

    expect($ticket->technician_id)->toBe($technician->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->response_due_at)->not->toBeNull();
});

test('customer cannot close ticket before it is resolved', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket(['customer' => $customer]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.close', $ticket, false))
        ->assertStatus(422);

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_OPEN);
});

test('customer cancels open ticket and history is recorded', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket(['customer' => $customer]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.cancel', $ticket, false))
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CANCELLED)
        ->and($ticket->messages()->where('message', 'Customer cancelled this ticket.')->exists())->toBeTrue()
        ->and(SystemLog::where('action', 'Ticket Cancelled')->where('description', 'like', '%'.$ticket->publicReference().'%')->exists())->toBeTrue();
});

test('customer cannot cancel another customers ticket', function () {
    $owner = makeTicketUser('customer');
    $otherCustomer = makeTicketUser('customer');
    $ticket = makeTicket(['customer' => $owner]);

    $this
        ->actingAs($otherCustomer)
        ->patch(route('customer.tickets.cancel', $ticket, false))
        ->assertStatus(403);

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_OPEN);
});

test('cancelled ticket cannot receive new replies', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_CANCELLED,
    ]);

    $this
        ->actingAs($customer)
        ->post(route('customer.tickets.reply', $ticket, false), [
            'message' => 'Can anyone still see this?',
        ])
        ->assertStatus(422);

    expect($ticket->messages()->count())->toBe(0);
});

test('customer requests cancellation for in progress ticket', function () {
    $customer = makeTicketUser('customer');
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'customer' => $customer,
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_IN_PROGRESS,
    ]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.cancellation.request', $ticket, false))
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CANCELLATION_REQUESTED)
        ->and($ticket->messages()->where('message', 'Customer requested ticket cancellation.')->exists())->toBeTrue()
        ->and(SystemLog::where('action', 'Ticket Cancellation Requested')->where('description', 'like', '%'.$ticket->publicReference().'%')->exists())->toBeTrue();
});

test('technician cannot resolve while cancellation is pending', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_CANCELLATION_REQUESTED,
    ]);

    $this
        ->actingAs($technician)
        ->patch(route('technician.tickets.resolve', $ticket, false), [
            'resolution_notes' => 'Work completed.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Customer requested cancellation. Waiting for admin decision.');

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CANCELLATION_REQUESTED);
});

test('admin approves ticket cancellation request', function () {
    $admin = makeTicketUser('admin');
    $ticket = makeTicket([
        'status' => Ticket::STATUS_CANCELLATION_REQUESTED,
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.tickets.cancellation.approve', $ticket, false))
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CANCELLED)
        ->and($ticket->messages()->where('message', 'Admin approved ticket cancellation.')->exists())->toBeTrue()
        ->and(SystemLog::where('action', 'Ticket Cancellation Approved')->where('description', 'like', '%'.$ticket->publicReference().'%')->exists())->toBeTrue();
});

test('admin rejects ticket cancellation request', function () {
    $admin = makeTicketUser('admin');
    $ticket = makeTicket([
        'status' => Ticket::STATUS_CANCELLATION_REQUESTED,
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.tickets.cancellation.reject', $ticket, false))
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_IN_PROGRESS)
        ->and($ticket->messages()->where('message', 'Admin rejected cancellation request. Ticket remains In Progress.')->exists())->toBeTrue()
        ->and(SystemLog::where('action', 'Ticket Cancellation Rejected')->where('description', 'like', '%'.$ticket->publicReference().'%')->exists())->toBeTrue();
});

test('technician cannot approve or reject cancellation requests', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_CANCELLATION_REQUESTED,
    ]);

    $this
        ->actingAs($technician)
        ->patch(route('admin.tickets.cancellation.approve', $ticket, false))
        ->assertStatus(403);

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CANCELLATION_REQUESTED);
});

test('customer closes resolved ticket', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_RESOLVED,
    ]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.close', $ticket, false))
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CLOSED)
        ->and($ticket->messages()->where('message', 'Ticket closed by customer.')->exists())->toBeTrue();
});

test('customer reopens resolved ticket with a reason', function () {
    $customer = makeTicketUser('customer');
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'customer' => $customer,
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_RESOLVED,
    ]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.reopen', $ticket, false), [
            'reason' => 'The connection dropped again.',
        ])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->technician_id)->toBeNull()
        ->and($ticket->appointment_date)->toBeNull()
        ->and($ticket->messages()->where('message', 'like', 'Ticket reopened by customer and returned to the queue%')->exists())->toBeTrue();
});

test('customer cannot reopen closed ticket', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_CLOSED,
    ]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.reopen', $ticket, false), [
            'reason' => 'The connection dropped again.',
        ])
        ->assertStatus(422);

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_CLOSED)
        ->and($ticket->hasPendingReopenRequest())->toBeFalse();
});

test('admin approves closed ticket reopen request', function () {
    $admin = makeTicketUser('admin');
    $customer = makeTicketUser('customer');
    $technician = makeTicketUser('technician');
    $ticket = makeTicket([
        'customer' => $customer,
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_CLOSED,
        'reopen_requested_at' => now(),
        'reopen_reason' => 'Still no connection.',
    ]);

    $this
        ->actingAs($admin)
        ->patch(route('admin.tickets.reopen-request.approve', $ticket, false))
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->technician_id)->toBeNull()
        ->and($ticket->appointment_date)->toBeNull()
        ->and($ticket->hasPendingReopenRequest())->toBeFalse()
        ->and($ticket->reopen_reviewed_by)->toBe($admin->id)
        ->and($ticket->messages()->where('message', 'Ticket reopen request approved by admin. Returned to the queue for a new technician appointment.')->exists())->toBeTrue();
});

test('closed ticket cannot receive new replies', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_CLOSED,
    ]);

    $this
        ->actingAs($customer)
        ->post(route('customer.tickets.reply', $ticket, false), [
            'message' => 'Can anyone still see this?',
        ])
        ->assertStatus(422);

    expect($ticket->messages()->count())->toBe(0);
});

test('resolved ticket does not show cancellation actions', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_RESOLVED,
    ]);

    $this
        ->actingAs($customer)
        ->get(route('customer.tickets.show', $ticket, false))
        ->assertOk()
        ->assertSee('Close Ticket')
        ->assertSee('Reopen Ticket')
        ->assertDontSee('Cancel Ticket')
        ->assertDontSee('Request Cancellation');
});

test('closed ticket detail is read only', function () {
    $customer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_CLOSED,
    ]);

    $this
        ->actingAs($customer)
        ->get(route('customer.tickets.show', $ticket, false))
        ->assertOk()
        ->assertSee('This ticket is read-only.')
        ->assertDontSee('Cancel Ticket')
        ->assertDontSee('Request Cancellation')
        ->assertDontSee('Reopen Ticket')
        ->assertDontSee('Send Reply</button>', false);
});

test('technician cannot modify tickets not assigned to them', function () {
    $technician = makeTicketUser('technician');
    $otherTechnician = makeTicketUser('technician');
    $ticket = makeTicket(['technician_id' => $otherTechnician->id]);

    $this
        ->actingAs($technician)
        ->patch(route('technician.tickets.start', $ticket, false))
        ->assertStatus(403);

    $this
        ->actingAs($technician)
        ->post(route('technician.tickets.reply', $ticket, false), [
            'message' => 'Working on this.',
        ])
        ->assertStatus(403);

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_OPEN);
});

test('customer cannot access another customers ticket', function () {
    $owner = makeTicketUser('customer');
    $otherCustomer = makeTicketUser('customer');
    $ticket = makeTicket(['customer' => $owner]);

    $this
        ->actingAs($otherCustomer)
        ->get(route('customer.tickets.show', $ticket, false))
        ->assertStatus(403);
});

test('customer cannot close another customers resolved ticket', function () {
    $owner = makeTicketUser('customer');
    $otherCustomer = makeTicketUser('customer');
    $ticket = makeTicket([
        'customer' => $owner,
        'status' => Ticket::STATUS_RESOLVED,
    ]);

    $this
        ->actingAs($otherCustomer)
        ->patch(route('customer.tickets.close', $ticket, false))
        ->assertStatus(403);

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_RESOLVED);
});
