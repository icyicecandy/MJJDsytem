<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk()
        ->assertSee('name="date_of_birth"', false)
        ->assertSee('name="gender"', false)
        ->assertDontSee('name="bio"', false)
        ->assertDontSee('name="language"', false)
        ->assertDontSee('name="timezone"', false)
        ->assertDontSee('name="theme_preference"', false)
        ->assertDontSee('name="profile_visibility"', false)
        ->assertDontSee('Notifications and privacy')
        ->assertDontSee('Account Tools')
        ->assertDontSee(route('profile.export', absolute: false), false)
        ->assertDontSee(route('profile.deletion-request', absolute: false), false)
        ->assertDontSee('Delete account');
});

test('profile information can be updated with avatar and core details', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'bio' => 'Existing support note',
        'phone' => '+639111111111',
        'phone_verified_at' => now(),
        'language' => 'fil',
        'timezone' => 'Asia/Manila',
        'theme_preference' => 'dark',
        'email_notifications' => false,
        'push_notifications' => true,
        'sms_notifications' => true,
        'profile_visibility' => 'public',
        'data_sharing_enabled' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'username' => 'Test User!!',
            'email' => 'test@example.com',
            'phone' => '+63 912 345 6789',
            'date_of_birth' => '1997-08-15',
            'gender' => 'female',
            'address' => 'Quezon City',
            'remove_avatar' => '0',
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->username)->toBe('test-user')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->phone)->toBe('+639123456789')
        ->and($user->bio)->toBe('Existing support note')
        ->and($user->gender)->toBe('female')
        ->and($user->address)->toBe('Quezon City')
        ->and($user->language)->toBe('fil')
        ->and($user->timezone)->toBe('Asia/Manila')
        ->and($user->theme_preference)->toBe('dark')
        ->and($user->email_notifications)->toBeFalse()
        ->and($user->push_notifications)->toBeTrue()
        ->and($user->sms_notifications)->toBeTrue()
        ->and($user->profile_visibility)->toBe('public')
        ->and($user->data_sharing_enabled)->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone_verified_at)->toBeNull()
        ->and($user->avatar_path)->not->toBeNull();

    Storage::disk('public')->assertExists($user->avatar_path);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create([
        'username' => 'existing-user',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'username' => 'existing-user',
            'email' => $user->email,
            'phone' => '',
            'date_of_birth' => '',
            'gender' => '',
            'address' => '',
            'remove_avatar' => '0',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('username availability endpoint reports taken usernames', function () {
    $user = User::factory()->create(['username' => 'current-user']);
    User::factory()->create(['username' => 'taken-name']);

    $response = $this
        ->actingAs($user)
        ->getJson('/profile/username-check?username=taken-name');

    $response
        ->assertStatus(422)
        ->assertJsonFragment([
            'available' => false,
            'sanitized' => 'taken-name',
        ]);
});

test('account export can be downloaded', function () {
    $user = User::factory()->create(['username' => 'export-user']);

    $response = $this
        ->actingAs($user)
        ->get('/profile/export');

    $response
        ->assertOk()
        ->assertHeader('content-type', 'application/json');
});

test('admin can request account deletion review', function () {
    $user = User::factory()->create(['role' => 'admin', 'username' => 'delete-request']);

    $response = $this
        ->actingAs($user)
        ->post('/profile/deletion-request');

    $response->assertRedirect('/profile');
    expect($user->refresh()->deletion_requested_at)->not->toBeNull();
});

test('customer cannot request account deletion review', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $response = $this
        ->actingAs($user)
        ->post('/profile/deletion-request');

    $response->assertForbidden();
    expect($user->refresh()->deletion_requested_at)->toBeNull();
});

test('admin can delete their account', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('customer cannot delete their account', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertForbidden();
    $this->assertNotNull($user->fresh());
});
