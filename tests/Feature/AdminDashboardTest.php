<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;

function adminDashboardApplication(string $status = Application::STATUS_SUBMITTED, array $overrides = []): Application
{
    $customer = User::factory()->create(['role' => 'customer']);

    return Application::create(array_merge([
        'user_id' => $customer->id,
        'reference_no' => 'APP-DASHBOARD-'.$customer->id,
        'first_name' => 'Dashboard',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '0917'.str_pad((string) $customer->id, 7, '0', STR_PAD_LEFT),
        'region' => 'Ilocos Region',
        'province' => 'Pangasinan',
        'municipality' => 'Bayambang',
        'barangay' => 'Dusoc',
        'lat' => 15.8100,
        'lng' => 120.4550,
        'plan_code' => 'S2S_50',
        'status' => $status,
    ], $overrides));
}

function adminDashboardInstallation(User $technician, string $date, string $time = '08:00', array $applicationOverrides = []): Installation
{
    $application = adminDashboardApplication(Application::STATUS_SCHEDULED, $applicationOverrides);

    return Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'installation_date' => $date,
        'installation_time' => $time,
        'status' => Installation::STATUS_SCHEDULED,
    ]);
}

test('admin workload dashboard defaults to this week and replaces the analytics overview', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('period', 'week')
        ->assertViewHas('periodStart', fn ($date) => $date->toDateString() === '2026-09-14')
        ->assertViewHas('periodEnd', fn ($date) => $date->toDateString() === '2026-09-20')
        ->assertViewHas('installationTotal', 0)
        ->assertSee('Current workload')
        ->assertSee('No installations in this period')
        ->assertSee('Open Analytics')
        ->assertDontSee('IDs awaiting verification')
        ->assertDontSee('Support workload now')
        ->assertDontSee('Items that may need an admin decision')
        ->assertDontSee('Operations Overview')
        ->assertDontSee('Applications over the last 6 months');
});

test('today week and next thirty day filters include only installations in their range', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create(['role' => 'technician']);

    adminDashboardInstallation($technician, '2026-09-16');
    adminDashboardInstallation($technician, '2026-09-18', '11:00', ['barangay' => 'Bical Norte']);
    adminDashboardInstallation($technician, '2026-09-30', '14:00', ['municipality' => 'Bautista']);
    adminDashboardInstallation($technician, '2026-10-20');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()->assertViewHas('installationTotal', 2);
    $this->get(route('admin.dashboard', ['period' => 'today']))
        ->assertOk()->assertViewHas('period', 'today')->assertViewHas('installationTotal', 1);
    $this->get(route('admin.dashboard', ['period' => '30days']))
        ->assertOk()->assertViewHas('period', '30days')->assertViewHas('installationTotal', 3);
    $this->get(route('admin.dashboard', ['period' => 'invalid']))
        ->assertOk()->assertViewHas('period', 'week')->assertViewHas('installationTotal', 2);
});

test('dashboard attention counts reflect live operational states', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    adminDashboardApplication(Application::STATUS_SUBMITTED);
    adminDashboardApplication(Application::STATUS_UNDER_REVIEW);
    adminDashboardApplication(Application::STATUS_CANCELLATION_REQUESTED);

    Ticket::create([
        'user_id' => $customer->id,
        'subject' => 'Service Outage in Area',
        'description' => 'Critical unassigned ticket',
        'status' => Ticket::STATUS_QUEUED,
        'urgency' => Ticket::URGENCY_CRITICAL,
    ]);
    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'assigned_staff_id' => $technician->id,
        'subject' => 'No Internet Connection',
        'description' => 'Overdue assigned ticket',
        'status' => Ticket::STATUS_IN_PROGRESS,
        'urgency' => Ticket::URGENCY_HIGH,
        'response_due_at' => now()->subHour(),
    ]);

    Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $technician->id,
        'assigned_staff_id' => $technician->id,
        'subject' => 'Installation Follow-up',
        'description' => 'Assigned open ticket',
        'status' => Ticket::STATUS_OPEN,
        'urgency' => Ticket::URGENCY_MEDIUM,
    ]);
    $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $response->assertViewHas('attentionItems', function ($items) {
        $counts = $items->pluck('count', 'label');

        return $counts['Applications to review'] === 2
            && $counts['Unassigned tickets'] === 1
            && $counts['Open tickets'] === 1
            && $counts['Cancellation requests'] === 1;
    });
});

test('technician workload combines period installations active tickets schedule and locations', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);
    $technician = User::factory()->create([
        'role' => 'technician',
        'name' => 'Raffy Workload',
        'technician_max_active_tickets' => 4,
        'technician_status' => 'available',
    ]);
    $customer = User::factory()->create(['role' => 'customer']);

    adminDashboardInstallation($technician, '2026-09-16', '11:00', ['barangay' => 'Dusoc', 'municipality' => 'Bayambang']);
    adminDashboardInstallation($technician, '2026-09-18', '14:00', ['barangay' => 'Pogo', 'municipality' => 'Bautista']);
    foreach ([Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS] as $status) {
        Ticket::create([
            'user_id' => $customer->id,
            'technician_id' => $technician->id,
            'assigned_staff_id' => $technician->id,
            'subject' => 'No Internet Connection',
            'description' => 'Technician workload ticket',
            'status' => $status,
            'urgency' => Ticket::URGENCY_HIGH,
        ]);
    }

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('technicianWorkloads', function ($workloads) use ($technician) {
            $workload = $workloads->firstWhere(fn ($row) => $row['technician']->is($technician));

            return $workload
                && $workload['schedule']['label'] === 'On duty'
                && $workload['installation_count'] === 2
                && $workload['installation_capacity'] === 15
                && $workload['active_tickets'] === 2
                && $workload['ticket_capacity'] === 4
                && $workload['next_installation'] instanceof Installation
                && $workload['areas']->contains('Dusoc, Bayambang')
                && $workload['areas']->contains('Pogo, Bautista');
        })
        ->assertSee('Raffy Workload')
        ->assertSee('On duty');
});

test('recent activity is limited to the five newest system events', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    foreach (range(1, 7) as $index) {
        SystemLog::create([
            'user_id' => $admin->id,
            'user_role' => 'admin',
            'action' => 'Dashboard Event '.$index,
            'description' => 'Operational activity '.$index,
        ])->forceFill(['created_at' => now()->addSeconds($index)])->save();
    }

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('recentActivity', fn ($logs) => $logs->count() === 5
            && $logs->first()->action === 'Dashboard Event 7'
            && $logs->last()->action === 'Dashboard Event 3')
        ->assertSee('Dashboard Event 7')
        ->assertDontSee('Dashboard Event 2');
});

test('other roles cannot access admin workload data', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('admin.dashboard'))->assertForbidden();
})->with(['customer', 'technician']);