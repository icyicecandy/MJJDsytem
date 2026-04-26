<?php

use App\Models\Ticket;
use App\Models\User;

test('technician dashboard shows assigned active tickets and correct count', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $openTicket = Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'No Internet Connection',
        'description' => 'Connection is down.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);

    $queuedTicket = Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Installation Follow-up',
        'description' => 'Need a schedule update.',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_MEDIUM,
    ]);

    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Old Closed Ticket',
        'description' => 'Already resolved.',
        'status' => Ticket::STATUS_CLOSED,
        'urgency' => Ticket::URGENCY_LOW,
    ]);

    $response = $this
        ->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false));

    $response
        ->assertOk()
        ->assertViewHas('assignedTickets', 2)
        ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 2)
        ->assertSee($openTicket->publicReference())
        ->assertSee($queuedTicket->publicReference())
        ->assertSee('No Internet Connection')
        ->assertSee('Installation Follow-up');
});

test('technician dashboard shows empty state when no active assigned tickets exist', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    $response = $this
        ->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false));

    $response
        ->assertOk()
        ->assertViewHas('assignedTickets', 0)
        ->assertViewHas('tickets', fn ($tickets) => $tickets->isEmpty())
        ->assertSee('No assigned tickets right now. New customer issues will appear here once assigned.');
});
