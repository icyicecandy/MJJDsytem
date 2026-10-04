<?php

use App\Models\Application;
use App\Models\ApplicationDraft;
use App\Models\User;
use App\Support\ApplicationContact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function contactPayload(array $overrides = []): array
{
    $region = config('luzon_service_areas.regions')[0];
    $province = $region['provinces'][0];
    $municipality = collect($province['municipalities'])->first(fn ($area) => ! empty($area['barangays']));
    $barangay = $municipality['barangays'][0];
    return array_replace([
        'first_name' => 'Contact', 'last_name' => 'Customer', 'email' => 'contact@example.test',
        'mobile_number' => '09171234567', 'region' => $region['name'], 'province' => $province['name'],
        'municipality' => $municipality['name'], 'barangay' => is_array($barangay) ? $barangay['name'] : $barangay,
        'lat' => '16.2', 'lng' => '121.0', 'plan_code' => 'S2S_50',
    ], $overrides);
}

function existingContactApplication(array $overrides = []): Application
{
    $customer = User::factory()->create(['role' => 'customer']);
    return Application::create(contactPayload($overrides) + [
        'user_id' => $customer->id, 'reference_no' => 'APP-CONTACT-'.$customer->id, 'status' => 'submitted',
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

test('equivalent Philippine phone formats are blocked in live checks and final submissions', function (string $number) {
    existingContactApplication();
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->getJson(route('customer.apply.contact-check', ['mobile_number' => $number]))
        ->assertOk()->assertJsonPath('mobile_number.duplicate', true);
    $this->post(route('customer.apply.store'), contactPayload(['email' => 'new@example.test', 'mobile_number' => $number]) + validApplicationIdentity())->assertSessionHasErrors('mobile_number');
    expect(Application::count())->toBe(1)->and(Storage::disk('local')->allFiles())->toBeEmpty();
})->with(['09171234567', '+639171234567', '639171234567', '0917 123 4567', '+63 (917) 123-4567']);

test('email capitalization and boundary spaces cannot bypass duplicate protection', function () {
    existingContactApplication();
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->getJson(route('customer.apply.contact-check', ['email' => ' CONTACT@EXAMPLE.TEST ']))
        ->assertOk()->assertJsonPath('email.duplicate', true);
    $this->post(route('customer.apply.store'), contactPayload(['email' => ' CONTACT@EXAMPLE.TEST ', 'mobile_number' => '09179876543']) + validApplicationIdentity())->assertSessionHasErrors('email');
    expect(Application::count())->toBe(1);
});

test('valid submission normalizes contacts and keeps private ID accessible to its owner', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->post(route('customer.apply.store'), contactPayload([
        'email' => ' CONTACT@EXAMPLE.TEST ', 'mobile_number' => '+63 (917) 123-4567',
    ]) + validApplicationIdentity())->assertSessionHasNoErrors()->assertRedirect();
    $application = Application::sole();
    expect($application->email)->toBe('contact@example.test')->and($application->email_key)->toBe('contact@example.test')
        ->and($application->mobile_number)->toBe('09171234567')->and($application->mobile_number_key)->toBe('09171234567');
    $this->get($application->idSelfieImageUrl())->assertOk();
});

test('owner may reapply using equivalent contact formats on their existing record', function (string $status) {
    $application = existingContactApplication(['status' => $status]);
    $this->actingAs($application->user)->getJson(route('customer.apply.contact-check', [
        'email' => 'CONTACT@EXAMPLE.TEST', 'mobile_number' => '+639171234567',
    ]))->assertOk()->assertJsonPath('email.duplicate', false)->assertJsonPath('mobile_number.duplicate', false);
    $this->post(route('customer.apply.store'), contactPayload(['mobile_number' => '+639171234567']) + validApplicationIdentity())->assertSessionHasNoErrors();
    expect(Application::count())->toBe(2)
        ->and($application->fresh()->status)->toBe($status)
        ->and($application->user->refresh()->application->status)->toBe('submitted')
        ->and($application->user->application->id)->not->toBe($application->id);
})->with(['rejected', 'cancelled']);

test('malformed contact values are rejected instead of silently changing identity', function ($email, $phone, $field) {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->getJson(route('customer.apply.contact-check', ['email' => $email, 'mobile_number' => $phone]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->postJson(route('customer.apply.store'), contactPayload(['email' => $email, 'mobile_number' => $phone]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Application::count())->toBe(0);
})->with([
    ['con tact@example.test', '09171234567', 'email'],
    ['contact@example.test', '0917ABC4567', 'mobile_number'],
    [['contact@example.test'], '09171234567', 'email'],
    ['contact@example.test', ['09171234567'], 'mobile_number'],
    ['contact@example.test', '++639171234567', 'mobile_number'],
]);

test('draft contacts do not reserve identities and rejected submission preserves the draft and photo', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->post(route('customer.apply.draft.save'), contactPayload() + ['draft_step' => 4] + validApplicationIdentity())->assertOk();
    $draft = ApplicationDraft::sole();
    $other = User::factory()->create(['role' => 'customer']);
    $this->actingAs($other)->getJson(route('customer.apply.contact-check', ['email' => 'contact@example.test']))
        ->assertJsonPath('email.duplicate', false);
    existingContactApplication();
    $this->actingAs($customer)->post(route('customer.apply.store'), contactPayload() + ['draft_token' => $draft->token])
        ->assertSessionHasErrors(['email', 'mobile_number']);
    expect(ApplicationDraft::sole()->token)->toBe($draft->token)->and(Application::count())->toBe(1);
    Storage::disk('local')->assertExists($draft->photo_path);
});

test('migration preserves historical duplicate contacts and unrelated status changes', function () {
    $migration = require database_path('migrations/2026_09_16_000001_add_application_contact_keys.php');
    $first = existingContactApplication();
    $second = existingContactApplication(['email' => 'second@example.test']);
    $migration->down();
    DB::table('applications')->where('id', $first->id)->update(['mobile_number' => '+63 917 123 4567']);
    $migration->up();
    expect(Application::count())->toBe(2)->and($first->fresh()->mobile_number)->toBe('+63 917 123 4567')
        ->and($first->fresh()->mobile_number_key)->toBe('09171234567');
    $first->refresh()->update(['status' => 'under_review']);
    expect($first->fresh()->mobile_number)->toBe('+63 917 123 4567');
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->post(route('customer.apply.store'), contactPayload(['email' => 'new@example.test']) + validApplicationIdentity())->assertSessionHasErrors('mobile_number');
});

test('submission takes the shared contact guard before reading contact identities', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    DB::enableQueryLog();
    $this->actingAs($customer)->post(route('customer.apply.store'), contactPayload() + validApplicationIdentity())->assertSessionHasNoErrors();
    $queries = array_column(DB::getQueryLog(), 'query');
    $guard = collect($queries)->search( fn ($sql) => str_contains($sql, 'application_contact_guard'));
    $contact = collect($queries)->search( fn ($sql) => str_contains($sql, 'email_key') && str_starts_with($sql, 'select'));
    expect($guard)->not->toBeNull()->and($contact)->not->toBeNull()->and($guard)->toBeLessThan($contact);
    DB::disableQueryLog();
});

test('email aliases remain distinct and international phone plus prefixes share a key', function () {
    expect(ApplicationContact::key('email', 'person+tag@gmail.com'))->not->toBe(ApplicationContact::key('email', 'person@gmail.com'))
        ->and(ApplicationContact::key('email', 'per.son@gmail.com'))->not->toBe(ApplicationContact::key('email', 'person@gmail.com'))
        ->and(ApplicationContact::key('mobile_number', '+14155552671'))->toBe(ApplicationContact::key('mobile_number', '14155552671'));
});
