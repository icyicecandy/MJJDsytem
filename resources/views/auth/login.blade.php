@extends('layouts.auth')

@section('title','Sign In - MJJD Telecom')

@section('content')
<h2 class="form-title">Sign In</h2>
<form method="POST" action="{{ route('login') }}" class="auth-form">
    @csrf
    <div class="form-group">
        <label for="email">Username</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com" />
        @error('email')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group password-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required placeholder="••••••••" />
        <button type="button" class="show-password" data-target="password">SHOW</button>
        @error('password')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group checkbox-group">
        <label>
            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} /> Remember me
        </label>
    </div>
    <div class="form-group">
        <button type="submit" class="btn primary">Sign In</button>
    </div>
    <div class="divider"><span>OR</span></div>
    <div class="form-group">
        <a href="{{ route('register') }}" class="btn secondary">Register</a>
    </div>
    <p class="small-text">Don't have an account? <a href="{{ route('register') }}">Register</a></p>
</form>
@endsection
