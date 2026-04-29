<?php

use App\Models\Application;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function firstServiceArea(): array
{
    $region = config('luzon_service_areas.regions')[0];
    $province = $region['provinces'][0];
    $municipality = collect($province['municipalities'])
        ->first(fn (array $item) => ! empty($item['barangays']))
        ?? $province['municipalities'][0];
    $barangay = $municipality['barangays'][0];

    return [
        'region' => $region['name'],
        'province' => $province['name'],
        'municipality' => $municipality['name'],
        'barangay' => is_array($barangay) ? $barangay['name'] : $barangay,
    ];
}

test('customer application stores consent metadata', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'role' => 'customer',
        'agreed_to_terms' => true,
        'agreed_to_privacy' => true,
        'consented_at' => now(),
        'terms_version' => '2026-03',
        'privacy_version' => '2026-03',
    ]);
    $area = firstServiceArea();

    $response = $this
        ->actingAs($user)
        ->post(route('customer.apply.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'mobile_number' => '09171234567',
            'region' => $area['region'],
            'province' => $area['province'],
            'municipality' => $area['municipality'],
            'barangay' => $area['barangay'],
            'street_landmark' => 'Near plaza',
            'lat' => 16.2,
            'lng' => 121.0,
            'plan_code' => 'S2S_50',
            'id_image' => UploadedFile::fake()->image('id.png'),
            'agreed_to_terms' => '1',
            'agreed_to_privacy' => '1',
            'terms_version' => '2026-03',
            'privacy_version' => '2026-03',
        ]);

    $application = $user->refresh()->application;

    $response->assertRedirect(route('customer.apply.done', ['reference' => $application->reference_no], false));

    expect($application)->not->toBeNull()
        ->and($application->agreed_to_terms)->toBeTrue()
        ->and($application->agreed_to_privacy)->toBeTrue()
        ->and($application->consented_at)->not->toBeNull()
        ->and($application->terms_version)->toBe('2026-03')
        ->and($application->privacy_version)->toBe('2026-03')
        ->and($application->id_image_path)->toStartWith('application-ids/');

    Storage::disk('public')->assertExists($application->id_image_path);
});

test('cancelled customer application can be submitted again with the same details', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'customer']);
    $area = firstServiceArea();

    $payload = [
        'first_name' => 'Jane',
        'last_name' => 'Soriano',
        'email' => 'jane@example.com',
        'mobile_number' => '09154123872',
        'region' => $area['region'],
        'province' => $area['province'],
        'municipality' => $area['municipality'],
        'barangay' => $area['barangay'],
        'street_landmark' => null,
        'lat' => 16.2,
        'lng' => 121.0,
        'plan_code' => 'S2S_50',
    ];

    $this
        ->actingAs($user)
        ->post(route('customer.apply.store'), $payload + [
            'id_image' => UploadedFile::fake()->image('first-id.png'),
        ])
        ->assertRedirect();

    $application = $user->refresh()->application;
    $originalId = $application->id;

    $this
        ->actingAs($user)
        ->patch(route('customer.application.cancel', absolute: false))
        ->assertRedirect();

    expect($application->refresh()->status)->toBe(Application::STATUS_CANCELLED);

    $this
        ->actingAs($user)
        ->post(route('customer.apply.store'), $payload + [
            'id_image' => UploadedFile::fake()->image('second-id.png'),
        ])
        ->assertRedirect();

    $application->refresh();

    expect(Application::where('user_id', $user->id)->count())->toBe(1)
        ->and($application->id)->toBe($originalId)
        ->and($application->status)->toBe(Application::STATUS_SUBMITTED)
        ->and($application->email)->toBe('jane@example.com')
        ->and($application->mobile_number)->toBe('09154123872');
});

test('rejected applications cannot be scheduled', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0001',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Test Region',
        'province' => 'Test Province',
        'municipality' => 'Test Municipality',
        'barangay' => 'Test Barangay',
        'street_landmark' => 'Test Landmark',
        'lat' => 16.2,
        'lng' => 121.0,
        'plan_code' => 'S2S_50',
        'status' => 'rejected',
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->post(route('admin.applications.schedule', $application, false), [
            'technician_id' => $technician->id,
            'installation_date' => '2026-04-10',
            'installation_time' => '09:00',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors('schedule');

    expect(Installation::count())->toBe(0)
        ->and($application->refresh()->status)->toBe('rejected');
});

test('approved applications cannot be rejected afterward', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-0002',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane2@example.com',
        'mobile_number' => '09171234567',
        'region' => 'Test Region',
        'province' => 'Test Province',
        'municipality' => 'Test Municipality',
        'barangay' => 'Test Barangay',
        'street_landmark' => 'Test Landmark',
        'lat' => 16.2,
        'lng' => 121.0,
        'plan_code' => 'S2S_50',
        'status' => 'approved',
        'serviceability_status' => 'serviceable',
        'slot_availability_status' => 'available',
        'assessment_notes' => 'Ready for installation.',
        'assessed_at' => now(),
        'assessed_by' => $admin->id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.applications.show', $application, false))
        ->patch(route('admin.applications.status', $application, false), [
            'status' => 'rejected',
            'serviceability_status' => 'serviceable',
            'slot_availability_status' => 'available',
            'assessment_notes' => 'Attempted change.',
            'rejection_reason' => 'Should not be allowed',
        ]);

    $response
        ->assertRedirect(route('admin.applications.show', $application, false))
        ->assertSessionHasErrors('status');

    expect($application->refresh()->status)->toBe('approved');
});
