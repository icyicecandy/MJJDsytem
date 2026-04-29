@extends('layouts.auth')

@section('title', 'Forgot Password | MJJD Telecom')

@section('content')
    <div class="login-shell">
        <div class="login-card">
            <div class="login-intro">
                <span class="login-kicker">Account Recovery</span>
                <h1 class="form-title">Reset your password</h1>
                <p class="form-subtitle">Enter the email linked to your MJJD account and we will send a secure reset link.</p>
            </div>

            <div class="login-form-card">
                <div class="login-form-head">
                    <span class="login-form-badge">Password Help</span>
                    <h3>Send reset link</h3>
                    <p>Use the reset email to create a new password before signing in again.</p>
                </div>

                <x-auth-session-status class="login-session-status" :status="session('status')" />

                <form method="POST" action="{{ route('password.email') }}" class="auth-form login-form">
                    @csrf

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you&#64;example.com">
                        @error('email')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group login-submit-row">
                        <button type="submit" class="btn primary">Email Password Reset Link</button>
                    </div>

                    <p class="small-text">Remembered your password? <a href="{{ url('/') }}">Back to sign in</a></p>
                </form>
            </div>
        </div>
    </div>
@endsection
