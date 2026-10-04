<form method="POST" action="{{ $route }}" class="chat-ui__presence-form">
    @csrf
    <input type="hidden" name="chat_available" value="{{ $user->chat_available ? '0' : '1' }}">
    <button
        type="submit"
        class="chat-ui__presence-toggle {{ $user->chat_available ? 'is-available' : 'is-away' }}"
        aria-label="{{ $user->chat_available ? 'Available for live chat. Select to set away.' : 'Away from live chat. Select to become available.' }}"
        title="{{ $user->chat_available ? 'Select to set away' : 'Select to become available' }}"
    >
        <span class="chat-ui__presence-dot" aria-hidden="true"></span>
        <span class="chat-ui__presence-copy">
            <strong>{{ $user->chatAvailabilityLabel() }}</strong>
            <small>{{ $user->chat_available ? 'Select to set away' : 'Select to become available' }}</small>
        </span>
        <span class="chat-ui__presence-chevron" aria-hidden="true">›</span>
    </button>
</form>