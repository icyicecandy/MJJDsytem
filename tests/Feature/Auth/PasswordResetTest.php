<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response
        ->assertStatus(200)
        ->assertSee('Reset your password')
        ->assertSee('MJJD Telecom')
        ->assertSee('Email Password Reset Link');
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('mail transport failure returns to the form without a server error', function () {
    $user = User::factory()->create();

    Password::shouldReceive('sendResetLink')
        ->once()
        ->andThrow(new RuntimeException('SMTP authentication unavailable'));

    $response = $this
        ->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email]);

    $response
        ->assertRedirect('/forgot-password')
        ->assertSessionHasInput('email', $user->email)
        ->assertSessionHasErrors([
            'email' => 'We could not send the reset email right now. No account changes were made. Please try again shortly or contact the system administrator.',
        ]);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'MjJd!Reset2026Qx7',
            'password_confirmation' => 'MjJd!Reset2026Qx7',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
