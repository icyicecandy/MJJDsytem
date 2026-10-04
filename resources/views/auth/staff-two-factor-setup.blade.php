@extends('layouts.staff-security')

@section('title', 'Set up two-factor authentication')

@section('content')
<div class="security-card security-card-wide">
    <div class="security-heading">
        <span class="security-eyebrow">Required staff protection</span>
        <h1>Secure your {{ $user->isAdmin() ? 'administrator' : 'technician' }} account</h1>
        <p>Connect an authenticator app before entering the staff portal. The app creates a new six-digit code every 30 seconds and works without mobile data.</p>
    </div>

    @if (session('status') === 'first-login-password-updated')
        <div class="security-alert security-alert-success">
            <strong>Password changed successfully.</strong>
            <span>Complete this final security step to open the technician portal.</span>
        </div>
    @endif

    <div class="security-setup-grid">
        <div class="security-steps">
            <article class="security-step">
                <span class="security-step-number">1</span>
                <div>
                    <h2>Open an authenticator app</h2>
                    <p>Use Google Authenticator, Microsoft Authenticator, Authy, or another TOTP-compatible app.</p>
                </div>
            </article>
            <article class="security-step">
                <span class="security-step-number">2</span>
                <div>
                    <h2>Scan the QR code</h2>
                    <p>If scanning is unavailable, enter the setup key manually. Keep this key private.</p>
                    <div class="security-secret" aria-label="Manual setup key">{{ $secret }}</div>
                </div>
            </article>
            <article class="security-step">
                <span class="security-step-number">3</span>
                <div>
                    <h2>Confirm the current code</h2>
                    <p>Enter the six-digit code shown by the app to finish enrollment.</p>
                </div>
            </article>
        </div>

        <div class="security-enrollment-panel">
            <div class="security-qr" aria-label="Authenticator setup QR code">{!! $qrSvg !!}</div>
            <div class="security-account-label">
                <span>Account</span>
                <strong>{{ $user->email }}</strong>
            </div>

            <form method="POST" action="{{ route('staff.two-factor.confirm') }}" class="security-form">
                @csrf
                <label for="code">Authenticator code</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]{6,7}" placeholder="000 000" required autofocus class="{{ $errors->has('code') ? 'is-invalid' : '' }}">
                @error('code')<span class="security-field-error">{{ $message }}</span>@enderror

                <label class="security-check">
                    <input type="checkbox" name="trust_device" value="1" checked>
                    <span><strong>Trust this browser for 30 days</strong><small>Use this only on a private staff device.</small></span>
                </label>

                <button type="submit" class="security-primary-button">Verify and continue</button>
            </form>
        </div>
    </div>
</div>
@endsection
