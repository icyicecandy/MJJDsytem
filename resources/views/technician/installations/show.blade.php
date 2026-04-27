@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .workflow-shell {
            display: grid;
            gap: 1rem;
        }
        .workflow-header,
        .workflow-card {
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }
        .workflow-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            flex-wrap: wrap;
        }
        .workflow-header-copy {
            flex: 1 1 34rem;
        }
        .workflow-eyebrow,
        .workflow-label,
        .workflow-field label,
        .workflow-coordinate-label {
            font-size: 0.75rem;
            line-height: 1.2;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 700;
        }
        .workflow-ref {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #475569;
        }
        .workflow-header h1 {
            margin: 0.35rem 0 0.45rem;
        }
        .workflow-subtitle,
        .workflow-note,
        .workflow-card p,
        .workflow-empty p,
        .workflow-success p {
            margin: 0;
            color: #64748b;
        }
        .workflow-header-meta {
            display: grid;
            gap: 0.55rem;
            justify-items: end;
            min-width: 14rem;
        }
        .workflow-schedule-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .workflow-badge--scheduled {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .workflow-badge--in_progress {
            background: #fef3c7;
            color: #b45309;
        }
        .workflow-badge--completed {
            background: #dcfce7;
            color: #15803d;
        }
        .workflow-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr);
            gap: 1rem;
            align-items: start;
        }
        .workflow-column {
            display: grid;
            gap: 1rem;
        }
        .workflow-card {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .workflow-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .workflow-card-head h2,
        .workflow-card-head h3 {
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .workflow-overview-grid,
        .workflow-summary-grid,
        .workflow-form-grid,
        .workflow-review-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem 1rem;
        }
        .workflow-detail,
        .workflow-summary-box {
            display: grid;
            gap: 0.3rem;
        }
        .workflow-detail.full,
        .workflow-field.full,
        .workflow-summary-box.full {
            grid-column: 1 / -1;
        }
        .workflow-value {
            color: #0f172a;
            font-size: 0.98rem;
            line-height: 1.5;
            font-weight: 500;
            word-break: break-word;
        }
        .workflow-overview-grid .workflow-detail,
        .workflow-summary-grid .workflow-summary-box {
            padding: 0.95rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.9rem;
            background: #f8fafc;
        }
        .workflow-address-line {
            color: #0f172a;
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 500;
        }
        .workflow-address-meta {
            color: #6b7280;
            font-size: 0.88rem;
        }
        .workflow-action-card {
            border: 1px solid rgba(59, 130, 246, 0.16);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .workflow-action-panel,
        .workflow-success,
        .workflow-empty,
        .workflow-errors {
            display: grid;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 0.9rem;
        }
        .workflow-action-panel {
            border: 1px solid rgba(37, 99, 235, 0.16);
            background: #f8fbff;
        }
        .workflow-success {
            border: 1px solid rgba(34, 197, 94, 0.2);
            background: #f0fdf4;
        }
        .workflow-empty {
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .workflow-errors {
            border: 1px solid rgba(220, 38, 38, 0.18);
            background: rgba(254, 242, 242, 0.96);
            color: #991b1b;
        }
        .workflow-errors ul {
            margin: 0;
            padding-left: 1.1rem;
        }
        .workflow-primary-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .workflow-primary-action .btn {
            min-width: 200px;
            justify-content: center;
        }
        .workflow-field {
            display: grid;
            gap: 0.45rem;
        }
        .workflow-field input:not([type="file"]),
        .workflow-field textarea,
        .workflow-field input[type="file"] {
            width: 100%;
            padding: 0.85rem 0.9rem;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: #fff;
        }
        .workflow-field textarea {
            min-height: 128px;
        }
        .workflow-map-card {
            display: grid;
            gap: 1rem;
        }
        .workflow-map-canvas {
            height: 18rem;
            border-radius: 0.9rem;
            border: 1px solid #dbe2ea;
            overflow: hidden;
        }
        .workflow-map-canvas.is-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }
        .workflow-map-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .workflow-coordinates {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            font-size: 0.84rem;
            color: #64748b;
        }
        .workflow-proof img {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }
        @media (max-width: 980px) {
            .workflow-grid,
            .workflow-overview-grid,
            .workflow-summary-grid,
            .workflow-form-grid,
            .workflow-review-grid {
                grid-template-columns: 1fr;
            }
            .workflow-header-meta {
                justify-items: start;
            }
        }
    </style>
@endpush

<x-app-layout>
    @php
        $application = $installation->application;
        $customerName = $application ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')) : '-';
        $fullAddress = $application ? $application->fullServiceAddress() : '-';
        $cityProvince = $application
            ? collect([$application->municipality ?? null, $application->province ?? null])->filter()->implode(', ')
            : '';
        $proofPhotos = collect($installation->proofPhotos())->filter();
        $locationLat = $application && is_numeric($application->lat) ? (float) $application->lat : null;
        $locationLng = $application && is_numeric($application->lng) ? (float) $application->lng : null;
        $hasLocation = $locationLat !== null && $locationLng !== null;
        $osmUrl = $hasLocation
            ? 'https://www.openstreetmap.org/?mlat=' . $locationLat . '&mlon=' . $locationLng . '#map=18/' . $locationLat . '/' . $locationLng
            : null;
        $statusClass = match ($installation->status) {
            'scheduled' => 'workflow-badge--scheduled',
            'in_progress' => 'workflow-badge--in_progress',
            'completed' => 'workflow-badge--completed',
            default => 'workflow-badge--scheduled',
        };
    @endphp

    <div class="job-page workflow-shell">
        <section class="workflow-header">
            <div class="workflow-header-copy">
                <div class="workflow-eyebrow">{{ $installation->displayJobReference() }}</div>
                <h1>Installation Work Order</h1>
                <p class="workflow-subtitle">Use this work order to confirm the customer, navigate to the service point, and move the job through the installation workflow.</p>
            </div>
            <div class="workflow-header-meta">
                <span class="badge {{ $installation->status ?? 'info' }} {{ $statusClass }}">{{ $installation->displayStatusLabel() }}</span>
                <span class="workflow-ref">Application Ref: {{ $application->reference_no ?? 'N/A' }}</span>
                @if($installation->installation_date)
                    <span class="workflow-schedule-chip">{{ $installation->installation_date->format('M d, Y') }}</span>
                @endif
            </div>
        </section>

        @if($errors->any())
            <section class="workflow-errors">
                <strong>Installation workflow needs attention.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="workflow-grid">
            <div class="workflow-column">
                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <div>
                            <h2>Job Overview</h2>
                            <p>Keep the core work-order details visible while you prepare for the visit.</p>
                        </div>
                    </div>

                    <div class="workflow-overview-grid">
                        <div class="workflow-detail">
                            <span class="workflow-label">Customer Name</span>
                            <div class="workflow-value">{{ $customerName ?: '-' }}</div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Contact</span>
                            <div class="workflow-value">
                                {{ $application->email ?? 'No email provided' }}<br>
                                {{ $application->mobile_number ?? 'No mobile number provided' }}
                            </div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Scheduled Date</span>
                            <div class="workflow-value">{{ $installation->installation_date?->format('F d, Y') ?? '-' }}</div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Scheduled Time</span>
                            <div class="workflow-value">{{ $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A') : 'Time to be confirmed' }}</div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Current Status</span>
                            <div class="workflow-value">{{ $installation->displayStatusLabel() }}</div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Assigned At</span>
                            <div class="workflow-value">{{ $installation->assigned_at?->displayDateTime('F d, Y h:i A') ?? '-' }}</div>
                        </div>
                    </div>
                </section>

                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <div>
                            <h2>Service Address</h2>
                            <p>Use the confirmed service address as the main field reference.</p>
                        </div>
                    </div>

                    <div class="workflow-address-line">{{ $fullAddress ?: '-' }}</div>
                    @if($cityProvince !== '')
                        <div class="workflow-address-meta">{{ $cityProvince }}</div>
                    @endif
                </section>

                @if($installation->status === 'scheduled')
                    <section class="workflow-card workflow-action-card">
                        <div class="workflow-card-head">
                            <div>
                                <h2>Installation Workflow</h2>
                                <p>This job is scheduled and ready to begin once you are onsite.</p>
                            </div>
                        </div>

                        <div class="workflow-action-panel">
                            <div class="workflow-primary-action">
                                <span class="workflow-note">Starting the installation updates the work order status to in progress.</span>
                                <form method="POST" action="{{ route('technician.installations.status', $installation->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn">Start Installation</button>
                                </form>
                            </div>
                        </div>
                    </section>
                @elseif($installation->isInProgress())
                    <form method="POST" enctype="multipart/form-data" action="{{ route('technician.installations.complete', $installation->id) }}" id="installationWorkflowForm" class="workflow-column">
                        @csrf

                        <section class="workflow-card workflow-action-card">
                            <div class="workflow-card-head">
                                <div>
                                    <h2>Installation Workflow</h2>
                                    <p>Capture the completion details, then close the work order when the install is finished.</p>
                                </div>
                            </div>

                            <div class="workflow-form-grid">
                                <div class="workflow-field full">
                                    <label for="technician_remarks">Technician Remarks</label>
                                    <textarea id="technician_remarks" name="technician_remarks" rows="5" required>{{ old('technician_remarks', $installation->technician_remarks) }}</textarea>
                                </div>
                                <div class="workflow-field full">
                                    <label for="installation_photo">Installation Photo <span class="field-badge optional">Optional</span></label>
                                    <input id="installation_photo" type="file" name="installation_photo" accept=".jpg,.jpeg,.png">
                                    <p class="workflow-note">Attach one photo if you want a simple proof of completion on record.</p>
                                </div>
                            </div>

                            <div class="workflow-review-grid">
                                <div class="workflow-detail full">
                                    <span class="workflow-label">Technician Remarks Preview</span>
                                    <div class="workflow-value" data-review-field="technician_remarks">-</div>
                                </div>
                                <div class="workflow-detail full">
                                    <span class="workflow-label">Selected Photo</span>
                                    <div class="workflow-value" data-review-photo>No file selected.</div>
                                </div>
                            </div>

                            <div class="workflow-action-panel">
                                <div class="workflow-primary-action">
                                    <span class="workflow-note">Submitting this form marks the installation as completed and updates the customer application status to installed.</span>
                                    <button type="submit" class="btn">Mark as Completed</button>
                                </div>
                            </div>
                        </section>
                    </form>
                @else
                    <section class="workflow-card workflow-action-card">
                        <div class="workflow-card-head">
                            <div>
                                <h2>Installation Workflow</h2>
                                <p>The work order is complete. Actions are now locked.</p>
                            </div>
                        </div>

                        <div class="workflow-success">
                            <strong>Installation completed successfully.</strong>
                            <p>This job is now read-only. Review the final record and attached proof below.</p>
                        </div>
                    </section>

                    <section class="workflow-card">
                        <div class="workflow-card-head">
                            <div>
                                <h2>Installation Summary</h2>
                                <p>Saved completion details for technician and admin review.</p>
                            </div>
                        </div>

                        <div class="workflow-summary-grid">
                            @foreach($installation->recordDetails() as $label => $value)
                                <div class="workflow-summary-box">
                                    <span class="workflow-label">{{ $label }}</span>
                                    <div class="workflow-value">{{ $value ?: '-' }}</div>
                                </div>
                            @endforeach
                            <div class="workflow-summary-box full">
                                <span class="workflow-label">Technician Remarks</span>
                                <div class="workflow-value">{{ $installation->technician_remarks ?: '-' }}</div>
                            </div>
                        </div>
                    </section>
                @endif

                @if($installation->isCompleted())
                    <section class="workflow-card">
                        <div class="workflow-card-head">
                            <div>
                                <h2>Installation Photo</h2>
                                <p>Optional proof of completion attached to the work order.</p>
                            </div>
                        </div>

                        <div class="workflow-proof">
                            @forelse($proofPhotos as $label => $path)
                                <div>
                                    <strong>{{ $label }}</strong>
                                    <img src="{{ asset('storage/' . $path) }}" alt="{{ $label }}">
                                </div>
                            @empty
                                <div class="empty-state compact">No installation photo uploaded.</div>
                            @endforelse
                        </div>
                    </section>
                @endif
            </div>

            <div class="workflow-column">
                <section class="workflow-card workflow-map-card">
                    <div class="workflow-card-head">
                        <div>
                            <h2>Pinned Service Location</h2>
                            <p>Keep the map secondary and use it as a field navigation reference when needed.</p>
                        </div>
                    </div>

                    @if($hasLocation)
                        <div id="technicianApplicationMap" class="workflow-map-canvas js-readonly-location-map" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $fullAddress }}" data-empty-label="No pinned location available."></div>
                        <div class="workflow-map-actions">
                            <span class="workflow-note">Open the saved pin in OpenStreetMap for full navigation.</span>
                            <a href="{{ $osmUrl }}" target="_blank" rel="noopener noreferrer" class="btn secondary">Open in OpenStreetMap</a>
                        </div>
                        <div class="workflow-coordinates">
                            <span><span class="workflow-coordinate-label">Latitude</span> {{ number_format($locationLat, 6) }}</span>
                            <span><span class="workflow-coordinate-label">Longitude</span> {{ number_format($locationLng, 6) }}</span>
                        </div>
                    @else
                        <div class="workflow-empty">
                            <strong>No pinned service location</strong>
                            <p>No pinned location available.</p>
                        </div>
                    @endif
                </section>
            </div>
        </div>

        <section class="card">
            <a href="{{ route('technician.installations.index') }}" class="btn secondary">Back</a>
        </section>
    </div>
    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            function initReadonlyMaps() {
                if (typeof window.initReadonlyLocationMaps === 'function') {
                    window.initReadonlyLocationMaps();
                    return;
                }

                if (typeof window.L === 'undefined') {
                    return;
                }

                document.querySelectorAll('.js-readonly-location-map').forEach(function (element) {
                    const lat = parseFloat(element.dataset.lat);
                    const lng = parseFloat(element.dataset.lng);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) {
                        if (element._readonlyLeafletMap) {
                            element._readonlyLeafletMap.remove();
                            element._readonlyLeafletMap = null;
                        }
                        element.classList.add('is-empty');
                        element.textContent = element.dataset.emptyLabel || 'No pinned location available.';
                        return;
                    }

                    if (element._readonlyLeafletMap) {
                        if (element.offsetWidth && element.offsetHeight) {
                            setTimeout(function () {
                                element._readonlyLeafletMap.invalidateSize();
                            }, 120);
                        }
                        return;
                    }

                    if (!element.offsetWidth || !element.offsetHeight) {
                        return;
                    }

                    element.classList.remove('is-empty');
                    element.textContent = '';

                    const map = L.map(element, {
                        center: [lat, lng],
                        zoom: 16,
                        scrollWheelZoom: false,
                        dragging: true,
                        zoomControl: true,
                        attributionControl: true,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    const popupNode = document.createElement('div');
                    popupNode.textContent = element.dataset.popup || 'Pinned service location';

                    L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(popupNode)
                        .openPopup();
                    element._readonlyLeafletMap = map;

                    setTimeout(function () {
                        map.invalidateSize();
                    }, 120);
                });
            }

            function initInstallationWorkflow() {
                const remarksNode = document.querySelector('[data-review-field="technician_remarks"]');
                const photoNode = document.querySelector('[data-review-photo]');
                const remarksField = document.getElementById('technician_remarks');
                const photoField = document.getElementById('installation_photo');

                if (remarksNode && remarksField) {
                    const updateRemarks = function () {
                        remarksNode.textContent = (remarksField.value || '').trim() || '-';
                    };
                    remarksField.addEventListener('input', updateRemarks);
                    remarksField.addEventListener('change', updateRemarks);
                    updateRemarks();
                }

                if (photoNode && photoField) {
                    const updatePhoto = function () {
                        photoNode.textContent = photoField.files && photoField.files[0] ? photoField.files[0].name : 'No file selected.';
                    };
                    photoField.addEventListener('change', updatePhoto);
                    updatePhoto();
                }

                initReadonlyMaps();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initInstallationWorkflow);
            } else {
                initInstallationWorkflow();
            }
        })();
    </script>
    @endpush
</x-app-layout>
