<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;
use App\Services\TechnicianSchedule;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-21 07:00:00'));
});

function scheduleRuleApplication(array $attributes = []): Application
{
    $customer = User::factory()->create(['role' => 'customer']);

    return Application::create(array_replace([
        'user_id' => $customer->id,
        'reference_no' => 'APP-RULE-'.$customer->id,
        'first_name' => 'Rule',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09'.str_pad((string) $customer->id, 9, '0', STR_PAD_LEFT),
        'municipality' => 'Bayambang',
        'barangay' => 'Poblacion',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_APPROVED,
    ], $attributes));
}

function scheduleRuleAttempt($test, Application $application, User $technician, string $date, string $time)
{
    return $test
        ->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => $date,
            'installation_time' => $time,
        ]);
}

test('installation choices display start times without estimated finish times', function (string $time, string $label) {
    expect(Installation::slotLabel($time))->toBe($label);
})->with([
    ['08:00', '8:00 AM'],
    ['11:00', '11:00 AM'],
    ['14:00', '2:00 PM'],
]);

test('admin installation scheduling uses a native calendar and a narrow-safe form layout', function () {
    $application = scheduleRuleApplication();
    User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.applications.show', $application))
        ->assertOk()
        ->assertSee('class="admin-app-form admin-app-scheduling-form"', false)
        ->assertSee('type="date"', false)
        ->assertSee('min="'.now()->toDateString().'"', false)
        ->assertSee('max="'.now()->addDays(89)->toDateString().'"', false)
        ->assertDontSee('<select id="installation_date"', false);
});

test('installation scheduling accepts only the three configured service windows', function (string $time) {
    $application = scheduleRuleApplication();
    $technician = User::factory()->create(['role' => 'technician']);

    scheduleRuleAttempt($this, $application, $technician, '2026-09-22', $time)
        ->assertSessionHasNoErrors();

    expect(Carbon::parse($application->installation->installation_time)->format('H:i'))->toBe($time);
})->with(['08:00', '11:00', '14:00']);

test('installation scheduling rejects weekends and the closing time', function (string $date, string $time, string $field) {
    $application = scheduleRuleApplication();
    $technician = User::factory()->create(['role' => 'technician']);

    scheduleRuleAttempt($this, $application, $technician, $date, $time)
        ->assertSessionHasErrors($field);

    expect($application->installation()->doesntExist())->toBeTrue();
})->with([
    ['2026-09-26', '08:00', 'installation_date'],
    ['2026-09-22', '17:00', 'installation_time'],
]);

test('part time technicians can only receive installations on their stored workdays', function () {
    $application = scheduleRuleApplication();
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_employment_type' => 'part_time',
        'technician_work_days' => ['wed'],
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
    ]);

    scheduleRuleAttempt($this, $application, $technician, '2026-09-22', '08:00')
        ->assertSessionHasErrors(['installation_date' => TechnicianSchedule::WORKDAY_MESSAGE]);

    scheduleRuleAttempt($this, $application, $technician, '2026-09-23', '08:00')
        ->assertSessionHasNoErrors();
});

test('technicians who are on leave cannot receive installations', function () {
    $application = scheduleRuleApplication();
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_status' => 'on_leave',
    ]);

    scheduleRuleAttempt($this, $application, $technician, '2026-09-22', '08:00')
        ->assertSessionHasErrors('technician_id');

    expect($application->installation()->doesntExist())->toBeTrue();
});

test('the daily installation cap also protects schedules containing legacy times', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $date = '2026-09-22';

    foreach (['05:00', '08:00', '14:00'] as $index => $time) {
        $existing = scheduleRuleApplication(['status' => Application::STATUS_SCHEDULED]);
        Installation::create([
            'application_id' => $existing->id,
            'technician_id' => $technician->id,
            'installation_date' => $date,
            'installation_time' => $time,
            'status' => Installation::STATUS_SCHEDULED,
        ]);
    }

    $application = scheduleRuleApplication();

    scheduleRuleAttempt($this, $application, $technician, $date, '11:00')
        ->assertSessionHasErrors(['installation_time' => TechnicianSchedule::DAILY_LIMIT_MESSAGE]);

    expect($application->installation()->doesntExist())->toBeTrue();
});

test('route distance blocks a far consecutive installation and permits a nearby one', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $date = '2026-09-22';

    $existing = scheduleRuleApplication([
        'status' => Application::STATUS_SCHEDULED,
        'lat' => 15.7333,
        'lng' => 120.3833,
    ]);
    Installation::create([
        'application_id' => $existing->id,
        'technician_id' => $technician->id,
        'installation_date' => $date,
        'installation_time' => '08:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);

    $farApplication = scheduleRuleApplication([
        'lat' => 16.1200,
        'lng' => 119.8000,
    ]);
    scheduleRuleAttempt($this, $farApplication, $technician, $date, '11:00')
        ->assertSessionHasErrors('installation_time');
    expect($farApplication->installation()->doesntExist())->toBeTrue();

    $nearApplication = scheduleRuleApplication([
        'lat' => 15.7400,
        'lng' => 120.3900,
    ]);
    scheduleRuleAttempt($this, $nearApplication, $technician, $date, '11:00')
        ->assertSessionHasNoErrors();
});


test('unavailable dates and break periods are shared installation availability rules', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_work_days' => ['tue'],
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
        'technician_break_start' => '12:00',
        'technician_break_end' => '13:00',
        'technician_unavailable_dates' => ['2026-09-29'],
    ]);

    scheduleRuleAttempt($this, scheduleRuleApplication(), $technician, '2026-09-29', '08:00')
        ->assertSessionHasErrors(['installation_date' => TechnicianSchedule::WORKDAY_MESSAGE]);

    scheduleRuleAttempt($this, scheduleRuleApplication(), $technician, '2026-09-22', '11:00')
        ->assertSessionHasErrors(['installation_time' => TechnicianSchedule::BREAK_MESSAGE]);

    scheduleRuleAttempt($this, scheduleRuleApplication(), $technician, '2026-09-22', '08:00')
        ->assertSessionHasNoErrors();
});

test('shared on duty checks exclude leave dates and break periods', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_status' => 'available',
        'technician_work_days' => ['mon'],
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
        'technician_break_start' => '12:00',
        'technician_break_end' => '13:00',
        'technician_unavailable_dates' => ['2026-09-28'],
    ]);

    expect(TechnicianSchedule::isOnDutyAt($technician, '2026-09-21 09:00'))->toBeTrue()
        ->and(TechnicianSchedule::isOnDutyAt($technician, '2026-09-21 12:30'))->toBeFalse()
        ->and(TechnicianSchedule::isOnDutyAt($technician, '2026-09-28 09:00'))->toBeFalse();
});
