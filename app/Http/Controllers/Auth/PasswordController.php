<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\StaffTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request, StaffTwoFactorService $twoFactor): RedirectResponse
    {
        $user = $request->user();
        $canSetInitialPassword = $user->canSetInitialPassword();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $canSetInitialPassword
                ? ['nullable', 'current_password']
                : ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        if ($user->requiresStaffTwoFactor()) {
            $twoFactor->revokeAllTrustedDevices($user);
            system_log('Staff Password Changed', 'The password changed and all trusted devices were revoked.');
        }

        $response = back()->with('status', $canSetInitialPassword ? 'password-set' : 'password-updated');

        return $user->requiresStaffTwoFactor()
            ? $response->withCookie($twoFactor->forgetTrustedDeviceCookie())
            : $response;
    }
}
