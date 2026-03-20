<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon/mjjdnewlogo.png') }}">
    <title>@yield('title', 'MJJD Telecom')</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <script defer src="{{ asset('js/auth.js') }}"></script>
</head>
@php($isRegisterPage = request()->routeIs('register'))
<body class="auth-layout {{ $isRegisterPage ? 'auth-register-page' : 'auth-login-page' }}">
    <section class="auth-stage">
        <div class="auth-backdrop" style="background-image: url('{{ asset('images/auth/bgforlogin.png') }}');"></div>

        <div class="auth-stage-content">
            <div class="auth-stage-top">
                <a href="{{ url('/') }}" class="auth-visual-brand">
                    <span class="auth-brand-dot"></span>
                    <strong>MJJD Telecom</strong>
                </a>
                <nav class="auth-visual-nav" aria-label="Auth Navigation">
                    <a href="{{ url('/') }}">Home</a>
                    @if($isRegisterPage)
                        <a href="{{ route('login') }}">Log In</a>
                    @else
                        <a href="{{ route('register') }}">Join</a>
                    @endif
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
</body>
</html>
