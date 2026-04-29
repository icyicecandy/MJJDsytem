<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDisabled()) {
            return $next($request);
        }

        if ($user->isAdmin() || $user->isTechnician()) {
            $user->forceFill([
                'is_online' => false,
                'chat_available' => false,
                'last_seen' => null,
            ])->saveQuietly();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')
            ->with('auth_modal', 'login')
            ->withErrors(['email' => User::DISABLED_ACCOUNT_MESSAGE], 'login');
    }
}
