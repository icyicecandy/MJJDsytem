<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $googleConfig = config('services.google');
        $clientId = $googleConfig['client_id'] ?? null;
        $clientSecret = $googleConfig['client_secret'] ?? null;
        $redirect = $googleConfig['redirect'] ?? null;
        $caBundle = $googleConfig['ca_bundle'] ?? null;
        $mode = $this->resolveMode($request->query('mode'));

        $request->session()->put('google_auth_mode', $mode);
        $request->session()->put('auth_modal', $mode);

        Log::info('Google OAuth configuration check.', [
            'client_id_present' => filled($clientId),
            'client_secret_present' => filled($clientSecret),
            'redirect' => $redirect,
            'ca_bundle_present' => filled($caBundle),
            'ca_bundle_exists' => filled($caBundle) ? file_exists($caBundle) : false,
            'mode' => $mode,
        ]);

        if ($this->hasMissingGoogleConfig($clientId, $clientSecret, $redirect)) {
            return redirect('/')
                ->with('auth_modal', $mode)
                ->with('google_auth_error', 'Google sign-in is not configured yet. Please add the Google OAuth credentials to the environment settings.');
        }

        return $this->googleDriver()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $mode = $this->resolveMode($request->session()->pull('google_auth_mode', 'login'));

        try {
            $googleUser = $this->googleDriver()->user();
        } catch (Throwable $exception) {
            Log::warning('Google authentication failed during callback.', [
                'error' => $exception->getMessage(),
                'mode' => $mode,
            ]);

            return redirect('/')
                ->with('auth_modal', $mode)
                ->with('google_auth_error', 'Google sign-in could not be completed. Please try again.');
        }

        $email = trim((string) $googleUser->getEmail());

        Log::info('Google user payload received.', [
            'google_id' => $googleUser->getId(),
            'email' => $email,
            'name' => $googleUser->getName(),
            'nickname' => $googleUser->getNickname(),
        ]);

        if ($email === '') {
            return redirect('/')
                ->with('auth_modal', $mode)
                ->with('google_auth_error', 'Google did not return a usable email address for this account.');
        }

        try {
            $user = User::query()->where('email', $email)->first();

            if ($user) {
                Log::info('Existing Google auth user found by email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                if ($user->isDisabled()) {
                    return redirect('/')
                        ->with('auth_modal', 'login')
                        ->with('google_auth_error', User::DISABLED_ACCOUNT_MESSAGE);
                }

                $updates = [];

                if (! $user->google_id) {
                    $updates['google_id'] = $googleUser->getId();
                }

                if (! $user->email_verified_at) {
                    $updates['email_verified_at'] = now();
                }

                if ($updates !== []) {
                    Log::info('Updating existing user during Google auth.', [
                        'user_id' => $user->id,
                        'update_keys' => array_keys($updates),
                    ]);

                    $user->forceFill($updates)->save();
                }
            } else {
                if ($mode !== 'register') {
                    return redirect('/')
                        ->with('auth_modal', 'login')
                        ->with('google_auth_error', 'No account matches that Google email. Register first before signing in with Google.');
                }

                $name = trim((string) ($googleUser->getName() ?: 'Google User'));
                $payload = [
                    'name' => $name !== '' ? $name : 'Google User',
                    'email' => $email,
                    'password' => Str::random(40),
                    'role' => 'customer',
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ];

                Log::info('Creating new user from Google auth.', [
                    'email' => $payload['email'],
                    'role' => $payload['role'],
                    'google_id_present' => filled($payload['google_id']),
                    'name_present' => filled($payload['name']),
                ]);

                $user = User::create($payload);

                Log::info('New user created from Google auth.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                event(new Registered($user));
            }
        } catch (Throwable $exception) {
            Log::error('Google auth user persistence failed.', [
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            return redirect('/')
                ->with('auth_modal', $mode)
                ->with('google_auth_error', 'Google sign-in could not create your account yet. Please try again or contact support.');
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if (session()->has('url.intended')) {
            return redirect()->intended();
        }

        return redirect($this->redirectPathFor($user));
    }

    private function googleDriver(): Provider
    {
        $driver = Socialite::driver('google');
        $caBundle = config('services.google.ca_bundle');

        if (filled($caBundle) && file_exists($caBundle)) {
            $driver->setHttpClient(new Client([
                'verify' => $caBundle,
            ]));
        }

        return $driver;
    }

    private function hasMissingGoogleConfig(?string $clientId, ?string $clientSecret, ?string $redirect): bool
    {
        $placeholderValues = [
            'your_client_id_here',
            'your_client_secret_here',
        ];

        return blank($clientId)
            || blank($clientSecret)
            || blank($redirect)
            || in_array($clientId, $placeholderValues, true)
            || in_array($clientSecret, $placeholderValues, true);
    }

    private function resolveMode(?string $mode): string
    {
        return in_array($mode, ['login', 'register'], true) ? $mode : 'login';
    }

    private function redirectPathFor(User $user): string
    {
        if ($user->role === 'admin') {
            return '/admin/dashboard';
        }

        if ($user->role === 'technician') {
            return '/technician/dashboard';
        }

        return '/customer/dashboard';
    }
}
