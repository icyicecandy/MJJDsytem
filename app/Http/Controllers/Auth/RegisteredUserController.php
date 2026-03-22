<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['nullable', 'in:customer,technician'],
            'agreed_to_terms' => ['accepted'],
            'agreed_to_privacy' => ['accepted'],
            'terms_version' => ['nullable', 'string', 'max:50'],
            'privacy_version' => ['nullable', 'string', 'max:50'],
        ], [
            'agreed_to_terms.accepted' => config('legal.validation_message'),
            'agreed_to_privacy.accepted' => config('legal.validation_message'),
        ]);

        $role = $validated['role'] ?? 'customer';
        $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);
        $consentedAt = now();

        $user = User::create([
            'name' => $fullName,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'agreed_to_terms' => true,
            'agreed_to_privacy' => true,
            'consented_at' => $consentedAt,
            'terms_version' => $validated['terms_version'] ?? config('legal.terms_version'),
            'privacy_version' => $validated['privacy_version'] ?? config('legal.privacy_version'),
        ]);

        event(new Registered($user));

        Auth::login($user);

        if (session()->has('url.intended')) {
            return redirect()->intended();
        }

        if ($user->isTechnician()) {
            return redirect()->route('technician.dashboard');
        }

        return redirect()->route('customer.dashboard');
    }
}
