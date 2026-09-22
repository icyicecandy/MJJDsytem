<?php

use App\Models\Ticket;
use App\Models\User;

test('administrator ticket queue filters by urgency and technician', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $firstTechnician = User::factory()->create(['role' => 'technician', 'name' => 'First Queue Technician']);
    $secondTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Second Queue Technician']);

    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $firstTechnician->id,
        'subject' => 'Visible high priority concern',
        'description' => 'Should match both filters.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $firstTechnician->id,
        'subject' => 'Hidden low priority concern',
        'description' => 'Wrong urgency.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_LOW,
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $secondTechnician->id,
        'subject' => 'Hidden other technician concern',
        'description' => 'Wrong technician.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', [
            'urgency' => Ticket::URGENCY_HIGH,
            'technician' => $firstTechnician->id,
        ], false))
        ->assertOk()
        ->assertViewHas('queueSummary', fn (array $summary): bool => $summary['open'] === 1)
        ->assertSee('Visible high priority concern')
        ->assertDontSee('Hidden low priority concern')
        ->assertDontSee('Hidden other technician concern')
        ->assertSee('ticket-filter-toolbar--admin', false)
        ->assertSee('Filter tickets by status');
});

test('technician ticket queue combines status and urgency filters', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Visible assigned high concern',
        'description' => 'Should match both filters.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Hidden assigned low concern',
        'description' => 'Wrong urgency.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_LOW,
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Hidden closed high concern',
        'description' => 'Wrong status.',
        'status' => Ticket::STATUS_CLOSED,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);

    $this->actingAs($technician)
        ->get(route('technician.tickets.index', [
            'status' => 'open',
            'urgency' => Ticket::URGENCY_HIGH,
        ], false))
        ->assertOk()
        ->assertViewHas('queueSummary', fn (array $summary): bool => $summary['open'] === 1)
        ->assertSee('Visible assigned high concern')
        ->assertDontSee('Hidden assigned low concern')
        ->assertDontSee('Hidden closed high concern')
        ->assertSee('ticket-filter-toolbar--technician', false)
        ->assertSee('Filter assigned tickets by status');
});