@extends('layouts.app')

@section('title', 'Edit Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
<div class="profile-page">
    <section class="telecom-hero profile-hero">
        <div class="profile-hero-copy">
            <span class="profile-kicker">Account Settings</span>
            <h1>Edit your profile</h1>
            <p>Keep your Surf2Sawa account details, security settings, preferences, and privacy controls up to date from one place.</p>
            <div class="telecom-chip-row profile-chip-row">
                <span class="telecom-chip {{ $user->email_verified_at ? 'is-success' : 'is-warning' }}">Email {{ $user->email_verified_at ? 'verified' : 'not verified' }}</span>
                @if($user->phone)
                    <span class="telecom-chip {{ $user->phone_verified_at ? 'is-success' : 'is-warning' }}">Phone {{ $user->phone_verified_at ? 'verified' : 'verification pending' }}</span>
                @endif
                <span class="telecom-chip">Theme: {{ ucfirst($user->theme_preference ?? 'system') }}</span>
                <span class="telecom-chip">Visibility: {{ ucfirst($user->profile_visibility ?? 'private') }}</span>
            </div>
        </div>
        <div class="profile-hero-card">
            <div class="profile-hero-avatar">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }} avatar">
                @else
                    <span>{{ $user->initials }}</span>
                @endif
            </div>
            <div>
                <strong>{{ $user->name }}</strong>
                <p>{{ '@' . ($user->username ?: 'set-your-username') }}</p>
                <small>{{ ucfirst($user->role ?? 'member') }} account</small>
            </div>
        </div>
    </section>

    <div class="profile-layout">
        <div class="profile-main">
            @include('profile.partials.update-profile-information-form')
        </div>

        <aside class="profile-side">
            @include('profile.partials.update-password-form')
            @include('profile.partials.delete-user-form')
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('js/profile-edit.js') }}"></script>
@endpush
