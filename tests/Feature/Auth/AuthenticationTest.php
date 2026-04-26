<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertRedirect('/');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('customer.dashboard', absolute: false));

    $followUp = $this->actingAs($user)->get(route('dashboard', absolute: false));
    $followUp->assertRedirect(route('customer.apply', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('google login does not create an account for an unregistered email', function () {
    config()->set('services.google.ca_bundle', null);

    $googleUser = mock(SocialiteUser::class);
    $googleUser->shouldReceive('getEmail')->andReturn('new-google-user@example.com');
    $googleUser->shouldReceive('getId')->andReturn('google-123');
    $googleUser->shouldReceive('getName')->andReturn('New Google User');
    $googleUser->shouldReceive('getNickname')->andReturn(null);

    $provider = mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $response = $this
        ->withSession(['google_auth_mode' => 'login'])
        ->get(route('auth.google.callback'));

    $this->assertGuest();
    expect(User::where('email', 'new-google-user@example.com')->exists())->toBeFalse();

    $response
        ->assertRedirect('/')
        ->assertSessionHas('auth_modal', 'login')
        ->assertSessionHas('google_auth_error', 'No account matches that Google email. Register first before signing in with Google.');
});
