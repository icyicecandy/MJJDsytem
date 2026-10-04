<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-16 07:00:00', config('app.timezone')));
});

afterEach(function () {
    $this->travelBack();
});

function queueDesignApplication(array $attributes = []): Application
{
    $customer = User::factory()->create(['role' => 'customer']);

    return Application::create(array_replace([
        'user_id' => $customer->id,
        'reference_no' => 'APP-QUEUE-DESIGN-'.$customer->id,
        'first_name' => 'Queue',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09'.str_pad((string) $customer->id, 9, '0', STR_PAD_LEFT),
        'municipality' => 'Agno',
        'barangay' => 'Allabon',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_APPROVED,
    ], $attributes));
}

test('legacy ticket appointment columns remain while ticket updates retire their values', function () {
    expect(Schema::hasColumns('tickets', ['appointment_date', 'appointment_time']))->toBeTrue();

    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $ticket = Ticket::create([
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'subject' => 'No Internet Connection',
        'description' => 'Legacy appointment migration test.',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'appointment_date' => '2026-09-18',
        'appointment_time' => '08:00',
    ]);

    $this->actingAs($admin)->patch(route('admin.tickets.update', $ticket), [
        'technician_id' => $technician->id,
        'urgency' => Ticket::URGENCY_CRITICAL,
        'confirm_assignment_override' => '1',
        // Historical integrations may still send these; active logic ignores them.
        'appointment_date' => '2026-09-19',
        'appointment_time' => '11:00',
    ])->assertSessionHasNoErrors();

    expect($ticket->refresh()->technician_id)->toBe($technician->id)
        ->and($ticket->appointment_date)->toBeNull()
        ->and($ticket->appointment_time)->toBeNull()
        ->and($ticket->response_due_at)->not->toBeNull();
});

test('legacy ticket appointment values do not block installation availability', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
    ]);
    Ticket::create([
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'technician_id' => $technician->id,
        'subject' => 'Repair',
        'description' => 'Historical appointment should not reserve calendar time.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
        'urgency_review_required' => false,
        'appointment_date' => '2026-09-17',
        'appointment_time' => '08:00',
        'response_due_at' => now()->addHours(4),
    ]);
    $application = queueDesignApplication();

    $this->actingAs($admin)->post(route('admin.applications.schedule', $application), [
        'technician_id' => $technician->id,
        'installation_date' => '2026-09-17',
        'installation_time' => '08:00',
    ])->assertSessionHasNoErrors();

    expect($application->refresh()->status)->toBe(Application::STATUS_SCHEDULED)
        ->and($application->installation->technician_id)->toBe($technician->id);
});

test('installation conflicts remain enforced after tickets move to the workload queue', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
    ]);
    $existingApplication = queueDesignApplication(['status' => Application::STATUS_SCHEDULED]);
    Installation::create([
        'application_id' => $existingApplication->id,
        'technician_id' => $technician->id,
        'installation_date' => '2026-09-17',
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);
    $candidate = queueDesignApplication();

    $this->actingAs($admin)->post(route('admin.applications.schedule', $candidate), [
        'technician_id' => $technician->id,
        'installation_date' => '2026-09-17',
        'installation_time' => '08:00',
    ])->assertSessionHasErrors('installation_time');

    expect($candidate->installation()->doesntExist())->toBeTrue();
});
