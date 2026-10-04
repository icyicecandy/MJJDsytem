@props(['title' => 'Nothing to show', 'description' => null, 'compact' => false])
<div {{ $attributes->class(['empty-state', 'ui-empty-state', 'compact' => $compact]) }} role="status">
    <strong>{{ $title }}</strong>
    @if($description)<span>{{ $description }}</span>@endif
    @isset($action)<div class="ui-empty-state__action">{{ $action }}</div>@endisset
</div>
