<aside class="sidebar" id="sidebar">
    @php
        $icons = [
            'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg>',
            'document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h8"></path><path d="M8 9h2"></path></svg>',
            'tool' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg>',
            'ticket' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg>',
            'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
            'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path><path d="M9 17a3 3 0 0 0 6 0"></path></svg>',
            'reports' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V9"></path><path d="M10 19V5"></path><path d="M16 19v-7"></path><path d="M22 19V3"></path><path d="M2 19h22"></path></svg>',
            'logs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>',
            'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
            'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9.6v-.1A1.7 1.7 0 0 0 8.5 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3V9.6h.1A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.1A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.4.3.7.6 1 .3.3.7.4 1.1.4h.1v4h-.1a1.7 1.7 0 0 0-1.7.6z"></path></svg>',
        ];
        $role = Auth::user()->role ?? '';
        $chatIndexRoute = filled($role)
            ? ($role === 'customer' ? route('chat.index') : route('role.chat.index', ['role' => $role]))
            : route('chat.index');
        $chatActive = $role === 'customer'
            ? request()->is('chat*')
            : (filled($role) ? request()->is($role . '/chat*') : request()->is('chat*'));
        $sidebarNotificationCounts = $sidebarNotificationCounts ?? [];
        $sidebarCount = fn (string $section): int => (int) ($sidebarNotificationCounts[$section] ?? 0);
    @endphp
    <nav class="sidebar-nav">
        <ul>
            @if($role === 'customer')
                <li class="sidebar-item {{ request()->is('customer/dashboard') ? 'active' : '' }}"><a href="{{ route('customer.dashboard') }}" data-notification-section="dashboard"><span class="nav-icon">{!! $icons['dashboard'] !!}</span><span class="nav-label">Dashboard</span><span class="sidebar-notification-badge {{ $sidebarCount('dashboard') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="dashboard" aria-label="{{ $sidebarCount('dashboard') }} unread dashboard updates">{{ $sidebarCount('dashboard') > 99 ? '99+' : $sidebarCount('dashboard') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('customer/apply*') ? 'active' : '' }}"><a href="{{ route('customer.apply') }}" data-notification-section="applications"><span class="nav-icon">{!! $icons['document'] !!}</span><span class="nav-label">Apply</span><span class="sidebar-notification-badge {{ $sidebarCount('applications') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="applications" aria-label="{{ $sidebarCount('applications') }} unread application updates">{{ $sidebarCount('applications') > 99 ? '99+' : $sidebarCount('applications') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('customer/tickets*') ? 'active' : '' }}"><a href="{{ route('customer.tickets.index') }}" data-notification-section="tickets"><span class="nav-icon">{!! $icons['ticket'] !!}</span><span class="nav-label">Support Tickets</span><span class="sidebar-notification-badge {{ $sidebarCount('tickets') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="tickets" aria-label="{{ $sidebarCount('tickets') }} unread ticket updates">{{ $sidebarCount('tickets') > 99 ? '99+' : $sidebarCount('tickets') }}</span></a></li>
                <li class="sidebar-item {{ $chatActive ? 'active' : '' }}"><a href="{{ $chatIndexRoute }}" data-notification-section="chat"><span class="nav-icon">{!! $icons['chat'] !!}</span><span class="nav-label">Live Chat</span><span class="sidebar-notification-badge {{ $sidebarCount('chat') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="chat" aria-label="{{ $sidebarCount('chat') }} unread chat updates">{{ $sidebarCount('chat') > 99 ? '99+' : $sidebarCount('chat') }}</span></a></li>
            @elseif($role === 'technician')
                <li class="sidebar-item {{ request()->is('technician/dashboard') ? 'active' : '' }}"><a href="{{ route('technician.dashboard') }}" data-notification-section="dashboard"><span class="nav-icon">{!! $icons['dashboard'] !!}</span><span class="nav-label">Dashboard</span><span class="sidebar-notification-badge {{ $sidebarCount('dashboard') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="dashboard" aria-label="{{ $sidebarCount('dashboard') }} unread dashboard updates">{{ $sidebarCount('dashboard') > 99 ? '99+' : $sidebarCount('dashboard') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('technician/installations*') ? 'active' : '' }}"><a href="{{ route('technician.installations.index') }}" data-notification-section="installations"><span class="nav-icon">{!! $icons['tool'] !!}</span><span class="nav-label">Installations</span><span class="sidebar-notification-badge {{ $sidebarCount('installations') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="installations" aria-label="{{ $sidebarCount('installations') }} unread installation updates">{{ $sidebarCount('installations') > 99 ? '99+' : $sidebarCount('installations') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('technician/customers*') ? 'active' : '' }}"><a href="{{ route('technician.customers.index') }}"><span class="nav-icon">{!! $icons['users'] !!}</span><span class="nav-label">Customers</span></a></li>
                <li class="sidebar-item {{ request()->is('technician/tickets*') ? 'active' : '' }}"><a href="{{ route('technician.tickets.index') }}" data-notification-section="tickets"><span class="nav-icon">{!! $icons['ticket'] !!}</span><span class="nav-label">Tickets</span><span class="sidebar-notification-badge {{ $sidebarCount('tickets') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="tickets" aria-label="{{ $sidebarCount('tickets') }} unread ticket updates">{{ $sidebarCount('tickets') > 99 ? '99+' : $sidebarCount('tickets') }}</span></a></li>
                <li class="sidebar-item {{ $chatActive ? 'active' : '' }}"><a href="{{ $chatIndexRoute }}" data-notification-section="chat"><span class="nav-icon">{!! $icons['chat'] !!}</span><span class="nav-label">Live Chat</span><span class="sidebar-notification-badge {{ $sidebarCount('chat') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="chat" aria-label="{{ $sidebarCount('chat') }} unread chat updates">{{ $sidebarCount('chat') > 99 ? '99+' : $sidebarCount('chat') }}</span></a></li>
            @elseif($role === 'admin')
                <li class="sidebar-item {{ request()->is('admin/dashboard') ? 'active' : '' }}"><a href="{{ route('admin.dashboard') }}"><span class="nav-icon">{!! $icons['dashboard'] !!}</span><span class="nav-label">Dashboard</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/applications*') ? 'active' : '' }}"><a href="{{ route('admin.applications.index') }}" data-notification-section="applications"><span class="nav-icon">{!! $icons['document'] !!}</span><span class="nav-label">Applications</span><span class="sidebar-notification-badge {{ $sidebarCount('applications') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="applications" aria-label="{{ $sidebarCount('applications') }} unread application updates">{{ $sidebarCount('applications') > 99 ? '99+' : $sidebarCount('applications') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/installations*') ? 'active' : '' }}"><a href="{{ route('admin.installations.index') }}" data-notification-section="installations"><span class="nav-icon">{!! $icons['tool'] !!}</span><span class="nav-label">Installations</span><span class="sidebar-notification-badge {{ $sidebarCount('installations') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="installations" aria-label="{{ $sidebarCount('installations') }} unread installation updates">{{ $sidebarCount('installations') > 99 ? '99+' : $sidebarCount('installations') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/customers*') ? 'active' : '' }}"><a href="{{ route('admin.customers.index') }}" data-notification-section="customers"><span class="nav-icon">{!! $icons['users'] !!}</span><span class="nav-label">Customers</span><span class="sidebar-notification-badge {{ $sidebarCount('customers') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="customers" aria-label="{{ $sidebarCount('customers') }} unread customer updates">{{ $sidebarCount('customers') > 99 ? '99+' : $sidebarCount('customers') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/technicians*') || request()->is('admin/technician-unavailability*') ? 'active' : '' }}"><a href="{{ route('admin.technicians.index') }}" data-notification-section="technicians"><span class="nav-icon">{!! $icons['users'] !!}</span><span class="nav-label">Technicians</span><span class="sidebar-notification-badge {{ $sidebarCount('technicians') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="technicians" aria-label="{{ $sidebarCount('technicians') }} unread technician updates">{{ $sidebarCount('technicians') > 99 ? '99+' : $sidebarCount('technicians') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/tickets*') ? 'active' : '' }}"><a href="{{ route('admin.tickets.index') }}" data-notification-section="tickets"><span class="nav-icon">{!! $icons['ticket'] !!}</span><span class="nav-label">Tickets</span><span class="sidebar-notification-badge {{ $sidebarCount('tickets') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="tickets" aria-label="{{ $sidebarCount('tickets') }} unread ticket updates">{{ $sidebarCount('tickets') > 99 ? '99+' : $sidebarCount('tickets') }}</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/reports*') ? 'active' : '' }}"><a href="{{ route('admin.reports.index') }}"><span class="nav-icon">{!! $icons['reports'] !!}</span><span class="nav-label">Analytics</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/system-logs*') ? 'active' : '' }}"><a href="{{ route('admin.system-logs.index') }}"><span class="nav-icon">{!! $icons['logs'] !!}</span><span class="nav-label">System Logs</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/settings*') ? 'active' : '' }}"><a href="{{ route('admin.settings.index') }}"><span class="nav-icon">{!! $icons['settings'] !!}</span><span class="nav-label">System Settings</span></a></li>
                <li class="sidebar-item {{ $chatActive ? 'active' : '' }}"><a href="{{ $chatIndexRoute }}" data-notification-section="chat"><span class="nav-icon">{!! $icons['chat'] !!}</span><span class="nav-label">Live Chat</span><span class="sidebar-notification-badge {{ $sidebarCount('chat') > 0 ? '' : 'is-hidden' }}" data-sidebar-notification-badge="chat" aria-label="{{ $sidebarCount('chat') }} unread chat updates">{{ $sidebarCount('chat') > 99 ? '99+' : $sidebarCount('chat') }}</span></a></li>
            @endif
        </ul>
    </nav>

    <div class="sidebar-account">
        <a href="{{ route('profile.edit') }}" class="sidebar-account__profile {{ request()->is('profile*') ? 'is-active' : '' }}">
            <span class="sidebar-account__avatar">
                @if(Auth::user()->avatar_url)
                    <img src="{{ Auth::user()->avatar_url }}" alt="">
                @else
                    {{ Auth::user()->initials }}
                @endif
            </span>
            <span class="sidebar-account__copy">
                <strong>{{ Auth::user()->name }}</strong>
                <small>{{ ucfirst(Auth::user()->role) }}</small>
                <span>Profile settings</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="sidebar-account__logout">
            @csrf
            <button type="submit" aria-label="Log out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"></path>
                </svg>
            </button>
        </form>
    </div>
</aside>
