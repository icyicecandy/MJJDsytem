<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffTwoFactorIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->requiresStaffTwoFactor() || $user->hasStaffTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs(
            'staff.two-factor.setup',
            'staff.two-factor.confirm',
            'staff.two-factor.recovery-codes',
            'technician.password.change',
            'technician.password.change.update',
            'logout',
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Set up staff two-factor authentication before continuing.',
                'redirect' => route('staff.two-factor.setup'),
            ], 423);
        }

        return redirect()->route('staff.two-factor.setup');
    }
}
