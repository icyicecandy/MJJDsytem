@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endpush

<x-app-layout>
    @php
        $installation = $application->installation;
        $proofPhotos = $installation ? collect($installation->proofPhotos())->filter() : collect();
        $serviceabilityStatus = old('serviceability_status', $application->serviceability_status ?? 'pending');
        $slotAvailabilityStatus = old('slot_availability_status', $application->slot_availability_status ?? 'pending');
        $assessmentNotes = old('assessment_notes', $application->assessment_notes ?? '');
        $rejectionReason = old('rejection_reason', $application->rejection_reason ?? '');
        $canSchedule = in_array($application->status, ['approved', 'scheduled'], true);
    @endphp
    <div class="page-cluster admin-application-open-page">
        <section class="card section-title-card admin-application-open-hero">
            <div>
                <span class="application-status-kicker">Application Review</span>
                <h1>Application Details</h1>
                <p>Review customer information, service location, manual assessment, technician scheduling, and completion records for reference {{ $application->reference_no }}.</p>
            </div>
            <div class="admin-application-open-hero-meta">
                <span class="badge {{ $application->status }}">{{ ucfirst(str_replace('_',' ', $application->status)) }}</span>
                <small>{{ $application->reference_no }}</small>
            </div>
        </section>

        <div class="admin-application-open-grid">
            <section class="card admin-application-panel">
                <div class="admin-application-panel-head">
                    <div>
                        <h3>Customer Information</h3>
                        <p>Primary identity and contact details submitted by the applicant.</p>
                    </div>
                </div>
                <div class="tracking-grid admin-application-detail-grid">
                    <div class="tracking-meta"><strong>Full Name</strong>{{ $application->first_name }} {{ $application->last_name }}</div>
                    <div class="tracking-meta"><strong>Email Address</strong>{{ $application->email }}</div>
                    <div class="tracking-meta"><strong>Mobile Number</strong>{{ $application->mobile_number }}</div>
                    <div class="tracking-meta"><strong>Selected Plan</strong>{{ $application->plan_code }}</div>
                </div>
            </section>

            <section class="card admin-application-panel">
                <div class="admin-application-panel-head">
                    <div>
                        <h3>Service Address</h3>
                        <p>Installation site details and customer-submitted coordinates.</p>
                    </div>
                </div>
                <div class="tracking-grid admin-application-detail-grid">
                    <div class="tracking-meta"><strong>Region</strong>{{ $application->region ?: 'Not provided' }}</div>
                    <div class="tracking-meta"><strong>Province</strong>{{ $application->province ?: 'Not provided' }}</div>
                    <div class="tracking-meta"><strong>Municipality / City</strong>{{ $application->municipality ?: 'Not provided' }}</div>
                    <div class="tracking-meta"><strong>Barangay / Zone</strong>{{ $application->barangay ?: 'Not provided' }}</div>
                    <div class="tracking-meta full"><strong>Full Service Address</strong>{{ $application->fullServiceAddress() ?: 'Not provided' }}</div>
                    <div class="tracking-meta"><strong>Latitude</strong>{{ $application->lat ?? 'Not set' }}</div>
                    <div class="tracking-meta"><strong>Longitude</strong>{{ $application->lng ?? 'Not set' }}</div>
                </div>
            </section>
        </div>

        @include('partials.location-map-card', [
            'mapId' => 'adminApplicationMap',
            'lat' => $application->lat,
            'lng' => $application->lng,
            'title' => 'Pinned Service Location',
            'subtitle' => 'Review the customer-selected service point before approving or scheduling installation.',
            'address' => $application->fullServiceAddress(),
        ])

        <div class="admin-application-open-grid">
            <section class="card admin-application-panel">
                <div class="admin-application-panel-head">
                    <div>
                        <h3>Manual Assessment & Decision</h3>
                        <p>Record serviceability, slot availability, and the final review outcome before scheduling.</p>
                    </div>
                </div>

                <div class="admin-status-summary">
                    <span class="badge {{ $application->status }}">{{ ucfirst(str_replace('_',' ', $application->status)) }}</span>
                    <p>Manual assessment must be recorded before the application is approved or rejected.</p>
                </div>

                @if ($application->assessed_at)
                    <div class="tracking-grid admin-application-detail-grid">
                        <div class="tracking-meta"><strong>Serviceability</strong>{{ ucfirst(str_replace('_', ' ', $application->serviceability_status ?? 'pending')) }}</div>
                        <div class="tracking-meta"><strong>Slot Availability</strong>{{ ucfirst(str_replace('_', ' ', $application->slot_availability_status ?? 'pending')) }}</div>
                        <div class="tracking-meta"><strong>Assessed At</strong>{{ $application->assessed_at?->format('F d, Y h:i A') ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Assessed By</strong>{{ $application->assessor?->name ?? 'Not recorded' }}</div>
                        <div class="tracking-meta full"><strong>Assessment Notes</strong>{{ $application->assessment_notes ?: 'No notes recorded.' }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.applications.status', $application) }}" class="admin-schedule-form">
                    @csrf
                    @method('PATCH')

                    <div class="admin-schedule-grid">
                        <div class="admin-form-field">
                            <label for="serviceability_status">Serviceability</label>
                            <select id="serviceability_status" name="serviceability_status" required>
                                <option value="pending" @selected($serviceabilityStatus === 'pending')>Pending Assessment</option>
                                <option value="serviceable" @selected($serviceabilityStatus === 'serviceable')>Serviceable</option>
                                <option value="not_serviceable" @selected($serviceabilityStatus === 'not_serviceable')>Not Serviceable</option>
                            </select>
                            @error('serviceability_status')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="admin-form-field">
                            <label for="slot_availability_status">Slot Availability</label>
                            <select id="slot_availability_status" name="slot_availability_status" required>
                                <option value="pending" @selected($slotAvailabilityStatus === 'pending')>Pending Check</option>
                                <option value="available" @selected($slotAvailabilityStatus === 'available')>Available</option>
                                <option value="full" @selected($slotAvailabilityStatus === 'full')>Full</option>
                                <option value="not_applicable" @selected($slotAvailabilityStatus === 'not_applicable')>Not Applicable</option>
                            </select>
                            @error('slot_availability_status')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="admin-form-field full">
                            <label for="assessment_notes">Assessment Notes</label>
                            <textarea id="assessment_notes" name="assessment_notes" rows="4" placeholder="Document serviceability checks, site constraints, or slot observations.">{{ $assessmentNotes }}</textarea>
                            @error('assessment_notes')<span class="error">{{ $message }}</span>@enderror
                        </div>

                        <div class="admin-form-field full">
                            <label for="rejection_reason">Rejection Reason</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="3" placeholder="Required when rejecting an application.">{{ $rejectionReason }}</textarea>
                            @error('rejection_reason')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="admin-status-action-grid">
                        <button name="status" value="approved" class="btn success">Approve</button>
                        <button name="status" value="under_review" class="btn accent">Save as Under Review</button>
                        <button name="status" value="rejected" class="btn danger">Reject</button>
                    </div>
                </form>
            </section>

            <section class="card admin-application-panel">
                <div class="admin-application-panel-head">
                    <div>
                        <h3>Assign Technician & Schedule</h3>
                        <p>Prepare the technician assignment and planned installation visit.</p>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (! $canSchedule)
                    <div class="empty-state compact">This application must be approved before technician scheduling becomes available.</div>
                @endif

                <form method="POST" action="{{ route('admin.applications.schedule', $application) }}" class="admin-schedule-form">
                    @csrf

                    <div class="admin-schedule-grid">
                        <div class="admin-form-field">
                            <label>Technician</label>
                            <select name="technician_id" required @disabled(! $canSchedule)>
                                <option value="">Select Technician</option>
                                @foreach($technicians as $techUser)
                                    <option value="{{ $techUser->id }}" @if(optional($installation)->technician_id === $techUser->id) selected @endif>
                                        {{ $techUser->name }} ({{ $techUser->email }})
                                    </option>
                                @endforeach
                            </select>
                            <p>Shows accounts with role = technician.</p>
                        </div>

                        <div class="admin-form-field">
                            <label>Installation Date</label>
                            <input type="date" name="installation_date" value="{{ optional($installation)->installation_date }}" required @disabled(! $canSchedule)>
                        </div>

                        <div class="admin-form-field">
                            <label>Installation Time</label>
                            <input type="time" name="installation_time" value="{{ optional($installation)->installation_time }}" @disabled(! $canSchedule)>
                        </div>
                    </div>

                    <div class="admin-schedule-footer">
                        <button class="btn" @disabled(! $canSchedule)>Save Schedule</button>
                    </div>
                </form>
            </section>
        </div>

        @if($installation)
            <div class="admin-application-open-grid">
                <section class="card admin-application-panel">
                    <div class="admin-application-panel-head">
                        <div>
                            <h3>Field Activation Details</h3>
                            <p>Structured installation and activation data recorded by the technician.</p>
                        </div>
                    </div>
                    <div class="tracking-grid admin-application-detail-grid">
                        <div class="tracking-meta"><strong>Installation Status</strong>{{ ucfirst(str_replace('_', ' ', $installation->status)) }}</div>
                        <div class="tracking-meta"><strong>Activation Date</strong>{{ $installation->activation_date?->format('F d, Y') ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Activation Status</strong>{{ $installation->activation_status ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Package</strong>{{ $installation->package_name ?? $application->plan_code ?? '-' }}</div>
                        <div class="tracking-meta"><strong>NAP Code</strong>{{ $installation->nap_code ?? '-' }}</div>
                        <div class="tracking-meta"><strong>LP Code</strong>{{ $installation->lp_code ?? '-' }}</div>
                        <div class="tracking-meta"><strong>NP Code</strong>{{ $installation->np_code ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Full NAP Label</strong>{{ $installation->full_nap_label ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Port Number</strong>{{ $installation->port_number ?? '-' }}</div>
                        <div class="tracking-meta"><strong>JO Number</strong>{{ $installation->jo_number ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Router Serial</strong>{{ $installation->router_serial ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Cable Reel No.</strong>{{ $installation->cable_reel_no ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Speed Test Result</strong>{{ $installation->speed_test_result ?? '-' }}</div>
                        <div class="tracking-meta"><strong>Completed At</strong>{{ $installation->completed_at?->format('F d, Y h:i A') ?? '-' }}</div>
                        <div class="tracking-meta full"><strong>Technician Remarks</strong>{{ $installation->technician_remarks ?? '-' }}</div>
                        <div class="tracking-meta full"><strong>Material Notes</strong>{{ $installation->material_notes ?? '-' }}</div>
                    </div>
                </section>

                <section class="card admin-application-panel">
                    <div class="admin-application-panel-head">
                        <div>
                            <h3>Proof Photos</h3>
                            <p>Review the uploaded job evidence and acceptance records.</p>
                        </div>
                    </div>
                    <div class="proof-grid">
                        @forelse($proofPhotos as $label => $path)
                            @if($path)
                                <div class="proof-card">
                                    <strong>{{ $label }}</strong>
                                    <img src="{{ asset('storage/'.$path) }}" alt="{{ $label }}">
                                </div>
                            @endif
                        @empty
                            <div class="empty-state compact">No proof photos uploaded yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        <div class="admin-application-open-footer">
            <a href="{{ route('admin.applications.index') }}" class="btn secondary">Back to Applications</a>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const initReadonlyMaps = window.initReadonlyLocationMaps || function () {
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
            };

            initReadonlyMaps();
        });
    </script>
@endpush

