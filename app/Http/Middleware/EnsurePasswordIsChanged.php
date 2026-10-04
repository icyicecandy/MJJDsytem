<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->requiresPasswordChange()) {
            return $next($request);
        }

        if ($request->routeIs(
            'technician.password.change',
            'technician.password.change.update',
            'logout'
        )) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must create a new password before accessing the technician portal.',
                'redirect' => route('technician.password.change'),
            ], 423);
        }

        return redirect()->route('technician.password.change');
    }
}
