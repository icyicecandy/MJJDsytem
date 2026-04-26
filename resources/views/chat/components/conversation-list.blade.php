@php
    use Illuminate\Support\Str;

    $viewerRole = $viewerRole ?? auth()->user()->role;
    $activeConversationId = $activeConversationId ?? null;
    $search = $search ?? '';
    $scope = $scope ?? '';
    $showFilters = $showFilters ?? true;
    $showScopeFilter = $showScopeFilter ?? false;
    $sidebarTitle = $sidebarTitle ?? 'Conversations';
    $sidebarMeta = $sidebarMeta ?? ($conversations->count() . ' active');
    $emptyTitle = $emptyTitle ?? 'No conversations';
    $emptyMessage = $emptyMessage ?? 'New chat threads will appear here.';

    $badgeTone = function (?string $statusLabel): string {
        $status = Str::lower((string) $statusLabel);

        return match (true) {
            Str::contains($status, 'attention') => 'chat-ui__badge--red',
            Str::contains($status, 'waiting') => 'chat-ui__badge--gray',
            Str::contains($status, 'assigned'),
            Str::contains($status, 'connected') => 'chat-ui__badge--blue',
            Str::contains($status, 'progress'),
            Str::contains($status, 'active') => 'chat-ui__badge--yellow',
            Str::contains($status, 'resolved'),
            Str::contains($status, 'closed') => 'chat-ui__badge--green',
            Str::contains($status, 'urgent') => 'chat-ui__badge--red',
            default => 'chat-ui__badge--gray',
        };
    };
@endphp

<details class="chat-ui__sidebar-shell" open>
    <summary class="chat-ui__sidebar-toggle">
        <span>{{ $sidebarTitle }}</span>
        <span>{{ $conversations->count() }}</span>
    </summary>

    <aside class="chat-ui__sidebar">
        <div class="chat-ui__sidebar-head">
            <div>
                <h2>{{ $sidebarTitle }}</h2>
                <p>{{ $sidebarMeta }}</p>
            </div>
        </div>

        @if($showFilters)
            <form method="GET" action="{{ $filterAction }}" class="chat-ui__filters is-compact {{ $showScopeFilter ? 'has-scope' : '' }}">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search conversations">

                @if($showScopeFilter)
                    <select name="scope">
                        <option value="" {{ $scope === '' ? 'selected' : '' }}>All</option>
                        <option value="waiting" {{ $scope === 'waiting' ? 'selected' : '' }}>Waiting</option>
                        <option value="mine" {{ $scope === 'mine' ? 'selected' : '' }}>Mine</option>
                        <option value="human" {{ $scope === 'human' ? 'selected' : '' }}>In progress</option>
                        <option value="closed" {{ $scope === 'closed' ? 'selected' : '' }}>Resolved</option>
                    </select>
                @endif

                <div class="chat-ui__filter-actions">
                    <button type="submit" class="chat-ui__button chat-ui__button--secondary">Filter</button>
                    @if($search !== '' || $scope !== '')
                        <a href="{{ $filterAction }}" class="chat-ui__button chat-ui__button--secondary">Reset</a>
                    @endif
                </div>
            </form>
        @endif

        <div class="chat-ui__conversation-list" id="chat-conversation-list">
            @forelse($conversations as $conversation)
                @php
                    $customer = $conversation['customer'];
                    $latest = $conversation['latest_message'];
                    $statusLabel = $conversation['status_label'] ?? 'Waiting';
                    $statusTone = $badgeTone($statusLabel);
                    $itemHref = route('role.chat.conversation', [
                        'role' => auth()->user()->role,
                        'user' => $customer,
                        'search' => $search,
                        'scope' => $scope,
                    ]);
                    $secondaryMeta = $viewerRole === 'admin'
                        ? ($conversation['assigned_staff']?->name ? 'Assigned to ' . $conversation['assigned_staff']->name : 'Unassigned')
                        : ($conversation['last_activity'] ?? 'Recent activity');
                    $trailingMeta = $conversation['queue_age'] ?? ($conversation['application_reference'] ?? 'Recent');
                @endphp

                <a href="{{ $itemHref }}" class="chat-ui__conversation-item {{ $activeConversationId === $customer->id ? 'is-active' : '' }}" data-conversation-item data-customer-id="{{ $customer->id }}">
                    <div class="chat-ui__conversation-top">
                        <strong>{{ $customer->name }}</strong>
                        <span class="chat-ui__badge {{ $statusTone }}" data-conversation-status>{{ $statusLabel }}</span>
                    </div>

                    <p class="chat-ui__conversation-preview" data-conversation-preview>{{ Str::limit($latest->message, 78) }}</p>

                    <div class="chat-ui__conversation-meta">
                        <span data-conversation-meta-primary>{{ $secondaryMeta }}</span>
                        <span data-conversation-meta-secondary>{{ $trailingMeta }}</span>
                    </div>

                    @if($viewerRole === 'admin' && ($conversation['application_reference'] || $conversation['open_ticket_count']))
                        <div class="chat-ui__conversation-submeta" data-conversation-submeta>
                            @if($conversation['application_reference'])
                                <span>{{ $conversation['application_reference'] }}</span>
                            @endif
                            @if($conversation['open_ticket_count'])
                                <span>{{ $conversation['open_ticket_count'] }} open ticket{{ $conversation['open_ticket_count'] === 1 ? '' : 's' }}</span>
                            @endif
                        </div>
                    @endif
                </a>
            @empty
                @include('chat.components.empty-state', [
                    'icon' => '.',
                    'title' => $emptyTitle,
                    'message' => $emptyMessage,
                    'compact' => true,
                ])
            @endforelse
        </div>
    </aside>
</details>
