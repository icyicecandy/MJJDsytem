@props(['type' => 'submit', 'loadingLabel' => 'Working...'])
<button type="{{ $type }}" {{ $attributes->class(['btn'])->merge(['data-loading-label' => $loadingLabel]) }}><span data-button-label>{{ $slot }}</span></button>
