<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
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
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_set_at' => now(),
            'must_change_password' => false,
        ])->save();

        return back()->with('status', $canSetInitialPassword ? 'password-set' : 'password-updated');
    }
}
