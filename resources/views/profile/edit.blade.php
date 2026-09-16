@extends('layouts.app')

@section('title', 'Edit Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}?v={{ filemtime(public_path('css/profile.css')) }}">
@endpush

@section('content')
<div class="profile-page">
    <section class="telecom-hero profile-hero">
        <div class="profile-hero-copy">
            <span class="profile-kicker">Account Settings</span>
            <h1>Profile Settings</h1>
            <p>Update the main account details you use for applications, tickets, and support.</p>
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
        </aside>
    </div>
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('js/profile-edit.js') }}?v={{ filemtime(public_path('js/profile-edit.js')) }}"></script>
@endpush
