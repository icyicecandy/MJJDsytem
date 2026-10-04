<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureStaffTwoFactorIsConfigured;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TrackStaffPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'customer.verified' => EnsureCustomerEmailIsVerified::class,
            'role' => RoleMiddleware::class,
            'track.staff.presence' => TrackStaffPresence::class,
        ]);

        $middleware->web(append: [
            EnsurePasswordIsChanged::class,
            EnsureStaffTwoFactorIsConfigured::class,
        ]);

        $middleware->redirectGuestsTo(fn () => url('/'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
