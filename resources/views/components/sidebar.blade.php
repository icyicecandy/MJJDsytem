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
        ];
        $role = Auth::user()->role ?? '';
        $chatIndexRoute = filled($role)
            ? ($role === 'customer' ? route('chat.index') : route('role.chat.index', ['role' => $role]))
            : route('chat.index');
        $chatActive = $role === 'customer'
            ? request()->is('chat*')
            : (filled($role) ? request()->is($role . '/chat*') : request()->is('chat*'));
    @endphp
    <nav class="sidebar-nav">
        <ul>
            @if($role === 'customer')
                <li class="sidebar-item {{ request()->is('customer/dashboard') ? 'active' : '' }}"><a href="{{ route('customer.dashboard') }}"><span class="nav-icon">{!! $icons['dashboard'] !!}</span><span class="nav-label">Dashboard</span></a></li>
                <li class="sidebar-item {{ request()->is('customer/apply*') ? 'active' : '' }}"><a href="{{ route('customer.apply') }}"><span class="nav-icon">{!! $icons['document'] !!}</span><span class="nav-label">Apply</span></a></li>
                <li class="sidebar-item {{ request()->is('customer/tickets*') ? 'active' : '' }}"><a href="{{ route('customer.tickets.index') }}"><span class="nav-icon">{!! $icons['ticket'] !!}</span><span class="nav-label">Support Tickets</span></a></li>
                <li class="sidebar-item {{ $chatActive ? 'active' : '' }}"><a href="{{ $chatIndexRoute }}"><span class="nav-icon">{!! $icons['chat'] !!}</span><span class="nav-label">Live Chat</span></a></li>
            @elseif($role === 'technician')
                <li class="sidebar-item {{ request()->is('technician/dashboard') ? 'active' : '' }}"><a href="{{ route('technician.dashboard') }}"><span class="nav-icon">{!! $icons['dashboard'] !!}</span><span class="nav-label">Dashboard</span></a></li>
                <li class="sidebar-item {{ request()->is('technician/installations*') ? 'active' : '' }}"><a href="{{ route('technician.installations.index') }}"><span class="nav-icon">{!! $icons['tool'] !!}</span><span class="nav-label">Installations</span></a></li>
                <li class="sidebar-item {{ request()->is('technician/customers*') ? 'active' : '' }}"><a href="{{ route('technician.customers.index') }}"><span class="nav-icon">{!! $icons['users'] !!}</span><span class="nav-label">Customers</span></a></li>
                <li class="sidebar-item {{ request()->is('technician/tickets*') ? 'active' : '' }}"><a href="{{ route('technician.tickets.index') }}"><span class="nav-icon">{!! $icons['ticket'] !!}</span><span class="nav-label">Tickets</span></a></li>
                <li class="sidebar-item {{ $chatActive ? 'active' : '' }}"><a href="{{ $chatIndexRoute }}"><span class="nav-icon">{!! $icons['chat'] !!}</span><span class="nav-label">Live Chat</span></a></li>
            @elseif($role === 'admin')
                <li class="sidebar-item {{ request()->is('admin/dashboard') ? 'active' : '' }}"><a href="{{ route('admin.dashboard') }}"><span class="nav-icon">{!! $icons['dashboard'] !!}</span><span class="nav-label">Dashboard</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/applications*') ? 'active' : '' }}"><a href="{{ route('admin.applications.index') }}"><span class="nav-icon">{!! $icons['document'] !!}</span><span class="nav-label">Applications</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/installations*') ? 'active' : '' }}"><a href="{{ route('admin.installations.index') }}"><span class="nav-icon">{!! $icons['tool'] !!}</span><span class="nav-label">Installations</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/customers*') ? 'active' : '' }}"><a href="{{ route('admin.customers.index') }}"><span class="nav-icon">{!! $icons['users'] !!}</span><span class="nav-label">Customers</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/technicians*') ? 'active' : '' }}"><a href="{{ route('admin.technicians.index') }}"><span class="nav-icon">{!! $icons['users'] !!}</span><span class="nav-label">Technicians</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/tickets*') ? 'active' : '' }}"><a href="{{ route('admin.tickets.index') }}"><span class="nav-icon">{!! $icons['ticket'] !!}</span><span class="nav-label">Tickets</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/reports*') ? 'active' : '' }}"><a href="{{ route('admin.reports.index') }}"><span class="nav-icon">{!! $icons['reports'] !!}</span><span class="nav-label">Analytics</span></a></li>
                <li class="sidebar-item {{ request()->is('admin/system-logs*') ? 'active' : '' }}"><a href="{{ route('admin.system-logs.index') }}"><span class="nav-icon">{!! $icons['logs'] !!}</span><span class="nav-label">System Logs</span></a></li>
                <li class="sidebar-item {{ $chatActive ? 'active' : '' }}"><a href="{{ $chatIndexRoute }}"><span class="nav-icon">{!! $icons['chat'] !!}</span><span class="nav-label">Live Chat</span></a></li>
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
