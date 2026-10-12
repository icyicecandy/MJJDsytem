@props(['labels' => [], 'resetUrl'])

@php($activeLabels = collect($labels)->filter(fn ($label) => filled($label)))
@if($activeLabels->isNotEmpty())
    <div class="active-filter-summary" role="status">
        <strong>Active filters:</strong>
        <div>
            @foreach($activeLabels as $label)<span>{{ $label }}</span>@endforeach
        </div>
        <a href="{{ $resetUrl }}">Clear Filters</a>
    </div>
@endif
