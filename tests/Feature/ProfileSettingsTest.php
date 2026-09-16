<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response(config('profile_test.breach_response', ''), 200)]);
});

function profileSettingsPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => 'Profile Tester', 'username' => $user->username,
        'email' => $user->email, 'phone' => '+63 912 345 6789',
        'address' => 'Test address', 'date_of_birth' => '1995-01-02',
        'gender' => 'female', 'remove_avatar' => '0',
    ], $overrides);
}

function profileSettingsImage(string $extension = 'png'): UploadedFile
{
    return new UploadedFile(__DIR__.'/../Fixtures/application-id/holding-id.'.$extension,
        'avatar.'.$extension, 'image/'.($extension === 'jpg' ? 'jpeg' : $extension), null, true);
}

test('each role saves profile details and adds replaces and removes an avatar', function (string $role) {
    Storage::fake('public');
    $user = User::factory()->create(['role' => $role, 'username' => 'profile-'.$role]);
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('profileForm', false)->assertSee('passwordForm', false);
    foreach (['png', 'jpg', 'webp'] as $extension) {
        $old = $user->avatar_path;
        $this->post('/profile', profileSettingsPayload($user, [
            '_method' => 'PATCH', 'avatar' => profileSettingsImage($extension),
            'email' => '  SAVED-'.$role.'@example.test  ',
            'role' => 'admin', 'password' => 'UnwantedChange1!', 'avatar_path' => 'untrusted/path',
        ]))->assertSessionHasNoErrors()->assertRedirect('/profile')->assertSessionHas('status', 'profile-updated');
        $user->refresh();
        expect($user->name)->toBe('Profile Tester')->and($user->phone)->toBe('+639123456789')
            ->and($user->email)->toBe('saved-'.$role.'@example.test')->and($user->role)->toBe($role)
            ->and($user->address)->toBe('Test address')->and($user->date_of_birth->toDateString())->toBe('1995-01-02')
            ->and($user->gender)->toBe('female')->and(Hash::check('password', $user->password))->toBeTrue();
        Storage::disk('public')->assertExists($user->avatar_path);
        expect(Storage::disk('public')->get($user->avatar_path))->toBe(file_get_contents(__DIR__.'/../Fixtures/application-id/holding-id.'.$extension));
        if ($old) Storage::disk('public')->assertMissing($old);
        $this->get('/profile')->assertOk()->assertSee($user->avatar_url, false)->assertSee('Test address');
    }
    $old = $user->avatar_path;
    $this->patch('/profile', profileSettingsPayload($user, ['remove_avatar' => '1']))->assertSessionHasNoErrors();
    expect($user->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($old);
})->with(['admin', 'technician', 'customer']);

test('a failed profile save preserves the old photo and cleans up any replacement', function (bool $replace) {
    Storage::fake('public');
    $user = User::factory()->create(['username' => 'rollback-profile', 'avatar_path' => 'avatars/old.png']);
    Storage::disk('public')->put($user->avatar_path, 'original photo');
    $before = $user->fresh()->getRawOriginal();
    $dispatcher = User::getEventDispatcher();
    $isolated = clone $dispatcher;
    User::setEventDispatcher($isolated);
    $isolated->listen('eloquent.updating: '.User::class, function () { throw new RuntimeException('Simulated profile database failure'); });
    try {
        $this->withoutExceptionHandling()->actingAs($user)->patch('/profile', profileSettingsPayload($user,
            $replace ? ['avatar' => profileSettingsImage()] : ['remove_avatar' => '1']));
        $this->fail('Expected a database failure');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Simulated profile database failure');
    } finally {
        User::setEventDispatcher($dispatcher);
    }
    expect($user->fresh()->getRawOriginal())->toBe($before);
    Storage::disk('public')->assertExists('avatars/old.png');
    expect(Storage::disk('public')->allFiles())->toBe(['avatars/old.png']);
})->with([true, false]);

test('invalid profile fields and duplicate contacts do not save partial changes', function (string $field, mixed $value) {
    Storage::fake('public');
    $user = User::factory()->create(['username' => 'validation-profile']);
    User::factory()->create(['username' => 'taken-profile', 'email' => 'taken@example.test']);
    $before = $user->fresh()->getRawOriginal();
    if ($value === 'invalid-image') $value = UploadedFile::fake()->create('avatar.txt', 1, 'text/plain');
    if ($value === 'oversized-image') $value = UploadedFile::fake()->image('large.png')->size(2049);
    $this->actingAs($user)->patch('/profile', profileSettingsPayload($user, [$field => $value]))->assertSessionHasErrors($field);
    expect($user->fresh()->getRawOriginal())->toBe($before);
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    ['username', 'taken-profile'], ['email', 'TAKEN@example.test'], ['email', 'not-an-email'],
    ['phone', 'abcdefghij'], ['phone', '09123456789abc'], ['phone', '123'],
    ['phone', '1234567890123456'], ['date_of_birth', '2999-01-01'], ['gender', 'invalid'],
    ['avatar', 'invalid-image'], ['avatar', 'oversized-image'], ['name', ''], ['address', str_repeat('x', 501)],
]);

test('password change persists and only the new password works for each role', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password', 'password' => 'ProfileQa2026!z', 'password_confirmation' => 'ProfileQa2026!z',
    ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');
    expect(Hash::check('ProfileQa2026!z', $user->fresh()->password))->toBeTrue();
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrorsIn('login', 'email');
    $this->assertGuest();
    $this->get('/');
    $this->post('/login', ['email' => $user->email, 'password' => 'ProfileQa2026!z'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
})->with(['admin', 'technician', 'customer']);

test('rejected password updates leave the existing password usable', function (array $overrides, string $field) {
    $user = User::factory()->create();
    $before = $user->password;
    $this->actingAs($user)->from('/profile')->put('/password', array_merge([
        'current_password' => 'password', 'password' => 'ProfileQa2026!z', 'password_confirmation' => 'ProfileQa2026!z',
    ], $overrides))->assertSessionHasErrorsIn('updatePassword', $field);
    expect($user->fresh()->password)->toBe($before);
})->with([
    [['current_password' => 'wrong'], 'current_password'],
    [['password_confirmation' => 'different'], 'password'],
    [['password' => 'weak', 'password_confirmation' => 'weak'], 'password'],
    [['password' => '', 'password_confirmation' => ''], 'password'],
]);


test('a breached password is rejected without changing the stored password', function () {
    $user = User::factory()->create();
    $candidate = 'ProfileQa2026!z';
    config(['profile_test.breach_response' => substr(strtoupper(sha1($candidate)), 5).':10']);
    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password', 'password' => $candidate, 'password_confirmation' => $candidate,
    ])->assertSessionHasErrorsIn('updatePassword', 'password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});


test('storage failure does not replace the avatar or save other profile changes', function () {
    $disk = Storage::fake('public');
    $user = User::factory()->create(['username' => 'storage-failure', 'avatar_path' => 'avatars/original.png']);
    $disk->put($user->avatar_path, 'original');
    $before = $user->fresh()->getRawOriginal();
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('putFileAs')->andReturn(false);
    Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);
    $this->actingAs($user)->patch('/profile', profileSettingsPayload($user, ['avatar' => profileSettingsImage()]))
        ->assertSessionHasErrors('avatar');
    expect($user->fresh()->getRawOriginal())->toBe($before);
    expect($disk->allFiles())->toBe(['avatars/original.png']);
});
