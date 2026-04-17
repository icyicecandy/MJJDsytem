@extends('layouts.auth')

@section('title','Sign In - MJJD Telecom')

@section('content')
<div class="login-shell">
    <section class="login-card" aria-label="Login form">
        <div class="login-intro">
            <span class="login-kicker">MJJD Telecom</span>
            <h2 class="form-title">Welcome to MJJD Telecom</h2>
            <p class="form-subtitle">Sign in to manage your account, applications, and service updates.</p>
        </div>

        <div class="login-form-card">
            <div class="login-form-head">
                <span class="login-form-badge">Secure Sign In</span>
                <h3>Welcome back</h3>
                <p>Use your registered email and password to continue.</p>
            </div>

            <x-auth-session-status class="login-session-status" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="auth-form login-form">
                @csrf
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com" />
                    @error('email')<span class="error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group password-group">
                    <div class="field-head">
                        <label for="password">Password</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="field-link">Forgot password?</a>
                        @endif
                    </div>
                    <input id="password" type="password" name="password" required placeholder="Enter your password" />
                    <button
                        type="button"
                        class="show-password"
                        data-target="password"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M12 5C6.5 5 2.1 8.4 1 12c1.1 3.6 5.5 7 11 7s9.9-3.4 11-7c-1.1-3.6-5.5-7-11-7Zm0 11.2A4.2 4.2 0 1 1 12 7.8a4.2 4.2 0 0 1 0 8.4Zm0-6.7A2.5 2.5 0 1 0 12 14.5a2.5 2.5 0 0 0 0-5Z"/>
                        </svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="m3.3 4.5 16.2 15-1.1 1.2-3-2.8A12.8 12.8 0 0 1 12 19c-5.5 0-9.9-3.4-11-7 .5-1.8 1.9-3.5 3.8-4.7L2.2 5.7l1.1-1.2Zm7 6.5 3.2 3a2.5 2.5 0 0 1-3.2-3Zm1.7-6c5.5 0 9.9 3.4 11 7-.4 1.4-1.4 2.8-2.8 4l-2.2-2c.7-.6 1.3-1.3 1.6-2-1.1-2.7-4.6-5.3-8.6-5.3-1 0-2 .2-2.9.5L6.4 5.9c1.7-.6 3.6-.9 5.6-.9Zm-.5 2.8a4.2 4.2 0 0 1 4.1 5l-1.6-1.5a2.5 2.5 0 0 0-2.8-2.7l-1.6-1.5c.6-.2 1.2-.3 1.9-.3Z"/>
                        </svg>
                    </button>
                    @error('password')<span class="error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group checkbox-group login-options-row">
                    <label>
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} /> Keep me signed in
                    </label>
                </div>

                <div class="form-group login-submit-row">
                    <button type="submit" class="btn primary">Sign In</button>
                </div>

                <p class="small-text">Need an account? <a href="{{ route('register') }}">Create one here</a></p>
            </form>
        </div>
    </section>
</div>
@endsection
