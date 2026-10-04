<?php

use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-21 20:07:00', config('app.timezone')));
});

afterEach(function () {
    $this->travelBack();
});

test('critical after-hours tickets wait for an on-duty technician and alert administrators', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_status' => 'available',
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
        'technician_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
    ]);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->post(route('customer.tickets.store', absolute: false), [
        'subject' => 'service_outage_in_area',
        'description' => 'The entire area has no service after hours.',
    ])->assertSessionHasNoErrors();

    $ticket = Ticket::where('user_id', $customer->id)->sole();

    expect($ticket->status)->toBe(Ticket::STATUS_QUEUED)
        ->and($ticket->assigned_staff_id)->toBeNull()
        ->and($ticket->displayStatusLabel())->toBe('Queued – Waiting for On-Duty Technician')
        ->and(app(TicketAssignmentService::class)->nextEligibleShiftAt()?->format('Y-m-d H:i'))
        ->toBe('2026-09-22 08:00');

    $this->assertDatabaseHas('app_notifications', [
        'user_id' => $admin->id,
        'title' => 'Critical after-hours ticket waiting',
    ]);

    $notification = $admin->appNotifications()->where('title', 'Critical after-hours ticket waiting')->sole();
    expect($notification->message)
        ->toContain('No technician is currently on duty')
        ->toContain('Sep 22, 2026 08:00 AM');

    $this->actingAs($customer)
        ->get(route('customer.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('customer-ticket-status-cell', false)
        ->assertSee('Queued')
        ->assertSee('Not yet assigned')
        ->assertDontSee('Queued â€“ Waiting for On-Duty Technician');

    $this->get(route('customer.tickets.show', $ticket, absolute: false))
        ->assertOk()
        ->assertSee('Queued')
        ->assertSee('No technician is currently on duty.')
        ->assertSee('Automatic assignment retries every five minutes.');

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('Queued – Waiting for On-Duty Technician')
        ->assertSee('No technician is currently on duty.')
        ->assertSee('Sep 22, 2026 08:00 AM')
        ->assertSee('Automatic queue')
        ->assertSee('ticket-col-customer', false)
        ->assertSee('ticket-col-assignee', false)
        ->assertSee('Waiting for on-duty technician')
        ->assertSee('ticket-queue-status__badge', false);

    $this->travelTo(Carbon::parse('2026-09-22 08:00:00', config('app.timezone')));

    expect(app(TicketAssignmentService::class)->dispatchQueued())->toBe(1);

    $ticket->refresh();
    expect($ticket->assigned_staff_id)->toBe($technician->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->assignment_source)->toBe('automatic');
});

test('the queue dispatcher never assigns an off-duty technician', function () {
    User::factory()->create([
        'role' => 'technician',
        'technician_status' => 'available',
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
        'technician_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
    ]);
    $ticket = Ticket::create([
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'subject' => 'Service Outage in Area',
        'description' => 'Queue safety test.',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_CRITICAL,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHour(),
    ]);

    expect(app(TicketAssignmentService::class)->dispatchQueued())->toBe(0)
        ->and($ticket->fresh()->assigned_staff_id)->toBeNull()
        ->and($ticket->fresh()->status)->toBe(Ticket::STATUS_QUEUED);
});
