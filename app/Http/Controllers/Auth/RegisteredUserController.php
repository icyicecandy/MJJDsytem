<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function emailCheck(Request $request)
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $email = trim((string) ($validated['email'] ?? ''));
        $exists = $email !== '' && User::query()->where('email', $email)->exists();

        return response()->json([
            'email' => [
                'duplicate' => $exists,
                'message' => $exists ? 'This email address is already registered. Please sign in instead.' : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255', "regex:/^[A-Za-z][A-Za-z\\s'\\-]*$/"],
            'last_name' => ['required', 'string', 'max:255', "regex:/^[A-Za-z][A-Za-z\\s'\\-]*$/"],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'agreed_to_terms' => ['accepted'],
            'agreed_to_privacy' => ['accepted'],
            'terms_version' => ['nullable', 'string', 'max:50'],
            'privacy_version' => ['nullable', 'string', 'max:50'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes.',
            'email.unique' => 'This email address is already registered. Please sign in instead.',
            'agreed_to_terms.accepted' => config('legal.validation_message'),
            'agreed_to_privacy.accepted' => config('legal.validation_message'),
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'register')
                ->withInput()
                ->with('auth_modal', 'register');
        }

        $validated = $validator->validated();

        $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);
        $consentedAt = now();

        $user = User::create([
            'name' => $fullName,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
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

        return redirect()->route('customer.dashboard');
    }
}
