@extends('layouts.app')

@section('title','Notifications')

@section('content')
<div class="telecom-page">
    <section class="telecom-hero">
        <h1>Notifications</h1>
        <p>Keep up with application updates, installation progress, ticket activity, and other important telecom operations events.</p>
        <div class="telecom-hero-actions">
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="btn secondary">Mark all as read</button>
            </form>
        </div>
    </section>

    <div class="panel-card notifications-page-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path>
                        <path d="M9 17a3 3 0 0 0 6 0"></path>
                    </svg>
                </span>
                <div>
                    <div class="panel-title">All Notifications</div>
                    <div class="panel-subtitle">Newest items appear first. Open any notification to go directly to the related page.</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($notifications->count())
                <div class="notifications-page-list" data-notifications-page-list>
                    @foreach($notifications as $notification)
                        <a href="{{ route('notifications.redirect', $notification) }}" class="notifications-page-item {{ $notification->read_at ? '' : 'is-unread' }}">
                            <div class="notifications-page-main">
                                <div class="notifications-page-title-row">
                                    <span class="notifications-page-title">{{ $notification->title }}</span>
                                    @if(!$notification->read_at)
                                        <span class="notifications-page-badge">Unread</span>
                                    @endif
                                </div>
                                @if($notification->message)
                                    <p class="notifications-page-message">{{ $notification->message }}</p>
                                @endif
                            </div>
                            <div class="notifications-page-time">{{ $notification->created_at->diffForHumans() }}</div>
                        </a>
                    @endforeach
                </div>

                <div class="notifications-page-pagination">
                    {{ $notifications->links() }}
                </div>
            @else
                <div class="empty-state">No notifications yet. Important activity will appear here automatically.</div>
            @endif
        </div>
    </div>
</div>
@endsection
