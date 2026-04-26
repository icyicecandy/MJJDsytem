@php
    $compact = $compact ?? false;
@endphp

<div class="chat-ui__empty-state {{ $compact ? 'is-compact' : '' }}">
    @if(!empty($icon))
        <div class="chat-ui__empty-icon">{{ $icon }}</div>
    @endif
    <h3>{{ $title }}</h3>
    <p>{{ $message }}</p>
</div>
