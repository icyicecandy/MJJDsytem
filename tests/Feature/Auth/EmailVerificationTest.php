<?php

use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\VerifyCustomerEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('customer email verification screen explains the next steps', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertOk()
        ->assertSee('Verify your email address')
        ->assertSee($user->email)
        ->assertSee('Resend verification email')
        ->assertSee('Change email address');
});

test('customer verification email uses MJJD Telecom branding and a signed action link', function () {
    $user = User::factory()->unverified()->create([
        'name' => 'Email Recipient',
        'role' => 'customer',
    ]);

    $message = (new VerifyCustomerEmail)->toMail($user);

    expect($message->subject)->toBe('Verify your MJJD Telecom email address')
        ->and($message->greeting)->toBe('Welcome to MJJD Telecom, Email Recipient!')
        ->and($message->actionText)->toBe('Verify my email address')
        ->and(URL::hasValidSignature(request()->create($message->actionUrl)))->toBeTrue()
        ->and($message->salutation)->toBe('MJJD Telecom Customer Support');
});

test('customer email can be verified and is recorded in system logs', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(SystemLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'Customer Email Verified')
            ->exists())->toBeTrue();

    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('customer can verify from a browser where they are not signed in', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->get($verificationUrl)
        ->assertOk()
        ->assertSee('Email verified successfully')
        ->assertSee($user->email);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(SystemLog::query()
            ->where('user_id', $user->id)
            ->where('action', 'Customer Email Verified')
            ->exists())->toBeTrue();
});

test('customer signed link verifies its owner when another account is signed in', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'admin']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($otherUser)
        ->get($verificationUrl)
        ->assertOk()
        ->assertSee('Email verified successfully')
        ->assertSee('You are currently signed in as');

    $this->assertAuthenticatedAs($otherUser);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('expired customer verification link is rejected', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->get($verificationUrl)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('signed verification link cannot verify an ineligible account', function (array $attributes) {
    $user = User::factory()->unverified()->create($attributes);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->get($verificationUrl)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
})->with([
    'disabled customer' => [['role' => 'customer', 'disabled_at' => now()]],
    'staff account' => [['role' => 'technician']],
]);

test('unverified customers are redirected away from customer services', function (string $path) {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get($path)
        ->assertRedirect(route('verification.notice', absolute: false));
})->with([
    'customer dashboard' => '/customer/dashboard',
    'application form' => '/customer/apply',
    'support tickets' => '/customer/tickets',
    'live chat' => '/chat',
]);

test('unverified customer json requests receive a clear forbidden response', function () {
    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->getJson('/customer/dashboard')
        ->assertForbidden()
        ->assertJson([
            'message' => 'Please verify your email address before using customer services.',
            'redirect' => route('verification.notice'),
        ]);
});

test('verified customers can continue to customer services', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get('/customer/dashboard')
        ->assertOk();
});

test('email verification requirement does not block staff accounts', function (string $role, string $expectedRoute) {
    $user = User::factory()->unverified()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route($expectedRoute, absolute: false));
})->with([
    'admin' => ['admin', 'admin.dashboard'],
    'technician' => ['technician', 'technician.dashboard'],
]);

test('customer can request a replacement verification link', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->post(route('verification.send', absolute: false))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyCustomerEmail::class);
});

test('failed verification resend shows a recoverable delivery message', function () {
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection unavailable'));
    $this->app->instance(Dispatcher::class, $dispatcher);

    $user = User::factory()->unverified()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->post(route('verification.send', absolute: false))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-failed');

    $this->actingAs($user)
        ->withSession(['status' => 'verification-link-failed'])
        ->get(route('verification.notice', absolute: false))
        ->assertOk()
        ->assertSee('Email delivery is temporarily unavailable')
        ->assertSee('Your account is safe');
});
