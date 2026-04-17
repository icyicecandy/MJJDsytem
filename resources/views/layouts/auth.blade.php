<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon/mjjdnewlogo.png') }}">
    <title>@yield('title', 'MJJD Telecom')</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/consent.css') }}">
    @stack('styles')
    <script defer src="{{ asset('js/auth.js') }}"></script>
    <script defer src="{{ asset('js/consent.js') }}"></script>
</head>
@php($isRegisterPage = request()->routeIs('register'))
<body class="auth-layout {{ $isRegisterPage ? 'auth-register-page' : 'auth-login-page' }}">
    <section class="auth-stage">
        <div class="auth-backdrop" style="background-image: url('{{ asset('images/auth/bgforlogin.png') }}');"></div>

        <div class="auth-stage-content">
            <div class="auth-stage-top">
                <nav class="auth-visual-nav" aria-label="Auth Navigation">
                    <a href="{{ url('/') }}" class="auth-brand-lockup" aria-label="MJJD Telecom Home">
                        <img src="{{ asset('images/favicon/mjjdnewlogo.png') }}" alt="MJJD Telecom" class="auth-nav-logo-mark" />
                        <span class="auth-brand-text">
                            <strong>MJJD Telecom</strong>
                            <span>Customer Portal</span>
                        </span>
                    </a>

                    <div class="auth-nav-links">
                        <a href="{{ url('/') }}">Home</a>
                        @if($isRegisterPage)
                            <a href="{{ route('login') }}">Log In</a>
                        @else
                            <a href="{{ route('register') }}">Register</a>
                        @endif
                    </div>
                </nav>
            </div>

            <div class="auth-center-wrap">
                <div class="auth-center-panel {{ $isRegisterPage ? 'is-register' : 'is-login' }}">
                    <img src="{{ asset('build/assets/images/MJJDLOGO.png') }}" alt="MJJD Telecom" class="auth-card-logo" />
                    @yield('content')
                </div>
            </div>
        </div>
    </section>
    @stack('scripts')
</body>
</html>
