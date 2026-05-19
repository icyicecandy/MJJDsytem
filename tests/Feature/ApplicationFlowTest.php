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

test('pangasinan service catalog has barangays and municipality pin centers', function () {
    $region = config('luzon_service_areas.regions')[0];
    $province = $region['provinces'][0];
    $municipalities = collect($province['municipalities']);
    $binalonan = $municipalities->firstWhere('name', 'Binalonan');

    expect($region['name'])->toBe('Ilocos Region')
        ->and($province['name'])->toBe('Pangasinan')
        ->and($municipalities)->toHaveCount(48)
        ->and($municipalities->filter(fn (array $item) => empty($item['barangays'])))->toHaveCount(0)
        ->and($municipalities->sum(fn (array $item) => count($item['barangays'])))->toBe(1364)
        ->and($binalonan)->not->toBeNull()
        ->and(round($binalonan['lat'], 3))->toBe(16.044)
        ->and(round($binalonan['lng'], 3))->toBe(120.592)
        ->and($binalonan['barangays'])->toContain('Sto. Niño');
});

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
            'id_selfie_image' => UploadedFile::fake()->image('id-selfie.png'),
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
        ->and($application->id_image_path)->toStartWith('application-ids/')
        ->and($application->id_selfie_image_path)->toStartWith('application-ids/');

    Storage::disk('public')->assertExists($application->id_image_path);
    Storage::disk('public')->assertExists($application->id_selfie_image_path);
});

test('customer application rejects invalid email and mobile number formats', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'customer']);
    $area = firstServiceArea();

    $response = $this
        ->actingAs($user)
        ->from(route('customer.apply', absolute: false))
        ->post(route('customer.apply.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane-at-example.test',
            'mobile_number' => '0917ABC4567',
            'region' => $area['region'],
            'province' => $area['province'],
            'municipality' => $area['municipality'],
            'barangay' => $area['barangay'],
            'street_landmark' => 'Near plaza',
            'lat' => 16.2,
            'lng' => 121.0,
            'plan_code' => 'S2S_50',
            'id_image' => UploadedFile::fake()->image('id.png'),
            'id_selfie_image' => UploadedFile::fake()->image('id-selfie.png'),
        ]);

    $response
        ->assertRedirect(route('customer.apply', absolute: false))
        ->assertSessionHasErrors(['email', 'mobile_number']);

    expect($user->refresh()->application)->toBeNull();
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
            'id_selfie_image' => UploadedFile::fake()->image('first-id-selfie.png'),
        ])
        ->assertRedirect();

    $application = $user->refresh()->application;
    $originalId = $application->id;

    $this
        ->actingAs($user)
        ->patch(route('customer.application.cancel', absolute: false), [
            'cancellation_reason' => 'I need to correct my application details.',
        ])
        ->assertRedirect();

    expect($application->refresh()->status)->toBe(Application::STATUS_CANCELLED)
        ->and($application->cancellation_reason)->toBe('I need to correct my application details.');

    $this
        ->actingAs($user)
        ->post(route('customer.apply.store'), $payload + [
            'id_image' => UploadedFile::fake()->image('second-id.png'),
            'id_selfie_image' => UploadedFile::fake()->image('second-id-selfie.png'),
        ])
        ->assertRedirect();

    $application->refresh();

    expect(Application::where('user_id', $user->id)->count())->toBe(1)
        ->and($application->id)->toBe($originalId)
        ->and($application->status)->toBe(Application::STATUS_SUBMITTED)
        ->and($application->cancellation_reason)->toBeNull()
        ->and($application->email)->toBe('jane@example.com')
        ->and($application->mobile_number)->toBe('09154123872');
});

test('admin can see customer cancellation reason on a cancelled application', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $area = firstServiceArea();

    $application = Application::create([
        'user_id' => $customer->id,
        'reference_no' => 'APP-2026-CANCEL-VIEW',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.cancel@example.com',
        'mobile_number' => '09170007001',
        'region' => $area['region'],
        'province' => $area['province'],
        'municipality' => $area['municipality'],
        'barangay' => $area['barangay'],
        'street_landmark' => 'Near market',
        'lat' => 16.2,
        'lng' => 121.0,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SUBMITTED,
    ]);

    $this
        ->actingAs($customer)
        ->from(route('customer.application.show', absolute: false))
        ->patch(route('customer.application.cancel', absolute: false), [
            'cancellation_reason' => 'Moving to another address.',
        ])
        ->assertRedirect(route('customer.application.show', absolute: false))
        ->assertSessionHasNoErrors();

    $this
        ->actingAs($admin)
        ->get(route('admin.applications.show', $application, false))
        ->assertOk()
        ->assertSee('Customer cancellation reason')
        ->assertSee('Moving to another address.');
});

test('customer must provide a reason before cancelling an application', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $area = firstServiceArea();

    $application = Application::create([
        'user_id' => $user->id,
        'reference_no' => 'APP-2026-REQ-CANCEL',
        'first_name' => 'Jane',
        'last_name' => 'Soriano',
        'email' => 'jane.cancel@example.com',
        'mobile_number' => '09154123872',
        'region' => $area['region'],
        'province' => $area['province'],
        'municipality' => $area['municipality'],
        'barangay' => $area['barangay'],
        'street_landmark' => null,
        'lat' => 16.2,
        'lng' => 121.0,
        'plan_code' => 'S2S_50',
        'status' => Application::STATUS_SUBMITTED,
    ]);

    $this
        ->actingAs($user)
        ->from(route('customer.application.show', absolute: false))
        ->patch(route('customer.application.cancel', absolute: false), [
            'cancellation_reason' => '',
        ])
        ->assertRedirect(route('customer.application.show', absolute: false))
        ->assertSessionHasErrors([
            'cancellation_reason' => 'Please provide a reason before requesting cancellation.',
        ]);

    expect($application->refresh()->status)->toBe(Application::STATUS_SUBMITTED);
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
