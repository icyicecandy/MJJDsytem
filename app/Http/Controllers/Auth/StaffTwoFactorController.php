<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\StaffTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffTwoFactorController extends Controller
{
    private const PENDING_SESSION_KEY = 'staff_two_factor.pending';

    private const ENROLLMENT_SECRET_KEY = 'staff_two_factor.enrollment_secret';

    public function setup(Request $request, StaffTwoFactorService $twoFactor): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->requiresStaffTwoFactor(), 403);

        if ($user->hasStaffTwoFactorEnabled()) {
            return redirect()->route('profile.edit');
        }

        $secret = (string) $request->session()->get(self::ENROLLMENT_SECRET_KEY);

        if ($secret === '') {
            $secret = $twoFactor->generateSecret();
            $request->session()->put(self::ENROLLMENT_SECRET_KEY, $secret);
        }

        return view('auth.staff-two-factor-setup', [
            'user' => $user,
            'secret' => $secret,
            'qrSvg' => $twoFactor->qrSvg($user, $secret),
        ]);
    }

    public function confirm(Request $request, StaffTwoFactorService $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->requiresStaffTwoFactor() && ! $user->hasStaffTwoFactorEnabled(), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\s*\d{3}\s?\d{3}\s*$/'],
            'trust_device' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Enter the current six-digit code from your authenticator app.',
        ]);

        $secret = (string) $request->session()->get(self::ENROLLMENT_SECRET_KEY);
        $rateKey = 'staff-2fa-enroll:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 6)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Wait '.RateLimiter::availableIn($rateKey).' seconds and try again.',
            ]);
        }

        if ($secret === '' || ! $twoFactor->verifyTotp($user, $validated['code'], $secret)) {
            RateLimiter::hit($rateKey, 60);
            $this->record($user, 'Staff Two-Factor Enrollment Failed', 'An invalid authenticator code was entered during setup.');

            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired. Check your phone time and enter the newest code.',
            ]);
        }

        RateLimiter::clear($rateKey);
        $recovery = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recovery['hashed'],
            'two_factor_confirmed_at' => now(),
            'remember_token' => null,
            'is_online' => true,
            'last_seen' => now(),
            'chat_available' => true,
        ])->save();

        $request->session()->forget(self::ENROLLMENT_SECRET_KEY);
        $request->session()->regenerate();
        $request->session()->flash('staff_two_factor.new_recovery_codes', $recovery['plain']);

        $this->record($user, 'Staff Two-Factor Enabled', 'Authenticator-based two-factor authentication was enabled.');
        $this->record($user, 'Account Login', 'Staff sign-in completed after mandatory two-factor enrollment.');

        $response = redirect()->route('staff.two-factor.recovery-codes');

        if ($request->boolean('trust_device')) {
            $trusted = $twoFactor->trustBrowser($user, $request);
            $this->record($user, 'Trusted Device Added', $trusted['device']->name.' was trusted for 30 days.');

            $response->withCookie($trusted['cookie']);
        }

        return $response;
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->requiresStaffTwoFactor() && $user->hasStaffTwoFactorEnabled(), 403);

        $codes = $request->session()->get('staff_two_factor.new_recovery_codes');

        if (! is_array($codes) || $codes === []) {
            return redirect()->route('profile.edit');
        }

        return view('auth.staff-two-factor-recovery-codes', compact('user', 'codes'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $user = $this->pendingUser($request);

        if (! $user) {
            return $this->expiredChallenge($request);
        }

        return view('auth.staff-two-factor-challenge', compact('user'));
    }

    public function verifyChallenge(Request $request, StaffTwoFactorService $twoFactor): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'trust_device' => ['nullable', 'boolean'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return $this->expiredChallenge($request);
        }

        $rateKey = 'staff-2fa-challenge:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Wait '.RateLimiter::availableIn($rateKey).' seconds and try again.',
            ]);
        }

        $usedRecoveryCode = false;
        $valid = $twoFactor->verifyTotp($user, $validated['code']);

        if (! $valid) {
            $usedRecoveryCode = $twoFactor->consumeRecoveryCode($user, $validated['code']);
            $valid = $usedRecoveryCode;
        }

        if (! $valid) {
            RateLimiter::hit($rateKey, 60);
            $this->record($user, 'Staff Two-Factor Challenge Failed', 'An invalid authenticator or recovery code was entered.');

            throw ValidationException::withMessages([
                'code' => 'That authenticator or recovery code is invalid.',
            ]);
        }

        RateLimiter::clear($rateKey);
        $pending = $request->session()->pull(self::PENDING_SESSION_KEY, []);
        Auth::login($user, false);
        $request->session()->regenerate();

        $user->forceFill([
            'is_online' => true,
            'last_seen' => now(),
            'chat_available' => true,
        ])->save();

        $this->record(
            $user,
            $usedRecoveryCode ? 'Staff Recovery Code Used' : 'Staff Two-Factor Challenge Passed',
            $usedRecoveryCode
                ? 'A one-time recovery code completed staff sign-in.'
                : 'An authenticator code completed staff sign-in.',
        );
        $this->record($user, 'Account Login', 'Signed in using email, password, and staff two-factor authentication.');

        $destination = $this->safeDestination($user, $pending['intended'] ?? null);
        $response = redirect()->to($destination);

        if ($request->boolean('trust_device')) {
            $trusted = $twoFactor->trustBrowser($user, $request);
            $this->record($user, 'Trusted Device Added', $trusted['device']->name.' was trusted for 30 days.');
            $response->withCookie($trusted['cookie']);
        }

        return $response;
    }

    public function regenerateRecoveryCodes(Request $request, StaffTwoFactorService $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasStaffTwoFactorEnabled(), 403);

        $validated = $request->validateWithBag('recoveryCodes', [
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->verifySecurityCode($twoFactor, $user, $validated['code'])) {
            $exception = ValidationException::withMessages([
                'code' => 'Enter a valid authenticator or unused recovery code.',
            ]);
            $exception->errorBag = 'recoveryCodes';
            throw $exception;
        }

        $recovery = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $recovery['hashed']])->save();
        $request->session()->flash('staff_two_factor.new_recovery_codes', $recovery['plain']);

        system_log('Staff Recovery Codes Regenerated', 'All previous staff recovery codes were replaced.');

        return redirect()->route('staff.two-factor.recovery-codes');
    }

    public function resetAuthenticator(Request $request, StaffTwoFactorService $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasStaffTwoFactorEnabled(), 403);

        $validated = $request->validateWithBag('replaceAuthenticator', [
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->verifySecurityCode($twoFactor, $user, $validated['code'])) {
            $exception = ValidationException::withMessages([
                'code' => 'Enter a valid authenticator or unused recovery code.',
            ]);
            $exception->errorBag = 'replaceAuthenticator';
            throw $exception;
        }

        $twoFactor->revokeAllTrustedDevices($user);
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
        ])->save();

        $request->session()->forget(self::ENROLLMENT_SECRET_KEY);
        system_log('Staff Authenticator Replacement Started', 'The previous authenticator and all trusted devices were revoked.');

        return redirect()
            ->route('staff.two-factor.setup')
            ->withCookie($twoFactor->forgetTrustedDeviceCookie());
    }

    public function revokeDevice(
        Request $request,
        TrustedDevice $trustedDevice,
        StaffTwoFactorService $twoFactor,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user?->hasStaffTwoFactorEnabled() && $trustedDevice->user_id === $user->id, 404);

        $request->validateWithBag('trustedDevice', [
            'current_password' => ['required', 'current_password'],
        ]);

        $trustedDevice->forceFill(['revoked_at' => now()])->save();
        system_log('Trusted Device Revoked', $trustedDevice->name.' was removed from trusted devices.');

        $response = back()->with('status', 'trusted-device-revoked');

        if ($twoFactor->currentTrustedDeviceId($request) === $trustedDevice->id) {
            $response->withCookie($twoFactor->forgetTrustedDeviceCookie());
        }

        return $response;
    }

    public function revokeAllDevices(Request $request, StaffTwoFactorService $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasStaffTwoFactorEnabled(), 403);

        $request->validateWithBag('trustedDevice', [
            'current_password' => ['required', 'current_password'],
        ]);

        $twoFactor->revokeAllTrustedDevices($user);
        system_log('All Trusted Devices Revoked', 'Every trusted browser for this staff account was revoked.');

        return back()
            ->with('status', 'trusted-devices-revoked')
            ->withCookie($twoFactor->forgetTrustedDeviceCookie());
    }

    public static function beginChallenge(Request $request, User $user): RedirectResponse
    {
        $request->session()->put(self::PENDING_SESSION_KEY, [
            'user_id' => $user->id,
            'started_at' => now()->timestamp,
            'intended' => $request->session()->get('url.intended'),
        ]);

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->regenerate();

        return redirect()->route('staff.two-factor.challenge');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_array($pending)
            || ! isset($pending['user_id'], $pending['started_at'])
            || (int) $pending['started_at'] < now()->subMinutes(StaffTwoFactorService::PENDING_TTL_MINUTES)->timestamp) {
            return null;
        }

        $user = User::find($pending['user_id']);

        if (! $user || $user->isDisabled() || ! $user->hasStaffTwoFactorEnabled()) {
            return null;
        }

        return $user;
    }

    private function expiredChallenge(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_SESSION_KEY);

        return redirect('/')
            ->with('auth_modal', 'login')
            ->withErrors(['email' => 'Your staff security check expired. Sign in again.'], 'login');
    }

    private function verifySecurityCode(StaffTwoFactorService $twoFactor, User $user, string $code): bool
    {
        return $twoFactor->verifyTotp($user, $code) || $twoFactor->consumeRecoveryCode($user, $code);
    }

    private function safeDestination(User $user, mixed $intended): string
    {
        if (is_string($intended)) {
            $target = parse_url($intended);
            $application = parse_url(url('/'));

            if (($target['scheme'] ?? null) === ($application['scheme'] ?? null)
                && ($target['host'] ?? null) === ($application['host'] ?? null)
                && ($target['port'] ?? null) === ($application['port'] ?? null)) {
                return $intended;
            }
        }

        return $user->isAdmin() ? route('admin.dashboard') : route('technician.dashboard');
    }

    private function record(User $user, string $action, string $description): void
    {
        SystemLog::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
