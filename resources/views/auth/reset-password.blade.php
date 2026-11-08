@extends('layouts.auth')

@section('title', 'Reset Password | MJJD Telecom')

@section('content')
    <div class="login-shell">
        <div class="login-card">
            <div class="login-intro">
                <span class="login-kicker">Account Recovery</span>
                <h1 class="form-title">Choose a new password</h1>
                <p class="form-subtitle">Update the password for your MJJD account, then sign in with your new credentials.</p>
            </div>
            <div class="login-form-card">
                <div class="login-form-head">
                    <span class="login-form-badge">Password Help</span>
                    <h3>Reset password</h3>
                    <p>Enter your account email and confirm your new password.</p>
                </div>
                <form method="POST" action="{{ route('password.store') }}" class="auth-form login-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
                        @error('email')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="password-group">
                            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="resetPasswordHelp">
                            <button type="button" class="show-password" data-target="password" aria-label="Show password" title="Show password">Show</button>
                        </div>
                        <p class="small-text" id="resetPasswordHelp">Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.</p>
                        @error('password')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <div class="password-group">
                            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                            <button type="button" class="show-password" data-target="password_confirmation" aria-label="Show password" title="Show password">Show</button>
                        </div>
                        @error('password_confirmation')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group login-submit-row">
                        <button type="submit" class="btn primary">Reset Password</button>
                    </div>
                    <p class="small-text"><a href="{{ url('/') }}">Back to sign in</a></p>
                </form>
            </div>
        </div>
    </div>
@endsection
