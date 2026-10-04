@props(['title', 'description' => null])
<section {{ $attributes->class(['card', 'section-title-card', 'ui-page-heading']) }}>
    <div class="ui-page-heading__copy">
        <h1>{{ $title }}</h1>
        @if($description)<p>{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="ui-page-heading__actions">{{ $actions }}</div>@endisset
</section>
