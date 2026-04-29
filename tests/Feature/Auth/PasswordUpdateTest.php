<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'Ab1!xyZ9',
            'password_confirmation' => 'Ab1!xyZ9',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'password-updated')
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertTrue(Hash::check('Ab1!xyZ9', $user->password));
    expect($user->password_set_at)->not->toBeNull();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'Ab1!xyZ9',
            'password_confirmation' => 'Ab1!xyZ9',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});

test('google account can set an initial password without current password', function () {
    $user = User::factory()->create([
        'google_id' => 'google-123',
        'password_set_at' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'password' => 'Gg1!xYz9',
            'password_confirmation' => 'Gg1!xYz9',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'password-set')
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertTrue(Hash::check('Gg1!xYz9', $user->password));
    expect($user->password_set_at)->not->toBeNull();
});

test('google account with a set password must provide current password', function () {
    $user = User::factory()->create([
        'google_id' => 'google-456',
        'password_set_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'password' => 'Gg1!xYz9',
            'password_confirmation' => 'Gg1!xYz9',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});

test('profile shows distinct password success messages', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->withSession(['status' => 'password-updated'])
        ->get('/profile')
        ->assertOk()
        ->assertSee('Password successfully changed')
        ->assertDontSee('Password updated');

    $this
        ->actingAs($user)
        ->withSession(['status' => 'password-set'])
        ->get('/profile')
        ->assertOk()
        ->assertSee('Password successfully set');
});
