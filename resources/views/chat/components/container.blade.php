<div class="chat-ui chat-ui--{{ $role }} {{ !empty($hasSidebar) ? 'chat-ui--has-sidebar' : '' }} {{ !empty($hasActiveConversation) ? 'chat-ui--has-conversation' : '' }}">
    <section class="chat-ui__shell" aria-label="Support chat workspace">
        <header class="chat-ui__header">
            <div class="chat-ui__header-copy">
                <p class="chat-ui__eyebrow">{{ $eyebrow }}</p>
                <h1>{{ $title }}</h1>
                @if(!empty($subtitle))
                    <p class="chat-ui__subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            @if(!empty($headerActions))
                <div class="chat-ui__header-actions">
                    {!! $headerActions !!}
                </div>
            @endif
        </header>

        {!! $body !!}
    </section>
</div>