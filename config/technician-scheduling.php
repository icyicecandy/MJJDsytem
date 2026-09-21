<?php

return [
    // Straight-line limit between consecutive jobs. This avoids assignments that
    // cannot reasonably fit within the three-hour service windows without an API.
    'max_consecutive_route_distance_km' => (float) env('TECHNICIAN_MAX_ROUTE_KM', 30),
];
