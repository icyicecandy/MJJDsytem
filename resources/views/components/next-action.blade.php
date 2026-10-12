@props(['message', 'tone' => 'info', 'title' => 'Next action'])

<aside {{ $attributes->class(['next-action-card', 'next-action-card--'.$tone]) }} role="note" aria-label="{{ $title }}">
    <span class="next-action-card__marker" aria-hidden="true"></span>
    <div>
        <strong>{{ $title }}</strong>
        <p>{{ $message }}</p>
    </div>
</aside>
