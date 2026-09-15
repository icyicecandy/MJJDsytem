<?php

use App\Models\Application;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;

function overviewApplication(string $createdAt, string $status = Application::STATUS_SUBMITTED): Application
{
    $customer = User::factory()->create(['role' => 'customer']);
    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-OVERVIEW-'.$customer->id,
        'first_name' => 'Overview', 'last_name' => 'Customer',
        'email' => $customer->email, 'mobile_number' => '09171234567',
        'region' => 'Ilocos Region', 'province' => 'Pangasinan',
        'municipality' => 'Bayambang', 'barangay' => 'Dusoc',
        'plan_code' => 'S2S_50', 'status' => $status,
    ]);
    $application->forceFill(['created_at' => $createdAt])->save();

    return $application;
}

test('admin overview shows six zero months and an honest empty state', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-14 12:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('monthlyApplications', fn ($months) => $months->pluck('month')->all() === [
            '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09',
        ] && $months->pluck('count')->all() === [0, 0, 0, 0, 0, 0])
        ->assertViewHas('overviewTotal', 0)
        ->assertViewHas('overviewMaximum', 1)
        ->assertSee('No applications submitted in this period.')
        ->assertDontSee('data-chart="monthly-applications"', false);
});

test('admin overview counts submissions across month and year boundaries including leap days', function (int $year, int $februaryCount) {
    $this->travelTo(CarbonImmutable::parse("{$year}-03-15 12:00:00", config('app.timezone')));
    $lastYear = $year - 1;
    foreach (["{$lastYear}-09-30 23:59:59", "{$lastYear}-10-01 00:00:00", "{$lastYear}-12-31 23:59:59",
        "{$year}-01-01 00:00:00", "{$year}-02-01 00:00:00", "{$year}-02-28 23:59:59",
        "{$year}-03-01 00:00:00", "{$year}-03-16 00:00:00", "{$year}-04-01 00:00:00"] as $date) {
        overviewApplication($date, Application::STATUS_REJECTED);
    }
    if ($year === 2024) {
        overviewApplication('2024-02-29 23:59:59', Application::STATUS_ACTIVATED);
    }
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('monthlyApplications', fn ($months) => $months->pluck('count')->all() === [1, 0, 1, 1, $februaryCount, 1])
        ->assertViewHas('overviewTotal', 4 + $februaryCount)
        ->assertViewHas('overviewMaximum', $februaryCount)
        ->assertDontSee('No applications submitted in this period.');
})->with([[2026, 2], [2024, 3]]);

test('refresh reflects new submissions without counting status changes as new applications', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-14 12:00:00', config('app.timezone')));
    $old = overviewApplication('2026-08-01 00:00:00');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertViewHas('overviewTotal', 1);
    $old->update(['status' => Application::STATUS_ACTIVATED]);
    overviewApplication('2026-09-14 11:59:59');
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('overviewTotal', 2)
        ->assertViewHas('monthlyApplications', fn ($months) => $months->pluck('count')->all() === [0, 0, 0, 0, 1, 1])
        ->assertViewHas('installedApplications', 1);
});

test('admin metric counts include queued open tickets and preserve application status totals', function () {
    foreach ([Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW, Application::STATUS_APPROVED,
        Application::STATUS_SCHEDULED, Application::STATUS_INSTALLED, Application::STATUS_ACTIVATED,
        Application::STATUS_REJECTED, Application::STATUS_CANCELLED] as $status) {
        overviewApplication(now()->toDateTimeString(), $status);
    }
    $customer = User::factory()->create(['role' => 'customer']);
    $tech = User::factory()->create(['role' => 'technician']);
    foreach ([Ticket::STATUS_OPEN, Ticket::STATUS_QUEUED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_RESOLVED,
        Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED, Ticket::STATUS_CANCELLATION_REQUESTED] as $index => $status) {
        Ticket::create(['user_id' => $customer->id, 'technician_id' => $index % 2 ? $tech->id : null,
            'subject' => 'No Internet Connection', 'description' => 'Overview fixture', 'status' => $status, 'urgency' => 'high']);
    }
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertViewHas('totalApplications', 8)->assertViewHas('pendingApplications', 2)
        ->assertViewHas('scheduledInstallations', 1)->assertViewHas('installedApplications', 2)
        ->assertViewHas('openTickets', 2)->assertViewHas('overviewTotal', 8);
});

test('other roles cannot access admin overview data', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('admin.dashboard'))->assertForbidden();
})->with(['customer', 'technician']);
