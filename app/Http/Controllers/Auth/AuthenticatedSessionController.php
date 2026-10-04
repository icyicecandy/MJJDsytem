<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\StaffTwoFactorService;
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
    public function store(LoginRequest $request, StaffTwoFactorService $twoFactor): RedirectResponse
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
            $requiresPasswordChange = $user->requiresPasswordChange();

            $user->forceFill([
                'is_online' => false,
                'last_seen' => null,
                'chat_available' => false,
            ])->save();

            if ($requiresPasswordChange) {
                return redirect()->route('technician.password.change');
            }

            if (! $user->hasStaffTwoFactorEnabled()) {
                SystemLog::create([
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'action' => 'Staff Two-Factor Enrollment Required',
                    'description' => 'Password verification passed; authenticator enrollment is required.',
                ]);

                return redirect()->route('staff.two-factor.setup');
            }

            $trustedDevice = $twoFactor->trustedDevice($user, $request);

            if (! $trustedDevice) {
                SystemLog::create([
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'action' => 'Staff Two-Factor Challenge Started',
                    'description' => 'Password verification passed on an untrusted browser.',
                ]);

                return StaffTwoFactorController::beginChallenge($request, $user);
            }

            $user->forceFill([
                'is_online' => true,
                'last_seen' => now(),
                'chat_available' => true,
            ])->save();

            system_log('Trusted Device Used', $trustedDevice->name.' completed the staff security check.');
            system_log('Account Login', 'Signed in using email and password on a trusted browser.');

            return redirect($user->isAdmin() ? '/admin/dashboard' : '/technician/dashboard');
        }

        if ($request->boolean('remember')) {
            Auth::login($user, true);
            $request->session()->regenerate();
        }

        system_log('Account Login', 'Signed in using email and password.');

        // If user originally tried to access a protected page
        if (session()->has('url.intended')) {
            return redirect()->intended();
        }

        return redirect('/customer/dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            system_log('Account Logout', 'Signed out of the system.');
        }

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
