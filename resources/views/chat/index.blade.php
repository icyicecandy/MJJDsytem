@extends('layouts.app')

@section('title', 'Support Chat')

@section('content')
@php
    $viewerRole = auth()->user()->role;
    $isAdmin = auth()->user()->isAdmin();
    $staffStatus = auth()->user()->chatAvailabilityLabel();
    $availabilityRoute = route('role.chat.availability', ['role' => auth()->user()->role]);
    $pageTitle = $isAdmin ? 'Support queue' : 'Assigned conversations';
    $pageSubtitle = $isAdmin
        ? 'Monitor customer conversations, route them to staff, and pick up threads when needed.'
        : 'Review the customer conversations currently assigned to you and respond from one workspace.';
    $headerActions = view('chat.components.empty-state', [
        'icon' => auth()->user()->chat_available ? 'On' : 'Off',
        'title' => auth()->user()->chat_available ? 'Available' : 'Away',
        'message' => 'Chat availability can be changed from this page.',
        'compact' => true,
    ])->render();
    $sidebarContent = view('chat.components.conversation-list', [
        'viewerRole' => $viewerRole,
        'sidebarTitle' => $isAdmin ? 'Customer queue' : 'My chats',
        'sidebarMeta' => $conversations->count() . ' active conversation' . ($conversations->count() === 1 ? '' : 's'),
        'conversations' => $conversations,
        'activeConversationId' => optional(request()->route('user'))->id,
        'filterAction' => route('role.chat.index', ['role' => auth()->user()->role]),
        'search' => $search,
        'scope' => $scope,
        'showFilters' => true,
        'showScopeFilter' => $isAdmin,
        'emptyTitle' => $isAdmin ? 'No queued conversations' : 'No assigned conversations',
        'emptyMessage' => $isAdmin ? 'Customer chats will appear here when someone starts a conversation.' : 'New customer chats assigned to you will appear here.',
    ])->render();
    $panelContent = '<section class="chat-ui__panel">' . view('chat.components.empty-state', [
        'icon' => '.',
        'title' => 'Select a conversation',
        'message' => 'Choose a customer from the list to open the chat thread.',
    ])->render() . '</section>';
@endphp

@include('chat.layouts.chat-layout', [
    'role' => $viewerRole,
    'eyebrow' => $isAdmin ? 'Admin' : 'Technician',
    'title' => $pageTitle,
    'subtitle' => $pageSubtitle,
    'headerActions' => '<form method="POST" action="' . $availabilityRoute . '">'
        . '<span class="chat-ui__button chat-ui__button--secondary" style="pointer-events:none;">' . e($staffStatus) . '</span>'
        . csrf_field()
        . '<input type="hidden" name="chat_available" value="' . (auth()->user()->chat_available ? '0' : '1') . '">'
        . '<button type="submit" class="chat-ui__button ' . (auth()->user()->chat_available ? 'chat-ui__button--secondary' : 'chat-ui__button--primary') . '">'
        . (auth()->user()->chat_available ? 'Set away' : 'Set available')
        . '</button></form>',
    'sidebarContent' => $sidebarContent,
    'panelContent' => $panelContent,
])
@endsection
