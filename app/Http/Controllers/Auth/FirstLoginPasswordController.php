<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class FirstLoginPasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->requiresPasswordChange()) {
            return redirect()->route('dashboard');
        }

        return view('auth.first-login-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->requiresPasswordChange(), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'current_password.current_password' => 'The temporary account key is incorrect.',
            'password.different' => 'Your new password must be different from the temporary account key.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
            'is_online' => false,
            'last_seen' => null,
            'chat_available' => false,
        ])->save();

        $request->session()->regenerate();

        system_log(
            'Technician Initial Password Changed',
            $user->displayTechnicianRecordId().' completed the required first-login password change.'
        );

        return redirect()
            ->route('staff.two-factor.setup')
            ->with('status', 'first-login-password-updated');
    }
}
