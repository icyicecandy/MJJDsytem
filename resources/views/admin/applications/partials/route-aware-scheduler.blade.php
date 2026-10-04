<section class="admin-route-planner" data-route-scheduler aria-labelledby="routePlannerTitle">
    <div class="admin-route-planner__header">
        <div>
            <span class="admin-route-planner__eyebrow">Technician recommendations</span>
            <h4 id="routePlannerTitle">Choose the best available technician</h4>
            <p>Recommendations use the selected schedule, technician workload, and nearby installation locations.</p>
        </div>
        <span class="admin-route-planner__count" data-route-count>Select a schedule</span>
    </div>

    <div class="admin-route-planner__location">
        <span class="admin-route-planner__location-icon" aria-hidden="true">◎</span>
        <div>
            <strong>New installation location</strong>
            <span>{{ collect([$application->barangay, $application->municipality])->filter()->implode(', ') ?: 'Location not provided' }}</span>
        </div>
        <span class="admin-route-planner__estimate">Straight-line estimates</span>
    </div>

    <div class="admin-route-planner__state" data-route-state role="status" aria-live="polite">
        Select an installation date and time to compare technician routes.
    </div>
    @error('technician_id')
        <span class="error">{{ $message }}</span>
    @enderror

    <div class="admin-route-planner__available" data-route-available hidden>
        <div class="admin-route-planner__section-heading">
            <strong>Available technicians</strong>
            <span>Choose a card to assign the technician.</span>
        </div>
        <div class="admin-route-planner__cards" data-route-cards></div>
    </div>

    <details class="admin-route-planner__unavailable" data-route-unavailable hidden>
        <summary>
            <span>Unavailable technicians</span>
            <span data-route-unavailable-count>0</span>
        </summary>
        <div class="admin-route-planner__unavailable-list" data-route-unavailable-list></div>
    </details>

    <div class="admin-route-planner__selection" data-route-selection hidden>
        <div>
            <span>Selected technician</span>
            <strong data-route-selection-name>None selected</strong>
            <small data-route-selection-meta></small>
        </div>
        <span class="admin-route-planner__selection-check" aria-hidden="true">✓</span>
    </div>
</section>
