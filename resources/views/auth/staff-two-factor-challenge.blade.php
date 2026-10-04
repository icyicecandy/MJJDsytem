@extends('layouts.staff-security')

@section('title', 'Verify staff sign-in')

@section('content')
<div class="security-card security-card-compact">
    <div class="security-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg>
    </div>
    <div class="security-heading">
        <span class="security-eyebrow">Staff security check</span>
        <h1>Confirm it’s you</h1>
        <p>The password for <strong>{{ $user->email }}</strong> was accepted. Enter the current authenticator code to finish signing in.</p>
    </div>

    <form method="POST" action="{{ route('staff.two-factor.challenge.verify') }}" class="security-form">
        @csrf
        <label for="code">Authenticator or recovery code</label>
        <input id="code" name="code" type="text" autocomplete="one-time-code" maxlength="32" placeholder="Six-digit code or recovery code" required autofocus class="{{ $errors->has('code') ? 'is-invalid' : '' }}">
        @error('code')<span class="security-field-error">{{ $message }}</span>@enderror

        <label class="security-check">
            <input type="checkbox" name="trust_device" value="1">
            <span><strong>Trust this browser for 30 days</strong><small>Do not select this on a shared or public computer.</small></span>
        </label>

        <button type="submit" class="security-primary-button">Verify and sign in</button>
    </form>

    <div class="security-help">
        <strong>Cannot access your authenticator?</strong>
        <p>Use one of the recovery codes saved during setup. Each recovery code works once.</p>
    </div>
</div>
@endsection
