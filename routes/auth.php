<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\StaffTwoFactorController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', fn () => redirect('/'));

    Route::get('register/email-check', [RegisteredUserController::class, 'emailCheck'])
        ->name('register.email-check');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->name('register');

    Route::get('login', fn () => redirect('/'));

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    Route::get('staff/two-factor-challenge', [StaffTwoFactorController::class, 'challenge'])
        ->name('staff.two-factor.challenge');
    Route::post('staff/two-factor-challenge', [StaffTwoFactorController::class, 'verifyChallenge'])
        ->name('staff.two-factor.challenge.verify');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('staff/two-factor/setup', [StaffTwoFactorController::class, 'setup'])
        ->name('staff.two-factor.setup');
    Route::post('staff/two-factor/setup', [StaffTwoFactorController::class, 'confirm'])
        ->name('staff.two-factor.confirm');
    Route::get('staff/two-factor/recovery-codes', [StaffTwoFactorController::class, 'recoveryCodes'])
        ->name('staff.two-factor.recovery-codes');
    Route::post('staff/two-factor/recovery-codes', [StaffTwoFactorController::class, 'regenerateRecoveryCodes'])
        ->name('staff.two-factor.recovery-codes.regenerate');
    Route::post('staff/two-factor/replace', [StaffTwoFactorController::class, 'resetAuthenticator'])
        ->name('staff.two-factor.replace');
    Route::delete('staff/trusted-devices', [StaffTwoFactorController::class, 'revokeAllDevices'])
        ->name('staff.trusted-devices.revoke-all');
    Route::delete('staff/trusted-devices/{trustedDevice}', [StaffTwoFactorController::class, 'revokeDevice'])
        ->name('staff.trusted-devices.revoke');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
