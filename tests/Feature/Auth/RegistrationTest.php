<?php

use App\Models\AppNotification;
use App\Models\User;
use App\Notifications\VerifyCustomerEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;

test('registration route redirects to the homepage modal', function () {
    $response = $this->get('/register');

    $response->assertRedirect('/');
});

test('new manual users register as unverified customers and receive a verification email', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => '  TEST@EXAMPLE.COM ',
        'password' => 'MjJd!Reg2026Qx7',
        'password_confirmation' => 'MjJd!Reg2026Qx7',
        'role' => 'technician',
        'agreed_to_terms' => '1',
        'agreed_to_privacy' => '1',
        'terms_version' => '2026-03',
        'privacy_version' => '2026-03',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::firstOrFail();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->role)->toBe('customer')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->agreed_to_terms)->toBeTrue()
        ->and($user->agreed_to_privacy)->toBeTrue()
        ->and($user->consented_at)->not->toBeNull()
        ->and($user->terms_version)->toBe('2026-03')
        ->and($user->privacy_version)->toBe('2026-03');

    Notification::assertSentTo($user, VerifyCustomerEmail::class);
});

test('new customer registration creates an admin customers sidebar notification', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->post('/register', [
        'first_name' => 'New',
        'last_name' => 'Customer',
        'email' => 'new.customer@example.com',
        'password' => 'MjJd!Reg2026Qx8',
        'password_confirmation' => 'MjJd!Reg2026Qx8',
        'agreed_to_terms' => '1',
        'agreed_to_privacy' => '1',
    ])->assertRedirect(route('verification.notice', absolute: false));

    $notification = AppNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'customer_registered')
        ->firstOrFail();

    expect($notification->sidebarSectionFor($admin))->toBe('customers')
        ->and($notification->read_at)->toBeNull();
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

test('manual registration rejects passwords that do not meet the shared password policy', function (string $password) {
    Notification::fake();

    $this->from('/')
        ->post('/register', [
            'first_name' => 'Weak',
            'last_name' => 'Password',
            'email' => 'weak.'.md5($password).'@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'agreed_to_terms' => '1',
            'agreed_to_privacy' => '1',
        ])
        ->assertRedirect('/')
        ->assertSessionHasErrorsIn('register', 'password')
        ->assertSessionHas('auth_modal', 'register');

    $this->assertGuest();
    expect(User::query()->where('email', 'weak.'.md5($password).'@example.com')->exists())->toBeFalse();
})->with([
    'seven digits' => '1234567',
    'missing uppercase' => 'lowercase1!',
    'missing lowercase' => 'UPPERCASE1!',
    'missing number' => 'NoNumber!',
    'missing symbol' => 'NoSymbol1',
]);

test('customer registration remains recoverable when verification email delivery fails', function () {
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection unavailable'));
    $this->app->instance(Dispatcher::class, $dispatcher);

    $response = $this->post('/register', [
        'first_name' => 'Delivery',
        'last_name' => 'Retry',
        'email' => 'delivery.retry@example.com',
        'password' => 'MjJd!Reg2026Qx9',
        'password_confirmation' => 'MjJd!Reg2026Qx9',
        'agreed_to_terms' => '1',
        'agreed_to_privacy' => '1',
    ]);

    $user = User::where('email', 'delivery.retry@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'verification-link-failed');
    expect($user->hasVerifiedEmail())->toBeFalse();
});
