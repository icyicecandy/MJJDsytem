@props(['label', 'value', 'description' => null, 'tone' => 'default'])
<article {{ $attributes->class(['metric-card', 'ui-metric-card', 'ui-metric-card--'.$tone]) }}>
    <div class="metric-head">
        <div class="ui-metric-card__copy">
            <div class="metric-label">{{ $label }}</div>
            <div class="metric-value">{{ $value }}</div>
            @if($description)<div class="metric-meta">{{ $description }}</div>@endif
        </div>
        @isset($icon)<div class="metric-icon">{{ $icon }}</div>@endisset
    </div>
</article>
