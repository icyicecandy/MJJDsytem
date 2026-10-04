@extends('layouts.app')

@section('title', 'Support Chat')

@section('content')
@php
    $viewer = auth()->user();
    $viewerRole = $viewer->role;
    $isAdmin = $viewer->isAdmin();
    $availabilityRoute = route('role.chat.availability', ['role' => $viewerRole]);
    $pageTitle = $isAdmin ? 'Support queue' : 'Assigned conversations';
    $pageSubtitle = $isAdmin
        ? 'Monitor customer conversations, route them to staff, and pick up threads when needed.'
        : 'Review the customer conversations currently assigned to you and respond from one workspace.';
    $headerActions = view('chat.components.availability-control', [
        'user' => $viewer,
        'route' => $availabilityRoute,
    ])->render();
    $sidebarContent = view('chat.components.conversation-list', [
        'viewerRole' => $viewerRole,
        'sidebarTitle' => $isAdmin ? 'Customer queue' : 'My conversations',
        'sidebarMeta' => $conversations->count() . ' active conversation' . ($conversations->count() === 1 ? '' : 's'),
        'conversations' => $conversations,
        'activeConversationId' => optional(request()->route('user'))->id,
        'filterAction' => route('role.chat.index', ['role' => $viewerRole]),
        'search' => $search,
        'scope' => $scope,
        'showFilters' => true,
        'showScopeFilter' => $isAdmin,
        'emptyTitle' => $isAdmin ? 'No queued conversations' : 'No assigned conversations',
        'emptyMessage' => $isAdmin ? 'Customer chats will appear here when someone starts a conversation.' : 'New customer chats assigned to you will appear here.',
    ])->render();
    $panelContent = '<section class="chat-ui__panel">' . view('chat.components.empty-state', [
        'icon' => 'Chat',
        'title' => 'Select a conversation',
        'message' => 'Choose a customer from the queue to open the message thread and customer details.',
    ])->render() . '</section>';
@endphp

@include('chat.layouts.chat-layout', [
    'role' => $viewerRole,
    'eyebrow' => $isAdmin ? 'Admin support' : 'Technician support',
    'title' => $pageTitle,
    'subtitle' => $pageSubtitle,
    'headerActions' => $headerActions,
    'sidebarContent' => $sidebarContent,
    'panelContent' => $panelContent,
    'hasActiveConversation' => false,
])
@endsection