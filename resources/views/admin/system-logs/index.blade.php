@extends('layouts.app')

@section('title', 'System Logs')

@section('content')
@php
    $moduleLabels = [
        'application' => 'Applications',
        'installation' => 'Installations',
        'ticket' => 'Tickets',
        'chat' => 'Live Chat',
        'account' => 'Accounts',
        'email' => 'Email',
        'system' => 'System',
        'general' => 'General',
    ];

    $moduleFor = static function ($log): string {
        $action = Str::lower((string) $log->action);

        return match (true) {
            Str::contains($action, 'application') => 'application',
            Str::contains($action, 'installation') => 'installation',
            Str::contains($action, 'ticket') => 'ticket',
            Str::contains($action, 'chat') => 'chat',
            Str::contains($action, 'email') => 'email',
            Str::contains($action, ['account', 'profile', 'password']) => 'account',
            $log->user_role === 'system' => 'system',
            default => 'general',
        };
    };

    $today = today()->toDateString();
    $sevenDaysAgo = today()->subDays(6)->toDateString();
    $thirtyDaysAgo = today()->subDays(29)->toDateString();
    $currentFrom = $filters['date_from'] ?? null;
    $currentTo = $filters['date_to'] ?? null;
    $activePeriod = match (true) {
        $currentFrom === $today && $currentTo === $today => 'today',
        $currentFrom === $sevenDaysAgo && $currentTo === $today => '7-days',
        $currentFrom === $thirtyDaysAgo && $currentTo === $today => '30-days',
        blank($currentFrom) && blank($currentTo) => 'all',
        default => 'custom',
    };

    $activeFilters = collect([
        'search' => filled($filters['search'] ?? null) ? 'Search: '.$filters['search'] : null,
        'user_id' => filled($filters['user_id'] ?? null) ? 'User: '.optional($users->firstWhere('id', (int) $filters['user_id']))->name : null,
        'action' => filled($filters['action'] ?? null) ? 'Action: '.$filters['action'] : null,
        'module' => filled($filters['module'] ?? null) ? 'Module: '.($moduleLabels[$filters['module']] ?? ucfirst($filters['module'])) : null,
        'date_from' => filled($filters['date_from'] ?? null) ? 'From: '.\Illuminate\Support\Carbon::parse($filters['date_from'])->format('M d, Y') : null,
        'date_to' => filled($filters['date_to'] ?? null) ? 'To: '.\Illuminate\Support\Carbon::parse($filters['date_to'])->format('M d, Y') : null,
    ])->filter();
@endphp

<div class="system-log-page" data-system-log-page>
    <header class="system-log-hero">
        <div class="system-log-hero__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/>
            </svg>
        </div>
        <div class="system-log-hero__copy">
            <span class="system-log-kicker">Audit and accountability</span>
            <h1>System Logs</h1>
            <p>Review important administrator, technician, customer, and automated activity across the system.</p>
        </div>
        <div class="system-log-hero__meta">
            <span class="system-log-readonly"><span aria-hidden="true"></span>Read-only audit trail</span>
            <small>Times shown in {{ config('app.timezone') }}</small>
        </div>
    </header>

    <nav class="record-view-tabs" aria-label="System log views">
        <a href="{{ route('admin.system-logs.index') }}" @class(['is-active' => $selectedView === 'activity'])>Activity</a>
        <a href="{{ route('admin.system-logs.index', ['view' => 'email']) }}" @class(['is-active' => $selectedView === 'email'])>Email Delivery</a>
    </nav>

    @if($selectedView === 'activity')
    <section class="panel system-log-filter-panel" aria-labelledby="systemLogFiltersTitle">
        <div class="system-log-section-head">
            <div>
                <span class="system-log-kicker">Find activity</span>
                <h2 id="systemLogFiltersTitle">Filter the audit trail</h2>
                <p>Search descriptions or narrow records by person, module, action, and date.</p>
            </div>
            <div class="system-log-periods" aria-label="Quick date ranges" data-live-filter-sync="system-log-periods">
                <a href="{{ route('admin.system-logs.index', request()->except(['date_from', 'date_to', 'page'])) }}" class="{{ $activePeriod === 'all' ? 'is-active' : '' }}">All time</a>
                <a href="{{ route('admin.system-logs.index', array_merge(request()->except(['date_from', 'date_to', 'page']), ['date_from' => $today, 'date_to' => $today])) }}" class="{{ $activePeriod === 'today' ? 'is-active' : '' }}">Today</a>
                <a href="{{ route('admin.system-logs.index', array_merge(request()->except(['date_from', 'date_to', 'page']), ['date_from' => $sevenDaysAgo, 'date_to' => $today])) }}" class="{{ $activePeriod === '7-days' ? 'is-active' : '' }}">7 days</a>
                <a href="{{ route('admin.system-logs.index', array_merge(request()->except(['date_from', 'date_to', 'page']), ['date_from' => $thirtyDaysAgo, 'date_to' => $today])) }}" class="{{ $activePeriod === '30-days' ? 'is-active' : '' }}">30 days</a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.system-logs.index', absolute: false) }}" class="system-log-filter-form" data-mobile-filter data-live-filter data-live-filter-target="#systemLogResults">
            <label class="system-log-field">
                <span>Log type</span>
                <select name="view" data-filter-page-switch>
                    <option value="activity" selected>Activity</option>
                    <option value="email">Email Delivery</option>
                </select>
            </label>
            <label class="system-log-field system-log-field--search">
                <span>Search activity</span>
                <span class="system-log-search-control">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, action, or description">
                </span>
            </label>
            <label class="system-log-field">
                <span>User</span>
                <select name="user_id">
                    <option value="">All users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </label>
            <label class="system-log-field">
                <span>Module</span>
                <select name="module">
                    <option value="">All modules</option>
                    @foreach(['application' => 'Applications', 'installation' => 'Installations', 'ticket' => 'Tickets', 'chat' => 'Live Chat', 'account' => 'Accounts', 'email' => 'Email', 'system' => 'System'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['module'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="system-log-field system-log-field--action">
                <span>Action</span>
                <select name="action">
                    <option value="">All actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </label>
            <label class="system-log-field">
                <span>From date</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" max="{{ $filters['date_to'] ?? $today }}">
            </label>
            <label class="system-log-field">
                <span>To date</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" min="{{ $filters['date_from'] ?? '' }}" max="{{ $today }}">
            </label>
            <div class="system-log-filter-actions">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="{{ route('admin.system-logs.index') }}" class="btn secondary">Reset</a>
            </div>
        </form>

        <div data-live-filter-sync="system-log-active-filters">
        @if($activeFilters->isNotEmpty())
            <div class="system-log-active-filters" aria-label="Active filters">
                <span class="system-log-active-filters__label">{{ $activeFilterCount }} active {{ Str::plural('filter', $activeFilterCount) }}</span>
                <div>
                    @foreach($activeFilters as $key => $label)
                        <a href="{{ route('admin.system-logs.index', request()->except([$key, 'page'])) }}" title="Remove {{ $label }}">
                            {{ $label }} <span aria-hidden="true">&times;</span>
                        </a>
                    @endforeach
                </div>
                <a class="system-log-clear" href="{{ route('admin.system-logs.index') }}">Clear all</a>
            </div>
        @endif
        </div>
    </section>

    <section id="systemLogResults" class="panel system-log-records" aria-labelledby="activityRecordsTitle" data-live-filter-results aria-busy="false">
        <div class="system-log-records__head">
            <div>
                <span class="system-log-kicker">Recorded events</span>
                <h2 id="activityRecordsTitle">Activity Records</h2>
                <p>{{ number_format($logs->total()) }} matching {{ Str::plural('record', $logs->total()) }}, newest first</p>
            </div>
            <span class="system-log-page-count">Page {{ $logs->currentPage() }} of {{ max(1, $logs->lastPage()) }}</span>
        </div>

        <div class="table-wrap system-log-table-wrap" tabindex="0" role="region" aria-label="System activity records">
            <table class="telecom-table system-log-table" data-mobile-cards data-mobile-cards-expandable>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Actor</th>
                        <th>Details</th>
                        <th>Occurred</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $module = $moduleFor($log);
                            $actorName = $log->user?->name ?? 'Automated System';
                            $actorRole = ucfirst($log->user_role ?: 'system');
                            $actorInitials = collect(preg_split('/\s+/', trim($actorName)))
                                ->filter()
                                ->take(2)
                                ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
                                ->implode('');
                            $occurredAt = $log->created_at->timezone(config('app.timezone'));
                        @endphp
                        <tr data-log-module="{{ $module }}">
                            <td data-mobile-primary>
                                <div class="system-log-event">
                                    <span class="system-log-module system-log-module--{{ $module }}">{{ $moduleLabels[$module] }}</span>
                                    <strong>{{ $log->action }}</strong>
                                    <small>Event #{{ $log->id }}</small>
                                </div>
                            </td>
                            <td>
                                <div class="system-log-actor">
                                    <span class="system-log-avatar" aria-hidden="true">{{ $actorInitials ?: 'SY' }}</span>
                                    <span><strong>{{ $actorName }}</strong><small>{{ $actorRole }}</small></span>
                                </div>
                            </td>
                            <td data-mobile-secondary>
                                @if(Str::length($log->description) > 150)
                                    <details class="ui-log-description system-log-description">
                                        <summary><span class="ui-log-description__preview">{{ Str::limit($log->description, 150) }}</span><span>View full details</span></summary>
                                        <span class="ui-log-description__full">{{ $log->description }}</span>
                                    </details>
                                @else
                                    <p class="system-log-description-text">{{ $log->description }}</p>
                                @endif
                            </td>
                            <td data-mobile-secondary>
                                <time class="system-log-time" datetime="{{ $occurredAt->toIso8601String() }}">
                                    <strong>{{ $occurredAt->format('M d, Y') }}</strong>
                                    <span>{{ $occurredAt->format('h:i A') }}</span>
                                    <small>{{ $occurredAt->diffForHumans() }}</small>
                                </time>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="system-log-empty">
                                    <span aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 9h8M8 13h6"/></svg>
                                    </span>
                                    <h3>No matching system logs</h3>
                                    <p>Change the search, date range, or filters to view more activity.</p>
                                    <a href="{{ route('admin.system-logs.index') }}" class="btn secondary">Clear Filters</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.pagination :paginator="$logs" />
    </section>
    @else
        <section class="panel system-log-filter-panel" aria-labelledby="emailDeliveryFiltersTitle">
            <div class="system-log-section-head">
                <div>
                    <span class="system-log-kicker">Delivery visibility</span>
                    <h2 id="emailDeliveryFiltersTitle">Email delivery records</h2>
                    <p>Check the recipient, notification type, sending result, and whether a failed message is available for retry.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.system-logs.index', absolute: false) }}" class="system-log-filter-form" data-mobile-filter>
                <label class="system-log-field">
                    <span>Log type</span>
                    <select name="view" data-filter-page-switch>
                        <option value="activity">Activity</option>
                        <option value="email" selected>Email Delivery</option>
                    </select>
                </label>
                <label class="system-log-field system-log-field--search">
                    <span>Recipient or type</span>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Email address or notification type">
                </label>
                <label class="system-log-field">
                    <span>Status</span>
                    <select name="email_status">
                        <option value="">All statuses</option>
                        @foreach(['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['email_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="system-log-field">
                    <span>Notification type</span>
                    <select name="notification_type">
                        <option value="">All types</option>
                        @foreach($emailTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['notification_type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="system-log-field"><span>From date</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label class="system-log-field"><span>To date</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <div class="system-log-filter-actions">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="{{ route('admin.system-logs.index', ['view' => 'email']) }}" class="btn secondary">Clear Filters</a>
                </div>
            </form>
        </section>

        <section class="panel system-log-records" aria-labelledby="emailDeliveryRecordsTitle">
            <div class="system-log-records__head">
                <div>
                    <span class="system-log-kicker">Recorded messages</span>
                    <h2 id="emailDeliveryRecordsTitle">Email Delivery</h2>
                    <p>{{ number_format($emailDeliveries?->total() ?? 0) }} matching {{ Str::plural('record', $emailDeliveries?->total() ?? 0) }}</p>
                </div>
            </div>

            @if($emailDeliveries)
                <div class="table-wrap system-log-table-wrap" tabindex="0" role="region" aria-label="Email delivery records">
                    <table class="telecom-table system-log-table" data-mobile-cards data-mobile-cards-expandable>
                        <thead><tr><th>Notification</th><th>Recipient</th><th>Status</th><th>Attempt</th><th>Sent / failed</th></tr></thead>
                        <tbody>
                            @forelse($emailDeliveries as $delivery)
                                <tr>
                                    <td data-mobile-primary><strong>{{ $delivery->notification_type }}</strong></td>
                                    <td data-mobile-secondary>{{ $delivery->recipient }}</td>
                                    <td><span class="status-badge status-{{ $delivery->status }}">{{ ucfirst($delivery->status) }}</span></td>
                                    <td>{{ $delivery->attempts }}<br><small>{{ str_replace('_', ' ', ucfirst($delivery->retry_status)) }}</small></td>
                                    <td data-mobile-secondary>
                                        <time>{{ optional($delivery->sent_at ?? $delivery->failed_at ?? $delivery->last_attempted_at)->timezone(config('app.timezone'))->format('M d, Y h:i A') }}</time>
                                        @if($delivery->error_message)<details><summary>Failure details</summary><small>{{ $delivery->error_message }}</small></details>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="system-log-empty"><h3>No matching email records</h3><p>New system emails will appear here after the migration is installed.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$emailDeliveries" />
            @else
                <div class="system-log-empty"><h3>Email logging is not installed yet</h3><p>Run the pending database migration to enable delivery visibility.</p></div>
            @endif
        </section>
    @endif
</div>
@endsection
