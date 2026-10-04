<?php

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

test('manual registration rejects an email already created through Google', function () {
    $existing = User::factory()->create([
        'role' => 'customer',
        'email' => 'same.account@gmail.com',
        'google_id' => 'google-existing-123',
        'email_verified_at' => now(),
    ]);

    $this->post(route('register', absolute: false), [
        'first_name' => 'Duplicate',
        'last_name' => 'Customer',
        'email' => 'same.account@gmail.com',
        'password' => 'SecureRegistration2026!',
        'password_confirmation' => 'SecureRegistration2026!',
        'agreed_to_terms' => '1',
        'agreed_to_privacy' => '1',
    ])
        ->assertSessionHasErrorsIn('register', 'email')
        ->assertSessionHas('auth_modal', 'register');

    expect(User::query()->where('email', $existing->email)->count())->toBe(1);
});

test('Google login links to an existing manual account instead of creating a duplicate', function () {
    config()->set('services.google.ca_bundle', null);

    $existing = User::factory()->unverified()->create([
        'role' => 'customer',
        'email' => 'manual.account@gmail.com',
        'google_id' => null,
        'password' => Hash::make('OriginalManualPassword2026!'),
        'password_set_at' => now(),
    ]);

    $googleUser = mock(SocialiteUser::class);
    $googleUser->shouldReceive('getEmail')->andReturn($existing->email);
    $googleUser->shouldReceive('getId')->andReturn('google-linked-456');
    $googleUser->shouldReceive('getName')->andReturn('Manual Account');
    $googleUser->shouldReceive('getNickname')->andReturn(null);

    $provider = mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->withSession(['google_auth_mode' => 'login'])
        ->get(route('auth.google.callback'))
        ->assertRedirect('/customer/dashboard');

    $this->assertAuthenticatedAs($existing);

    expect(User::query()->where('email', $existing->email)->count())->toBe(1)
        ->and($existing->refresh()->google_id)->toBe('google-linked-456')
        ->and($existing->email_verified_at)->not->toBeNull()
        ->and($existing->password_set_at)->toBeNull()
        ->and(Hash::check('OriginalManualPassword2026!', $existing->password))->toBeFalse()
        ->and(SystemLog::query()
            ->where('user_id', $existing->id)
            ->where('action', 'Account Login')
            ->where('description', 'Signed in using Google.')
            ->exists())->toBeTrue();
});
