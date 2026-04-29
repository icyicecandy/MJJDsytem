<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => User::DISABLED_ACCOUNT_MESSAGE], 'login')
                ->with('auth_modal', 'login');
        }

        if ($user->isAdmin() || $user->isTechnician()) {
            $user->forceFill([
                'is_online' => true,
                'last_seen' => now(),
                'chat_available' => true,
            ])->save();
        }

        // If user originally tried to access a protected page
        if (session()->has('url.intended')) {
            return redirect()->intended();
        }

        if ($user->role === 'admin') {
            return redirect('/admin/dashboard');
        }

        if ($user->role === 'technician') {
            return redirect('/technician/dashboard');
        }

        return redirect('/customer/dashboard');
    }





    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isTechnician())) {
            $user->forceFill([
                'is_online' => false,
                'chat_available' => false,
                'last_seen' => null,
            ])->save();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
