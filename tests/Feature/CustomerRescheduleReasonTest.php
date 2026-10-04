<?php

use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;

function reasonFixture(): array
{
    $admin = User::factory()->create(['role' => 'admin']);
    $tech = User::factory()->create(['role' => 'technician']);
    $customer = User::factory()->create(['role' => 'customer']);
    $application = Application::create([
        'user_id' => $customer->id, 'reference_no' => 'APP-REASON-'.$customer->id,
        'first_name' => 'Reason', 'last_name' => 'Customer', 'email' => $customer->email, 'mobile_number' => '09171234567',
        'municipality' => 'Agno', 'barangay' => 'Allabon', 'plan_code' => 'S2S_50', 'status' => 'scheduled',
    ]);
    $job = Installation::create([
        'application_id' => $application->id, 'technician_id' => $tech->id,
        'installation_date' => now()->subDay()->toDateString(), 'installation_time' => '09:00',
        'status' => 'scheduled', 'technician_remarks' => 'Private staff-only note',
    ]);
    $payload = ['technician_id' => $tech->id, 'installation_date' => now()->addWeek()->startOfWeek()->toDateString(), 'installation_time' => '11:00'];

    return [$admin, $tech, $customer, $application, $job, $payload];
}

test('installation rescheduling requires a meaningful bounded public reason', function ($reason) {
    [$admin, , , , $job, $payload] = reasonFixture();
    $before = $job->fresh()->getAttributes();
    $this->actingAs($admin)->patch(route('admin.installations.reschedule', $job), $payload + ['customer_reschedule_reason' => $reason])
        ->assertSessionHasErrors('customer_reschedule_reason');
    expect($job->fresh()->getAttributes())->toBe($before)->and(AppNotification::count())->toBe(0);
})->with([null, '   ', str_repeat('x', 1001), [['invalid']]]);

test('admin reschedule saves the public reason notifies the customer and escapes its display', function () {
    [$admin, , $customer, , $job, $payload] = reasonFixture();
    $reason = 'Unsafe weather delayed the visit. <script>alert(1)</script>';
    $this->actingAs($admin)->patch(route('admin.installations.reschedule', $job), $payload + ['customer_reschedule_reason' => $reason])
        ->assertSessionHasNoErrors();
    expect($job->fresh()->customer_reschedule_reason)->toBe($reason)->and($job->fresh()->customer_rescheduled_at)->not->toBeNull();
    $notification = AppNotification::where('user_id', $customer->id)->where('type', 'installation_rescheduled_customer')->sole();
    expect($notification->message)->toContain($reason, '11:00 AM')->not->toContain('Private staff-only note');
    $this->actingAs($customer)->get(route('customer.application.show'))->assertOk()->assertSee('Reason for the latest reschedule')
        ->assertSee($reason)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Private staff-only note');
    $other = User::factory()->create(['role' => 'customer']);
    $this->actingAs($other)->get(route('customer.application.show'))->assertDontSee($reason);
});

test('customer-request approval includes the reviewed reason and rejection does not require an approval reason', function () {
    [$admin, , $customer, $app, $job, $payload] = reasonFixture();
    $job->update(['installation_date' => now()->addDays(2)->toDateString(), 'reschedule_requested_at' => now(), 'reschedule_request_reason' => 'Customer unavailable.']);
    $url = route('admin.applications.reschedule', $app);
    $this->actingAs($admin)->patch($url, $payload + ['decision' => 'approve'])->assertSessionHasErrors('customer_reschedule_reason');
    $this->patch($url, $payload + ['decision' => 'approve', 'customer_reschedule_reason' => 'Approved your request because you will be away.'])->assertSessionHasNoErrors();
    expect(AppNotification::where('user_id', $customer->id)->where('type', 'installation_reschedule_approved')->sole()->message)
        ->toContain('Approved your request because you will be away.');
    $job->refresh()->update(['reschedule_requested_at' => now(), 'reschedule_reviewed_at' => null]);
    $this->patch($url, ['decision' => 'reject', 'reschedule_rejection_reason' => 'Please contact support.'])->assertSessionHasNoErrors();
});

test('conflicting reschedule preserves the previous explanation and sends no notification', function () {
    [$admin, $tech, $customer, , $job, $payload] = reasonFixture();
    $job->update(['customer_reschedule_reason' => 'Previous explanation']);
    $blockingApplication = Application::create([
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'reference_no' => 'APP-BLOCKING-'.uniqid(),
        'first_name' => 'Blocking', 'last_name' => 'Customer', 'email' => 'blocking'.uniqid().'@example.test',
        'mobile_number' => '0917'.random_int(1000000, 9999999),
        'municipality' => 'Agno', 'barangay' => 'Allabon', 'plan_code' => 'S2S_50', 'status' => 'scheduled',
    ]);
    Installation::create([
        'application_id' => $blockingApplication->id,
        'technician_id' => $tech->id,
        'installation_date' => $payload['installation_date'],
        'installation_time' => $payload['installation_time'],
        'status' => 'scheduled',
    ]);
    $this->actingAs($admin)->patch(route('admin.installations.reschedule', $job), $payload + ['customer_reschedule_reason' => 'New explanation'])
        ->assertSessionHasErrors('installation_time');
    expect($job->fresh()->customer_reschedule_reason)->toBe('Previous explanation')->and(AppNotification::count())->toBe(0);
});

test('ticket priority changes notify the customer and replace legacy appointments with queue handling', function () {
    [$admin, $tech, $customer] = reasonFixture();
    $ticket = Ticket::create([
        'user_id' => $customer->id,
        'technician_id' => $tech->id,
        'subject' => 'Repair',
        'description' => 'Repair',
        'status' => 'open',
        'urgency' => 'medium',
        'appointment_date' => now()->addDays(3)->toDateString(),
        'appointment_time' => '09:00',
    ]);

    $this->actingAs($admin)->patch(route('admin.tickets.update', $ticket), [
        'technician_id' => $tech->id,
        'urgency' => 'critical',
    ])->assertSessionHasNoErrors();

    $ticket->refresh();

    expect($ticket->urgency)->toBe('critical')
        ->and($ticket->appointment_date)->toBeNull()
        ->and($ticket->appointment_time)->toBeNull()
        ->and($ticket->response_due_at)->not->toBeNull();

    $notice = AppNotification::where('user_id', $customer->id)
        ->where('type', 'ticket_queue_updated')
        ->sole();

    expect($notice->message)->toContain('Critical', $tech->name);

    $this->actingAs($customer)
        ->get(route('customer.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Response target')
        ->assertDontSee('Appointment');
});
