<?php

use App\Models\User;

test('registration route redirects to the homepage modal', function () {
    $response = $this->get('/register');

    $response->assertRedirect('/');
});

test('new users register as customers and consent is stored', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'technician',
        'agreed_to_terms' => '1',
        'agreed_to_privacy' => '1',
        'terms_version' => '2026-03',
        'privacy_version' => '2026-03',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('customer.dashboard', absolute: false));

    $user = User::firstOrFail();

    expect($user->name)->toBe('Test User')
        ->and($user->role)->toBe('customer')
        ->and($user->agreed_to_terms)->toBeTrue()
        ->and($user->agreed_to_privacy)->toBeTrue()
        ->and($user->consented_at)->not->toBeNull()
        ->and($user->terms_version)->toBe('2026-03')
        ->and($user->privacy_version)->toBe('2026-03');
});

test('registration validation reopens modal with compact errors', function () {
    $response = $this
        ->from('/')
        ->followingRedirects()
        ->post('/register', []);

    $response
        ->assertOk()
        ->assertSee('registerModal', false)
        ->assertSee('const activeAuthModal = "register";', false)
        ->assertSee('register-error-banner', false)
        ->assertSee('Please complete the highlighted fields.')
        ->assertSee('register-consent-error is-visible', false)
        ->assertDontSee('register-error-summary', false)
        ->assertDontSee('Please fix the following errors');
});
