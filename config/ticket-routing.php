<?php

return [
    'max_active_tickets_per_technician' => (int) env('TICKET_MAX_ACTIVE_PER_TECHNICIAN', 5),
    'unavailable_requeue_minutes' => (int) env('TICKET_UNAVAILABLE_REQUEUE_MINUTES', 30),
    'response_hours' => [
        'critical' => 1,
        'high' => 4,
        'medium' => 8,
        'low' => 24,
    ],
];
