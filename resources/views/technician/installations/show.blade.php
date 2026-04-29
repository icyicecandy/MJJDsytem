@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .workflow-shell {
            display: grid;
            gap: 1.5rem;
            padding: 1rem;
            background: #f6f8fb;
            color: #111827;
        }
        .workflow-header,
        .workflow-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }
        .workflow-header {
            position: static;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: center;
            padding: 1.25rem;
        }
        .workflow-header-main {
            display: grid;
            gap: 0.45rem;
            min-width: 0;
        }
        .workflow-title-row,
        .workflow-meta-row,
        .workflow-header-actions,
        .workflow-map-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }
        .workflow-eyebrow,
        .workflow-label,
        .workflow-field label,
        .workflow-coordinate-label,
        .workflow-step-state {
            color: #6b7280;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .workflow-ref {
            color: #475569;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.82rem;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .workflow-header h1 {
            margin: 0;
            color: #0b1f3a;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: 0;
        }
        .workflow-note,
        .workflow-card p,
        .workflow-empty p,
        .workflow-success p,
        .workflow-muted {
            margin: 0;
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .workflow-schedule-chip,
        .workflow-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 750;
            line-height: 1.2;
        }
        .workflow-schedule-chip {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .workflow-badge--scheduled {
            background: #e5e7eb;
            color: #374151;
        }
        .workflow-badge--in_progress {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .workflow-badge--completed {
            background: #dcfce7;
            color: #15803d;
        }
        .workflow-badge--activated {
            background: #ccfbf1;
            color: #0f766e;
        }
        .workflow-badge--reschedule_required {
            background: #ffedd5;
            color: #c2410c;
        }
        .workflow-grid {
            display: grid;
            grid-template-columns: minmax(0, 65fr) minmax(320px, 35fr);
            gap: 1.5rem;
            align-items: start;
        }
        .workflow-column {
            display: grid;
            gap: 1.5rem;
            align-content: start;
        }
        .workflow-card {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .workflow-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid #edf0f4;
        }
        .workflow-card-head h2,
        .workflow-card-head h3 {
            margin: 0;
            color: #0b1f3a;
            font-size: 1.05rem;
            font-weight: 750;
            line-height: 1.25;
            letter-spacing: 0;
        }
        .workflow-overview-grid,
        .workflow-summary-grid,
        .workflow-form-grid,
        .workflow-review-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .workflow-detail,
        .workflow-summary-box {
            display: grid;
            min-width: 0;
            gap: 0.35rem;
        }
        .workflow-detail.full,
        .workflow-field.full,
        .workflow-summary-box.full {
            grid-column: 1 / -1;
        }
        .workflow-value {
            color: #111827;
            font-size: 0.98rem;
            font-weight: 650;
            line-height: 1.45;
            word-break: break-word;
        }
        .workflow-overview-grid .workflow-detail,
        .workflow-summary-grid .workflow-summary-box,
        .workflow-review-grid .workflow-detail {
            padding: 0.9rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
        }
        .workflow-action-card {
            border-color: rgba(37, 99, 235, 0.24);
            box-shadow: 0 14px 34px rgba(37, 99, 235, 0.1);
        }
        .workflow-action-panel,
        .workflow-success,
        .workflow-empty,
        .workflow-errors,
        .workflow-upload-zone {
            display: grid;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 12px;
        }
        .workflow-action-panel {
            border: 1px solid rgba(37, 99, 235, 0.18);
            background: #eff6ff;
        }
        .workflow-success {
            border: 1px solid rgba(22, 163, 74, 0.22);
            background: #f0fdf4;
        }
        .workflow-empty {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .workflow-errors {
            border: 1px solid rgba(220, 38, 38, 0.18);
            background: #fef2f2;
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
        .workflow-primary-action .btn,
        .workflow-header-actions .btn {
            justify-content: center;
        }
        .workflow-header-actions form {
            margin: 0;
        }
        .workflow-field {
            display: grid;
            gap: 0.5rem;
        }
        .workflow-field textarea {
            width: 100%;
            min-height: 108px;
            padding: 0.85rem 0.9rem;
            border: 1px solid rgba(148, 163, 184, 0.34);
            border-radius: 12px;
            background: #fff;
            color: #111827;
            resize: vertical;
        }
        .workflow-field input[type="text"] {
            width: 100%;
            padding: 0.85rem 0.9rem;
            border: 1px solid rgba(148, 163, 184, 0.34);
            border-radius: 12px;
            background: #fff;
            color: #111827;
        }
        .workflow-upload-zone {
            position: relative;
            align-items: center;
            justify-items: center;
            min-height: 138px;
            border: 1px dashed #93c5fd;
            background: #f8fbff;
            text-align: center;
        }
        .workflow-upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .workflow-upload-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.35rem;
            padding: 0.55rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: #0b1f3a;
            font-weight: 750;
        }
        .workflow-photo-preview {
            display: none;
            gap: 0.75rem;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
        }
        .workflow-photo-preview.is-visible {
            display: flex;
        }
        .workflow-photo-preview img {
            width: 76px;
            height: 76px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }
        .workflow-map-canvas {
            position: relative;
            isolation: isolate;
            z-index: 0;
            height: 220px;
            border: 1px solid #dbe2ea;
            border-radius: 12px;
            overflow: hidden;
            background: #f8fafc;
        }
        .workflow-map-canvas .leaflet-top,
        .workflow-map-canvas .leaflet-bottom {
            z-index: 5;
        }
        .workflow-map-canvas.is-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: #64748b;
            text-align: center;
        }
        .workflow-map-actions {
            justify-content: space-between;
        }
        .workflow-coordinates {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem 1rem;
            color: #64748b;
            font-size: 0.84rem;
        }
        .workflow-address-line {
            color: #111827;
            font-size: 1rem;
            font-weight: 650;
            line-height: 1.55;
        }
        .workflow-address-meta {
            color: #6b7280;
            font-size: 0.88rem;
        }
        .workflow-progress {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0;
        }
        .workflow-step {
            display: grid;
            gap: 0.65rem;
            min-width: 0;
        }
        .workflow-step-marker {
            display: grid;
            grid-template-columns: 2rem minmax(0, 1fr);
            align-items: center;
            gap: 0.55rem;
        }
        .workflow-step-dot {
            display: inline-flex;
            width: 2rem;
            height: 2rem;
            align-items: center;
            justify-content: center;
            border: 2px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            color: #6b7280;
            font-weight: 800;
            box-shadow: 0 0 0 5px #f3f4f6;
        }
        .workflow-step-line {
            height: 2px;
            border-radius: 999px;
            background: #d1d5db;
        }
        .workflow-step:last-child .workflow-step-line {
            visibility: hidden;
        }
        .workflow-step.is-complete .workflow-step-dot {
            border-color: #16a34a;
            background: #16a34a;
            color: #fff;
            box-shadow: 0 0 0 5px #dcfce7;
        }
        .workflow-step.is-active .workflow-step-dot {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 0 0 5px #dbeafe;
        }
        .workflow-step.is-complete .workflow-step-line {
            background: #16a34a;
        }
        .workflow-step strong {
            display: block;
            color: #111827;
            font-size: 0.95rem;
        }
        .workflow-proof {
            display: grid;
            gap: 0.75rem;
        }
        .workflow-proof-card {
            display: grid;
            gap: 0.75rem;
        }
        .workflow-proof-card img {
            width: 100%;
            max-height: 320px;
            object-fit: cover;
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 12px;
        }
        .workflow-timeline {
            position: relative;
            display: grid;
            gap: 0.85rem;
            padding-left: 1.1rem;
        }
        .workflow-timeline::before {
            content: "";
            position: absolute;
            top: 0.35rem;
            bottom: 0.35rem;
            left: 0.28rem;
            width: 1px;
            background: #d1d5db;
        }
        .workflow-timeline-item {
            position: relative;
            display: grid;
            gap: 0.25rem;
            padding-left: 1rem;
        }
        .workflow-timeline-item::before {
            content: "";
            position: absolute;
            top: 0.3rem;
            left: -0.02rem;
            width: 0.58rem;
            height: 0.58rem;
            border-radius: 999px;
            background: #2563eb;
            box-shadow: 0 0 0 4px #dbeafe;
        }
        .workflow-timeline-meta {
            color: #6b7280;
            font-size: 0.82rem;
            font-weight: 650;
        }
        .workflow-shell .btn {
            display: inline-flex;
            min-height: 2.5rem;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.62rem 0.95rem;
            border: 1px solid transparent;
            border-radius: 10px;
            background: #0b1f3a;
            color: #fff;
            font-weight: 750;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .workflow-shell .btn:hover {
            background: #123a68;
            box-shadow: 0 10px 18px rgba(11, 31, 58, 0.14);
            transform: translateY(-1px);
        }
        .workflow-shell .btn.secondary {
            border-color: #cbd5e1;
            background: #fff;
            color: #0b1f3a;
        }
        .workflow-shell .btn.secondary:hover {
            border-color: #0b1f3a;
            background: #f8fafc;
        }
        .workflow-shell .btn.success {
            background: #15803d;
        }
        .workflow-shell .btn.success:hover {
            background: #166534;
        }
        .workflow-shell .btn[disabled] {
            opacity: 0.62;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        @media (max-width: 1100px) {
            .workflow-header,
            .workflow-grid {
                grid-template-columns: 1fr;
            }
            .workflow-header-actions {
                justify-content: flex-start;
            }
        }
        @media (max-width: 760px) {
            .workflow-shell {
                padding: 0.75rem;
            }
            .workflow-card,
            .workflow-header {
                padding: 1rem;
            }
            .workflow-overview-grid,
            .workflow-summary-grid,
            .workflow-form-grid,
            .workflow-review-grid,
            .workflow-progress {
                grid-template-columns: 1fr;
            }
            .workflow-step-line {
                display: none;
            }
            .workflow-header-actions,
            .workflow-primary-action {
                display: grid;
                width: 100%;
            }
            .workflow-header-actions .btn,
            .workflow-primary-action .btn {
                width: 100%;
            }
        }
    </style>
@endpush

<x-app-layout>
    @php
        $application = $installation->application;
        $technicalDetails = $installation->technicalDetails();
        $hasTechnicalDetails = $installation->hasTechnicalDetails();
        $customerName = $application ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')) : '-';
        $fullAddress = $application ? $application->fullServiceAddress() : '-';
        $cityProvince = $application
            ? collect([$application->municipality ?? null, $application->province ?? null])->filter()->implode(', ')
            : '';
        $shortAddress = $application
            ? (collect([$application->barangay ?? null, $application->municipality ?? null])->filter()->implode(', ') ?: $fullAddress)
            : '-';
        $proofPhotos = collect($installation->proofPhotos())->filter();
        $installationLogs = $installation->logs ?? collect();
        $locationLat = $application && is_numeric($application->lat) ? (float) $application->lat : null;
        $locationLng = $application && is_numeric($application->lng) ? (float) $application->lng : null;
        $hasLocation = $locationLat !== null && $locationLng !== null;
        $osmUrl = $hasLocation
            ? 'https://www.openstreetmap.org/?mlat=' . $locationLat . '&mlon=' . $locationLng . '#map=18/' . $locationLat . '/' . $locationLng
            : null;
        $statusClass = match ($installation->status) {
            'scheduled' => 'workflow-badge--scheduled',
            'in_progress' => 'workflow-badge--in_progress',
            'reschedule_required' => 'workflow-badge--reschedule_required',
            'completed' => 'workflow-badge--completed',
            'activated' => 'workflow-badge--activated',
            default => 'workflow-badge--scheduled',
        };
        $scheduledDateLabel = $installation->installation_date?->format('F d, Y') ?? 'Date to be confirmed';
        $scheduledTimeLabel = $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A') : 'Time to be confirmed';
        $scheduledAtLabel = $scheduledDateLabel . ' - ' . $scheduledTimeLabel;
        $completedAtLabel = $installation->completed_at?->displayDateTime('F d, Y h:i A') ?? 'Not completed yet';
        $activationDateLabel = $installation->activation_date?->format('F d, Y') ?? 'Not activated yet';
        $workflowSteps = [
            'scheduled' => ['label' => 'Scheduled', 'description' => 'Job assigned'],
            'in_progress' => ['label' => 'In Progress', 'description' => 'Work underway'],
            'reschedule_required' => ['label' => 'Reschedule Required', 'description' => 'Admin follow-up'],
            'completed' => ['label' => 'Completed', 'description' => 'Proof submitted'],
            'activated' => ['label' => 'Activated', 'description' => 'Service activated'],
        ];
        $workflowStepMap = [
            'scheduled' => 1,
            'in_progress' => 2,
            'reschedule_required' => 3,
            'completed' => 4,
            'activated' => 5,
        ];
        $currentWorkflowStep = $workflowStepMap[$installation->status] ?? 1;
    @endphp

    <div class="job-page workflow-shell">
        <section class="workflow-header">
            <div class="workflow-header-main">
                <div class="workflow-title-row">
                    <span class="workflow-eyebrow">{{ $installation->displayJobReference() }}</span>
                    <span class="workflow-status-pill {{ $statusClass }}">{{ $installation->displayStatusLabel() }}</span>
                </div>
                <h1>Installation Work Order</h1>
                <div class="workflow-meta-row">
                    <span class="workflow-schedule-chip">{{ $scheduledAtLabel }}</span>
                    <span class="workflow-ref">Application Ref: {{ $application->reference_no ?? 'N/A' }}</span>
                </div>
            </div>
            <div class="workflow-header-actions">
                @if($installation->status === 'scheduled')
                    <form method="POST" action="{{ route('technician.installations.status', $installation->id) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit" class="btn">Start Job</button>
                    </form>
                @elseif($installation->isInProgress())
                    <button type="submit" form="installationWorkflowForm" class="btn success">Mark as Completed</button>
                @elseif($installation->isCompleted())
                    <form method="POST" action="{{ route('technician.installations.activate', $installation->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn success">Mark as Activated</button>
                    </form>
                @elseif($installation->isActivated())
                    <button type="button" class="btn success" disabled>Activated</button>
                @else
                    <button type="button" class="btn secondary" disabled>Awaiting Reschedule</button>
                @endif
                <a href="{{ route('technician.installations.index') }}" class="btn secondary">Back</a>
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
            <main class="workflow-column">
                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <h2>Customer + Job Summary</h2>
                    </div>

                    <div class="workflow-overview-grid">
                        <div class="workflow-detail">
                            <span class="workflow-label">Customer Name</span>
                            <div class="workflow-value">{{ $customerName ?: '-' }}</div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Contact Info</span>
                            <div class="workflow-value">
                                {{ $application->mobile_number ?? 'No mobile number provided' }}<br>
                                {{ $application->email ?? 'No email provided' }}
                            </div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Service Address</span>
                            <div class="workflow-value">{{ $shortAddress ?: '-' }}</div>
                        </div>
                        <div class="workflow-detail">
                            <span class="workflow-label">Scheduled</span>
                            <div class="workflow-value">{{ $scheduledAtLabel }}</div>
                        </div>
                    </div>
                </section>

                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <div>
                            <h2>Workflow Status</h2>
                            <p>Update job status as you proceed.</p>
                        </div>
                    </div>

                    <div class="workflow-progress" aria-label="Installation workflow progress">
                        @foreach($workflowSteps as $stepKey => $step)
                            @php
                                $stepNumber = $loop->iteration;
                                $isComplete = $stepNumber < $currentWorkflowStep;
                                $isActive = $stepNumber === $currentWorkflowStep;
                                $stepStateLabel = $isComplete ? 'Completed' : ($isActive ? 'Current' : 'Pending');
                            @endphp
                            <div class="workflow-step {{ $isComplete ? 'is-complete' : '' }} {{ $isActive ? 'is-active' : '' }}">
                                <div class="workflow-step-marker">
                                    <span class="workflow-step-dot" aria-hidden="true">{{ $stepNumber }}</span>
                                    <span class="workflow-step-line" aria-hidden="true"></span>
                                </div>
                                <div>
                                    <span class="workflow-step-state">{{ $stepStateLabel }}</span>
                                    <strong>{{ $step['label'] }}</strong>
                                    <p>{{ $step['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if($installation->status === 'scheduled')
                    <section class="workflow-card workflow-action-card">
                        <div class="workflow-card-head">
                            <h2>Technician Action Panel</h2>
                        </div>

                        <div class="workflow-action-panel">
                            <div class="workflow-primary-action">
                                <div>
                                    <strong>Ready to start onsite work</strong>
                                    <p class="workflow-note">Start the job to unlock completion notes and proof photo upload.</p>
                                </div>
                                <form method="POST" action="{{ route('technician.installations.status', $installation->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn">Mark as In Progress</button>
                                </form>
                            </div>
                        </div>
                    </section>
                @elseif($installation->isInProgress())
                    <form method="POST" enctype="multipart/form-data" action="{{ route('technician.installations.complete', $installation->id) }}" id="installationWorkflowForm" class="workflow-column">
                        @csrf

                        <section class="workflow-card workflow-action-card">
                            <div class="workflow-card-head">
                                <h2>Technician Action Panel</h2>
                            </div>

                            <div class="workflow-form-grid">
                                <div class="workflow-field full">
                                    <label for="technician_remarks">Technician Remarks</label>
                                    <textarea id="technician_remarks" name="technician_remarks" rows="4" required placeholder="Add installation notes, customer confirmation, or onsite issues.">{{ old('technician_remarks', $installation->technician_remarks) }}</textarea>
                                </div>
                                <div class="workflow-field full">
                                    <span class="workflow-label">Technical Installation Details</span>
                                </div>
                                <div class="workflow-field">
                                    <label for="nap_code">NAP Code</label>
                                    <input id="nap_code" type="text" name="nap_code" value="{{ old('nap_code', $installation->nap_code) }}" maxlength="100" required>
                                </div>
                                <div class="workflow-field">
                                    <label for="port_number">Port No.</label>
                                    <input id="port_number" type="text" name="port_number" value="{{ old('port_number', $installation->port_number) }}" maxlength="50" required>
                                </div>
                                <div class="workflow-field">
                                    <label for="equipment_serial_number">Equipment Serial No.</label>
                                    <input id="equipment_serial_number" type="text" name="equipment_serial_number" value="{{ old('equipment_serial_number', $installation->equipment_serial_number) }}" maxlength="100" required>
                                </div>
                                <div class="workflow-field">
                                    <label for="cable_length">Cable Length</label>
                                    <input id="cable_length" type="text" name="cable_length" value="{{ old('cable_length', $installation->cable_length) }}" maxlength="50" required>
                                </div>
                                <div class="workflow-field full">
                                    <label for="installation_photo">Upload proof of installation <span class="field-badge optional">Optional</span></label>
                                    <label class="workflow-upload-zone" for="installation_photo">
                                        <span class="workflow-upload-button">Choose Photo</span>
                                        <span class="workflow-note">JPG, JPEG, PNG, or WEBP. Tap to replace before submitting.</span>
                                        <input id="installation_photo" type="file" name="installation_photo" accept=".jpg,.jpeg,.png,.webp">
                                    </label>
                                    <div class="workflow-photo-preview" data-photo-preview>
                                        <img src="" alt="Selected installation proof preview" data-photo-preview-image>
                                        <div>
                                            <span class="workflow-label">Selected Photo</span>
                                            <div class="workflow-value" data-review-photo>No file selected.</div>
                                            <p class="workflow-note">Select a different file to replace it.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="workflow-review-grid">
                                <div class="workflow-detail full">
                                    <span class="workflow-label">Remarks Preview</span>
                                    <div class="workflow-value" data-review-field="technician_remarks">-</div>
                                </div>
                            </div>

                            <div class="workflow-action-panel">
                                <div class="workflow-primary-action">
                                    <div>
                                        <strong>Submit completion</strong>
                                        <p class="workflow-note">This marks the installation completed and updates the customer application status.</p>
                                    </div>
                                    <button type="submit" class="btn success">Mark as Completed</button>
                                </div>
                            </div>
                        </section>
                    </form>

                    <form method="POST" action="{{ route('technician.installations.status', $installation->id) }}" class="workflow-column">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="reschedule_required">

                        <section class="workflow-card">
                            <div class="workflow-card-head">
                                <h2>Reschedule Required</h2>
                            </div>

                            <div class="workflow-field">
                                <label for="reschedule_technician_remarks">Remarks</label>
                                <textarea id="reschedule_technician_remarks" name="technician_remarks" rows="3" required placeholder="Customer unavailable at location">{{ old('technician_remarks') }}</textarea>
                            </div>

                            <div class="workflow-action-panel">
                                <div class="workflow-primary-action">
                                    <div>
                                        <strong>Customer absent or site unavailable</strong>
                                        <p class="workflow-note">This sends the job back to admin for manual rescheduling.</p>
                                    </div>
                                    <button type="submit" class="btn secondary">Mark as Reschedule Required</button>
                                </div>
                            </div>
                        </section>
                    </form>
                @elseif($installation->isFinalized())
                    <section class="workflow-card workflow-action-card">
                        <div class="workflow-card-head">
                            <h2>Technician Action Panel</h2>
                        </div>

                        <div class="workflow-success">
                            @if($installation->isActivated())
                                <strong>Service activated successfully.</strong>
                                <p>Activated on {{ $activationDateLabel }}.</p>
                            @else
                                <strong>Installation completed successfully.</strong>
                                <p>Mark this job activated once manual service activation is done.</p>
                            @endif
                        </div>

                        @if($installation->isCompleted())
                            <div class="workflow-action-panel">
                                <div class="workflow-primary-action">
                                    <div>
                                        <strong>Ready for activation</strong>
                                        <p class="workflow-note">This sets the installation and customer application status to activated.</p>
                                    </div>
                                    <form method="POST" action="{{ route('technician.installations.activate', $installation->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn success">Mark as Activated</button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div class="workflow-summary-grid">
                            <div class="workflow-summary-box full">
                                <span class="workflow-label">Technician Remarks</span>
                                <div class="workflow-value">{{ $installation->technician_remarks ?: 'No remarks yet.' }}</div>
                            </div>
                            <div class="workflow-summary-box full">
                                <span class="workflow-label">Technical Installation Details</span>
                                @if($hasTechnicalDetails)
                                    <div class="workflow-summary-grid">
                                        @foreach($technicalDetails as $label => $value)
                                            <div class="workflow-summary-box">
                                                <span class="workflow-label">{{ $label }}</span>
                                                <div class="workflow-value">{{ $value ?: '-' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="workflow-value">Technical details not yet recorded.</div>
                                @endif
                            </div>
                        </div>

                        <div class="workflow-proof">
                            <span class="workflow-label">Proof of Installation</span>
                            <x-photo-gallery
                                :photos="$proofPhotos"
                                title="Installation Photos"
                                empty="No installation photo uploaded."
                                button-text="View photo/photos"
                                class="workflow-proof-card"
                            />
                        </div>
                    </section>
                @else
                    <section class="workflow-card workflow-action-card">
                        <div class="workflow-card-head">
                            <h2>Technician Action Panel</h2>
                        </div>

                        <div class="workflow-empty">
                            <strong>Reschedule required</strong>
                            <p>Admin must set a new date and time before this job can continue.</p>
                        </div>

                        <div class="workflow-summary-grid">
                            <div class="workflow-summary-box full">
                                <span class="workflow-label">Technician Remarks</span>
                                <div class="workflow-value">{{ $installation->technician_remarks ?: 'No remarks yet.' }}</div>
                            </div>
                        </div>
                    </section>
                @endif
            </main>

            <aside class="workflow-column">
                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <h2>Map</h2>
                    </div>

                    @if($hasLocation)
                        <div id="technicianApplicationMap" class="workflow-map-canvas js-readonly-location-map" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $fullAddress }}" data-empty-label="No pinned location available."></div>
                        <div class="workflow-map-actions">
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

                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <h2>Full Address</h2>
                    </div>

                    <div class="workflow-address-line">{{ $fullAddress ?: '-' }}</div>
                    @if($cityProvince !== '')
                        <div class="workflow-address-meta">{{ $cityProvince }}</div>
                    @endif
                </section>

                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <h2>Installation Summary</h2>
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
                            <div class="workflow-value">{{ $installation->technician_remarks ?: 'No remarks yet.' }}</div>
                        </div>
                        <div class="workflow-summary-box full">
                            <span class="workflow-label">Technical Installation Details</span>
                            @if($hasTechnicalDetails)
                                <div class="workflow-summary-grid">
                                    @foreach($technicalDetails as $label => $value)
                                        <div class="workflow-summary-box">
                                            <span class="workflow-label">{{ $label }}</span>
                                            <div class="workflow-value">{{ $value ?: '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="workflow-value">Technical details not yet recorded.</div>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="workflow-card">
                    <div class="workflow-card-head">
                        <h2>Installation History</h2>
                        <button type="button" class="btn secondary" data-open-installation-history-modal>View History</button>
                    </div>

                    <p class="workflow-muted">
                        {{ $installationLogs->isNotEmpty() ? $installationLogs->count() . ' history ' . \Illuminate\Support\Str::plural('entry', $installationLogs->count()) . ' recorded.' : 'No installation history recorded yet.' }}
                    </p>
                </section>
            </aside>
        </div>
    </div>

    <div class="admin-ticket-modal installation-history-modal" id="installationHistoryModal" aria-hidden="true">
        <div class="admin-ticket-modal__backdrop" data-close-installation-history-modal></div>
        <div class="admin-ticket-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="installationHistoryModalTitle">
            <button type="button" class="admin-ticket-modal__close" data-close-installation-history-modal aria-label="Close installation history">&times;</button>
            <div class="admin-ticket-modal__shell">
                <div class="admin-ticket-modal__intro">
                    <h2 id="installationHistoryModalTitle">Installation History</h2>
                    <p>Review scheduling, workflow, reschedule, and completion notes for this work order.</p>
                </div>

                <div class="installation-history-modal__body">
                    @if($installationLogs->isNotEmpty())
                        <div class="workflow-timeline">
                            @foreach($installationLogs as $log)
                                <div class="workflow-timeline-item">
                                    <div class="workflow-timeline-meta">
                                        {{ $log->created_at?->displayDateTime('F d, Y h:i A') ?? '-' }} - {{ $log->user?->name ?? 'System' }}
                                    </div>
                                    <div class="workflow-value">{{ $log->notes }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="workflow-empty">
                            <strong>No history yet</strong>
                            <p>No installation history recorded yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
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
                        zoomControl: false,
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
                const photoPreview = document.querySelector('[data-photo-preview]');
                const photoPreviewImage = document.querySelector('[data-photo-preview-image]');
                const historyModal = document.getElementById('installationHistoryModal');
                const historyOpeners = document.querySelectorAll('[data-open-installation-history-modal]');
                const historyClosers = document.querySelectorAll('[data-close-installation-history-modal]');
                let photoPreviewUrl = null;

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
                        const file = photoField.files && photoField.files[0] ? photoField.files[0] : null;
                        photoNode.textContent = file ? file.name : 'No file selected.';

                        if (photoPreviewUrl) {
                            URL.revokeObjectURL(photoPreviewUrl);
                            photoPreviewUrl = null;
                        }

                        if (!photoPreview || !photoPreviewImage) {
                            return;
                        }

                        if (!file) {
                            photoPreview.classList.remove('is-visible');
                            photoPreviewImage.removeAttribute('src');
                            return;
                        }

                        photoPreviewUrl = URL.createObjectURL(file);
                        photoPreviewImage.src = photoPreviewUrl;
                        photoPreview.classList.add('is-visible');
                    };
                    photoField.addEventListener('change', updatePhoto);
                    updatePhoto();
                }

                if (historyModal && historyOpeners.length) {
                    const openHistoryModal = function () {
                        historyModal.classList.add('is-open');
                        historyModal.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('ticket-overlay-open');
                    };

                    const closeHistoryModal = function () {
                        historyModal.classList.remove('is-open');
                        historyModal.setAttribute('aria-hidden', 'true');
                        document.body.classList.remove('ticket-overlay-open');
                    };

                    historyOpeners.forEach(function (button) {
                        button.addEventListener('click', openHistoryModal);
                    });

                    historyClosers.forEach(function (button) {
                        button.addEventListener('click', closeHistoryModal);
                    });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && historyModal.classList.contains('is-open')) {
                            closeHistoryModal();
                        }
                    });
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
