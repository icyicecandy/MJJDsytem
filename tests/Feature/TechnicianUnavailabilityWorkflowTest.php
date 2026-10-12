<?php

use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Installation;
use App\Models\TechnicianUnavailabilityRequest;
use App\Models\User;
use App\Services\TechnicianSchedule;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-12 08:00:00'));
    Mail::fake();
    Storage::fake('local');
});

function unavailabilityApplication(array $attributes = []): Application
{
    $customer = User::factory()->create(['role' => 'customer']);

    return Application::create(array_replace([
        'user_id' => $customer->id,
        'reference_no' => 'APP-UNAV-'.$customer->id,
        'first_name' => 'Coverage',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'mobile_number' => '09171234567',
        'municipality' => 'Bayambang',
        'barangay' => 'Poblacion',
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_APPROVED,
    ], $attributes));
}

test('technician submission creates a private pending request and notifies administrators', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($technician)
        ->post(route('technician.unavailability.store'), [
            'requested_start_at' => '2026-10-13 08:00:00',
            'requested_end_at' => '2026-10-13 17:00:00',
            'category' => 'emergency',
            'reason' => 'Urgent family matter requiring absence.',
            'evidence' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('technician.unavailability.index'))
        ->assertSessionHasNoErrors();

    $unavailability = TechnicianUnavailabilityRequest::firstOrFail();
    expect($unavailability->technician_id)->toBe($technician->id)
        ->and($unavailability->status)->toBe(TechnicianUnavailabilityRequest::STATUS_PENDING)
        ->and($unavailability->evidence_path)->not->toBeNull();
    Storage::disk('local')->assertExists($unavailability->evidence_path);
    expect(AppNotification::where('user_id', $admin->id)->where('type', 'technician_unavailability_submitted')->exists())->toBeTrue();
});

test('pending and approved requests block scheduling while rejection releases the hold', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);
    $unavailability = TechnicianUnavailabilityRequest::create([
        'technician_id' => $technician->id,
        'requested_start_at' => '2026-10-13 08:00:00',
        'requested_end_at' => '2026-10-13 17:00:00',
        'category' => 'planned_leave',
        'reason' => 'Planned personal leave for the full workday.',
        'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
    ]);

    $application = unavailabilityApplication();
    $this->actingAs($admin)->post(route('admin.applications.schedule', $application), [
        'technician_id' => $technician->id,
        'installation_date' => '2026-10-13',
        'installation_time' => '11:00',
    ])->assertSessionHasErrors('installation_time');

    $this->actingAs($admin)->patch(route('admin.unavailability.decision', $unavailability), [
        'decision' => 'reject',
        'admin_note' => 'The submitted period was not approved after schedule review.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.applications.schedule', $application), [
        'technician_id' => $technician->id,
        'installation_date' => '2026-10-13',
        'installation_time' => '11:00',
    ])->assertSessionHasNoErrors();

    expect($application->fresh()->installation)->not->toBeNull();
});

test('administrator approval records the effective period and notifies the technician', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);
    $unavailability = TechnicianUnavailabilityRequest::create([
        'technician_id' => $technician->id,
        'requested_start_at' => '2026-10-13 08:00:00',
        'requested_end_at' => '2026-10-14 17:00:00',
        'category' => 'medical',
        'reason' => 'Medical appointment and required recovery period.',
        'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
    ]);

    $this->actingAs($admin)->patch(route('admin.unavailability.decision', $unavailability), [
        'decision' => 'approve',
        'approved_start_at' => '2026-10-13 13:00:00',
        'approved_end_at' => '2026-10-14 12:00:00',
        'admin_note' => 'Approved with an adjusted start time.',
    ])->assertSessionHasNoErrors();

    $unavailability->refresh();
    expect($unavailability->status)->toBe(TechnicianUnavailabilityRequest::STATUS_APPROVED)
        ->and($unavailability->reviewed_by)->toBe($admin->id)
        ->and($unavailability->effectiveStartAt()->format('Y-m-d H:i'))->toBe('2026-10-13 13:00');
    expect(AppNotification::where('user_id', $technician->id)->where('type', 'technician_unavailability_decision')->exists())->toBeTrue();
});

test('administrator must resolve affected installations before approving unavailability', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);
    $application = unavailabilityApplication(['status' => Application::STATUS_SCHEDULED]);
    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'installation_date' => '2026-10-13',
        'installation_time' => '11:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);
    $unavailability = TechnicianUnavailabilityRequest::create([
        'technician_id' => $technician->id,
        'requested_start_at' => '2026-10-13 08:00:00',
        'requested_end_at' => '2026-10-13 17:00:00',
        'category' => 'emergency',
        'reason' => 'Emergency absence affecting an assigned appointment.',
        'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
    ]);

    $this->actingAs($admin)->patch(route('admin.unavailability.decision', $unavailability), [
        'decision' => 'approve',
        'approved_start_at' => '2026-10-13 08:00:00',
        'approved_end_at' => '2026-10-13 17:00:00',
    ])->assertSessionHasErrors('decision');

    expect($unavailability->fresh()->status)->toBe(TechnicianUnavailabilityRequest::STATUS_PENDING);

    $this->actingAs($admin)->patch(route('admin.unavailability.require-reschedule', [$unavailability, $installation]), [
        'coverage_note' => 'No replacement technician is available for the current appointment.',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->patch(route('admin.unavailability.decision', $unavailability), [
        'decision' => 'approve',
        'approved_start_at' => '2026-10-13 08:00:00',
        'approved_end_at' => '2026-10-13 17:00:00',
    ])->assertSessionHasNoErrors();

    expect($unavailability->fresh()->status)->toBe(TechnicianUnavailabilityRequest::STATUS_APPROVED)
        ->and($installation->fresh()->status)->toBe(Installation::STATUS_RESCHEDULE_REQUIRED);
});

test('administrator can move an overlapping assigned job to the reschedule queue', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);
    $application = unavailabilityApplication(['status' => Application::STATUS_SCHEDULED]);
    $installation = Installation::create([
        'application_id' => $application->id,
        'technician_id' => $technician->id,
        'installation_date' => '2026-10-13',
        'installation_time' => '11:00',
        'status' => Installation::STATUS_SCHEDULED,
    ]);
    $unavailability = TechnicianUnavailabilityRequest::create([
        'technician_id' => $technician->id,
        'requested_start_at' => '2026-10-13 08:00:00',
        'requested_end_at' => '2026-10-13 17:00:00',
        'category' => 'emergency',
        'reason' => 'Emergency absence affecting the assigned appointment.',
        'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
    ]);

    $this->actingAs($admin)->patch(route('admin.unavailability.require-reschedule', [$unavailability, $installation]), [
        'coverage_note' => 'No replacement technician can keep the existing appointment.',
    ])->assertSessionHasNoErrors();

    expect($installation->fresh()->status)->toBe(Installation::STATUS_RESCHEDULE_REQUIRED)
        ->and($installation->logs()->count())->toBe(1);
    expect(AppNotification::where('user_id', $application->user_id)->where('type', 'installation_requires_new_schedule')->exists())->toBeTrue();
});

test('current unavailability also removes a technician from live duty checks', function () {
    $technician = User::factory()->create([
        'role' => 'technician',
        'technician_status' => 'available',
        'technician_work_days' => ['mon'],
        'technician_shift_start' => '08:00',
        'technician_shift_end' => '17:00',
    ]);
    TechnicianUnavailabilityRequest::create([
        'technician_id' => $technician->id,
        'requested_start_at' => '2026-10-12 07:30:00',
        'requested_end_at' => '2026-10-12 12:00:00',
        'category' => 'emergency',
        'reason' => 'Emergency absence during the morning work period.',
        'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
    ]);

    expect(TechnicianSchedule::isOnDutyAt($technician, '2026-10-12 09:00'))->toBeFalse()
        ->and(TechnicianSchedule::isOnDutyAt($technician, '2026-10-12 13:00'))->toBeTrue();
});

test('technician and administrator availability pages render with role-safe access', function () {
    $technician = User::factory()->create(['role' => 'technician']);
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $unavailability = TechnicianUnavailabilityRequest::create([
        'technician_id' => $technician->id,
        'requested_start_at' => '2026-10-13 08:00:00',
        'requested_end_at' => '2026-10-13 17:00:00',
        'category' => 'planned_leave',
        'reason' => 'Planned leave submitted for schedule coverage review.',
        'status' => TechnicianUnavailabilityRequest::STATUS_PENDING,
    ]);

    $this->actingAs($technician)->get(route('technician.unavailability.index'))
        ->assertOk()->assertSee('Report unavailability')->assertSee($unavailability->reference());
    $this->actingAs($technician)->get(route('technician.dashboard'))
        ->assertOk()->assertSee('Report Unavailability')->assertDontSee('<span class="nav-label">Availability</span>', false);
    $this->actingAs($admin)->get(route('admin.unavailability.index'))
        ->assertOk()->assertSee('Technician Availability Requests')->assertSee($unavailability->reference());
    $this->actingAs($admin)->get(route('admin.unavailability.show', $unavailability))
        ->assertOk()->assertSee('Resolve affected installations')->assertSee('Administrator decision');
    $this->actingAs($customer)->get(route('technician.unavailability.index'))->assertForbidden();
    $this->actingAs($technician)->get(route('admin.unavailability.index'))->assertForbidden();
});
