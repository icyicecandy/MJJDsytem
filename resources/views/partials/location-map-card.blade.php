@php
    $hasLocation = filled($lat ?? null) && filled($lng ?? null);
@endphp

<section class="panel-card location-map-card {{ $containerClass ?? '' }}">
    <div class="location-map-header">
        <div>
            <h3>{{ $title ?? 'Pinned Service Location' }}</h3>
            @if(!empty($subtitle ?? null))
                <p>{{ $subtitle }}</p>
            @endif
        </div>
        @if($hasLocation)
            <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map=16/{{ $lat }}/{{ $lng }}" target="_blank" rel="noopener noreferrer" class="btn secondary location-map-link">Open in OpenStreetMap</a>
        @endif
    </div>

    @if($hasLocation)
        <div id="{{ $mapId }}" class="location-map-canvas js-readonly-location-map" data-lat="{{ $lat }}" data-lng="{{ $lng }}"></div>
        <div class="location-map-meta-grid">
            <div class="tracking-meta"><strong>Latitude</strong><span>{{ number_format((float) $lat, 6) }}</span></div>
            <div class="tracking-meta"><strong>Longitude</strong><span>{{ number_format((float) $lng, 6) }}</span></div>
            @if(!empty($address ?? null))
                <div class="tracking-meta full-width"><strong>Address</strong><span>{{ $address }}</span></div>
            @endif
        </div>
    @else
        <div class="empty-state compact">No saved location pin is available for this application yet.</div>
    @endif
</section>
