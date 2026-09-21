<?php

use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OverdueTaskAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function overdueFixture(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $tech = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);
    $application = Application::create([
        'user_id' => $customer->id, 'reference_no' => 'APP-OVERDUE-'.$customer->id,
        'first_name' => 'Test', 'last_name' => 'Customer', 'email' => $customer->email, 'mobile_number' => '09171234567',
        'municipality' => 'Agno', 'barangay' => 'Allabon', 'plan_code' => 'S2S_50', 'status' => 'scheduled',
    ]);
    $job = Installation::create([
        'application_id' => $application->id, 'technician_id' => $tech->id,
        'installation_date' => today()->toDateString(), 'installation_time' => '08:00', 'status' => 'scheduled',
    ]);
    $ticket = Ticket::create([
        'user_id' => $customer->id, 'technician_id' => $tech->id, 'subject' => 'Repair', 'description' => 'Repair',
        'status' => 'in_progress', 'urgency' => 'high', 'response_due_at' => now()->subHour(),
    ]);

    return [$admin, $tech, $customer, $application, $job, $ticket];
}

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-09-16 11:00:00', config('app.timezone')));
});

afterEach(function () {
    $this->travelBack();
});

test('overdue alerts reach each active admin and only the assigned technician with authorized links', function () {
    [$admin, $tech, $customer, $application, $job, $ticket] = overdueFixture();
    $secondAdmin = User::factory()->create(['role' => 'admin']);
    $otherTech = User::factory()->create(['role' => 'technician']);
    $disabledAdmin = User::factory()->create(['role' => 'admin', 'disabled_at' => now()]);
    $before = [$job->fresh()->getAttributes(), $ticket->fresh()->getAttributes()];
    $this->artisan('tasks:alert-overdue')->expectsOutput('6 overdue alert(s) created.')->assertSuccessful();
    expect(AppNotification::count())->toBe(6)
        ->and(AppNotification::whereIn('user_id', [$customer->id, $otherTech->id, $disabledAdmin->id])->count())->toBe(0)
        ->and([$job->fresh()->getAttributes(), $ticket->fresh()->getAttributes()])->toBe($before);
    foreach ([$admin, $secondAdmin, $tech] as $user) {
        foreach (AppNotification::where('user_id', $user->id)->get() as $notice) {
            expect($notice->message)->toContain(config('app.timezone'))
                ->and($notice->message)->toMatch('/remains (unfinished|unresolved)/');
            $this->actingAs($user)->get($notice->url)->assertOk();
        }
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Installation overdue')->assertSee('Ticket overdue');
    }
    $this->actingAs($otherTech)->get(route('technician.tickets.show', $ticket))->assertForbidden();
    $this->actingAs($otherTech)->get(route('technician.installations.show', $job))->assertForbidden();
});

test('appointments become overdue at slot end but not during or before their slot', function () {
    [, , , , $job, $ticket] = overdueFixture();
    $ticket->update(['status' => 'resolved']);
    $this->travelTo(now()->setTime(7, 59, 59));
    expect(app(OverdueTaskAlerts::class)->run())->toBe(0);
    $this->travelTo(now()->setTime(10, 59, 59));
    expect(app(OverdueTaskAlerts::class)->run())->toBe(0);
    $this->travelTo(now()->setTime(11, 0, 0));
    expect(app(OverdueTaskAlerts::class)->run())->toBe(2);
});

test('ticket response deadlines trigger overdue alerts without appointment fields', function () {
    [$admin, $tech, , , $job, $ticket] = overdueFixture();
    $job->update(['status' => 'completed']);
    $ticket->update([
        'appointment_date' => null,
        'appointment_time' => null,
        'response_due_at' => now()->subMinute(),
    ]);

    expect(app(OverdueTaskAlerts::class)->run())->toBe(2)
        ->and(AppNotification::where('user_id', $admin->id)->where('type', 'task_overdue')->count())->toBe(1)
        ->and(AppNotification::where('user_id', $tech->id)->where('type', 'task_overdue')->count())->toBe(1);

    $notice = AppNotification::where('user_id', $admin->id)->sole();
    expect($notice->message)->toContain('response target', 'remains unresolved');
});

test('repeated checks and reading or deleting notifications never repeat the same appointment alert', function () {
    overdueFixture();
    $alerts = app(OverdueTaskAlerts::class);
    expect($alerts->run())->toBe(4)->and($alerts->run())->toBe(0);
    AppNotification::query()->update(['read_at' => now()]);
    expect($alerts->run())->toBe(0);
    AppNotification::query()->delete();
    expect($alerts->run())->toBe(0)->and(DB::table('overdue_alert_deliveries')->count())->toBe(4);
});

test('preview reports new recipient alerts without changing any records', function () {
    overdueFixture();
    $this->artisan('tasks:alert-overdue --dry-run')->expectsOutput('4 overdue alert(s) would be created. No notifications sent.')->assertSuccessful();
    expect(AppNotification::count())->toBe(0)->and(DB::table('overdue_alert_deliveries')->count())->toBe(0);
});

test('finalized and pending-review work is excluded', function ($jobStatus, $ticketStatus) {
    [, , , , $job, $ticket] = overdueFixture();
    $job->update(['status' => $jobStatus]);
    $ticket->update(['status' => $ticketStatus]);
    expect(app(OverdueTaskAlerts::class)->run())->toBe(0);
})->with([
    ['completed', 'resolved'], ['activated', 'closed'], ['cancelled', 'cancelled'], ['reschedule_required', 'cancellation_requested'],
]);

test('pending customer cancellation or rescheduling pauses installation overdue alerts', function ($state) {
    [, , , $application, $job, $ticket] = overdueFixture();
    $ticket->update(['status' => 'closed']);
    if ($state === 'reschedule') {
        $job->update(['reschedule_requested_at' => now(), 'reschedule_reviewed_at' => null]);
    } else {
        $application->update(['status' => $state]);
    }
    expect(app(OverdueTaskAlerts::class)->run())->toBe(0);
})->with(['cancellation_requested', 'cancelled', 'reschedule']);

test('missing appointment dates and times do not invent deadlines', function () {
    [, , , , $job, $ticket] = overdueFixture();
    $job->update(['installation_time' => null]);
    $ticket->update(['appointment_date' => null, 'appointment_time' => null, 'response_due_at' => null]);
    expect(app(OverdueTaskAlerts::class)->run())->toBe(0);
});

test('disabled technician receives no alert while admin still sees overdue work', function () {
    [$admin, $tech] = overdueFixture();
    $tech->update(['disabled_at' => now()]);
    expect(app(OverdueTaskAlerts::class)->run())->toBe(2)
        ->and(AppNotification::where('user_id', $tech->id)->count())->toBe(0)
        ->and(AppNotification::where('user_id', $admin->id)->count())->toBe(2);
});

test('rescheduling replaces the deadline and alerts again only when the new slot expires', function () {
    [, , , , $job, $ticket] = overdueFixture();
    $alerts = app(OverdueTaskAlerts::class);
    expect($alerts->run())->toBe(4);
    $job->update(['installation_date' => today()->addDay()->toDateString()]);
    $ticket->update(['response_due_at' => now()->addDay()]);
    expect($alerts->run())->toBe(0);
    $this->travelTo(now()->addDay());
    expect($alerts->run())->toBe(4)->and($alerts->run())->toBe(0);
});

test('reassignment alerts the new technician and does not send another notice to the previous technician', function () {
    [, $tech, , , $job, $ticket] = overdueFixture();
    $alerts = app(OverdueTaskAlerts::class);
    expect($alerts->run())->toBe(4);
    $newTech = User::factory()->create(['role' => 'technician']);
    $job->update(['technician_id' => $newTech->id]);
    $ticket->update(['technician_id' => $newTech->id]);
    expect($alerts->run())->toBe(4)
        ->and(AppNotification::where('user_id', $tech->id)->count())->toBe(2)
        ->and(AppNotification::where('user_id', $newTech->id)->count())->toBe(2);
});

test('a delivery failure rolls back the marker so the next check can retry', function () {
    overdueFixture();
    $event = 'eloquent.creating: '.AppNotification::class;
    Event::listen($event, function () {
        throw new RuntimeException('Simulated notification write failure');
    });
    try {
        expect(fn () => app(OverdueTaskAlerts::class)->run())->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    expect(AppNotification::count())->toBe(0)->and(DB::table('overdue_alert_deliveries')->count())->toBe(0)
        ->and(app(OverdueTaskAlerts::class)->run())->toBe(4);
});
