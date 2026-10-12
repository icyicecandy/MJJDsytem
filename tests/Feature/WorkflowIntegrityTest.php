<?php

use App\Http\Controllers\AdminApplicationController;
use App\Models\Application;
use App\Models\AppNotification;
use App\Models\Installation;
use App\Models\InstallationLog;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WorkflowTransaction;
use App\Support\CustomerEmailSender;
use App\Support\PublicUpload;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function integrityApplication(array $attributes = []): Application
{
    $customer = User::factory()->create(['role' => 'customer']);

    return Application::create(array_merge([
        'user_id' => $customer->id,
        'reference_no' => 'APP-'.fake()->unique()->numerify('########'),
        'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => $customer->email, 'mobile_number' => '09171234567',
        'region' => 'Ilocos Region', 'province' => 'Pangasinan',
        'municipality' => 'Bayambang', 'barangay' => 'Dusoc',
        'lat' => 15.7333, 'lng' => 120.3833,
        'plan_code' => 'S2S_50', 'status' => Application::STATUS_APPROVED,
    ], $attributes));
}

function integrityInstallationDate(): string
{
    return now()->next('Monday')->toDateString();
}

function integrityInstallation(Application $application, User $technician, array $attributes = []): Installation
{
    return Installation::create(array_merge([
        'application_id' => $application->id, 'technician_id' => $technician->id,
        'installation_date' => integrityInstallationDate(),
        'installation_time' => '08:00', 'status' => Installation::STATUS_SCHEDULED,
    ], $attributes));
}

test('a failed scheduling audit rolls back installation application history and notifications', function () {
    Mail::fake();
    $application = integrityApplication();
    $technician = User::factory()->create(['role' => 'technician']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->withoutExceptionHandling();
    SystemLog::creating(fn () => throw new RuntimeException('Forced audit failure'));

    expect(fn () => $this->post(route('admin.applications.schedule', $application), [
        'technician_id' => $technician->id,
        'installation_date' => integrityInstallationDate(),
        'installation_time' => '08:00',
    ]))->toThrow(RuntimeException::class, 'Forced audit failure');

    expect($application->fresh()->status)->toBe(Application::STATUS_APPROVED);
    $this->assertDatabaseCount('installations', 0);
    $this->assertDatabaseCount('installation_logs', 0);
    $this->assertDatabaseCount('app_notifications', 0);
    Mail::assertNothingSent();
});

test('a failed cancellation leaves both the application and installation unchanged', function () {
    $application = integrityApplication(['status' => Application::STATUS_CANCELLATION_REQUESTED]);
    $installation = integrityInstallation($application, User::factory()->create(['role' => 'technician']));
    $this->actingAs(User::factory()->create(['role' => 'admin']))->withoutExceptionHandling();
    InstallationLog::creating(fn () => throw new RuntimeException('Forced history failure'));

    expect(fn () => $this->patch(route('admin.applications.cancellation', $application), [
        'decision' => 'approve',
    ]))->toThrow(RuntimeException::class);

    expect($application->fresh()->status)->toBe(Application::STATUS_CANCELLATION_REQUESTED)
        ->and($installation->fresh()->status)->toBe(Installation::STATUS_SCHEDULED);
});

test('a failed activation leaves the completed installation and application unchanged', function () {
    $application = integrityApplication(['status' => Application::STATUS_INSTALLED]);
    $technician = User::factory()->create(['role' => 'technician']);
    $installation = integrityInstallation($application, $technician, [
        'status' => Installation::STATUS_COMPLETED,
        'completed_at' => now(),
        'nap_code' => 'NAP-INTEGRITY',
        'port_number' => 'P-01',
        'equipment_serial_number' => 'EQ-INTEGRITY',
        'cable_length' => '40m',
        'installation_photo_paths' => ['installation-photos/integrity-proof.jpg'],
    ]);
    $this->actingAs($technician)->withoutExceptionHandling();
    AppNotification::creating(fn () => throw new RuntimeException('Forced notification failure'));

    expect(fn () => $this->patch(route('technician.installations.activate', $installation)))
        ->toThrow(RuntimeException::class);

    expect($installation->fresh()->status)->toBe(Installation::STATUS_COMPLETED)
        ->and($installation->fresh()->activation_date)->toBeNull()
        ->and($application->fresh()->status)->toBe(Application::STATUS_INSTALLED);
    $this->assertDatabaseCount('installation_logs', 0);
});

test('a failed ticket cancellation rolls back its status and conversation message', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $ticket = Ticket::create(['user_id' => $customer->id, 'subject' => 'Connection',
        'description' => 'Connection issue', 'status' => Ticket::STATUS_OPEN]);
    $this->actingAs($customer)->withoutExceptionHandling();
    SystemLog::creating(fn () => throw new RuntimeException('Forced audit failure'));

    expect(fn () => $this->patch(route('customer.tickets.cancel', $ticket)))->toThrow(RuntimeException::class);
    expect($ticket->fresh()->status)->toBe(Ticket::STATUS_OPEN);
    $this->assertDatabaseCount('ticket_messages', 0);
});

test('workflow locks reload stale application installation and ticket state', function () {
    $application = integrityApplication();
    $installation = integrityInstallation($application, User::factory()->create(['role' => 'technician']));
    $ticket = Ticket::create(['user_id' => $application->user_id, 'subject' => 'Connection',
        'description' => 'Issue', 'status' => Ticket::STATUS_OPEN]);
    Application::whereKey($application->id)->update(['status' => Application::STATUS_CANCELLED]);
    Installation::whereKey($installation->id)->update(['status' => Installation::STATUS_CANCELLED]);
    Ticket::whereKey($ticket->id)->update(['status' => Ticket::STATUS_CLOSED]);

    WorkflowTransaction::application($application, function ($fresh) {
        expect($fresh->status)->toBe(Application::STATUS_CANCELLED)
            ->and($fresh->installation->status)->toBe(Installation::STATUS_CANCELLED);
    });
    WorkflowTransaction::installation($installation, function ($fresh) {
        expect($fresh->status)->toBe(Installation::STATUS_CANCELLED)
            ->and($fresh->application->status)->toBe(Application::STATUS_CANCELLED);
    });
    WorkflowTransaction::ticket($ticket, fn ($fresh) => expect($fresh->status)->toBe(Ticket::STATUS_CLOSED));
});

test('duplicate scheduling with the same stale route model creates only one booking and history entry', function () {
    Mail::fake();
    $application = integrityApplication();
    $technician = User::factory()->create(['role' => 'technician']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $request = Request::create('/admin/applications/'.$application->id.'/schedule', 'POST', [
        'technician_id' => $technician->id,
        'installation_date' => integrityInstallationDate(), 'installation_time' => '08:00',
    ]);
    $controller = app(AdminApplicationController::class);
    $controller->schedule($request, $application);
    $controller->schedule($request, $application);

    $this->assertDatabaseCount('installations', 1);
    $this->assertDatabaseCount('installation_logs', 1);
    expect($application->fresh()->status)->toBe(Application::STATUS_SCHEDULED);
});

test('reassignment rejects an occupied slot without changing the original assignment', function () {
    $first = integrityApplication(['status' => Application::STATUS_SCHEDULED]);
    $second = integrityApplication(['status' => Application::STATUS_SCHEDULED]);
    $oldTechnician = User::factory()->create(['role' => 'technician']);
    $newTechnician = User::factory()->create(['role' => 'technician']);
    $installation = integrityInstallation($first, $oldTechnician);
    integrityInstallation($second, $newTechnician);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.installations.reassign', $installation), ['technician_id' => $newTechnician->id])
        ->assertSessionHasErrors('technician_id');

    expect($installation->fresh()->technician_id)->toBe($oldTechnician->id);
    $this->assertDatabaseCount('installation_logs', 0);
});

test('a pending reschedule cannot revive a cancelled installation', function () {
    $application = integrityApplication(['status' => Application::STATUS_CANCELLED]);
    $technician = User::factory()->create(['role' => 'technician']);
    $installation = integrityInstallation($application, $technician, [
        'status' => Installation::STATUS_CANCELLED, 'reschedule_requested_at' => now(),
    ]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.applications.reschedule', $application), [
            'customer_reschedule_reason' => 'Appointment moved at the customer request.',
            'decision' => 'approve', 'technician_id' => $technician->id,
            'installation_date' => now()->addDays(6)->toDateString(), 'installation_time' => '11:00',
        ])->assertSessionHasErrors('reschedule');

    expect($application->fresh()->status)->toBe(Application::STATUS_CANCELLED)
        ->and($installation->fresh()->status)->toBe(Installation::STATUS_CANCELLED);
});

test('rolled back workflows delete only their newly uploaded files', function () {
    Storage::fake('public');
    Storage::disk('public')->put('existing-proof.png', 'original');
    expect(fn () => WorkflowTransaction::run(function () {
        PublicUpload::storeFile(UploadedFile::fake()->image('proof.png'), 'installation-photos');
        throw new RuntimeException('Failed after upload');
    }))->toThrow(RuntimeException::class);

    expect(Storage::disk('public')->allFiles())->toBe(['existing-proof.png']);
});

test('successful workflows keep their uploaded files', function () {
    Storage::fake('public');
    $path = WorkflowTransaction::run(fn () => PublicUpload::storeFile(
        UploadedFile::fake()->image('proof.png'), 'installation-photos'
    ));
    Storage::disk('public')->assertExists($path);
});

test('customer emails wait until commit and are discarded on rollback', function () {
    Mail::fake();
    $application = integrityApplication();
    expect(fn () => WorkflowTransaction::run(function () use ($application) {
        CustomerEmailSender::sendApplicationApproved($application);
        Mail::assertNothingSent();
        throw new RuntimeException('Rollback approval');
    }))->toThrow(RuntimeException::class);
    Mail::assertNothingSent();

    WorkflowTransaction::run(function () use ($application) {
        CustomerEmailSender::sendApplicationApproved($application);
        Mail::assertNothingSent();
    });
    Mail::assertSent(\App\Mail\ApplicationApprovedMail::class, 1);
});

test('duplicate activation does not add duplicate history or notifications', function () {
    $application = integrityApplication(['status' => Application::STATUS_INSTALLED]);
    $technician = User::factory()->create(['role' => 'technician']);
    $installation = integrityInstallation($application, $technician, [
        'status' => Installation::STATUS_COMPLETED,
        'completed_at' => now(),
        'nap_code' => 'NAP-DUPLICATE',
        'port_number' => 'P-02',
        'equipment_serial_number' => 'EQ-DUPLICATE',
        'cable_length' => '50m',
        'installation_photo_paths' => ['installation-photos/duplicate-proof.jpg'],
    ]);
    $this->actingAs($technician)->patch(route('technician.installations.activate', $installation))
        ->assertSessionHasNoErrors();
    $count = AppNotification::count();
    $this->patch(route('technician.installations.activate', $installation))->assertSessionHasErrors('status');
    expect(AppNotification::count())->toBe($count);
    $this->assertDatabaseCount('installation_logs', 1);
});

test('a pending cancellation blocks starting or reassigning the installation', function () {
    $application = integrityApplication(['status' => Application::STATUS_CANCELLATION_REQUESTED]);
    $technician = User::factory()->create(['role' => 'technician']);
    $replacement = User::factory()->create(['role' => 'technician']);
    $installation = integrityInstallation($application, $technician);
    $this->actingAs($technician)->patch(route('technician.installations.status', $installation), [
        'status' => Installation::STATUS_IN_PROGRESS,
    ])->assertSessionHasErrors('status');
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.installations.reassign', $installation), [
            'technician_id' => $replacement->id,
        ])->assertSessionHasErrors('reassignment');

    expect($installation->fresh()->status)->toBe(Installation::STATUS_SCHEDULED)
        ->and($installation->fresh()->technician_id)->toBe($technician->id);
    $this->assertDatabaseCount('installation_logs', 0);
});
