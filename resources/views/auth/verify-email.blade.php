@extends('layouts.app')

@section('title', 'Verify Your Email')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/email-verification.css') }}?v={{ filemtime(public_path('css/email-verification.css')) }}">
@endpush

@section('content')
<section class="email-verification-page" aria-labelledby="verification-title">
    <article class="email-verification-card">
        <div class="email-verification-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                <path d="m3 7 9 6 9-6"></path>
                <path d="m16.5 17 1.5 1.5 3-3"></path>
            </svg>
        </div>

        <p class="email-verification-eyebrow">Account security</p>
        <h1 id="verification-title">Verify your email address</h1>
        <p class="email-verification-lead">
            {{ session('status') === 'verification-link-failed' ? 'Your account was created, but we could not send the verification link to' : 'Check your inbox for a secure verification link sent to' }}
            <strong>{{ auth()->user()->email }}</strong>.
            @if (session('status') !== 'verification-link-failed')
                Open that message and select <strong>Verify Email Address</strong> to continue.
            @endif
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="email-verification-success" role="status">
                <span aria-hidden="true">&#10003;</span>
                A new verification link has been sent. Please check your inbox.
            </div>
        @elseif (session('status') === 'verification-link-failed')
            <div class="email-verification-error" role="alert">
                <span aria-hidden="true">!</span>
                Email delivery is temporarily unavailable. Your account is safe. Please try resending in a few minutes or change the email address.
            </div>
        @endif

        <div class="email-verification-help">
            <h2>Did not receive it?</h2>
            <ol>
                <li>Wait a minute, then check your spam or junk folder.</li>
                <li>Confirm that the email address shown above is correct.</li>
                <li>Request another link using the button below.</li>
            </ol>
        </div>

        <div class="email-verification-actions">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="email-verification-primary">
                    Resend verification email
                </button>
            </form>

            <a href="{{ route('profile.edit') }}" class="email-verification-secondary">
                Change email address
            </a>
        </div>

        <div class="email-verification-footer">
            <span>Signed in as {{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        </div>
    </article>
</section>
@endsection
