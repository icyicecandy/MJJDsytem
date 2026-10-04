<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;

function createAdminApplicationFilterRecord(
    string $customerName,
    string $reference,
    string $status,
    string $email,
    string $mobile,
    string $plan = 'S2S_50'
): Application {
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => $customerName,
        'email' => $email,
    ]);

    return Application::create([
        'user_id' => $customer->id,
        'reference_no' => $reference,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'mobile_number' => $mobile,
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bautista',
        'barangay' => 'Pogo',
        'street_landmark' => 'Near municipal hall',
        'plan_code' => $plan,
        'status' => $status,
    ]);
}

test('administrator application page exposes live search and instant status controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $application = createAdminApplicationFilterRecord(
        'Maria Santos',
        'APP-LIVE-0001',
        Application::STATUS_SUBMITTED,
        'maria.live@example.test',
        '09170001001'
    );

    $this->actingAs($admin)
        ->get(route('admin.applications.index', absolute: false))
        ->assertOk()
        ->assertSee('data-live-application-search', false)
        ->assertSee('data-live-application-search-input', false)
        ->assertSee('data-live-application-status', false)
        ->assertSee('data-application-results', false)
        ->assertSee('data-application-status-nav', false)
        ->assertSee('data-application-status-card="submitted"', false)
        ->assertSee('data-application-status-card="under_review"', false)
        ->assertSee('data-application-status-card="approved"', false)
        ->assertSee('data-application-status-card="scheduled"', false)
        ->assertSee('data-application-status-card="reschedule"', false)
        ->assertSee('data-application-status-card="cancellation_requested"', false)
        ->assertSee('admin-applications.js', false)
        ->assertSee($application->reference_no)
        ->assertSee('Maria Santos');
});

test('administrator can use partial application search while retaining the status filter', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = createAdminApplicationFilterRecord(
        'Maria Santos',
        'APP-LIVE-0010',
        Application::STATUS_APPROVED,
        'maria.santos@example.test',
        '09170001010',
        'S2S_100'
    );
    $other = createAdminApplicationFilterRecord(
        'Hidden Customer',
        'APP-LIVE-0011',
        Application::STATUS_SUBMITTED,
        'hidden.customer@example.test',
        '09170001011'
    );

    $this->actingAs($admin)
        ->get(route('admin.applications.index', ['search' => 'Mar'], false))
        ->assertOk()
        ->assertSee($target->reference_no)
        ->assertSee('Maria Santos')
        ->assertDontSee($other->reference_no);

    $this->actingAs($admin)
        ->get(route('admin.applications.index', [
            'status' => Application::STATUS_APPROVED,
            'search' => 'Mar',
        ], false))
        ->assertOk()
        ->assertSee($target->reference_no)
        ->assertSee('S2S_100')
        ->assertDontSee($other->reference_no);

    $this->actingAs($admin)
        ->get(route('admin.applications.index', [
            'status' => Application::STATUS_SUBMITTED,
            'search' => 'Mar',
        ], false))
        ->assertOk()
        ->assertSee('No applications matched your search.')
        ->assertDontSee($target->reference_no);
});

test('application progress cards are limited to six operational filters and reschedule finds actionable records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $rescheduleApplication = createAdminApplicationFilterRecord(
        'Route Change Customer',
        'APP-RESCHEDULE-0001',
        Application::STATUS_SCHEDULED,
        'route.change@example.test',
        '09170001991'
    );
    $ordinaryScheduled = createAdminApplicationFilterRecord(
        'Ordinary Scheduled Customer',
        'APP-SCHEDULED-0002',
        Application::STATUS_SCHEDULED,
        'ordinary.schedule@example.test',
        '09170001992'
    );

    $technician = User::factory()->create(['role' => 'technician']);

    Installation::create([
        'application_id' => $rescheduleApplication->id,
        'technician_id' => $technician->id,
        'installation_date' => now()->addDay()->toDateString(),
        'installation_time' => '08:00',
        'status' => Installation::STATUS_RESCHEDULE_REQUIRED,
    ]);

    $page = $this->actingAs($admin)
        ->get(route('admin.applications.index', absolute: false))
        ->assertOk();

    expect(substr_count($page->getContent(), 'data-application-status-card='))->toBe(6);

    $this->actingAs($admin)
        ->get(route('admin.applications.index', ['status' => 'reschedule'], false))
        ->assertOk()
        ->assertSee($rescheduleApplication->reference_no)
        ->assertSee('Reschedule')
        ->assertDontSee($ordinaryScheduled->reference_no);
});
