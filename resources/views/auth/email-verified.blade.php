@extends('layouts.app')

@section('title', 'Email Verified')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/email-verification.css') }}?v={{ filemtime(public_path('css/email-verification.css')) }}">
@endpush

@section('content')
<section class="email-verification-page" aria-labelledby="verification-complete-title">
    <article class="email-verification-card">
        <div class="email-verification-icon email-verification-icon--success" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m5 12 4 4L19 6"></path>
            </svg>
        </div>

        <p class="email-verification-eyebrow">Verification complete</p>
        <h1 id="verification-complete-title">Email verified successfully</h1>
        <p class="email-verification-lead">
            <strong>{{ $verifiedEmail }}</strong> is now verified and can access customer services after signing in.
        </p>

        @if ($currentUser && strcasecmp($currentUser->email, $verifiedEmail) !== 0)
            <div class="email-verification-notice" role="status">
                You are currently signed in as <strong>{{ $currentUser->email }}</strong>. Sign out before opening the verified customer account.
            </div>
        @endif

        <div class="email-verification-actions">
            @auth
                <a href="{{ route('dashboard') }}" class="email-verification-primary">Return to dashboard</a>
            @else
                <a href="{{ url('/') }}" class="email-verification-primary">Continue to sign in</a>
            @endauth
        </div>
    </article>
</section>
@endsection
