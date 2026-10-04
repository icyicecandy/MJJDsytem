<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon/mjjdnewlogo.png') }}">
    <title>@yield('title', 'Staff Security') | {{ config('app.name', 'MJJD Telecom') }}</title>
    <link rel="stylesheet" href="{{ asset('css/staff-security.css') }}?v={{ filemtime(public_path('css/staff-security.css')) }}">
</head>
<body class="staff-security-page">
    <header class="staff-security-header">
        <a href="{{ url('/') }}" class="staff-security-brand" aria-label="MJJD Telecom home">
            <img src="{{ asset('images/favicon/mjjdnewlogo.png') }}" alt="">
            <span><strong><span>MJJD</span> Telecom</strong><small>Staff account security</small></span>
        </a>

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="security-link-button">Sign out</button>
            </form>
        @else
            <a href="{{ url('/') }}" class="security-link-button">Back to sign in</a>
        @endauth
    </header>

    <main class="staff-security-main">
        <section class="staff-security-shell">
            @if ($errors->any())
                <div class="security-alert security-alert-error" role="alert">
                    <strong>Please check the highlighted field.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @yield('content')
        </section>
    </main>

    <footer class="staff-security-footer">&copy; {{ date('Y') }} MJJD Telecom. Authorized staff access only.</footer>
    @stack('scripts')
</body>
</html>
