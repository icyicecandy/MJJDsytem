<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;

function createAdminInstallationFilterRecord(
    User $technician,
    string $customerName,
    string $reference,
    string $status,
    string $plan = 'S2S_50'
): Installation {
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => $reference,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => strtolower($firstName).'.'.strtolower($lastName).'@example.com',
        'mobile_number' => '0917'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bautista',
        'barangay' => 'Pogo',
        'street_landmark' => 'Near municipal hall',
        'plan_code' => $plan,
        'status' => $status === Installation::STATUS_ACTIVATED
            ? Application::STATUS_ACTIVATED
            : Application::STATUS_SCHEDULED,
    ]);

    return Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'assigned_at' => now(),
        'installation_date' => '2026-10-29',
        'installation_time' => '11:00',
        'status' => $status,
    ]);
}

test('administrator installation page defaults to all records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);

    $scheduled = createAdminInstallationFilterRecord(
        $technician,
        'Scheduled Customer',
        'APP-SEARCH-001',
        Installation::STATUS_SCHEDULED
    );
    $activated = createAdminInstallationFilterRecord(
        $technician,
        'Activated Customer',
        'APP-SEARCH-002',
        Installation::STATUS_ACTIVATED
    );

    $this->actingAs($admin)
        ->get(route('admin.installations.index', absolute: false))
        ->assertOk()
        ->assertViewHas('selectedGroup', 'all')
        ->assertViewHas('allCount', 2)
        ->assertSee('Search Installations')
        ->assertSee('data-live-installation-search', false)
        ->assertSee('data-live-installation-search-input', false)
        ->assertSee('data-installation-results', false)
        ->assertSee('data-installation-status-nav', false)
        ->assertSee('data-installation-status-card', false)
        ->assertSee($scheduled->displayJobReference())
        ->assertSee($activated->displayJobReference())
        ->assertSee('Scheduled Customer')
        ->assertSee('Activated Customer');
});


test('installation progress navigation contains the six operational filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);

    createAdminInstallationFilterRecord(
        $technician,
        'Status Navigation Customer',
        'APP-STATUS-NAV-001',
        Installation::STATUS_SCHEDULED
    );

    $page = $this->actingAs($admin)
        ->get(route('admin.installations.index', absolute: false))
        ->assertOk()
        ->assertSee('>All<', false)
        ->assertSee('>Scheduled<', false)
        ->assertSee('>In Progress<', false)
        ->assertSee('>Reschedule<', false)
        ->assertSee('>Installed<', false)
        ->assertSee('>Activated<', false);

    expect(substr_count($page->getContent(), 'data-installation-status-card'))->toBe(6);
});

test('scheduled and reschedule installation filters return separate workloads', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);

    $scheduled = createAdminInstallationFilterRecord(
        $technician,
        'Scheduled Work',
        'APP-FILTER-SCHEDULED',
        Installation::STATUS_SCHEDULED
    );
    $reschedule = createAdminInstallationFilterRecord(
        $technician,
        'Reschedule Work',
        'APP-FILTER-RESCHEDULE',
        Installation::STATUS_RESCHEDULE_REQUIRED
    );

    $this->actingAs($admin)
        ->get(route('admin.installations.index', ['group' => 'scheduled'], false))
        ->assertOk()
        ->assertViewHas('scheduledCount', 1)
        ->assertViewHas('rescheduleCount', 1)
        ->assertSee($scheduled->displayJobReference())
        ->assertDontSee($reschedule->displayJobReference());

    $this->actingAs($admin)
        ->get(route('admin.installations.index', ['status' => Installation::STATUS_RESCHEDULE_REQUIRED], false))
        ->assertOk()
        ->assertSee($reschedule->displayJobReference())
        ->assertDontSee($scheduled->displayJobReference());
});

test('administrator can search installations by job reference and customer while retaining status filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $targetTechnician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Raffy Searchable',
        'email' => 'raffy.searchable@example.test',
    ]);
    $otherTechnician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Other Technician',
        'email' => 'other.technician@example.test',
    ]);

    $target = createAdminInstallationFilterRecord(
        $targetTechnician,
        'Maria Santos',
        'APP-SEARCH-010',
        Installation::STATUS_ACTIVATED,
        'S2S_100'
    );
    $other = createAdminInstallationFilterRecord(
        $otherTechnician,
        'Hidden Customer',
        'APP-SEARCH-011',
        Installation::STATUS_SCHEDULED
    );

    $this->actingAs($admin)
        ->get(route('admin.installations.index', [
            'group' => 'all',
            'search' => $target->displayJobReference(),
        ], false))
        ->assertOk()
        ->assertSee($target->displayJobReference())
        ->assertDontSee($other->displayJobReference());

    $this->actingAs($admin)
        ->get(route('admin.installations.index', [
            'group' => 'all',
            'search' => 'Mar',
        ], false))
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertDontSee('Hidden Customer');

    $this->actingAs($admin)
        ->get(route('admin.installations.index', [
            'status' => Installation::STATUS_ACTIVATED,
            'search' => 'Maria Santos',
        ], false))
        ->assertOk()
        ->assertSee('Maria Santos')
        ->assertSee('S2S_100')
        ->assertDontSee('Hidden Customer');

    $this->actingAs($admin)
        ->get(route('admin.installations.index', [
            'status' => Installation::STATUS_SCHEDULED,
            'search' => 'Maria Santos',
        ], false))
        ->assertOk()
        ->assertSee('No installations matched your search.')
        ->assertDontSee($target->displayJobReference());
});
