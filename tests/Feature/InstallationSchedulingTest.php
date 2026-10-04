<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-04-20 07:00:00'));
});

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
            'installation_time' => '08:00',
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

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application, false))
        ->assertOk()
        ->assertSee('Installation History')
        ->assertSee('data-workspace-tab="history"', false)
        ->assertSee('data-app-workspace-panel="history"', false)
        ->assertDontSee('installationHistoryModal');

    $this->actingAs($customer)
        ->get(route('customer.application.show', absolute: false))
        ->assertOk()
        ->assertDontSee('Installation Assigned At')
        ->assertSee('Installation Date');

    $this->actingAs($technician)
        ->get(route('technician.installations.show', $installation, false))
        ->assertOk()
        ->assertSee('View History')
        ->assertSee('installationHistoryModal')
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
            'installation_time' => '08:00',
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
        'installation_time' => '08:00',
        'status' => 'scheduled',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-05-02',
            'installation_time' => '11:00',
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

test('same technician cannot be scheduled twice in the same three-hour window', function () {
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
        'installation_time' => '08:00',
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
            'installation_time' => '08:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors(['installation_time' => 'This technician already has an installation or ticket appointment during that time slot.']);

    expect(Installation::where('application_id', $application->id)->doesntExist())->toBeTrue()
        ->and($application->refresh()->status)->toBe('approved');
});

test('same technician can be scheduled on the next three-hour window', function () {
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
        'installation_time' => '08:00',
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
            'installation_time' => '11:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation = Installation::where('application_id', $application->id)->first();

    expect($installation)->not->toBeNull()
        ->and(\Carbon\Carbon::parse($installation->installation_time)->format('H:i'))->toBe('11:00')
        ->and($application->refresh()->status)->toBe('scheduled');
});

test('customer can request installation reschedule with preferred date and time', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $currentDate = now()->addDays(3)->toDateString();
    $preferredDate = now()->addDays(7)->toDateString();

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0301',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane301@example.com',
        'mobile_number' => '09170000301',
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
        'installation_date' => $currentDate,
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    $response = $this
        ->actingAs($customer)
        ->from(route('customer.application.show', absolute: false))
        ->patch(route('customer.application.reschedule', absolute: false), [
            'reschedule_preferred_date' => $preferredDate,
            'reschedule_preferred_time' => '11:00',
            'reschedule_reason' => 'Customer will be out of town.',
        ]);

    $response
        ->assertRedirect(route('customer.application.show', absolute: false))
        ->assertSessionHasNoErrors();

    $installation->refresh();

    expect($installation->hasPendingCustomerRescheduleRequest())->toBeTrue()
        ->and($installation->reschedule_preferred_date->format('Y-m-d'))->toBe($preferredDate)
        ->and(Carbon::parse($installation->reschedule_preferred_time)->format('H:i'))->toBe('11:00')
        ->and($installation->reschedule_request_reason)->toBe('Customer will be out of town.')
        ->and($installation->installation_date->format('Y-m-d'))->toBe($currentDate)
        ->and($installation->status)->toBe(Installation::STATUS_SCHEDULED);
});

test('customer reschedule page only lists dates with available technician slots', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $currentDate = now()->addDays(3)->toDateString();
    $fullDate = now()->addDays(7)->toDateString();
    $freeDate = now()->addDays(8)->toDateString();

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0311',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane311@example.com',
        'mobile_number' => '09170000311',
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
        'installation_date' => $currentDate,
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    foreach (Installation::AVAILABLE_TIME_SLOTS as $index => $timeSlot) {
        $blockedApplication = Application::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'reference_no' => 'APP-2026-0311-B'.$index,
            'first_name' => 'Blocked',
            'last_name' => 'Slot',
            'email' => 'blocked311'.$index.'@example.com',
            'mobile_number' => '0917311'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
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
            'application_id' => $blockedApplication->id,
            'technician_id' => $technician->id,
            'assigned_at' => now(),
            'installation_date' => $fullDate,
            'installation_time' => $timeSlot,
            'status' => Installation::STATUS_SCHEDULED,
        ]);
    }

    $this
        ->actingAs($customer)
        ->get(route('customer.application.show', absolute: false))
        ->assertOk()
        ->assertSee(Carbon::parse($freeDate)->format('F j, Y'))
        ->assertDontSee(Carbon::parse($fullDate)->format('F j, Y'));
});

test('customer cannot request reschedule for a taken technician slot', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $currentDate = now()->addDays(3)->toDateString();
    $preferredDate = now()->addDays(7)->toDateString();

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0312',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane312@example.com',
        'mobile_number' => '09170000312',
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

    $otherApplication = Application::create([
        'user_id' => $otherCustomer->id,
        'reference_no' => 'APP-2026-0313',
        'first_name' => 'Other',
        'last_name' => 'Customer',
        'email' => 'other313@example.com',
        'mobile_number' => '09170000313',
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
        'installation_date' => $currentDate,
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    Installation::create([
        'application_id' => $otherApplication->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => $preferredDate,
        'installation_time' => '11:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    $this
        ->actingAs($customer)
        ->from(route('customer.application.show', absolute: false))
        ->patch(route('customer.application.reschedule', absolute: false), [
            'reschedule_preferred_date' => $preferredDate,
            'reschedule_preferred_time' => '11:00',
            'reschedule_reason' => 'Customer wants this unavailable slot.',
        ])
        ->assertRedirect(route('customer.application.show', absolute: false))
        ->assertSessionHasErrors('reschedule_preferred_time');

    expect($installation->refresh()->hasPendingCustomerRescheduleRequest())->toBeFalse();
});

test('admin approves customer reschedule request and updates installation schedule', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $newDate = now()->addDays(8)->toDateString();

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0302',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane302@example.com',
        'mobile_number' => '09170000302',
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
        'installation_date' => now()->addDays(3)->toDateString(),
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
        'reschedule_requested_at' => now(),
        'reschedule_preferred_date' => $newDate,
        'reschedule_preferred_time' => '11:00',
        'reschedule_request_reason' => 'Customer requested a morning visit.',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.applications.reschedule', $application, false), [
            'customer_reschedule_reason' => 'Appointment moved at the customer request.',
            'decision' => 'approve',
            'technician_id' => $technician->id,
            'installation_date' => $newDate,
            'installation_time' => '11:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation->refresh();

    expect($installation->installation_date->format('Y-m-d'))->toBe($newDate)
        ->and(Carbon::parse($installation->installation_time)->format('H:i'))->toBe('11:00')
        ->and($installation->reschedule_reviewed_at)->not->toBeNull()
        ->and($installation->reschedule_rejection_reason)->toBeNull()
        ->and($application->refresh()->status)->toBe(Application::STATUS_SCHEDULED)
        ->and(InstallationLog::where('installation_id', $installation->id)->where('notes', 'like', '%Customer reschedule request approved%')->exists())->toBeTrue();
});

test('admin rejection of customer reschedule request requires and stores reason', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $currentDate = now()->addDays(3)->toDateString();

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0303',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane303@example.com',
        'mobile_number' => '09170000303',
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
        'installation_date' => $currentDate,
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
        'reschedule_requested_at' => now(),
        'reschedule_preferred_date' => now()->addDays(5)->toDateString(),
        'reschedule_preferred_time' => '11:00',
        'reschedule_request_reason' => 'Customer requested a different day.',
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.applications.reschedule', $application, false), [
            'customer_reschedule_reason' => 'Appointment moved at the customer request.',
            'decision' => 'reject',
        ])
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors('reschedule_rejection_reason');

    expect($installation->refresh()->hasPendingCustomerRescheduleRequest())->toBeTrue();

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.applications.reschedule', $application, false), [
            'customer_reschedule_reason' => 'Appointment moved at the customer request.',
            'decision' => 'reject',
            'reschedule_rejection_reason' => 'No technician slot is available for the requested time.',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation->refresh();

    expect($installation->hasPendingCustomerRescheduleRequest())->toBeFalse()
        ->and($installation->installation_date->format('Y-m-d'))->toBe($currentDate)
        ->and($installation->reschedule_rejection_reason)->toBe('No technician slot is available for the requested time.')
        ->and($installation->reschedule_reviewed_at)->not->toBeNull();
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
        'installation_time' => '08:00',
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
            'installation_time' => '08:00',
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
        'installation_time' => '08:00',
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
    Storage::fake('public');

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
        'installation_time' => '11:00',
        'status' => 'in_progress',
    ]);

    $response = $this
        ->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->post(route('technician.installations.complete', $installation, false), [
            'technician_remarks' => 'Installation finished successfully.',
            'nap_code' => 'NAP-001',
            'port_number' => 'P1',
            'equipment_serial_number' => 'EQ-12345',
            'cable_length' => '80m',
            'installation_photos' => [
                UploadedFile::fake()->image('proof-a.png'),
                UploadedFile::fake()->image('proof-b.png'),
            ],
        ]);

    $response
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasNoErrors();

    expect($installation->refresh()->status)->toBe('completed')
        ->and($installation->completed_at)->not->toBeNull()
        ->and($installation->nap_code)->toBe('NAP-001')
        ->and($installation->port_number)->toBe('P1')
        ->and($installation->equipment_serial_number)->toBe('EQ-12345')
        ->and($installation->cable_length)->toBe('80m')
        ->and($installation->installation_photo_path)->toStartWith('installation-photos/')
        ->and($installation->installation_photo_paths)->toHaveCount(2)
        ->and($application->refresh()->status)->toBe('installed');

    foreach ($installation->installation_photo_paths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    expect(InstallationLog::where('installation_id', $installation->id)->where('notes', 'like', '%NAP Code: NAP-001%')->exists())->toBeTrue();
});

test('assigned technician can activate a completed installation', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0305',
        'first_name' => 'Alex',
        'last_name' => 'Doe',
        'email' => 'alex305@example.com',
        'mobile_number' => '09170003305',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_INSTALLED,
    ]);

    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-05-06',
        'installation_time' => '14:00',
        'status' => Installation::STATUS_COMPLETED,
        'completed_at' => now(),
        'technician_remarks' => 'Installed and ready for activation.',
    ]);

    $response = $this
        ->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->patch(route('technician.installations.activate', $installation, false));

    $response
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasNoErrors();

    expect($installation->refresh()->status)->toBe(Installation::STATUS_ACTIVATED)
        ->and($installation->activation_status)->toBe(Installation::STATUS_ACTIVATED)
        ->and($installation->activation_date)->not->toBeNull()
        ->and($application->refresh()->status)->toBe(Application::STATUS_ACTIVATED);

    expect(InstallationLog::where('installation_id', $installation->id)->where('notes', 'like', '%marked activated%')->exists())->toBeTrue();

    $technicianPage = $this->actingAs($technician)
        ->get(route('technician.installations.show', $installation, false))
        ->assertOk()
        ->assertSee('Proof of Installation')
        ->assertSee('Installation History');

    expect(substr_count($technicianPage->getContent(), 'Technical Installation Details'))->toBe(1);

    $this->actingAs($admin)
        ->get(route('admin.installations.index', ['group' => 'scheduled'], false))
        ->assertOk()
        ->assertDontSee($installation->displayJobReference());

    $this->get(route('admin.installations.index', ['status' => Installation::STATUS_ACTIVATED], false))
        ->assertOk()
        ->assertSee($installation->displayJobReference())
        ->assertSee('Activated');
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
        'installation_time' => '14:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($otherTechnician)
        ->patch(route('technician.installations.status', $installation, false), [
            'status' => 'in_progress',
        ])
        ->assertForbidden();

    expect($installation->refresh()->status)->toBe('scheduled');
});

test('admin can reassign a scheduled installation without changing scheduled status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $oldTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Old Tech']);
    $newTechnician = User::factory()->create(['role' => 'technician', 'name' => 'New Tech']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0401',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane401@example.com',
        'mobile_number' => '09170003333',
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
        'technician_id' => $oldTechnician->id,
        'assigned_at' => now(),
        'installation_date' => now()->addDays(3)->format('Y-m-d'),
        'installation_time' => '08:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.installations.reassign', $installation, false), [
            'technician_id' => $newTechnician->id,
            'reassignment_reason' => 'Technician unavailable.',
        ])
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    $installation->refresh();

    expect($installation->technician_id)->toBe($newTechnician->id)
        ->and($installation->status)->toBe('scheduled')
        ->and($installation->reassignment_reason)->toBe('Technician unavailable.')
        ->and($application->refresh()->status)->toBe('scheduled')
        ->and(InstallationLog::where('installation_id', $installation->id)->where('notes', 'like', '%Technician reassigned due to unavailability.%')->exists())->toBeTrue();
});

test('scheduled customer cancellation requires admin approval', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0402',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane402@example.com',
        'mobile_number' => '09170004444',
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
        'installation_date' => now()->addDays(3)->format('Y-m-d'),
        'installation_time' => '11:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($customer)
        ->from(route('customer.application.show', absolute: false))
        ->patch(route('customer.application.cancel', absolute: false), [
            'cancellation_reason' => 'Schedule conflict.',
        ])
        ->assertRedirect(route('customer.application.show', absolute: false))
        ->assertSessionHasNoErrors();

    expect($application->refresh()->status)->toBe('cancellation_requested')
        ->and($application->cancellation_reason)->toBe('Schedule conflict.')
        ->and($installation->refresh()->status)->toBe('scheduled');

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application, false))
        ->assertOk()
        ->assertSee('Customer cancellation reason')
        ->assertSee('Schedule conflict.');

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.applications.cancellation', $application, false), [
            'decision' => 'approve',
            'cancellation_notes' => 'Approved by admin.',
        ])
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    expect($application->refresh()->status)->toBe('cancelled')
        ->and($application->cancellation_reason)->toBe('Schedule conflict.')
        ->and($installation->refresh()->status)->toBe('cancelled')
        ->and(InstallationLog::where('installation_id', $installation->id)->where('notes', 'like', '%Cancellation request approved by admin.%')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application, false))
        ->assertOk()
        ->assertSee('Customer cancellation reason')
        ->assertSee('Schedule conflict.');
});

test('technician can mark reschedule required and admin can reschedule', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0403',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john403@example.com',
        'mobile_number' => '09170005555',
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
        'installation_date' => now()->addDays(2)->format('Y-m-d'),
        'installation_time' => '11:00',
        'status' => 'in_progress',
    ]);

    $this->actingAs($technician)
        ->from(route('technician.installations.show', $installation, false))
        ->patch(route('technician.installations.status', $installation, false), [
            'status' => 'reschedule_required',
            'technician_remarks' => 'Customer unavailable at location',
        ])
        ->assertRedirect(route('technician.installations.show', $installation, false))
        ->assertSessionHasNoErrors();

    expect($installation->refresh()->status)->toBe('reschedule_required')
        ->and($installation->technician_remarks)->toBe('Customer unavailable at location');

    $this->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.installations.reschedule', $installation, false), [
            'customer_reschedule_reason' => 'Appointment moved at the customer request.',
            'technician_id' => $technician->id,
            'installation_date' => now()->addDays(4)->format('Y-m-d'),
            'installation_time' => '14:00',
        ])
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasNoErrors();

    expect($installation->refresh()->status)->toBe('scheduled')
        ->and(\Carbon\Carbon::parse($installation->installation_time)->format('H:i'))->toBe('14:00')
        ->and($application->refresh()->status)->toBe('scheduled')
        ->and(InstallationLog::where('installation_id', $installation->id)->where('notes', 'like', '%Installation rescheduled by admin%')->exists())->toBeTrue();
});


test('in progress stop work request blocks completion and rejection restores exact state', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-STOP-WORK-1',
        'first_name' => 'Ana',
        'last_name' => 'Muñoz-Santos',
        'email' => 'ana.stop@example.test',
        'mobile_number' => '09170009991',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);
    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => today()->toDateString(),
        'installation_time' => '08:00',
        'status' => Installation::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($customer)->patch(route('customer.application.cancel'), [
        'cancellation_reason' => 'Please stop work while I confirm the property approval.',
    ])->assertSessionHasNoErrors();

    expect($application->refresh()->status)->toBe(Application::STATUS_CANCELLATION_REQUESTED)
        ->and($application->cancellation_previous_status)->toBe(Application::STATUS_SCHEDULED)
        ->and($installation->refresh()->status)->toBe(Installation::STATUS_IN_PROGRESS)
        ->and($installation->cancellation_previous_status)->toBe(Installation::STATUS_IN_PROGRESS)
        ->and($installation->hasPendingStopWorkRequest())->toBeTrue();

    $this->actingAs($technician)->patch(route('technician.installations.stop-work-notes', $installation), [
        'stop_work_technician_notes' => 'Work paused before mounting the router.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($technician)->post(route('technician.installations.complete', $installation), [
        'technician_remarks' => 'Should remain blocked.',
    ])->assertSessionHasErrors('status');

    $this->actingAs($admin)->patch(route('admin.applications.cancellation', $application), [
        'decision' => 'reject',
        'cancellation_notes' => 'Property owner confirmed approval.',
    ])->assertSessionHasNoErrors();

    expect($application->refresh()->status)->toBe(Application::STATUS_SCHEDULED)
        ->and($installation->refresh()->status)->toBe(Installation::STATUS_IN_PROGRESS)
        ->and($installation->stop_work_technician_notes)->toBe('Work paused before mounting the router.')
        ->and($installation->stop_work_reviewed_by)->toBe($admin->id)
        ->and($installation->stop_work_reviewed_at)->not->toBeNull()
        ->and($installation->stop_work_admin_notes)->toBe('Property owner confirmed approval.');
});
