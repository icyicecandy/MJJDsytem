<?php

use App\Models\Ticket;
use App\Models\User;

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

test('customer creates ticket with open status', function () {
    $customer = makeTicketUser('customer');

    $this
        ->actingAs($customer)
        ->post(route('customer.tickets.store', absolute: false), [
            'subject' => 'no_internet_connection',
            'description' => 'Internet connection is down.',
        ])
        ->assertRedirect(route('customer.tickets.index', absolute: false));

    $ticket = Ticket::firstOrFail();

    expect($ticket->user_id)->toBe($customer->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN);
});

test('technician starts assigned open ticket', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket(['technician_id' => $technician->id]);

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

test('technician cannot resolve open ticket before starting work', function () {
    $technician = makeTicketUser('technician');
    $ticket = makeTicket(['technician_id' => $technician->id]);

    $this
        ->actingAs($technician)
        ->patch(route('technician.tickets.resolve', $ticket, false), [
            'resolution_notes' => 'Done.',
        ])
        ->assertStatus(422);

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
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN);
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
    $ticket = makeTicket([
        'customer' => $customer,
        'status' => Ticket::STATUS_RESOLVED,
    ]);

    $this
        ->actingAs($customer)
        ->patch(route('customer.tickets.reopen', $ticket, false), [
            'reason' => 'The connection dropped again.',
        ])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_IN_PROGRESS)
        ->and($ticket->messages()->where('message', 'like', '%The connection dropped again.%')->exists())->toBeTrue();
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
