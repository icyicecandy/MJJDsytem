@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/chat.css') }}?v={{ filemtime(public_path('css/chat.css')) }}">
    @endpush
@endonce

@php
    $hasSidebar = !empty($sidebarContent);
    $hasActiveConversation = $hasActiveConversation ?? false;
    $layoutMarkup = '<div class="chat-ui__layout' . ($hasSidebar ? '' : ' is-single') . '">';

    if ($hasSidebar) {
        $layoutMarkup .= $sidebarContent;
    }

    $layoutMarkup .= $panelContent;
    $layoutMarkup .= '</div>';
@endphp

@include('chat.components.container', [
    'role' => $role,
    'eyebrow' => $eyebrow,
    'title' => $title,
    'subtitle' => $subtitle ?? null,
    'headerActions' => $headerActions ?? null,
    'hasSidebar' => $hasSidebar,
    'hasActiveConversation' => $hasActiveConversation,
    'body' => new \Illuminate\Support\HtmlString($layoutMarkup),
])