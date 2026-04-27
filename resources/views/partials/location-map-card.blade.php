@php
    $locationLat = is_numeric($lat ?? null) ? (float) $lat : null;
    $locationLng = is_numeric($lng ?? null) ? (float) $lng : null;
    $hasLocation = $locationLat !== null && $locationLng !== null;
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
            <a href="https://www.openstreetmap.org/?mlat={{ $locationLat }}&mlon={{ $locationLng }}#map=18/{{ $locationLat }}/{{ $locationLng }}" target="_blank" rel="noopener noreferrer" class="btn secondary location-map-link">Open in OpenStreetMap</a>
        @endif
    </div>

    @if($hasLocation)
        <div id="{{ $mapId }}" class="location-map-canvas js-readonly-location-map" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $address ?? 'Pinned service location' }}" data-empty-label="No pinned location available."></div>
        <div class="location-map-meta-grid">
            <div class="tracking-meta"><strong>Latitude</strong><span>{{ number_format($locationLat, 6) }}</span></div>
            <div class="tracking-meta"><strong>Longitude</strong><span>{{ number_format($locationLng, 6) }}</span></div>
            @if(!empty($address ?? null))
                <div class="tracking-meta full-width"><strong>Address</strong><span>{{ $address }}</span></div>
            @endif
        </div>
    @else
        <div class="empty-state compact">No pinned location available.</div>
    @endif
</section>
