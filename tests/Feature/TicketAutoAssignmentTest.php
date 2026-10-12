<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-21 09:00:00', config('app.timezone')));
});

afterEach(function () {
    $this->travelBack();
});

function routingTechnician(array $attributes = []): User
{
    return User::factory()->create(array_replace([
        'role' => 'technician',
        'technician_status' => 'available',
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
        'technician_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
    ], $attributes));
}

function routingTicket(array $attributes = []): Ticket
{
    return Ticket::create(array_replace([
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'subject' => 'No Internet Connection',
        'description' => 'Routing test',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(4),
    ], $attributes));
}

test('known subjects are automatically assigned to the least loaded on-shift technician', function () {
    $busy = routingTechnician(['name' => 'Busy Technician']);
    $available = routingTechnician(['name' => 'Available Technician']);

    routingTicket(['technician_id' => $busy->id, 'status' => Ticket::STATUS_OPEN, 'assigned_at' => now()->subHour()]);
    routingTicket(['technician_id' => $busy->id, 'status' => Ticket::STATUS_IN_PROGRESS, 'assigned_at' => now()->subMinutes(30)]);

    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->post(route('customer.tickets.store'), [
        'subject' => 'no_internet_connection',
        'description' => 'The modem has no connection.',
    ])->assertSessionHasNoErrors();

    $ticket = Ticket::where('user_id', $customer->id)->sole();

    expect($ticket->technician_id)->toBe($available->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->assignment_source)->toBe('automatic')
        ->and($ticket->assigned_at)->not->toBeNull()
        ->and($ticket->response_due_at?->equalTo(now()->addHours(4)))->toBeTrue();

    $this->assertDatabaseHas('email_delivery_logs', [
        'notification_type' => 'Ticket assignment',
        'recipient' => $customer->email,
        'status' => 'sent',
    ]);

    $this->actingAs($customer)
        ->get(route('customer.tickets.show', $ticket, false))
        ->assertOk()
        ->assertSee($available->name.' (Technician)');
});

test('technicians who are off shift on leave or busy with an installation are skipped', function () {
    $busy = routingTechnician(['name' => 'Installation Technician']);
    routingTechnician([
        'name' => 'Late Shift Technician',
        'technician_shift_start' => '10:00',
    ]);
    routingTechnician([
        'name' => 'Leave Technician',
        'technician_status' => 'on_leave',
    ]);
    $available = routingTechnician(['name' => 'Eligible Technician']);

    $customer = User::factory()->create(['role' => 'customer']);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-ROUTING-1',
        'first_name' => 'Routing',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09171230001',
        'municipality' => 'Agno',
        'barangay' => 'Allabon',
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);
    Installation::create([
        'application_id' => $application->id,
        'technician_id' => $busy->id,
        'installation_date' => today()->toDateString(),
        'installation_time' => '08:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($customer)->post(route('customer.tickets.store'), [
        'subject' => 'damaged_line_or_equipment',
        'description' => 'The outdoor line is damaged.',
    ])->assertSessionHasNoErrors();

    expect(Ticket::where('user_id', $customer->id)->sole()->technician_id)->toBe($available->id);
});

test('known tickets stay queued when no eligible technician is working', function () {
    routingTechnician(['technician_shift_start' => '10:00']);
    routingTechnician(['technician_status' => 'on_leave']);

    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->post(route('customer.tickets.store'), [
        'subject' => 'service_outage_in_area',
        'description' => 'The whole street has no service.',
    ])->assertSessionHasNoErrors();

    $ticket = Ticket::where('user_id', $customer->id)->sole();

    expect($ticket->technician_id)->toBeNull()
        ->and($ticket->status)->toBe(Ticket::STATUS_QUEUED)
        ->and($ticket->response_due_at)->not->toBeNull();
});

test('other concerns wait for admin urgency review before automatic assignment', function () {
    $technician = routingTechnician();
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('customer.tickets.store'), [
        'subject' => 'other_technical_concern',
        'description' => 'A concern that needs manual review.',
    ])->assertSessionHasNoErrors();

    $ticket = Ticket::where('user_id', $customer->id)->sole();

    expect($ticket->technician_id)->toBeNull()
        ->and($ticket->status)->toBe(Ticket::STATUS_QUEUED)
        ->and($ticket->urgency_review_required)->toBeTrue()
        ->and($ticket->response_due_at)->toBeNull();

    $this->actingAs($admin)->patch(route('admin.tickets.update', $ticket), [
        'admin_action' => 'save',
        'urgency' => Ticket::URGENCY_CRITICAL,
        'technician_id' => null,
    ])->assertSessionHasNoErrors();

    $ticket->refresh();

    expect($ticket->urgency)->toBe(Ticket::URGENCY_CRITICAL)
        ->and($ticket->urgency_review_required)->toBeFalse()
        ->and($ticket->triaged_by)->toBe($admin->id)
        ->and($ticket->technician_id)->toBe($technician->id)
        ->and($ticket->assignment_source)->toBe('automatic_after_triage')
        ->and($ticket->response_due_at?->equalTo(now()->addHour()))->toBeTrue();
});

test('queued dispatch always assigns higher urgency before lower urgency', function () {
    config()->set('ticket-routing.max_active_tickets_per_technician', 1);
    $technician = routingTechnician();
    $low = routingTicket(['urgency' => Ticket::URGENCY_LOW]);
    $critical = routingTicket(['urgency' => Ticket::URGENCY_CRITICAL]);
    $high = routingTicket(['urgency' => Ticket::URGENCY_HIGH]);

    expect(app(TicketAssignmentService::class)->dispatchQueued())->toBe(1);

    expect($critical->fresh()->technician_id)->toBe($technician->id)
        ->and($high->fresh()->technician_id)->toBeNull()
        ->and($low->fresh()->technician_id)->toBeNull();
});

test('assigned technician can start queue work without an appointment slot', function () {
    $technician = routingTechnician();
    $ticket = routingTicket([
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_OPEN,
        'assigned_at' => now(),
        'appointment_date' => null,
        'appointment_time' => null,
    ]);

    $this->actingAs($technician)
        ->patch(route('technician.tickets.start', $ticket))
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->status)->toBe(Ticket::STATUS_IN_PROGRESS);
});
