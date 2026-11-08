<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('ticket descriptions and replies reject oversized text without saving it', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->from(route('customer.tickets.index', absolute: false))
        ->post(route('customer.tickets.store', absolute: false), [
            'subject' => 'no_internet_connection',
            'description' => str_repeat('a', 4001),
        ])
        ->assertRedirect(route('customer.tickets.index', absolute: false))
        ->assertSessionHasErrors('description');

    expect(Ticket::query()->count())->toBe(0);

    $ticket = Ticket::create([
        'user_id' => $customer->id,
        'subject' => 'No Internet Connection',
        'description' => 'Connection is down.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);

    $this->actingAs($customer)
        ->from(route('customer.tickets.show', $ticket, false))
        ->post(route('customer.tickets.reply', $ticket, false), [
            'message' => str_repeat('b', 4001),
        ])
        ->assertRedirect(route('customer.tickets.show', $ticket, false))
        ->assertSessionHasErrors('message');

    expect($ticket->messages()->count())->toBe(0);
});

test('application assessment notes reject oversized text without changing the record', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-VALIDATION-001',
        'first_name' => 'Validation',
        'last_name' => 'Customer',
        'email' => 'validation-customer@example.test',
        'mobile_number' => '09170001001',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SUBMITTED,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.applications.status', $application, false), [
            'status' => Application::STATUS_UNDER_REVIEW,
            'serviceability_status' => 'pending',
            'slot_availability_status' => 'pending',
            'assessment_notes' => str_repeat('c', 2001),
        ])
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors('assessment_notes');

    expect($application->refresh()->status)->toBe(Application::STATUS_SUBMITTED)
        ->and($application->assessment_notes)->toBeNull();
});

test('installation completion rejects oversized remarks without completing the job', function () {
    Storage::fake('public');

    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-VALIDATION-002',
        'first_name' => 'Install',
        'last_name' => 'Customer',
        'email' => 'installation-validation@example.test',
        'mobile_number' => '09170001002',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);
    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => now()->toDateString(),
        'installation_time' => '08:00',
        'status' => Installation::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->post(route('technician.installations.complete', $installation, false), [
            'technician_remarks' => str_repeat('d', 1001),
            'nap_code' => 'NAP-01',
            'port_number' => '1',
            'equipment_serial_number' => 'SERIAL-001',
            'cable_length' => '50 meters',
            'installation_photos' => [UploadedFile::fake()->image('proof.png')],
        ])
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasErrors('technician_remarks');

    expect($installation->refresh()->status)->toBe(Installation::STATUS_IN_PROGRESS)
        ->and($installation->installation_photo_paths)->toBeNull();
});

test('operational forms expose the same text limits enforced by the server', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('customer.tickets.index', absolute: false))
        ->assertOk()
        ->assertSee('name="description" rows="5" required maxlength="4000"', false);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-VALIDATION-003',
        'first_name' => 'Form',
        'last_name' => 'Customer',
        'email' => 'form-validation@example.test',
        'mobile_number' => '09170001003',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SUBMITTED,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application, false))
        ->assertOk()
        ->assertSee('name="assessment_notes" rows="4" maxlength="2000"', false);
});
