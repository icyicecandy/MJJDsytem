@extends('layouts.auth')

@section('title','Register - MJJD Telecom')

@section('content')
<h2 class="form-title">Register</h2>
<form method="POST" action="{{ route('register') }}" class="auth-form register-form-grid">
    @csrf
    <input type="hidden" name="role" value="customer" />
    <div class="form-group">
        <label for="name">Full Name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Jane Doe" />
        @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com" />
        @error('email')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label for="phone">Phone Number</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="09xxxxxxxxx" />
    </div>
    <div class="form-group">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="jdoe" />
    </div>
    <div class="form-group full-span">
        <label for="address">Address</label>
        <input id="address" type="text" name="address" value="{{ old('address') }}" placeholder="Street, Barangay, Municipality" />
    </div>
    <div class="form-group password-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required placeholder="Password" />
        <button type="button" class="show-password" data-target="password">SHOW</button>
        @error('password')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group password-group">
        <label for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required placeholder="Confirm password" />
        <button type="button" class="show-password" data-target="password_confirmation">SHOW</button>
    </div>
    <div class="form-group full-span">
        <button type="submit" class="btn primary">Register</button>
    </div>
    <p class="small-text full-span">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</form>
@endsection

