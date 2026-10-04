<?php

use App\Models\Ticket;
use App\Models\TicketAssignmentEvent;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-21 09:00:00', config('app.timezone')));
});

afterEach(function () {
    $this->travelBack();
});

function staffFlowUser(string $role, array $attributes = []): User
{
    $defaults = ['role' => $role];

    if ($role === 'technician') {
        $defaults = array_merge($defaults, [
            'technician_status' => 'available',
            'technician_shift_start' => '08:00',
            'technician_shift_end' => '17:00',
            'technician_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'technician_max_active_tickets' => 5,
        ]);
    }

    return User::factory()->create(array_replace($defaults, $attributes));
}

function staffFlowTicket(User $customer, array $attributes = []): Ticket
{
    return Ticket::create(array_replace([
        'user_id' => $customer->id,
        'subject' => 'No Internet Connection',
        'description' => 'Staff assignment workflow test.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'response_due_at' => now()->addHours(4),
    ], $attributes));
}

test('administrator can take over start and resolve a technician ticket with an audited reason', function () {
    $admin = staffFlowUser('admin');
    $technician = staffFlowUser('technician');
    $customer = staffFlowUser('customer');
    $ticket = staffFlowTicket($customer, [
        'technician_id' => $technician->id,
        'assigned_at' => now()->subMinutes(10),
        'assignment_source' => 'automatic',
    ]);

    expect($ticket->assigned_staff_id)->toBe($technician->id);

    $this->actingAs($admin)
        ->get(route('admin.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('type="submit" name="take_over_ticket" value="1"', false);

    $this->actingAs($admin)
        ->get(route('admin.tickets.show', $ticket, false))
        ->assertOk()
        ->assertSee('type="submit" name="take_over_ticket" value="1"', false);

    $this->actingAs($admin)
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'ticket_id' => $ticket->id,
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $technician->id,
            'take_over_ticket' => '1',
        ])
        ->assertSessionHasErrors('assignment_reason');

    expect($ticket->refresh()->assigned_staff_id)->toBe($technician->id);

    $this->get(route('admin.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('The assignment was not saved.');

    $this->actingAs($admin)
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'ticket_id' => $ticket->id,
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $technician->id,
            'take_over_ticket' => '1',
            'assignment_reason' => 'Administrator is handling this customer escalation.',
        ])
        ->assertRedirect(route('admin.tickets.index', absolute: false))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Ticket taken over successfully. You are now the assigned handler.');

    $ticket->refresh();

    expect($ticket->assigned_staff_id)->toBe($admin->id)
        ->and($ticket->technician_id)->toBeNull()
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->assignment_source)->toBe('admin_takeover')
        ->and($ticket->assignment_reason)->toBe('Administrator is handling this customer escalation.')
        ->and(TicketAssignmentEvent::where([
            'ticket_id' => $ticket->id,
            'from_staff_id' => $technician->id,
            'to_staff_id' => $admin->id,
            'actor_id' => $admin->id,
        ])->where('reason', 'Administrator is handling this customer escalation.')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.tickets.start', $ticket, false))
        ->assertSessionHasNoErrors();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_IN_PROGRESS);

    $this->actingAs($admin)
        ->patch(route('admin.tickets.resolve', $ticket, false), [
            'resolution_notes' => 'Connection settings were corrected.',
        ])
        ->assertSessionHasNoErrors();

    expect($ticket->refresh()->status)->toBe(Ticket::STATUS_RESOLVED);
});

test('transfers require a reason and active work also requires explicit confirmation', function () {
    $admin = staffFlowUser('admin');
    $first = staffFlowUser('technician');
    $second = staffFlowUser('technician');
    $customer = staffFlowUser('customer');
    $ticket = staffFlowTicket($customer, [
        'technician_id' => $first->id,
        'assigned_at' => now()->subHour(),
        'assignment_source' => 'automatic',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.tickets.show', $ticket, false))
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $second->id,
        ])
        ->assertSessionHasErrors('assignment_reason');

    expect($ticket->refresh()->assigned_staff_id)->toBe($first->id);

    $ticket->forceFill(['status' => Ticket::STATUS_IN_PROGRESS])->save();

    $this->actingAs($admin)
        ->from(route('admin.tickets.show', $ticket, false))
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $second->id,
            'assignment_reason' => 'Transfer requested by the operations manager.',
        ])
        ->assertSessionHasErrors('confirm_in_progress_transfer');

    expect($ticket->refresh()->assigned_staff_id)->toBe($first->id);

    $this->actingAs($admin)
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $second->id,
            'assignment_reason' => 'Transfer requested by the operations manager.',
            'confirm_in_progress_transfer' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($ticket->refresh()->assigned_staff_id)->toBe($second->id)
        ->and($ticket->technician_id)->toBe($second->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_IN_PROGRESS);
});

test('off schedule and over capacity assignments require an explicit override', function () {
    $admin = staffFlowUser('admin');
    $offSchedule = staffFlowUser('technician', [
        'technician_shift_start' => '10:00',
        'technician_max_active_tickets' => 1,
    ]);
    $customer = staffFlowUser('customer');

    staffFlowTicket(staffFlowUser('customer'), [
        'technician_id' => $offSchedule->id,
        'assigned_at' => now()->subHour(),
    ]);
    $ticket = staffFlowTicket($customer, ['status' => Ticket::STATUS_QUEUED]);

    $this->actingAs($admin)
        ->from(route('admin.tickets.show', $ticket, false))
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $offSchedule->id,
        ])
        ->assertSessionHasErrors('confirm_assignment_override');

    expect($ticket->refresh()->assigned_staff_id)->toBeNull();

    $this->actingAs($admin)
        ->patch(route('admin.tickets.update', $ticket, false), [
            'admin_action' => 'save',
            'urgency' => Ticket::URGENCY_HIGH,
            'assigned_staff_id' => $offSchedule->id,
            'confirm_assignment_override' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($ticket->refresh()->assigned_staff_id)->toBe($offSchedule->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN);
});

test('an unavailable open ticket returns to the priority queue after the grace period and is reassigned', function () {
    config(['ticket-routing.unavailable_requeue_minutes' => 30]);

    $unavailable = staffFlowUser('technician');
    $replacement = staffFlowUser('technician');
    $customer = staffFlowUser('customer');
    $ticket = staffFlowTicket($customer, [
        'technician_id' => $unavailable->id,
        'assigned_at' => now()->subHour(),
        'assignment_source' => 'automatic',
    ]);

    $unavailable->forceFill(['technician_status' => 'off_duty'])->save();

    $service = app(TicketAssignmentService::class);

    expect($service->requeueUnavailableOpenAssignments())->toBe(0)
        ->and($ticket->refresh()->assigned_staff_id)->toBe($unavailable->id)
        ->and($ticket->assignment_unavailable_since)->not->toBeNull();

    $this->travel(31)->minutes();

    expect($service->dispatchQueued())->toBe(1)
        ->and($ticket->refresh()->assigned_staff_id)->toBe($replacement->id)
        ->and($ticket->technician_id)->toBe($replacement->id)
        ->and($ticket->status)->toBe(Ticket::STATUS_OPEN)
        ->and($ticket->assignmentEvents()->where('source', 'automatic_requeue')->exists())->toBeTrue();
});

test('automatic requeue never moves in progress work and never removes an active admin owner', function () {
    config(['ticket-routing.unavailable_requeue_minutes' => 1]);

    $technician = staffFlowUser('technician', ['technician_status' => 'off_duty']);
    $admin = staffFlowUser('admin');
    $customer = staffFlowUser('customer');

    $inProgress = staffFlowTicket($customer, [
        'technician_id' => $technician->id,
        'status' => Ticket::STATUS_IN_PROGRESS,
        'assignment_unavailable_since' => now()->subHour(),
    ]);
    $adminOwned = staffFlowTicket(staffFlowUser('customer'), [
        'assigned_staff_id' => $admin->id,
        'technician_id' => null,
        'status' => Ticket::STATUS_OPEN,
        'assignment_unavailable_since' => now()->subHour(),
    ]);

    expect(app(TicketAssignmentService::class)->requeueUnavailableOpenAssignments())->toBe(0)
        ->and($inProgress->refresh()->assigned_staff_id)->toBe($technician->id)
        ->and($inProgress->status)->toBe(Ticket::STATUS_IN_PROGRESS)
        ->and($adminOwned->refresh()->assigned_staff_id)->toBe($admin->id)
        ->and($adminOwned->assignment_unavailable_since)->toBeNull();
});


test('disabled and on leave technicians immediately release unstarted tickets', function () {
    $disabled = staffFlowUser('technician', ['disabled_at' => now()]);
    $onLeave = staffFlowUser('technician', ['technician_status' => 'on_leave']);

    $disabledTicket = staffFlowTicket(staffFlowUser('customer'), [
        'technician_id' => $disabled->id,
        'assigned_at' => now()->subMinutes(10),
    ]);
    $leaveTicket = staffFlowTicket(staffFlowUser('customer'), [
        'technician_id' => $onLeave->id,
        'assigned_at' => now()->subMinutes(10),
    ]);

    expect(app(TicketAssignmentService::class)->requeueUnavailableOpenAssignments())->toBe(2)
        ->and($disabledTicket->refresh()->assigned_staff_id)->toBeNull()
        ->and($disabledTicket->status)->toBe(Ticket::STATUS_QUEUED)
        ->and($leaveTicket->refresh()->assigned_staff_id)->toBeNull()
        ->and($leaveTicket->status)->toBe(Ticket::STATUS_QUEUED);
});
