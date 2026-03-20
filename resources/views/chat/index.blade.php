@extends('layouts.app')

@section('title','Support Chat')

@section('content')
@php
    $isRoleChat = auth()->user()->role !== 'customer';
    $availabilityRoute = $isRoleChat ? route('role.chat.availability', ['role' => auth()->user()->role]) : route('chat.availability');
    $isAdmin = auth()->user()->isAdmin();
@endphp

<div class="support-chat-page support-chat-page-staff">
    <section class="card support-chat-header-card support-chat-hero-card">
        <div class="support-chat-hero-copy">
            <span class="application-status-kicker">Support Workspace</span>
            <h1>{{ $isAdmin ? 'Admin Live Chat' : 'Technician Live Chat' }}</h1>
            <p>{{ $isAdmin ? 'Supervise customer conversations, review AI handoff status, and rebalance support work without leaving the operations dashboard.' : 'Review your assigned customer threads, respond quickly, and keep support moving from one focused workspace.' }}</p>
            <div class="support-hero-stat-row">
                <span class="support-hero-stat">
                    <strong>{{ $conversations->count() }}</strong>
                    <span>{{ $isAdmin ? 'Open customer threads' : 'Assigned chats' }}</span>
                </span>
                <span class="support-hero-stat">
                    <strong>{{ auth()->user()->chat_available ? 'Online' : 'Offline' }}</strong>
                    <span>Availability status</span>
                </span>
            </div>
        </div>
        <form method="POST" action="{{ $availabilityRoute }}" class="support-availability-form">
            @csrf
            <input type="hidden" name="chat_available" value="{{ auth()->user()->chat_available ? 0 : 1 }}">
            <button type="submit" class="btn {{ auth()->user()->chat_available ? 'secondary' : '' }}">
                {{ auth()->user()->chat_available ? 'Set Unavailable' : 'Set Available' }}
            </button>
        </form>
    </section>

    <div class="support-chat-layout support-chat-layout-staff">
        <aside class="support-conversation-sidebar card">
            <div class="support-sidebar-head">
                <div>
                    <h3>{{ $isAdmin ? 'All Customer Conversations' : 'Assigned Conversations' }}</h3>
                    <p>{{ $isAdmin ? 'Pick any customer thread that needs review, escalation, or reassignment.' : 'Only conversations assigned to you appear here.' }}</p>
                </div>
                <span class="badge info">{{ $conversations->count() }}</span>
            </div>

            <div class="support-conversation-list">
                @forelse($conversations as $conversation)
                    @php
                        $customer = $conversation['customer'];
                        $latest = $conversation['latest_message'];
                    @endphp
                    <a href="{{ route('role.chat.conversation', ['role' => auth()->user()->role, 'user' => $customer]) }}" class="support-conversation-item {{ request()->route('user') && request()->route('user')->id === $customer->id ? 'active' : '' }}">
                        <div class="support-conversation-top">
                            <strong>{{ $customer->name }}</strong>
                            <span class="badge {{ $conversation['status_class'] }}">{{ $conversation['status_label'] }}</span>
                        </div>
                        <div class="support-conversation-meta">
                            <span>{{ $conversation['assigned_staff']?->name ? 'Connected to: ' . $conversation['assigned_staff']->name : 'Waiting for human support' }}</span>
                            @if($conversation['is_mine'])
                                <span class="badge success">Mine</span>
                            @endif
                        </div>
                        <p>{{ \Illuminate\Support\Str::limit($latest->message, 96) }}</p>
                    </a>
                @empty
                    <div class="support-sidebar-empty">
                        <div class="support-sidebar-empty-icon">...</div>
                        <strong>{{ $isAdmin ? 'No customer conversations yet' : 'No assigned conversations yet' }}</strong>
                        <p>{{ $isAdmin ? 'Customer threads will appear here once people start chatting with support.' : 'When a customer thread is assigned to you, it will appear in this queue.' }}</p>
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="support-chat-main card support-chat-main-placeholder">
            <div class="support-chat-empty-state support-chat-empty-state-rich">
                <div class="support-empty-illustration">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <h3>Select a conversation</h3>
                <p>{{ $isAdmin ? 'Choose a customer thread to inspect AI handling, claim support, or reassign the conversation.' : 'Choose one of your assigned customer threads to continue support and keep the conversation moving.' }}</p>
                <div class="support-empty-tips">
                    <span>Open a thread from the left column</span>
                    <span>Review the latest message and status</span>
                    <span>Respond or update assignment from the chat panel</span>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

