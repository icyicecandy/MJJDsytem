<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="{{ asset('images/favicon/mjjdnewlogo.png') }}" />
    <title>@yield('title', 'MJJD Telecom CRM')</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}" />
    @stack('styles')
    <script defer src="{{ asset('js/dashboard.js') }}"></script>
</head>
<body class="layout {{ auth()->check() ? 'is-auth' : 'is-guest' }}">
    <header class="navbar">
        <div class="navbar-left">
            @auth
                <button id="sidebarToggle" class="icon-btn" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 6h16"></path>
                        <path d="M4 12h16"></path>
                        <path d="M4 18h16"></path>
                    </svg>
                </button>
            @endauth
            <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="logo">
                <img src="{{ asset('images/favicon/mjjdnewlogo.png') }}" alt="MJJD Telecom" class="logo-mark" />
                <span class="logo-copy">
                    <strong>MJJD Telecom</strong>
                    <small>Powered by Surf2Sawa</small>
                </span>
            </a>
        </div>

        <div class="navbar-right">
            @auth
                @php
                    $chatUrl = filled(Auth::user()->role)
                        ? (Auth::user()->role === 'customer'
                            ? route('chat.index')
                            : route('role.chat.index', ['role' => Auth::user()->role]))
                        : route('chat.index');
                @endphp
                <span class="user-meta">
                    <strong>{{ Auth::user()->name }}</strong>
                    <span class="user-role">{{ ucfirst(Auth::user()->role) }}</span>
                </span>
                <button id="notifToggle" class="icon-btn" aria-label="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path>
                        <path d="M9 17a3 3 0 0 0 6 0"></path>
                    </svg>
                </button>
                <button id="chatToggle" class="icon-btn" aria-label="Chat" data-chat-url="{{ $chatUrl }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </button>
                <div class="profile-dropdown">
                    <button id="profileToggle" class="icon-btn profile-toggle" aria-label="Profile menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="caret">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </span>
                    </button>
                    <div id="profileDropdown" class="dropdown-content">
                        <a href="{{ route('profile.edit') }}">Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-link">Logout</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="guest-nav-wrap">
                    <nav class="guest-nav" aria-label="Primary">
                        <a href="{{ url('/') }}">Home</a>
                        <a href="{{ url('/#plans') }}">Plans</a>
                        <a href="{{ url('/#support') }}">Support</a>
                    </nav>
                    <div class="guest-actions">
                        <a href="{{ route('login') }}" class="btn secondary">Log in</a>
                        <a href="{{ route('register') }}" class="btn">Register</a>
                    </div>
                </div>
            @endauth
        </div>
    </header>

    @auth
        @include('components.sidebar')
    @endauth

    <main class="main-content">
        @isset($header)
            <section class="page-header card page-header-card">
                {{ $header }}
            </section>
        @endisset

        @if(session('success') || session('error') || session('status'))
            <div class="flash-stack">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error">{{ session('error') }}</div>
                @endif
                @if(session('status'))
                    <div class="alert alert-info">{{ session('status') }}</div>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="flash-stack">
                <div class="alert alert-error">{{ $errors->first() }}</div>
            </div>
        @endif

        <div class="page-body">
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </div>
    </main>

    @unless (trim($__env->yieldContent('hide_global_footer')) === '1')
        <footer class="footer">
            <p>&copy; {{ date('Y') }} MJJD Telecom. All rights reserved.</p>
        </footer>
    @endunless

    @stack('scripts')
    @yield('scripts')
</body>
</html>






