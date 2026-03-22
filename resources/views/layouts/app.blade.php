<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="icon" type="image/png" href="{{ asset('images/favicon/mjjdnewlogo.png') }}" />
    <title>@yield('title', 'MJJD Telecom CRM')</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}" />
    @stack('styles')
    <script defer src="{{ asset('js/dashboard.js') }}"></script>
</head>
<body
    class="layout {{ auth()->check() ? 'is-auth' : 'is-guest' }}"
    @auth
        data-notifications-feed="{{ route('notifications.feed') }}"
        data-notifications-index="{{ route('notifications.index') }}"
        data-notifications-mark-all="{{ route('notifications.mark-all-read') }}"
    @endauth
>
    @php
        $headerNotifications = collect();
        $unreadNotificationCount = 0;

        if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('app_notifications')) {
            $notificationQuery = auth()->user()->appNotifications();
            $headerNotifications = (clone $notificationQuery)->take(8)->get();
            $unreadNotificationCount = (clone $notificationQuery)->unread()->count();
        }
    @endphp

    <header class="navbar">
        <div class="navbar-left">
            @auth
                <button id="sidebarToggle" class="icon-btn sidebar-floating-toggle" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 6h16"></path>
                        <path d="M4 12h16"></path>
                        <path d="M4 18h16"></path>
                    </svg>
                </button>
            @endauth
            <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="logo">
                @guest
                    <img src="{{ asset('images/favicon/mjjdnewlogo.png') }}" alt="MJJD Telecom" class="logo-mark" />
                @endguest
                <span class="logo-copy">
                    <strong><span class="brand-mjjd">MJJD</span> <span class="brand-telecom">Telecom</span></strong>
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
                <div class="notif-shell">
                    <button id="notifToggle" class="icon-btn notif-toggle" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path>
                            <path d="M9 17a3 3 0 0 0 6 0"></path>
                        </svg>
                        <span class="notif-dot {{ $unreadNotificationCount > 0 ? '' : 'is-hidden' }}" aria-hidden="true"></span>
                    </button>
                    <div id="notifDropdown" class="dropdown-content notif-dropdown">
                        <div class="notif-dropdown-head">
                            <strong>Notifications</strong>
                            <span id="notifUnreadLabel">{{ $unreadNotificationCount > 0 ? $unreadNotificationCount . ' unread' : 'All caught up' }}</span>
                        </div>
                        <div id="notifDropdownList">
                            @forelse($headerNotifications as $notification)
                                <a href="{{ route('notifications.redirect', $notification) }}" class="notif-item {{ $notification->read_at ? '' : 'is-unread' }}">
                                    <span class="notif-title">{{ $notification->title }}</span>
                                    @if($notification->message)
                                        <span class="notif-message">{{ $notification->message }}</span>
                                    @endif
                                    <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
                                </a>
                            @empty
                                <div class="notif-empty">No notifications yet.</div>
                            @endforelse
                        </div>
                        <div class="notif-dropdown-actions">
                            <form id="notifMarkAllForm" method="POST" action="{{ route('notifications.mark-all-read') }}">
                                @csrf
                                <button type="submit" class="btn-link">Mark all as read</button>
                            </form>
                            <a href="{{ route('notifications.index') }}" class="notif-view-all">View all</a>
                        </div>
                    </div>
                </div>
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
