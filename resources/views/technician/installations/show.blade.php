@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .workflow-shell {
            display: grid;
            gap: 1rem;
        }
        .workflow-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .workflow-hero > div:first-child {
            flex: 1 1 34rem;
        }
        .workflow-hero-meta {
            display: grid;
            gap: 0.65rem;
            justify-items: end;
            min-width: 13rem;
        }
        .workflow-ref {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #475569;
        }
        .workflow-panel .job-card-header {
            padding-bottom: 0.95rem;
        }
        .workflow-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(20rem, 0.85fr);
            gap: 1rem;
            align-items: start;
        }
        .workflow-overview-grid,
        .workflow-form-grid,
        .workflow-upload-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .workflow-overview-grid .full,
        .workflow-form-grid .full,
        .workflow-upload-grid .full {
            grid-column: 1 / -1;
        }
        .workflow-summary-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        }
        .workflow-review-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .workflow-review-box {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 14px;
            padding: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .workflow-review-box strong {
            display: block;
            margin-bottom: 0.55rem;
        }
        .workflow-checklist {
            display: grid;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .workflow-checklist li {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 14px;
            padding: 0.85rem 0.95rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .workflow-nav-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1.25rem;
            flex-wrap: wrap;
        }
        .workflow-section-note {
            color: #64748b;
            font-size: 0.88rem;
            max-width: 36rem;
        }
        .workflow-errors {
            border: 1px solid rgba(220, 38, 38, 0.18);
            background: rgba(254, 242, 242, 0.96);
            border-radius: 16px;
            padding: 0.9rem 1rem;
            color: #991b1b;
        }
        .workflow-errors ul {
            margin: 0.5rem 0 0;
            padding-left: 1.1rem;
        }
        .workflow-form-grid .upload-field,
        .workflow-upload-grid .upload-field {
            height: 100%;
        }
        .workflow-form-grid .upload-field input:not([type="file"]),
        .workflow-form-grid .upload-field select,
        .workflow-form-grid .upload-field textarea,
        .workflow-upload-grid .upload-field input[type="file"] {
            width: 100%;
            padding: 0.85rem 0.9rem;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: #fff;
        }
        .workflow-upload-grid .upload-field input[type="file"] {
            min-height: 3.35rem;
        }
        .workflow-grid .location-map-card {
            height: 100%;
            align-content: start;
            border-radius: 12px;
        }
        .workflow-grid .location-map-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 0.8rem;
        }
        .workflow-grid .location-map-link {
            align-self: start;
        }
        .workflow-grid .location-map-canvas {
            min-height: 14.5rem;
            height: 14.5rem;
            border-radius: 12px;
        }
        .workflow-grid .location-map-meta-grid {
            margin-top: 0.15rem;
        }
        @media (max-width: 980px) {
            .workflow-grid,
            .workflow-overview-grid,
            .workflow-form-grid,
            .workflow-upload-grid {
                grid-template-columns: 1fr;
            }
            .workflow-hero-meta {
                justify-items: start;
            }
        }
        @media (max-width: 640px) {
            .workflow-nav-actions > .btn,
            .workflow-nav-actions > span {
                width: 100%;
            }
        }
    </style>
@endpush

<x-app-layout>
    @php
        $application = $installation->application;
        $customerName = $application
            ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? ''))
            : '-';
        $contact = $application
            ? trim(($application->email ?? '-') . ' / ' . ($application->mobile_number ?? '-'))
            : '-';
        $address = $application ? $application->fullServiceAddress() : '-';
        $proofPhotos = collect($installation->proofPhotos())->filter();
    @endphp

    <div class="job-page workflow-shell">
        <section class="card job-header workflow-hero">
            <div>
                <div class="workflow-ref">{{ $installation->displayJobReference() }}</div>
                <h1>{{ $installation->isCompleted() ? 'Completed Installation Record' : 'Installation Work Order' }}</h1>
                <p>Review the job, record activation details, upload proof photos, and complete the installation from one page.</p>
            </div>
            <div class="workflow-hero-meta">
                <span class="badge {{ $installation->status ?? 'info' }}">{{ $installation->displayStatusLabel() }}</span>
                <span class="workflow-ref">Application Ref: {{ $application->reference_no ?? 'N/A' }}</span>
                @if($installation->installation_date)
                    <span class="badge info">{{ $installation->installation_date->format('M d, Y') }}</span>
                @endif
            </div>
        </section>

        @if(! $installation->isCompleted())
            @if($errors->any())
                <section class="workflow-errors">
                    <strong>Completion form needs attention.</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="workflow-panel card">
                <div class="job-card-header">
                    <div>
                        <h2>Job Overview</h2>
                        <p>Review the job context before recording the final field completion details.</p>
                    </div>
                </div>
                <div class="job-card-body workflow-grid">
                    <div class="workflow-overview-grid">
                        <div class="job-detail">
                            <span class="job-detail-label">Job Reference</span>
                            <div class="job-detail-value">{{ $installation->displayJobReference() }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Application Reference</span>
                            <div class="job-detail-value">{{ $application->reference_no ?? 'N/A' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Customer Name</span>
                            <div class="job-detail-value">{{ $customerName ?: '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Contact</span>
                            <div class="job-detail-value">{{ $contact }}</div>
                        </div>
                        <div class="job-detail full">
                            <span class="job-detail-label">Full Service Address</span>
                            <div class="job-detail-value">{{ $address ?: '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Region</span>
                            <div class="job-detail-value">{{ $application->region ?: '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Province</span>
                            <div class="job-detail-value">{{ $application->province ?: '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Municipality / City</span>
                            <div class="job-detail-value">{{ $application->municipality ?: '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Barangay / Zone</span>
                            <div class="job-detail-value">{{ $application->barangay ?: '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Package</span>
                            <div class="job-detail-value">{{ $installation->package_name ?? $application->plan_code ?? 'N/A' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Scheduled Date</span>
                            <div class="job-detail-value">{{ $installation->installation_date?->format('F d, Y') ?? '-' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Scheduled Time</span>
                            <div class="job-detail-value">{{ $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A') : 'Time to be confirmed' }}</div>
                        </div>
                        <div class="job-detail">
                            <span class="job-detail-label">Current Status</span>
                            <div class="job-detail-value">{{ $installation->displayStatusLabel() }}</div>
                        </div>
                    </div>

                    @include('partials.location-map-card', [
                        'mapId' => 'technicianApplicationMap',
                        'lat' => $application->lat ?? null,
                        'lng' => $application->lng ?? null,
                        'title' => 'Pinned Service Location',
                        'subtitle' => 'Use the saved service point as your field navigation reference.',
                        'address' => $address,
                        'containerClass' => 'job-card',
                    ])
                </div>
            </section>

            <form method="POST" enctype="multipart/form-data" action="{{ route('technician.installations.complete', $installation->id) }}" id="installationWorkflowForm">
                @csrf

                <section class="workflow-panel card">
                    <div class="job-card-header">
                        <div>
                            <h2>Field Activation Details</h2>
                            <p>Capture the NAP, JO, activation, and remarks data from the field record.</p>
                        </div>
                    </div>
                    <div class="job-card-body">
                        <div class="workflow-form-grid">
                            <div class="upload-field">
                                <label for="nap_code">NAP Code</label>
                                <input id="nap_code" type="text" name="nap_code" value="{{ old('nap_code', $installation->nap_code) }}" required>
                            </div>
                            <div class="upload-field">
                                <label for="lp_code">LP Code</label>
                                <input id="lp_code" type="text" name="lp_code" value="{{ old('lp_code', $installation->lp_code) }}">
                            </div>
                            <div class="upload-field">
                                <label for="np_code">NP Code</label>
                                <input id="np_code" type="text" name="np_code" value="{{ old('np_code', $installation->np_code) }}">
                            </div>
                            <div class="upload-field">
                                <label for="full_nap_label">Full NAP Label</label>
                                <input id="full_nap_label" type="text" name="full_nap_label" value="{{ old('full_nap_label', $installation->full_nap_label) }}" required>
                            </div>
                            <div class="upload-field">
                                <label for="port_number">Port Number</label>
                                <input id="port_number" type="text" name="port_number" value="{{ old('port_number', $installation->port_number) }}" required>
                            </div>
                            <div class="upload-field">
                                <label for="jo_number">JO Number</label>
                                <input id="jo_number" type="text" name="jo_number" value="{{ old('jo_number', $installation->jo_number) }}" required>
                            </div>
                            <div class="upload-field">
                                <label for="activation_date">Activation Date</label>
                                <input id="activation_date" type="date" name="activation_date" value="{{ old('activation_date', optional($installation->activation_date)->format('Y-m-d')) }}" required>
                            </div>
                            <div class="upload-field">
                                <label for="activation_status">Activation Status</label>
                                <select id="activation_status" name="activation_status" required>
                                    <option value="">Select status</option>
                                    @foreach(['Activated', 'Activated with Observation', 'For Follow-up'] as $statusOption)
                                        <option value="{{ $statusOption }}" {{ old('activation_status', $installation->activation_status) === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="upload-field">
                                <label for="package_name">Package Confirmation</label>
                                <input id="package_name" type="text" name="package_name" value="{{ old('package_name', $installation->package_name ?? $application->plan_code) }}" required>
                            </div>
                            <div class="upload-field">
                                <label for="router_serial">Router Serial</label>
                                <input id="router_serial" type="text" name="router_serial" value="{{ old('router_serial', $installation->router_serial) }}">
                            </div>
                            <div class="upload-field">
                                <label for="cable_reel_no">Cable Reel No.</label>
                                <input id="cable_reel_no" type="text" name="cable_reel_no" value="{{ old('cable_reel_no', $installation->cable_reel_no) }}">
                            </div>
                            <div class="upload-field">
                                <label for="speed_test_result">Speed Test Result</label>
                                <input id="speed_test_result" type="text" name="speed_test_result" value="{{ old('speed_test_result', $installation->speed_test_result) }}" placeholder="Example: 92 Mbps down / 94 Mbps up">
                            </div>
                            <div class="upload-field full">
                                <label for="technician_remarks">Technician Remarks</label>
                                <textarea id="technician_remarks" name="technician_remarks" rows="4" required>{{ old('technician_remarks', $installation->technician_remarks) }}</textarea>
                            </div>
                            <div class="upload-field full">
                                <label for="material_notes">Material Notes</label>
                                <textarea id="material_notes" name="material_notes" rows="3">{{ old('material_notes', $installation->material_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="workflow-panel card">
                    <div class="job-card-header">
                        <div>
                            <h2>Proof Uploads</h2>
                            <p>Upload the required proof set. Optional field photos can also be attached here.</p>
                        </div>
                    </div>
                    <div class="job-card-body">
                        <div class="workflow-upload-grid">
                            <div class="upload-field">
                                <label for="router_photo">Router Photo</label>
                                <input id="router_photo" type="file" name="router_photo" accept=".jpg,.jpeg,.png" required>
                            </div>
                            <div class="upload-field">
                                <label for="speed_test_photo">Speed Test Photo</label>
                                <input id="speed_test_photo" type="file" name="speed_test_photo" accept=".jpg,.jpeg,.png" required>
                            </div>
                            <div class="upload-field">
                                <label for="nap_box_photo">NAP Box Photo</label>
                                <input id="nap_box_photo" type="file" name="nap_box_photo" accept=".jpg,.jpeg,.png" required>
                            </div>
                            <div class="upload-field">
                                <label for="acceptance_report_photo">Acceptance Report Photo</label>
                                <input id="acceptance_report_photo" type="file" name="acceptance_report_photo" accept=".jpg,.jpeg,.png" required>
                            </div>
                            <div class="upload-field">
                                <label for="cable_reel_photo">Cable Reel Photo</label>
                                <input id="cable_reel_photo" type="file" name="cable_reel_photo" accept=".jpg,.jpeg,.png">
                            </div>
                            <div class="upload-field">
                                <label for="installation_site_photo">Installation Site Photo</label>
                                <input id="installation_site_photo" type="file" name="installation_site_photo" accept=".jpg,.jpeg,.png">
                            </div>
                            <div class="upload-field">
                                <label for="customer_signature_photo">Customer Signature Photo</label>
                                <input id="customer_signature_photo" type="file" name="customer_signature_photo" accept=".jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="workflow-panel card">
                    <div class="job-card-header">
                        <div>
                            <h2>Review & Complete</h2>
                            <p>Confirm the summary and checklist before submitting the completed installation record.</p>
                        </div>
                    </div>
                    <div class="job-card-body">
                        <div class="workflow-review-grid">
                            <div class="workflow-review-box">
                                <strong>Activation Summary</strong>
                                <div class="record-muted" data-review-field="nap_code">NAP Code: -</div>
                                <div class="record-muted" data-review-field="port_number">Port Number: -</div>
                                <div class="record-muted" data-review-field="jo_number">JO Number: -</div>
                                <div class="record-muted" data-review-field="activation_date">Activation Date: -</div>
                                <div class="record-muted" data-review-field="activation_status">Activation Status: -</div>
                                <div class="record-muted" data-review-field="package_name">Package: -</div>
                            </div>
                            <div class="workflow-review-box">
                                <strong>Optional Notes</strong>
                                <div class="record-muted" data-review-field="router_serial">Router Serial: -</div>
                                <div class="record-muted" data-review-field="cable_reel_no">Cable Reel No.: -</div>
                                <div class="record-muted" data-review-field="speed_test_result">Speed Test Result: -</div>
                            </div>
                        </div>

                        <ul class="workflow-checklist">
                            <li>Required structured fields must be complete: NAP code, full NAP label, port number, JO number, activation date, activation status, package confirmation, and technician remarks.</li>
                            <li>Required proofs must be attached: router photo, speed test photo, NAP box photo, and acceptance report photo.</li>
                            <li>Final submit uses the same completion logic and will be rejected server-side if any required item is missing.</li>
                        </ul>
                    </div>
                    <div class="workflow-nav-actions">
                        <span class="workflow-section-note">Once submitted, the installation will be marked completed and the customer and admins will receive the usual status updates.</span>
                        <button type="submit" class="btn">Complete Installation</button>
                    </div>
                </section>
            </form>
        @else
            <section class="card">
                <div class="job-card-header">
                    <div>
                        <h2>Completed Job Overview</h2>
                        <p>The installation is already marked completed. Review the final field record below.</p>
                    </div>
                </div>
                <div class="job-card-body workflow-summary-grid">
                    <div class="job-detail">
                        <span class="job-detail-label">Job Reference</span>
                        <div class="job-detail-value">{{ $installation->displayJobReference() }}</div>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Application Reference</span>
                        <div class="job-detail-value">{{ $application->reference_no ?? 'N/A' }}</div>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Customer</span>
                        <div class="job-detail-value">{{ $customerName }}</div>
                    </div>
                    <div class="job-detail">
                        <span class="job-detail-label">Completed At</span>
                        <div class="job-detail-value">{{ $installation->completed_at?->format('F d, Y h:i A') ?? '-' }}</div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="job-card-header">
                    <div>
                        <h2>Field Activation Record</h2>
                        <p>Saved structured field data for this completed installation.</p>
                    </div>
                </div>
                <div class="job-card-body job-detail-grid">
                    @foreach($installation->activationDetails() as $label => $value)
                        <div class="job-detail">
                            <span class="job-detail-label">{{ $label }}</span>
                            <div class="job-detail-value">{{ $value ?: '-' }}</div>
                        </div>
                    @endforeach
                    <div class="job-detail full">
                        <span class="job-detail-label">Technician Remarks</span>
                        <div class="job-detail-value">{{ $installation->technician_remarks ?: '-' }}</div>
                    </div>
                    <div class="job-detail full">
                        <span class="job-detail-label">Material Notes</span>
                        <div class="job-detail-value">{{ $installation->material_notes ?: '-' }}</div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="job-card-header">
                    <div>
                        <h2>Proof Photos</h2>
                        <p>Uploaded field evidence and acceptance records.</p>
                    </div>
                </div>
                <div class="job-card-body proof-grid">
                    @foreach($proofPhotos as $label => $path)
                        <div class="proof-card">
                            <strong>{{ $label }}</strong>
                            <img src="{{ asset('storage/'.$path) }}" alt="{{ $label }}">
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            function initReadonlyMaps() {
                if (typeof window.L === 'undefined') {
                    return;
                }

                document.querySelectorAll('.js-readonly-location-map').forEach(function (element) {
                    const lat = parseFloat(element.dataset.lat);
                    const lng = parseFloat(element.dataset.lng);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) {
                        return;
                    }

                    if (element._readonlyLeafletMap) {
                        setTimeout(function () {
                            element._readonlyLeafletMap.invalidateSize();
                        }, 120);
                        return;
                    }

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

                    L.marker([lat, lng]).addTo(map);
                    element._readonlyLeafletMap = map;

                    setTimeout(function () {
                        map.invalidateSize();
                    }, 120);
                });
            }

            function initInstallationWorkflow() {
                const reviewFields = Array.from(document.querySelectorAll('[data-review-field]'));

                function updateReview() {
                    reviewFields.forEach((node) => {
                        const field = document.getElementById(node.dataset.reviewField);
                        const label = node.textContent.split(':')[0];
                        const value = field ? (((field.value || '').trim()) || '-') : '-';
                        node.textContent = label + ': ' + value;
                    });
                }

                document.querySelectorAll('#installationWorkflowForm input, #installationWorkflowForm select, #installationWorkflowForm textarea').forEach((field) => {
                    field.addEventListener('input', updateReview);
                    field.addEventListener('change', updateReview);
                });

                updateReview();
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
