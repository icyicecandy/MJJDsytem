<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

test('technician dashboard shows assigned active tickets and correct count', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);

    $openTicket = Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'No Internet Connection',
        'description' => 'Connection is down.',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_HIGH,
    ]);

    $queuedTicket = Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Installation Follow-up',
        'description' => 'Need a schedule update.',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_MEDIUM,
    ]);

    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'subject' => 'Old Closed Ticket',
        'description' => 'Already resolved.',
        'status' => Ticket::STATUS_CLOSED,
        'urgency' => Ticket::URGENCY_LOW,
    ]);

    $response = $this
        ->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false));

    $response
        ->assertOk()
        ->assertViewHas('assignedTickets', 2)
        ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 2)
        ->assertSee($openTicket->publicReference())
        ->assertSee($queuedTicket->publicReference())
        ->assertSee('No Internet Connection')
        ->assertSee('Installation Follow-up');
});

test('technician dashboard shows installations assigned for the current app date', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $otherTechnician = User::factory()->create(['role' => 'technician']);
    $customerToday = User::factory()->create(['role' => 'customer']);
    $customerFuture = User::factory()->create(['role' => 'customer']);
    $customerOtherTech = User::factory()->create(['role' => 'customer']);

    $todayApplication = Application::create([
        'user_id' => $customerToday->id,
        'reference_no' => 'APP-2026-0430',
        'first_name' => 'April',
        'last_name' => 'Install',
        'email' => 'april.install@example.com',
        'mobile_number' => '09170000430',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);

    $futureApplication = Application::create([
        'user_id' => $customerFuture->id,
        'reference_no' => 'APP-2026-0501',
        'first_name' => 'May',
        'last_name' => 'Future',
        'email' => 'may.future@example.com',
        'mobile_number' => '09170000501',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);

    $otherTechApplication = Application::create([
        'user_id' => $customerOtherTech->id,
        'reference_no' => 'APP-2026-0430-B',
        'first_name' => 'Other',
        'last_name' => 'Technician',
        'email' => 'other.technician@example.com',
        'mobile_number' => '09170000431',
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'street_landmark' => 'Near plaza',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SCHEDULED,
    ]);

    $todayInstallation = Installation::create([
        'application_id' => $todayApplication->id,
        'technician_id' => $technician->id,
        'assigned_at' => '2026-04-29 15:00:00',
        'installation_date' => '2026-04-30',
        'installation_time' => '09:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    Installation::create([
        'application_id' => $futureApplication->id,
        'technician_id' => $technician->id,
        'assigned_at' => '2026-04-29 16:00:00',
        'installation_date' => '2026-05-01',
        'installation_time' => '10:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    Installation::create([
        'application_id' => $otherTechApplication->id,
        'technician_id' => $otherTechnician->id,
        'assigned_at' => '2026-04-29 17:00:00',
        'installation_date' => '2026-04-30',
        'installation_time' => '11:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-04-30 08:00:00', config('app.timezone')));

    try {
        $response = $this
            ->actingAs($technician)
            ->get(route('technician.dashboard', absolute: false));
    } finally {
        Carbon::setTestNow();
    }

    $response
        ->assertOk()
        ->assertViewHas('todayWorkloadDate', fn ($date) => $date->toDateString() === '2026-04-30')
        ->assertViewHas('todayInstallations', fn ($installations) => $installations->pluck('id')->all() === [$todayInstallation->id])
        ->assertSee('April 30, 2026')
        ->assertSee('April Install')
        ->assertSee('Contact: 09170000430')
        ->assertSee('09:00 AM')
        ->assertDontSee('May Future')
        ->assertDontSee('Other Technician');
});

test('technician dashboard shows empty state when no active assigned tickets exist', function () {
    $technician = User::factory()->create(['role' => 'technician']);

    $response = $this
        ->actingAs($technician)
        ->get(route('technician.dashboard', absolute: false));

    $response
        ->assertOk()
        ->assertViewHas('assignedTickets', 0)
        ->assertViewHas('tickets', fn ($tickets) => $tickets->isEmpty())
        ->assertSee('No assigned tickets right now. New customer issues will appear here once assigned.');
});
