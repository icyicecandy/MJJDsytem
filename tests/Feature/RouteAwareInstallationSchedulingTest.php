<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;
use App\Services\TechnicianSchedule;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-21 07:00:00'));
});

function routePlannerApplication(User $customer, array $attributes = []): Application
{
    return Application::create(array_replace([
        'user_id' => $customer->id,
        'reference_no' => 'APP-ROUTE-'.$customer->id.'-'.fake()->unique()->numerify('###'),
        'first_name' => 'Route',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09'.fake()->unique()->numerify('#########'),
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Poblacion',
        'lat' => 15.7333,
        'lng' => 120.3833,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_APPROVED,
    ], $attributes));
}

function routePlannerInstallation(Application $application, User $technician, string $time): Installation
{
    return Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'installation_date' => '2026-09-22',
        'installation_time' => $time,
        'status' => Installation::STATUS_SCHEDULED,
    ]);
}

test('route-aware availability recommends a nearby technician and exposes daily schedule context', function () {
    $candidate = routePlannerApplication(User::factory()->create(['role' => 'customer']));
    $nearbyTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Nearby Technician']);
    $emptyTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Empty Technician']);
    $busyTechnician = User::factory()->create(['role' => 'technician', 'name' => 'Busy Technician']);

    $nearbyJob = routePlannerApplication(User::factory()->create(['role' => 'customer']), [
        'status' => Application::STATUS_SCHEDULED,
        'barangay' => 'Poblacion',
        'municipality' => 'Bayambang',
        'lat' => 15.7380,
        'lng' => 120.3880,
    ]);
    routePlannerInstallation($nearbyJob, $nearbyTechnician, '08:00');

    $conflictingJob = routePlannerApplication(User::factory()->create(['role' => 'customer']), [
        'status' => Application::STATUS_SCHEDULED,
        'barangay' => 'Zone II',
        'municipality' => 'Bayambang',
        'lat' => 15.7390,
        'lng' => 120.3890,
    ]);
    routePlannerInstallation($conflictingJob, $busyTechnician, '11:00');

    $grid = TechnicianSchedule::installationAvailabilityGrid(
        collect([$nearbyTechnician, $emptyTechnician, $busyTechnician]),
        $candidate,
        '2026-09-22',
        '2026-09-22',
        includeRouteDetails: true
    );

    $slot = collect($grid['2026-09-22']['slots'])->firstWhere('value', '11:00');
    $options = collect($slot['technicians']);
    $nearby = $options->firstWhere('id', $nearbyTechnician->id);
    $busy = $options->firstWhere('id', $busyTechnician->id);


    expect($slot['available_count'])->toBe(2)
        ->and($slot['technician_ids'])->toContain($nearbyTechnician->id, $emptyTechnician->id)
        ->and($nearby['recommended'])->toBeTrue()
        ->and($nearby['route_label'])->toBe('Recommended')
        ->and($nearby['same_barangay'])->toBeTrue()
        ->and($nearby['jobs'][0]['time_label'])->toBe('8:00 AM')
        ->and($nearby['jobs'][0]['location'])->toBe('Poblacion, Bayambang')
        ->and($busy['available'])->toBeFalse()
        ->and($busy['unavailable_reason'])->toBe(TechnicianSchedule::CONFLICT_MESSAGE);
});

test('admin application scheduling renders the responsive route recommendation workspace', function () {
    $application = routePlannerApplication(User::factory()->create(['role' => 'customer']));
    User::factory()->create(['role' => 'technician', 'name' => 'Route Technician']);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.applications.show', $application, false))
        ->assertOk()
        ->assertSee('Choose the best available technician')
        ->assertSee('data-route-scheduler', false)
        ->assertSee('data-route-cards', false)
        ->assertSee('Straight-line estimates')
        ->assertSee('New installation location');
});
