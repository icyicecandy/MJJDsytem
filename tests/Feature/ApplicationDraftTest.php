<?php

use App\Models\Application;
use App\Models\ApplicationDraft;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function draftPhoto(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/application-id/holding-id.png'), 'holding-id.png', 'image/png', null, true);
}

function draftPayload(User $user): array
{
    $region = config('luzon_service_areas.regions')[0];
    $province = $region['provinces'][0];
    $municipality = collect($province['municipalities'])->first(fn ($area) => ! empty($area['barangays']));
    return [
        'first_name' => 'Draft', 'last_name' => 'Customer', 'email' => $user->email, 'mobile_number' => '09171234567',
        'region' => $region['name'], 'province' => $province['name'], 'municipality' => $municipality['name'],
        'barangay' => is_array($municipality['barangays'][0]) ? $municipality['barangays'][0]['name'] : $municipality['barangays'][0],
        'lat' => '16.2', 'lng' => '121.0', 'plan_code' => 'S2S_50', 'draft_step' => 4,
    ];
}

test('incomplete drafts are encrypted saved separately and resumed on a fresh request', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->postJson(route('customer.apply.draft.save'), [
        'first_name' => 'Draftname', 'email' => 'unfinished@', 'draft_step' => 2, 'status' => 'approved', 'user_id' => 999,
    ])->assertOk()->assertJsonStructure(['token', 'saved_at', 'expires_at', 'photo_url']);
    $draft = ApplicationDraft::sole();
    expect($draft->user_id)->toBe($user->id)->and($draft->payload['email'])->toBe('unfinished@')
        ->and($draft->payload)->not->toHaveKey('status')->and(Application::count())->toBe(0);
    expect(DB::table('application_drafts')->value('payload'))->not->toContain('Draftname', 'unfinished@');
    $this->get(route('customer.apply'))->assertOk()->assertSee('Your saved application draft has been restored.')
        ->assertSee('value="Draftname"', false)->assertSee('value="unfinished@"', false);
});

test('saved private photo is visible only to its customer and is reused on submission', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('customer.apply.draft.save'), draftPayload($user) + ['id_selfie_image' => draftPhoto()])
        ->assertOk();
    $draft = ApplicationDraft::sole();
    $path = $draft->photo_path;
    $url = $draft->photoUrl();
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
    $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($this->get($url)->streamedContent())->toBe(file_get_contents(base_path('tests/Fixtures/application-id/holding-id.png')));
    foreach (['admin', 'technician', 'customer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertStatus($role === 'customer' ? 404 : 403);
    }
    $this->actingAs($user)->post(route('customer.apply.store'), draftPayload($user) + ['draft_token' => $draft->token])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(ApplicationDraft::count())->toBe(0)->and($user->fresh()->application->id_selfie_image_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
    $this->get($url)->assertNotFound();
    $this->get($user->fresh()->application->idSelfieImageUrl())->assertOk();
});

test('stale saves discards and submissions cannot overwrite a newer draft', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->postJson(route('customer.apply.draft.save'), ['first_name' => 'First', 'draft_step' => 1])->assertOk();
    $old = ApplicationDraft::sole()->token;
    $this->postJson(route('customer.apply.draft.save'), ['first_name' => 'Latest', 'draft_step' => 2, 'draft_token' => $old])->assertOk();
    $this->postJson(route('customer.apply.draft.save'), ['first_name' => 'Stale', 'draft_step' => 1, 'draft_token' => $old])->assertConflict();
    $this->deleteJson(route('customer.apply.draft.destroy'), ['draft_token' => $old])->assertConflict();
    $this->post(route('customer.apply.store'), draftPayload($user) + ['draft_token' => $old, 'id_selfie_image' => draftPhoto()])->assertSessionHasErrors('draft');
    expect(ApplicationDraft::sole()->payload['first_name'])->toBe('Latest')->and(Application::count())->toBe(0);
});

test('draft saving cannot bypass final required fields address or duplicate checks', function (string $case) {
    $user = User::factory()->create(['role' => 'customer']);
    $payload = draftPayload($user);
    $field = match ($case) { 'missing' => 'first_name', 'address' => 'region', 'duplicate' => 'email' };
    if ($case === 'missing') $payload['first_name'] = '';
    if ($case === 'address') $payload['region'] = 'Invalid region';
    if ($case === 'duplicate') {
        $other = User::factory()->create();
        Application::create(draftPayload($other) + ['user_id' => $other->id, 'reference_no' => 'APP-DUPLICATE', 'status' => 'submitted']);
        $payload['email'] = $other->email;
    }
    $this->actingAs($user)->post(route('customer.apply.draft.save'), $payload + ['id_selfie_image' => draftPhoto()])->assertOk();
    $draft = ApplicationDraft::where('user_id', $user->id)->firstOrFail();
    $this->post(route('customer.apply.store'), $payload + ['draft_token' => $draft->token])->assertSessionHasErrors($field);
    expect($user->fresh()->application)->toBeNull()->and($draft->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($draft->photo_path);
})->with(['missing', 'address', 'duplicate']);

test('photo replacement and discard clean up only the corresponding draft files', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('customer.apply.draft.save'), ['draft_step' => 4, 'id_selfie_image' => draftPhoto()])->assertOk();
    $first = ApplicationDraft::sole();
    $this->post(route('customer.apply.draft.save'), ['draft_step' => 4, 'draft_token' => $first->token, 'id_selfie_image' => draftPhoto()])->assertOk();
    Storage::disk('local')->assertMissing($first->photo_path);
    $latest = ApplicationDraft::sole();
    $this->deleteJson(route('customer.apply.draft.destroy'), ['draft_token' => $latest->token])->assertNoContent();
    expect(ApplicationDraft::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('expired drafts cannot be viewed or submitted and cleanup preserves current drafts', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('customer.apply.draft.save'), draftPayload($user) + ['id_selfie_image' => draftPhoto()])->assertOk();
    $draft = ApplicationDraft::sole();
    $other = User::factory()->create(['role' => 'customer']);
    $this->actingAs($other)->postJson(route('customer.apply.draft.save'), ['draft_step' => 1])->assertOk();
    $draft->update(['expires_at' => now()->subSecond()]);
    $this->actingAs($user)->get(route('customer.apply'))->assertDontSee('Your saved application draft has been restored.');
    $this->get(route('customer.apply.draft.photo'))->assertNotFound();
    $this->post(route('customer.apply.store'), draftPayload($user) + ['draft_token' => $draft->token])->assertSessionHasErrors('draft');
    $this->artisan('applications:prune-drafts')->assertSuccessful();
    Storage::disk('local')->assertMissing($draft->photo_path);
    expect(ApplicationDraft::sole()->user_id)->toBe($other->id);
});

test('guests staff and customers with active applications cannot create drafts', function () {
    $this->postJson(route('customer.apply.draft.save'), ['draft_step' => 1])->assertUnauthorized();
    foreach (['admin', 'technician'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->postJson(route('customer.apply.draft.save'), ['draft_step' => 1])->assertForbidden();
    }
    $user = User::factory()->create(['role' => 'customer']);
    Application::create(draftPayload($user) + ['user_id' => $user->id, 'reference_no' => 'APP-ACTIVE', 'status' => 'submitted']);
    $this->actingAs($user)->postJson(route('customer.apply.draft.save'), ['draft_step' => 1])->assertConflict();
    expect(ApplicationDraft::count())->toBe(0);
});

test('invalid oversized and missing photos do not become usable draft uploads', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('customer.apply.draft.save'), ['draft_step' => 4, 'id_selfie_image' => UploadedFile::fake()->create('bad.txt', 2)])
        ->assertSessionHasErrors('id_selfie_image');
    $this->post(route('customer.apply.draft.save'), ['draft_step' => 4, 'id_selfie_image' => UploadedFile::fake()->image('large.png')->size(5121)])
        ->assertSessionHasErrors('id_selfie_image');
    $this->postJson(route('customer.apply.draft.save'), ['draft_step' => 4])->assertOk();
    $this->post(route('customer.apply.store'), draftPayload($user) + ['draft_token' => ApplicationDraft::sole()->token])->assertSessionHasErrors('id_selfie_image');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('failed draft replacement preserves old data and photo', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('customer.apply.draft.save'), ['draft_step' => 4, 'id_selfie_image' => draftPhoto()])->assertOk();
    $draft = ApplicationDraft::sole();
    $dispatcher = ApplicationDraft::getEventDispatcher();
    $isolated = clone $dispatcher;
    ApplicationDraft::setEventDispatcher($isolated);
    $isolated->listen('eloquent.updating: '.ApplicationDraft::class, fn () => throw new RuntimeException('Draft save failure'));
    try {
        $this->withoutExceptionHandling()->post(route('customer.apply.draft.save'), ['draft_step' => 4, 'draft_token' => $draft->token, 'id_selfie_image' => draftPhoto()]);
        $this->fail('Expected save failure');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Draft save failure');
    } finally { ApplicationDraft::setEventDispatcher($dispatcher); }
    expect($draft->fresh()->token)->toBe($draft->token)->and(Storage::disk('local')->allFiles())->toBe([$draft->photo_path]);
});

test('failed application submission preserves the draft and its existing photo', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user)->post(route('customer.apply.draft.save'), draftPayload($user) + ['id_selfie_image' => draftPhoto()])->assertOk();
    $draft = ApplicationDraft::sole();
    $dispatcher = Application::getEventDispatcher();
    $isolated = clone $dispatcher;
    Application::setEventDispatcher($isolated);
    $isolated->listen('eloquent.created: '.Application::class, fn () => throw new RuntimeException('Submission failure'));
    try {
        $this->withoutExceptionHandling()->post(route('customer.apply.store'), draftPayload($user) + ['draft_token' => $draft->token]);
        $this->fail('Expected submission failure');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Submission failure');
    } finally { Application::setEventDispatcher($dispatcher); }
    expect(Application::count())->toBe(0)->and($draft->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($draft->photo_path);
});
