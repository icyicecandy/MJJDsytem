<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MJJD Telecom')</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <script defer src="{{ asset('js/auth.js') }}"></script>
</head>
@php($isRegisterPage = request()->routeIs('register'))
<body class="auth-layout {{ $isRegisterPage ? 'auth-register-page' : 'auth-login-page' }}">
    <div class="auth-wrapper {{ $isRegisterPage ? 'register-wrapper' : 'login-wrapper' }}">
        @unless($isRegisterPage)
            <div class="auth-left">
                <div class="auth-slideshow" id="authSlideshow" aria-hidden="true">
                    <div class="auth-slide is-active" style="background-image: linear-gradient(rgba(7, 27, 54, 0.58), rgba(7, 27, 54, 0.78)), url('{{ asset('build/assets/images/surf2sawa-unlimited-prepaid-fiber-internet-philippines.jpg.webp') }}');"></div>
                    <div class="auth-slide" style="background-image: linear-gradient(rgba(7, 27, 54, 0.56), rgba(7, 27, 54, 0.76)), url('{{ asset('build/assets/images/bckgrd.png') }}');"></div>
                    <div class="auth-slide" style="background-image: linear-gradient(rgba(7, 27, 54, 0.62), rgba(7, 27, 54, 0.82)), url('{{ asset('build/assets/images/headernav.png') }}');"></div>
                </div>

                <div class="branding">
                    <div class="brand-kicker">MJJD Telecom</div>
                    <p>Your trusted provincial ISP for account access, service applications, and Surf2Sawa-powered prepaid fiber support.</p>
                    <div class="brand-chip-row">
                        <span class="brand-chip">Powered by Surf2Sawa</span>
                        <span class="brand-chip">Prepaid Fiber Access</span>
                    </div>
                    <div class="slide-dots" id="authSlideDots" aria-hidden="true">
                        <button type="button" class="slide-dot is-active" data-slide="0"></button>
                        <button type="button" class="slide-dot" data-slide="1"></button>
                        <button type="button" class="slide-dot" data-slide="2"></button>
                    </div>
                </div>
            </div>
        @endunless
        <div class="auth-right {{ $isRegisterPage ? 'register-right' : '' }}">
            <div class="auth-card {{ $isRegisterPage ? 'register-card' : '' }}">
                <img src="{{ asset('build/assets/images/MJJDLOGO.png') }}" alt="MJJD Telecom" class="auth-card-logo" />
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
