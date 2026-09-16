<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TechnicianSchedule;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-16 10:30:00'));
});

function sharedApplication(): Application
{
    $customer = User::factory()->create(['role' => 'customer']);
    return Application::create([
        'user_id' => $customer->id, 'reference_no' => 'APP-SHARED-'.$customer->id,
        'first_name' => 'Schedule', 'last_name' => 'Customer', 'email' => $customer->email,
        'mobile_number' => '09'.str_pad((string) $customer->id, 9, '0', STR_PAD_LEFT),
        'municipality' => 'Agno', 'barangay' => 'Allabon', 'plan_code' => 'S2S_50', 'status' => 'approved',
    ]);
}

function sharedTicket(?User $technician = null, array $attributes = []): Ticket
{
    return Ticket::create(array_replace([
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'subject' => 'No Internet Connection', 'description' => 'Test appointment', 'status' => 'open', 'urgency' => 'high',
        'technician_id' => $technician?->id,
        'appointment_date' => $technician ? '2026-09-17' : null,
        'appointment_time' => $technician ? '09:00' : null,
    ], $attributes));
}

function sharedInstallation(User $technician, array $attributes = []): Installation
{
    $application = sharedApplication();
    $application->update(['status' => 'scheduled']);
    return Installation::create(array_replace([
        'application_id' => $application->id, 'technician_id' => $technician->id,
        'installation_date' => '2026-09-17', 'installation_time' => '09:00', 'status' => 'scheduled',
    ], $attributes));
}

function schedulingAttempt(string $path, User $target, string $date = '2026-09-17', string $time = '09:00'): array
{
    $payload = ['customer_reschedule_reason' => 'Appointment adjustment requested.', 'technician_id' => $target->id, 'installation_date' => $date, 'installation_time' => $time];
    if ($path === 'ticket') {
        $record = sharedTicket();
        return ['patch', route('admin.tickets.update', $record), [
            'technician_id' => $target->id, 'admin_action' => 'save', 'appointment_date' => $date, 'appointment_time' => $time,
        ], $record];
    }
    if ($path === 'installation') {
        $record = sharedApplication();
        return ['post', route('admin.applications.schedule', $record), $payload, $record];
    }
    $record = sharedInstallation(User::factory()->create(['role' => 'technician']), [
        'status' => $path === 'reschedule' ? 'reschedule_required' : 'scheduled',
        'installation_date' => $path === 'reassign' ? $date : '2026-09-18',
        'installation_time' => $path === 'reassign' ? $time : '09:00',
    ]);
    if ($path === 'approve') {
        $record->update(['reschedule_requested_at' => now(), 'reschedule_preferred_date' => $date, 'reschedule_preferred_time' => $time]);
        return ['patch', route('admin.applications.reschedule', $record->application), $payload + ['decision' => 'approve'], $record];
    }
    return ['patch', route('admin.installations.'.$path, $record), $payload, $record];
}

test('all appointment paths reject disabled technicians without changing records', function (string $path) {
    $admin = User::factory()->create(['role' => 'admin']);
    $disabled = User::factory()->create(['role' => 'technician', 'disabled_at' => now()]);
    [$method, $url, $payload, $record] = schedulingAttempt($path, $disabled);
    $before = $record->fresh()->getAttributes();
    $this->actingAs($admin)->{$method}($url, $payload)->assertSessionHasErrors('technician_id');
    expect($record->fresh()->getAttributes())->toBe($before);
})->with(['installation', 'ticket', 'reschedule', 'approve', 'reassign']);

test('all appointment paths reject past dates and elapsed times today', function (string $path, string $date, string $time) {
    $admin = User::factory()->create(['role' => 'admin']);
    [$method, $url, $payload, $record] = schedulingAttempt($path, User::factory()->create(['role' => 'technician']), $date, $time);
    $before = $record->fresh()->getAttributes();
    $this->actingAs($admin)->{$method}($url, $payload)->assertSessionHasErrors();
    expect($record->fresh()->getAttributes())->toBe($before);
})->with(['installation', 'ticket', 'reschedule', 'approve', 'reassign'])->with([
    ['2026-09-15', '16:00'], ['2026-09-16', '10:00'],
]);

test('installation and ticket bookings reject overlap in every direction', function (string $existingKind, string $newKind) {
    $tech = User::factory()->create(['role' => 'technician']);
    $existingKind === 'installation' ? sharedInstallation($tech) : sharedTicket($tech);
    [$method, $url, $payload, $record] = schedulingAttempt($newKind, $tech);
    $before = $record->fresh()->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, $payload)
        ->assertSessionHasErrors([$newKind === 'ticket' ? 'appointment_time' : 'installation_time' => TechnicianSchedule::CONFLICT_MESSAGE]);
    expect($record->fresh()->getAttributes())->toBe($before);
})->with(['installation', 'ticket'])->with(['installation', 'ticket']);

test('ticket reservations are honored when reassigning and rescheduling installations', function (string $path) {
    $tech = User::factory()->create(['role' => 'technician']);
    sharedTicket($tech);
    [$method, $url, $payload, $record] = schedulingAttempt($path, $tech);
    $before = $record->fresh()->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, $payload)->assertSessionHasErrors();
    expect($record->fresh()->getAttributes())->toBe($before);
})->with(['reschedule', 'approve', 'reassign']);

test('adjacent slots different days and different technicians remain available', function (string $case) {
    $tech = User::factory()->create(['role' => 'technician']);
    sharedInstallation($tech);
    $target = $case === 'technician' ? User::factory()->create(['role' => 'technician']) : $tech;
    [$method, $url, $payload, $record] = schedulingAttempt('ticket', $target, $case === 'date' ? '2026-09-18' : '2026-09-17', $case === 'adjacent' ? '10:00' : '09:00');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, $payload)->assertSessionHasNoErrors();
    expect($record->fresh()->technician_id)->toBe($target->id);
})->with(['adjacent', 'date', 'technician']);

test('partial overlap with a legacy off-hour appointment is blocked', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    sharedTicket($tech, ['appointment_time' => '09:30']);
    [$method, $url, $payload] = schedulingAttempt('installation', $tech, '2026-09-17', '10:00');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, $payload)->assertSessionHasErrors('installation_time');
});

test('pending cancellations still reserve time while resolved closed and cancelled tickets release it', function (string $status, bool $blocked) {
    $tech = User::factory()->create(['role' => 'technician']);
    sharedTicket($tech, ['status' => $status]);
    [$method, $url, $payload] = schedulingAttempt('installation', $tech);
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, $payload);
    $blocked ? $response->assertSessionHasErrors('installation_time') : $response->assertSessionHasNoErrors();
})->with([['cancellation_requested', true], ['resolved', false], ['closed', false], ['cancelled', false]]);

test('tickets require a complete slot and reject malformed dates or non-technician assignees', function (array $overrides, string $field) {
    $tech = User::factory()->create(['role' => 'technician']);
    [$method, $url, $payload, $ticket] = schedulingAttempt('ticket', $tech);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, array_replace($payload, $overrides))->assertSessionHasErrors($field);
    expect($ticket->refresh()->technician_id)->toBeNull();
})->with([
    [['appointment_date' => null], 'appointment_date'], [['appointment_time' => null], 'appointment_time'],
    [['appointment_date' => '2026-02-30'], 'appointment_date'], [['appointment_time' => '09:30'], 'appointment_time'],
    [['technician_id' => [1]], 'technician_id'],
]);

test('same-day future appointments and saving the same ticket slot work', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    [$method, $url, $payload, $ticket] = schedulingAttempt('ticket', $tech, '2026-09-16', '11:00');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->{$method}($url, $payload)->assertSessionHasNoErrors();
    $this->{$method}($url, $payload)->assertSessionHasNoErrors();
    expect($ticket->refresh()->appointment_time)->toStartWith('11:00');
});

test('ticket reassignment checks installations and failure preserves the original assignment', function () {
    $original = User::factory()->create(['role' => 'technician']);
    $replacement = User::factory()->create(['role' => 'technician']);
    $ticket = sharedTicket($original);
    sharedInstallation($replacement);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.tickets.update', $ticket), [
        'technician_id' => $replacement->id, 'appointment_date' => '2026-09-17', 'appointment_time' => '09:00',
    ])->assertSessionHasErrors('appointment_time');
    expect($ticket->refresh()->technician_id)->toBe($original->id);
});

test('unassigning a ticket releases its slot and clearing the form cannot leave a reservation', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    $ticket = sharedTicket($tech);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.tickets.update', $ticket), [
        'technician_id' => null, 'appointment_change_reason' => 'A replacement appointment will be arranged.',
    ])->assertSessionHasNoErrors();
    expect($ticket->refresh()->appointment_date)->toBeNull()->and($ticket->appointment_time)->toBeNull()
        ->and(TechnicianSchedule::conflicts($tech->id, '2026-09-17', '09:00'))->toBeFalse();
});

test('customer reschedule availability excludes ticket appointments too', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    $installation = sharedInstallation($tech, ['installation_date' => '2026-09-20']);
    foreach (Installation::AVAILABLE_TIME_SLOTS as $slot) sharedTicket($tech, ['appointment_time' => $slot]);
    $this->actingAs($installation->application->user)->get(route('customer.application.show'))
        ->assertOk()->assertViewHas('rescheduleAvailability', fn ($availability) => ! isset($availability['2026-09-17']));
});

test('overdue installations can be moved to a future nonconflicting appointment', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    $installation = sharedInstallation($tech, ['installation_date' => '2026-09-15']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.applications.show', $installation->application))
        ->assertOk()->assertSee('Reschedule Installation');
    $this->patch(route('admin.installations.reschedule', $installation), [
        'customer_reschedule_reason' => 'The previous appointment was missed.',
        'technician_id' => $tech->id, 'installation_date' => '2026-09-17', 'installation_time' => '10:00',
    ])->assertSessionHasNoErrors();
    expect($installation->fresh()->installation_date->format('Y-m-d'))->toBe('2026-09-17');
});

test('reopening a ticket cannot reclaim a slot that another job has booked', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    $ticket = sharedTicket($tech, ['status' => 'resolved']);
    sharedInstallation($tech);
    $this->actingAs($ticket->user)->patch(route('customer.tickets.reopen', $ticket), ['reason' => 'Still disconnected'])->assertSessionHasNoErrors();
    expect($ticket->refresh()->status)->toBe('open')->and($ticket->technician_id)->toBeNull()
        ->and($ticket->appointment_date)->toBeNull()->and($ticket->appointment_time)->toBeNull();
});

test('historical unscheduled tickets remain visible but need an appointment before starting', function () {
    $tech = User::factory()->create(['role' => 'technician']);
    $ticket = sharedTicket($tech, ['appointment_date' => null, 'appointment_time' => null]);
    $this->actingAs($tech)->get(route('technician.tickets.show', $ticket))->assertOk()->assertSee('An admin must set an appointment');
    $this->patch(route('technician.tickets.start', $ticket))->assertSessionHasErrors('appointment');
    expect($ticket->refresh()->status)->toBe('open');
});
