@extends('layouts.auth')

@section('title','Register - MJJD Telecom')

@section('content')
<h2 class="form-title">Register</h2>
<form method="POST" action="{{ route('register') }}" class="auth-form">
    @csrf
    <input type="hidden" name="role" value="customer" />
    <div class="form-group">
        <label for="first_name">First Name</label>
        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus placeholder="Jane" />
        @error('first_name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label for="last_name">Last Name</label>
        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required placeholder="Doe" />
        @error('last_name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" />
        @error('email')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group password-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required placeholder="Password" />
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
    <div class="form-group password-group">
        <label for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required placeholder="Confirm password" />
        <button
            type="button"
            class="show-password"
            data-target="password_confirmation"
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
    </div>
    <div class="form-group">
        <button type="submit" class="btn primary">Register</button>
    </div>
    <p class="small-text">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</form>
@endsection
