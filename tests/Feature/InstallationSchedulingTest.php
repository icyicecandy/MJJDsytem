<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;

test('approved applications can be scheduled with assigned_at and installation pages remain accessible', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0100',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-05-01',
            'installation_time' => '09:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation = Installation::where('application_id', $application->id)->first();

    expect($installation)->not->toBeNull()
        ->and($installation->technician_id)->toBe($technician->id)
        ->and($installation->status)->toBe('scheduled')
        ->and($installation->assigned_at)->not->toBeNull()
        ->and($application->refresh()->status)->toBe('scheduled');

    $this->actingAs($customer)
        ->get(route('customer.application.show', absolute: false))
        ->assertOk()
        ->assertSee('Installation Assigned At');

    $this->actingAs($technician)
        ->get(route('technician.installations.show', $installation, false))
        ->assertOk()
        ->assertSee('Assigned At');
});

test('non approved applications cannot be scheduled', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0101',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane101@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'submitted',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-05-01',
            'installation_time' => '09:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors('schedule');

    expect(Installation::where('application_id', $application->id)->doesntExist())->toBeTrue()
        ->and($application->refresh()->status)->toBe('submitted');
});

test('scheduled applications cannot be scheduled again and remain locked', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0102',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane102@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-05-01',
        'installation_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-05-02',
            'installation_time' => '10:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors('schedule');

    $application->refresh();
    $installation = Installation::where('application_id', $application->id)->first();

    expect($application->status)->toBe('scheduled')
        ->and($installation)->not->toBeNull()
        ->and($installation->installation_date->format('Y-m-d'))->toBe('2026-05-01');
});

test('same technician cannot be scheduled twice in the same one hour slot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customerA = User::factory()->create(['role' => 'customer']);
    $customerB = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'name' => 'Rens']);

    $existingApplication = Application::create([
        'user_id' => $customerA->id,
        'reference_no' => 'APP-2026-0201',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane201@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    Installation::create([
        'application_id' => $existingApplication->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-04-29',
        'installation_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $application = Application::create([
        'user_id' => $customerB->id,
        'reference_no' => 'APP-2026-0202',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john202@example.com',
        'mobile_number' => '09179876543',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-04-29',
            'installation_time' => '09:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors(['installation_time' => 'This technician already has an installation scheduled during that time slot.']);

    expect(Installation::where('application_id', $application->id)->doesntExist())->toBeTrue()
        ->and($application->refresh()->status)->toBe('approved');
});

test('same technician can be scheduled on the next one hour slot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customerA = User::factory()->create(['role' => 'customer']);
    $customerB = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician', 'name' => 'Rens']);

    $existingApplication = Application::create([
        'user_id' => $customerA->id,
        'reference_no' => 'APP-2026-0203',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane203@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    Installation::create([
        'application_id' => $existingApplication->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-04-29',
        'installation_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $application = Application::create([
        'user_id' => $customerB->id,
        'reference_no' => 'APP-2026-0204',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john204@example.com',
        'mobile_number' => '09179876543',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-04-29',
            'installation_time' => '10:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation = Installation::where('application_id', $application->id)->first();

    expect($installation)->not->toBeNull()
        ->and(\Carbon\Carbon::parse($installation->installation_time)->format('H:i'))->toBe('10:00')
        ->and($application->refresh()->status)->toBe('scheduled');
});

test('different technician can use the same time slot on the same date', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customerA = User::factory()->create(['role' => 'customer']);
    $customerB = User::factory()->create(['role' => 'customer']);
    $technicianA = User::factory()->create(['role' => 'technician', 'name' => 'Rens']);
    $technicianB = User::factory()->create(['role' => 'technician', 'name' => 'Mika']);

    $existingApplication = Application::create([
        'user_id' => $customerA->id,
        'reference_no' => 'APP-2026-0205',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane205@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    Installation::create([
        'application_id' => $existingApplication->id,
        'technician_id' => $technicianA->id,
        'assigned_at' => now(),
        'installation_date' => '2026-04-29',
        'installation_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $application = Application::create([
        'user_id' => $customerB->id,
        'reference_no' => 'APP-2026-0206',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john206@example.com',
        'mobile_number' => '09179876543',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'approved',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technicianB->id,
            'installation_date' => '2026-04-29',
            'installation_time' => '09:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation = Installation::where('application_id', $application->id)->first();

    expect($installation)->not->toBeNull()
        ->and($installation->technician_id)->toBe($technicianB->id)
        ->and($application->refresh()->status)->toBe('scheduled');
});

test('assigned technician can start an installation and move it to in progress', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0301',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane301@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-05-02',
        'installation_time' => '09:00',
        'status' => 'scheduled',
    ]);

    $response = $this
        ->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->patch(route('technician.installations.status', $installation, false), [
            'status' => 'in_progress',
        ]);

    $response
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasNoErrors();

    expect($installation->refresh()->status)->toBe('in_progress');

    $this->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false))
        ->assertOk()
        ->assertSee('0')
        ->assertSee('Scheduled Jobs');
});

test('assigned technician can complete an in-progress installation', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0302',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john302@example.com',
        'mobile_number' => '09179876543',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-05-03',
        'installation_time' => '10:00',
        'status' => 'in_progress',
    ]);

    $response = $this
        ->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->post(route('technician.installations.complete', $installation, false), [
            'technician_remarks' => 'Installation finished successfully.',
        ]);

    $response
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasNoErrors();

    expect($installation->refresh()->status)->toBe('completed')
        ->and($installation->completed_at)->not->toBeNull()
        ->and($application->refresh()->status)->toBe('installed');
});

test('scheduled installations cannot be marked completed directly', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0303',
        'first_name' => 'Joan',
        'last_name' => 'Doe',
        'email' => 'joan303@example.com',
        'mobile_number' => '09170001111',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-05-04',
        'installation_time' => '11:00',
        'status' => 'scheduled',
    ]);

    $response = $this
        ->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->post(route('technician.installations.complete', $installation, false), [
            'technician_remarks' => 'Trying to skip steps.',
        ]);

    $response
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasErrors('status');

    expect($installation->refresh()->status)->toBe('scheduled')
        ->and($application->refresh()->status)->toBe('scheduled');
});

test('only the assigned technician can update installation workflow status', function () {
    $assignedTechnician = User::factory()->create(['role' => 'technician']);
    $otherTechnician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0304',
        'first_name' => 'Jake',
        'last_name' => 'Doe',
        'email' => 'jake304@example.com',
        'mobile_number' => '09170002222',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => 'scheduled',
    ]);

    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $assignedTechnician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-05-05',
        'installation_time' => '13:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($otherTechnician)
        ->patch(route('technician.installations.status', $installation, false), [
            'status' => 'in_progress',
        ])
        ->assertForbidden();

    expect($installation->refresh()->status)->toBe('scheduled');
});
