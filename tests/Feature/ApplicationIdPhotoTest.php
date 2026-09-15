<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function singleIdPayload(User $customer): array
{
    $region = config('luzon_service_areas.regions')[0];
    $province = $region['provinces'][0];
    $municipality = collect($province['municipalities'])->first(fn ($area) => ! empty($area['barangays']));
    $barangay = $municipality['barangays'][0];

    return [
        'first_name' => 'Photo', 'last_name' => 'Customer', 'email' => $customer->email,
        'mobile_number' => '09171234567', 'region' => $region['name'], 'province' => $province['name'],
        'municipality' => $municipality['name'], 'barangay' => is_array($barangay) ? $barangay['name'] : $barangay,
        'lat' => 16.2, 'lng' => 121.0, 'plan_code' => 'S2S_50',
    ];
}

function singleIdApplication(User $customer, string $path): Application
{
    return Application::create(singleIdPayload($customer) + [
        'user_id' => $customer->id, 'reference_no' => 'APP-PHOTO-'.$customer->id,
        'status' => Application::STATUS_SUBMITTED, 'id_selfie_image_path' => $path,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('one customer holding ID photo submits and is readable as the same image by admin and owner', function (string $extension) {
    $customer = User::factory()->create(['role' => 'customer']);
    $photo = new UploadedFile(base_path('tests/Fixtures/application-id/holding-id.'.$extension), 'holding-id.'.$extension, null, null, true);
    $bytes = file_get_contents($photo->getRealPath());
    $this->actingAs($customer)->get(route('customer.apply'))->assertOk()
        ->assertSee('name="id_selfie_image"', false)->assertDontSee('name="id_image"', false)
        ->assertDontSee('3 signatures')->assertDontSee('idSignatureInput');
    $this->post(route('customer.apply.store'), singleIdPayload($customer) + ['id_selfie_image' => $photo])
        ->assertSessionHasNoErrors()->assertRedirect();
    $application = $customer->fresh()->application;
    expect($application->id_image_path)->toBeNull();
    Storage::disk('local')->assertExists($application->id_selfie_image_path);
    Storage::disk('public')->assertMissing($application->id_selfie_image_path);
    $url = $application->idSelfieImageUrl();
    expect($url)->toContain('/id-photo/selfie')->not->toContain('/storage/');
    $response = $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe($bytes)
        ->and($response->headers->get('Content-Type'))->toStartWith('image/')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.applications.show', $application))->assertOk()->assertSee($url, false);
    expect($this->get($url)->assertOk()->streamedContent())->toBe($bytes);
})->with(['jpg', 'png', 'webp']);

test('missing invalid and oversized selfie uploads fail without creating records or files', function (string $case) {
    $customer = User::factory()->create(['role' => 'customer']);
    $payload = singleIdPayload($customer);
    if ($case === 'invalid') $payload['id_selfie_image'] = UploadedFile::fake()->create('not-image.txt', 2, 'text/plain');
    if ($case === 'oversized') $payload['id_selfie_image'] = UploadedFile::fake()->image('large.png')->size(5121);
    $this->actingAs($customer)->post(route('customer.apply.store'), $payload)
        ->assertSessionHasErrors('id_selfie_image')->assertSessionDoesntHaveErrors('id_image');
    expect($customer->fresh()->application)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([])->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['missing', 'invalid', 'oversized']);

test('guests other customers and technicians cannot retrieve ID photos', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $path = 'application-ids/private/test.png';
    $photo = UploadedFile::fake()->image('test.png');
    Storage::disk('local')->put($path, file_get_contents($photo->getRealPath()));
    $application = singleIdApplication($customer, $path);
    $url = $application->idSelfieImageUrl();
    $this->getJson($url)->assertUnauthorized();
    foreach (['customer', 'technician'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
    }
});

test('legacy public ID photos remain viewable through the authorized gallery route', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $path = 'application-ids/old-id.png';
    $photo = UploadedFile::fake()->image('old-id.png');
    $bytes = file_get_contents($photo->getRealPath());
    Storage::disk('public')->put($path, $bytes);
    $application = singleIdApplication($customer, $path);
    $application->update(['id_image_path' => $path]);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.applications.show', $application))->assertOk()
        ->assertSee('Previously uploaded ID')->assertSee($application->idImageUrl(), false);
    expect($this->get($application->idImageUrl())->assertOk()->streamedContent())->toBe($bytes);
    expect($this->get($application->idSelfieImageUrl())->assertOk()->streamedContent())->toBe($bytes);
});

test('missing files and invalid document selectors return not found', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $application = singleIdApplication($customer, 'application-ids/private/missing.png');
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('applications.id-photo', [$application, 'selfie']))->assertNotFound();
    $this->get(route('applications.id-photo', [$application, 'secret']))->assertNotFound();
    expect($application->idSelfieImageUrl())->toBeNull();
    $application->update(['id_selfie_image_path' => 'application-ids/../secret.png']);
    $this->get(route('applications.id-photo', [$application, 'selfie']))->assertNotFound();
});

test('a failed submission rolls back the new private image and the application together', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Storage::disk('local')->put('application-ids/private/existing.png', 'existing file');
    $event = 'eloquent.created: '.Application::class;
    Event::listen($event, fn () => throw new RuntimeException('Simulated failure after upload'));
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->actingAs($customer)->post(route('customer.apply.store'), singleIdPayload($customer) + [
            'id_selfie_image' => UploadedFile::fake()->image('new.png'),
        ]))->toThrow(RuntimeException::class, 'Simulated failure after upload');
    } finally {
        Event::forget($event);
    }
    expect($customer->fresh()->application)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe(['application-ids/private/existing.png']);
});
