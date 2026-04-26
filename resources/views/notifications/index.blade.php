@extends('layouts.app')

@section('title','Notifications')

@section('content')
<div class="telecom-page notifications-compact-page">
    <section class="panel-card notifications-compact-card">
        <div class="notifications-compact-head">
            <div>
                <h1>Notifications</h1>
                <p>Open any item to go straight to the related page.</p>
            </div>
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="btn secondary">Mark all as read</button>
            </form>
        </div>

        <div class="notifications-compact-filters">
            <a href="{{ route('notifications.index') }}" class="notifications-filter-chip {{ $filter === 'all' ? 'is-active' : '' }}">All</a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="notifications-filter-chip {{ $filter === 'unread' ? 'is-active' : '' }}">Unread</a>
        </div>

        <div class="panel-body">
            @if($notifications->count())
                <div class="notifications-page-list notifications-compact-list" data-notifications-page-list>
                    @foreach($notifications as $notification)
                        <a href="{{ route('notifications.redirect', $notification) }}" class="notifications-page-item notifications-compact-item {{ $notification->read_at ? '' : 'is-unread' }}">
                            <div class="notifications-compact-dot-wrap">
                                <span class="notifications-compact-dot {{ $notification->read_at ? '' : 'is-unread' }}"></span>
                            </div>
                            <div class="notifications-page-main">
                                <div class="notifications-page-title-row">
                                    <span class="notifications-page-title">{{ $notification->title }}</span>
                                </div>
                                @if($notification->message)
                                    <p class="notifications-page-message">{{ $notification->message }}</p>
                                @endif
                                <div class="notifications-page-time">{{ $notification->created_at->displayRelative() }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="notifications-page-pagination">
                    {{ $notifications->links() }}
                </div>
            @else
                <div class="empty-state">No notifications yet.</div>
            @endif
        </div>
    </section>
</div>
@endsection
