@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .admin-app-review {
            display: grid;
            gap: 1.5rem;
            padding: 1.5rem;
        }
        .admin-app-header,
        .admin-app-card {
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }
        .admin-app-header {
            display: grid;
            gap: 1.25rem;
            padding: 1.5rem;
        }
        .admin-app-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
        }
        .admin-app-kicker,
        .admin-app-label,
        .admin-app-field label,
        .admin-app-inline-label,
        .admin-app-progress-step span {
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }
        .admin-app-header h1 {
            margin: 0.35rem 0 0.5rem;
            font-size: 1.5rem;
            line-height: 1.2;
            font-weight: 700;
            color: #0f172a;
        }
        .admin-app-subtitle,
        .admin-app-card p,
        .admin-app-helper,
        .admin-app-muted,
        .admin-app-progress-copy p {
            margin: 0;
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .admin-app-header-meta {
            display: grid;
            justify-items: end;
            gap: 0.5rem;
        }
        .admin-app-ref {
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 600;
        }
        .admin-app-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .admin-app-status--submitted { background: #dbeafe; color: #1d4ed8; }
        .admin-app-status--under_review { background: #fef3c7; color: #b45309; }
        .admin-app-status--approved,
        .admin-app-status--installed,
        .admin-app-status--completed { background: #dcfce7; color: #15803d; }
        .admin-app-status--rejected { background: #fee2e2; color: #b91c1c; }
        .admin-app-status--scheduled { background: #e0e7ff; color: #4338ca; }
        .admin-app-status--pending { background: #e2e8f0; color: #475569; }
        .admin-app-progress {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-app-progress-step {
            display: grid;
            gap: 0.55rem;
        }
        .admin-app-progress-line {
            position: relative;
            height: 0.5rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .admin-app-progress-line::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: scaleX(0);
            transform-origin: left center;
            background: linear-gradient(90deg, #0f766e, #22c55e);
        }
        .admin-app-progress-step.is-complete .admin-app-progress-line::after,
        .admin-app-progress-step.is-active .admin-app-progress-line::after {
            transform: scaleX(1);
        }
        .admin-app-progress-step.is-active .admin-app-progress-line::after {
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }
        .admin-app-progress-copy strong {
            display: block;
            margin-bottom: 0.25rem;
            color: #0f172a;
            font-size: 0.95rem;
        }
        .admin-app-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(340px, 0.92fr);
            gap: 1.5rem;
            align-items: start;
        }
        .admin-app-column {
            display: grid;
            gap: 1.25rem;
        }
        .admin-app-card {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .admin-app-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .admin-app-card-head h2,
        .admin-app-card-head h3 {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .admin-app-detail-grid,
        .admin-app-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }
        .admin-app-detail,
        .admin-app-stat {
            display: grid;
            gap: 0.3rem;
        }
        .admin-app-detail--full,
        .admin-app-stat--full {
            grid-column: 1 / -1;
        }
        .admin-app-value {
            color: #0f172a;
            font-size: 0.97rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .admin-app-stat {
            padding: 0.9rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #f8fafc;
        }
        .admin-app-location-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        .admin-app-form {
            display: grid;
            gap: 1rem;
        }
        .admin-app-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-app-field {
            display: grid;
            gap: 0.45rem;
        }
        .admin-app-field--full {
            grid-column: 1 / -1;
        }
        .admin-app-field input,
        .admin-app-field select,
        .admin-app-field textarea {
            width: 100%;
        }
        .admin-app-field textarea {
            min-height: 112px;
        }
        .admin-app-field textarea#rejection_reason {
            min-height: 88px;
        }
        .admin-app-field.is-hidden {
            display: none;
        }
        .admin-app-summary-box,
        .admin-app-empty {
            display: grid;
            gap: 0.85rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #f8fafc;
        }
        .admin-app-empty {
            justify-items: start;
        }
        .admin-app-reject-box {
            padding: 1rem;
            border: 1px solid #fecaca;
            border-radius: 1rem;
            background: #fff1f2;
        }
        .admin-app-reject-box strong {
            display: block;
            margin-bottom: 0.35rem;
            color: #991b1b;
        }
        .admin-app-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-app-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .admin-app-timeline {
            position: relative;
            display: grid;
            gap: 1rem;
            padding-left: 1rem;
        }
        .admin-app-timeline::before {
            content: "";
            position: absolute;
            top: 0.25rem;
            bottom: 0.25rem;
            left: 0.2rem;
            width: 1px;
            background: #cbd5e1;
        }
        .admin-app-timeline-item {
            position: relative;
            display: grid;
            gap: 0.3rem;
            padding-left: 1rem;
        }
        .admin-app-timeline-item::before {
            content: "";
            position: absolute;
            top: 0.3rem;
            left: -0.02rem;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #2563eb;
            box-shadow: 0 0 0 4px #eff6ff;
        }
        .admin-app-timeline-meta {
            font-size: 0.82rem;
            color: #64748b;
        }
        .admin-app-photo {
            display: grid;
            gap: 0.75rem;
        }
        .admin-app-photo img {
            display: block;
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 1rem;
            border: 1px solid #dbe2ea;
        }
        .admin-app-map-trigger {
            align-self: start;
        }
        .admin-app-map-link {
            white-space: nowrap;
        }
        .admin-app-map {
            height: 360px;
            border-radius: 1rem;
            border: 1px solid #dbe2ea;
            overflow: hidden;
        }
        .admin-app-map-modal {
            position: fixed;
            inset: 0;
            z-index: 120;
            display: none;
        }
        .admin-app-map-modal.is-open {
            display: block;
        }
        .admin-app-map-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(3px);
        }
        .admin-app-map-modal__dialog {
            position: relative;
            z-index: 1;
            width: min(820px, calc(100vw - 2rem));
            margin: 4vh auto;
            background: #fff;
            border-radius: 1.25rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
            overflow: hidden;
        }
        .admin-app-map-modal__shell {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .admin-app-map-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .admin-app-map-modal__head h3 {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .admin-app-map-modal__close {
            width: 2.25rem;
            height: 2.25rem;
            border: 0;
            border-radius: 999px;
            background: #eff6ff;
            color: #0f172a;
            font-size: 1.35rem;
            cursor: pointer;
        }
        .admin-app-map-modal__meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .admin-app-footer {
            display: flex;
            justify-content: flex-start;
        }
        body.admin-app-map-open {
            overflow: hidden;
        }
        @media (max-width: 1080px) {
            .admin-app-layout,
            .admin-app-progress {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .admin-app-review {
                padding: 1rem;
            }
            .admin-app-header,
            .admin-app-card {
                padding: 1rem;
            }
            .admin-app-header-top,
            .admin-app-card-head {
                display: grid;
            }
            .admin-app-header-meta {
                justify-items: start;
            }
            .admin-app-detail-grid,
            .admin-app-stat-grid,
            .admin-app-form-grid,
            .admin-app-actions,
            .admin-app-map-modal__meta {
                grid-template-columns: 1fr;
            }
            .admin-app-map-modal__dialog {
                width: calc(100vw - 1rem);
                margin: 2vh auto;
            }
        }
    </style>
@endpush

<x-app-layout>
    @php
        $installation = $application->installation;
        $proofPhotos = $installation ? collect($installation->proofPhotos())->filter() : collect();
        $installationLogs = $installation ? $installation->logs : collect();
        $serviceabilityStatus = old('serviceability_status', $application->serviceability_status ?? 'pending');
        $slotAvailabilityStatus = old('slot_availability_status', $application->slot_availability_status ?? 'pending');
        $assessmentNotes = old('assessment_notes', $application->assessment_notes ?? '');
        $rejectionReason = old('rejection_reason', $application->rejection_reason ?? '');
        $selectedInstallationTime = old('installation_time', optional($installation)->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('H:i') : '');
        $selectedTechnicianId = old('technician_id', optional($installation)->technician_id);
        $selectedInstallationDate = old('installation_date', optional($installation)->installation_date ? optional($installation->installation_date)->format('Y-m-d') : '');
        $availableTimeSlots = \App\Models\Installation::AVAILABLE_TIME_SLOTS;
        $statusKey = $application->status;
        $fullName = trim($application->first_name . ' ' . $application->last_name);
        $fullAddress = $application->fullServiceAddress() ?: 'Not provided';
        $hasLocation = filled($application->lat) && filled($application->lng);
        $osmUrl = $hasLocation
            ? 'https://www.openstreetmap.org/?mlat=' . $application->lat . '&mlon=' . $application->lng . '#map=16/' . $application->lat . '/' . $application->lng
            : null;
        $showRejectionReason = old('status', $application->status) === 'rejected' || filled($rejectionReason);
        $statusLabel = ucfirst(str_replace('_', ' ', $statusKey));
        $statusClass = match ($statusKey) {
            'submitted' => 'admin-app-status--submitted',
            'under_review' => 'admin-app-status--under_review',
            'approved' => 'admin-app-status--approved',
            'scheduled' => 'admin-app-status--scheduled',
            'installed' => 'admin-app-status--installed',
            'completed' => 'admin-app-status--completed',
            'rejected' => 'admin-app-status--rejected',
            default => 'admin-app-status--pending',
        };
        $isReviewState = in_array($statusKey, ['submitted', 'under_review'], true);
        $isApprovedState = $statusKey === 'approved';
        $isScheduledState = $statusKey === 'scheduled';
        $isCompletedState = in_array($statusKey, ['installed', 'completed'], true);
        $isRejectedState = $statusKey === 'rejected';
        $showReviewSummary = $application->assessed_at || filled($application->assessment_notes) || filled($application->serviceability_status) || filled($application->slot_availability_status) || filled($application->rejection_reason);
        $showScheduleSummary = $installation && ($isScheduledState || $isCompletedState);
        $submittedSummary = match ($statusKey) {
            'submitted' => 'This application is ready for manual review and decision.',
            'under_review' => 'Assessment is in progress. Review details can still be updated.',
            'approved' => 'The review is complete. The next step is assigning a technician and installation slot.',
            'scheduled' => 'The application is approved and already scheduled for installation.',
            'installed', 'completed' => 'This application has moved into a final installation record.',
            'rejected' => 'The review is complete and the application will not proceed to scheduling.',
            default => 'Review the application details and current processing state.',
        };
        $reviewPanelTitle = match (true) {
            $isReviewState => 'Review Decision',
            $isApprovedState => 'Technician Scheduling',
            $isScheduledState => 'Schedule Summary',
            $isCompletedState => 'Installation Summary',
            $isRejectedState => 'Review Summary',
            default => 'Application Status',
        };
        $stepIndexMap = [
            'submitted' => 1,
            'under_review' => 1,
            'approved' => 2,
            'scheduled' => 3,
            'installed' => 4,
            'completed' => 4,
        ];
        $currentStepIndex = $stepIndexMap[$statusKey] ?? 0;
        $progressSteps = [
            'submitted' => ['label' => 'Submitted', 'description' => 'Application received'],
            'approved' => ['label' => 'Approved', 'description' => 'Review complete'],
            'scheduled' => ['label' => 'Scheduled', 'description' => 'Visit assigned'],
            'installed' => ['label' => 'Installed', 'description' => 'Job completed'],
        ];
        $activeScheduleMatrix = ($activeSchedules ?? collect())
            ->groupBy('technician_id')
            ->map(fn ($technicianSchedules) => $technicianSchedules
                ->groupBy(fn ($schedule) => optional($schedule->installation_date)->format('Y-m-d'))
                ->map(fn ($daySchedules) => $daySchedules
                    ->map(fn ($schedule) => [
                        'time' => \Carbon\Carbon::parse($schedule->installation_time)->format('H:i'),
                        'status' => $schedule->status,
                    ])
                    ->values()
                )
            );
        $reviewSummaryRows = [
            'Serviceability' => ucfirst(str_replace('_', ' ', $application->serviceability_status ?? 'pending')),
            'Slot availability' => ucfirst(str_replace('_', ' ', $application->slot_availability_status ?? 'pending')),
            'Assessed at' => $application->assessed_at?->displayDateTime('F d, Y h:i A') ?? 'Not recorded',
            'Assessed by' => $application->assessor?->name ?? 'Not recorded',
        ];
        $scheduleSummaryRows = $installation ? [
            'Assigned technician' => $installation->technician?->name ?? 'Not assigned',
            'Technician email' => $installation->technician?->email ?? 'Not recorded',
            'Scheduled date' => $installation->installation_date?->format('F d, Y') ?? 'Not scheduled',
            'Scheduled time' => $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('g:i A') : 'Time not set',
        ] : [];
        $installationSummaryRows = $installation ? [
            'Status' => $installation->displayStatusLabel(),
            'Assigned technician' => $installation->technician?->name ?? 'Not assigned',
            'Scheduled date' => $installation->installation_date?->format('F d, Y') ?? 'Not scheduled',
            'Scheduled time' => $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('g:i A') : 'Time not set',
            'Completed at' => $installation->completed_at?->displayDateTime('F d, Y h:i A') ?? 'Not completed yet',
        ] : [];
    @endphp

    <div class="admin-app-review">
        <section class="admin-app-header">
            <div class="admin-app-header-top">
                <div>
                    <div class="admin-app-kicker">Application Review</div>
                    <h1>Application Details</h1>
                    <p class="admin-app-subtitle">{{ $submittedSummary }}</p>
                </div>
                <div class="admin-app-header-meta">
                    <span class="admin-app-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    <div class="admin-app-ref">{{ $application->reference_no }}</div>
                </div>
            </div>

            <div class="admin-app-progress" aria-label="Application progress">
                @foreach($progressSteps as $stepKey => $step)
                    @php
                        $stepNumber = $loop->iteration;
                        $isComplete = ! $isRejectedState && $stepNumber < $currentStepIndex;
                        $isActive = ! $isRejectedState && $stepNumber === $currentStepIndex;
                    @endphp
                    <div class="admin-app-progress-step {{ $isComplete ? 'is-complete' : '' }} {{ $isActive ? 'is-active' : '' }}">
                        <span>{{ $step['label'] }}</span>
                        <div class="admin-app-progress-line"></div>
                        <div class="admin-app-progress-copy">
                            <strong>{{ $step['label'] }}</strong>
                            <p>{{ $step['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="admin-app-layout">
            <div class="admin-app-column">
                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <div>
                            <h2>Customer Information</h2>
                            <p>Core applicant details for review and contact verification.</p>
                        </div>
                    </div>

                    <div class="admin-app-detail-grid">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Full name</span>
                            <div class="admin-app-value">{{ $fullName ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Selected plan</span>
                            <div class="admin-app-value">{{ $application->plan_code ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Email</span>
                            <div class="admin-app-value">{{ $application->email ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Submitted date</span>
                            <div class="admin-app-value">{{ $application->created_at->displayDateTime('F d, Y h:i A') }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Mobile number</span>
                            <div class="admin-app-value">{{ $application->mobile_number ?: 'Not provided' }}</div>
                        </div>
                    </div>
                </section>

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <div>
                            <h2>Service Address</h2>
                            <p>Address details used to verify the coverage area and install location.</p>
                        </div>
                    </div>

                    <div class="admin-app-detail-grid">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Region</span>
                            <div class="admin-app-value">{{ $application->region ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Province</span>
                            <div class="admin-app-value">{{ $application->province ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Municipality / City</span>
                            <div class="admin-app-value">{{ $application->municipality ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Barangay</span>
                            <div class="admin-app-value">{{ $application->barangay ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Full address</span>
                            <div class="admin-app-value">{{ $fullAddress }}</div>
                        </div>
                    </div>
                </section>

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <div>
                            <h2>Location</h2>
                            <p>Open the saved pin only when needed instead of reserving page space for a large map.</p>
                        </div>
                    </div>

                    @if($hasLocation)
                        <div class="admin-app-location-meta">
                            <span>Latitude: {{ number_format((float) $application->lat, 6) }}</span>
                            <span>Longitude: {{ number_format((float) $application->lng, 6) }}</span>
                        </div>
                        <div>
                            <button type="button" class="btn secondary admin-app-map-trigger" data-open-map-modal>View pinned location</button>
                        </div>
                    @else
                        <div class="admin-app-empty">
                            <span class="admin-app-inline-label">Location unavailable</span>
                            <p class="admin-app-helper">No saved location pin is available for this application.</p>
                        </div>
                    @endif
                </section>
            </div>

            <div class="admin-app-column">
                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <div>
                            <h2>{{ $reviewPanelTitle }}</h2>
                            <p>
                                @if($isReviewState)
                                    Record the assessment and choose the next review state.
                                @elseif($isApprovedState)
                                    Assign a technician and installation slot for the approved application.
                                @elseif($isScheduledState)
                                    Review the schedule that was already assigned.
                                @elseif($isCompletedState)
                                    Review the final installation outcome and activity trail.
                                @else
                                    Review the saved assessment outcome for this application.
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($showReviewSummary)
                        <div class="admin-app-summary-box">
                            <div class="admin-app-detail-grid">
                                @foreach($reviewSummaryRows as $label => $value)
                                    <div class="admin-app-detail">
                                        <span class="admin-app-label">{{ $label }}</span>
                                        <div class="admin-app-value">{{ $value ?: 'Not recorded' }}</div>
                                    </div>
                                @endforeach
                                @if(filled($application->assessment_notes))
                                    <div class="admin-app-detail admin-app-detail--full">
                                        <span class="admin-app-label">Assessment notes</span>
                                        <div class="admin-app-value">{{ $application->assessment_notes }}</div>
                                    </div>
                                @endif
                            </div>

                            @if($isRejectedState && filled($application->rejection_reason))
                                <div class="admin-app-reject-box">
                                    <strong>Rejection Reason</strong>
                                    <p class="admin-app-helper">{{ $application->rejection_reason }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($isReviewState)
                        <form method="POST" action="{{ route('admin.applications.status', $application) }}" class="admin-app-form" id="applicationDecisionForm">
                            @csrf
                            @method('PATCH')

                            <div class="admin-app-form-grid">
                                <div class="admin-app-field">
                                    <label for="serviceability_status">Serviceability</label>
                                    <select id="serviceability_status" name="serviceability_status" required>
                                        <option value="pending" @selected($serviceabilityStatus === 'pending')>Pending Assessment</option>
                                        <option value="serviceable" @selected($serviceabilityStatus === 'serviceable')>Serviceable</option>
                                        <option value="not_serviceable" @selected($serviceabilityStatus === 'not_serviceable')>Not Serviceable</option>
                                    </select>
                                    @error('serviceability_status')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="admin-app-field">
                                    <label for="slot_availability_status">Slot availability</label>
                                    <select id="slot_availability_status" name="slot_availability_status" required>
                                        <option value="pending" @selected($slotAvailabilityStatus === 'pending')>Pending Check</option>
                                        <option value="available" @selected($slotAvailabilityStatus === 'available')>Available</option>
                                        <option value="full" @selected($slotAvailabilityStatus === 'full')>Full</option>
                                        <option value="not_applicable" @selected($slotAvailabilityStatus === 'not_applicable')>Not Applicable</option>
                                    </select>
                                    @error('slot_availability_status')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="admin-app-field admin-app-field--full">
                                    <label for="assessment_notes">Assessment notes</label>
                                    <textarea id="assessment_notes" name="assessment_notes" rows="4" placeholder="Document serviceability checks, site constraints, or slot observations.">{{ $assessmentNotes }}</textarea>
                                    @error('assessment_notes')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="admin-app-field admin-app-field--full {{ $showRejectionReason ? '' : 'is-hidden' }}" id="rejectionReasonField">
                                    <label for="rejection_reason">Rejection reason</label>
                                    <textarea id="rejection_reason" name="rejection_reason" rows="3" placeholder="State clearly why the application is being rejected.">{{ $rejectionReason }}</textarea>
                                    <p class="admin-app-helper">Required when rejecting the application.</p>
                                    @error('rejection_reason')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            @error('status')
                                <span class="error">{{ $message }}</span>
                            @enderror

                            <div class="admin-app-actions">
                                <button type="submit" name="status" value="approved" class="btn success" data-status-action="approved">Approve</button>
                                <button type="submit" name="status" value="under_review" class="btn secondary" data-status-action="under_review">Save as Under Review</button>
                                <button type="submit" name="status" value="rejected" class="btn danger" data-status-action="rejected">Reject</button>
                            </div>
                        </form>
                    @elseif($isApprovedState)
                        <form method="POST" action="{{ route('admin.applications.schedule', $application) }}" class="admin-app-form">
                            @csrf

                            <div class="admin-app-form-grid">
                                <div class="admin-app-field admin-app-field--full">
                                    <label for="technician_id">Technician</label>
                                    <select id="technician_id" name="technician_id" required>
                                        <option value="">Select Technician</option>
                                        @foreach($technicians as $techUser)
                                            <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                {{ $techUser->name }} ({{ $techUser->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="admin-app-helper">Only technician accounts are available for assignment.</p>
                                    @error('technician_id')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="admin-app-field">
                                    <label for="installation_date">Installation date</label>
                                    <input id="installation_date" type="date" name="installation_date" value="{{ $selectedInstallationDate }}" required>
                                    @error('installation_date')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="admin-app-field">
                                    <label for="installation_time">Installation time</label>
                                    <select id="installation_time" name="installation_time" required>
                                        <option value="">Select time slot</option>
                                        @foreach($availableTimeSlots as $timeSlot)
                                            <option value="{{ $timeSlot }}" @selected($selectedInstallationTime === $timeSlot)>{{ \Carbon\Carbon::createFromFormat('H:i', $timeSlot)->format('g:i A') }}</option>
                                        @endforeach
                                    </select>
                                    @error('installation_time')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <p class="admin-app-helper" id="installationTimeHint">Choose a technician and date to load available one-hour slots.</p>

                            @error('schedule')
                                <span class="error">{{ $message }}</span>
                            @enderror

                            <div>
                                <button class="btn">Save Schedule</button>
                            </div>
                        </form>
                    @elseif($showScheduleSummary)
                        <div class="admin-app-stat-grid">
                            @foreach($scheduleSummaryRows as $label => $value)
                                <div class="admin-app-stat">
                                    <span class="admin-app-label">{{ $label }}</span>
                                    <div class="admin-app-value">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    @elseif($isRejectedState)
                        <div class="admin-app-empty">
                            <span class="admin-app-inline-label">Review closed</span>
                            <p class="admin-app-helper">This application has been rejected and is no longer available for scheduling.</p>
                        </div>
                    @endif
                </section>

                @if($isCompletedState && $installation)
                    <section class="admin-app-card">
                        <div class="admin-app-card-head">
                            <div>
                                <h2>Installation Summary</h2>
                                <p>Final schedule, completion, and proof details.</p>
                            </div>
                        </div>

                        <div class="admin-app-stat-grid">
                            @foreach($installationSummaryRows as $label => $value)
                                <div class="admin-app-stat">
                                    <span class="admin-app-label">{{ $label }}</span>
                                    <div class="admin-app-value">{{ $value }}</div>
                                </div>
                            @endforeach
                            <div class="admin-app-stat admin-app-stat--full">
                                <span class="admin-app-label">Technician remarks</span>
                                <div class="admin-app-value">{{ $installation->technician_remarks ?: 'No technician remarks recorded.' }}</div>
                            </div>
                        </div>

                        <div class="admin-app-photo">
                            <span class="admin-app-label">Installation photo</span>
                            @forelse($proofPhotos as $label => $path)
                                @if($path)
                                    <div>
                                        <img src="{{ asset('storage/' . $path) }}" alt="{{ $label }}">
                                    </div>
                                @endif
                            @empty
                                <div class="admin-app-empty">
                                    <p class="admin-app-helper">No installation photo uploaded.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="admin-app-card">
                        <div class="admin-app-card-head">
                            <div>
                                <h2>Installation History</h2>
                                <p>Compact timeline of technician and system updates.</p>
                            </div>
                        </div>

                        @if($installationLogs->isNotEmpty())
                            <div class="admin-app-timeline">
                                @foreach($installationLogs as $log)
                                    <div class="admin-app-timeline-item">
                                        <div class="admin-app-timeline-meta">
                                            {{ $log->created_at?->displayDateTime('F d, Y h:i A') ?? '-' }} | {{ $log->user?->name ?? 'System' }}
                                        </div>
                                        <div class="admin-app-value">{{ $log->notes }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="admin-app-empty">
                                <p class="admin-app-helper">No installation history recorded yet.</p>
                            </div>
                        @endif
                    </section>
                @endif
            </div>
        </div>

        <div class="admin-app-footer">
            <a href="{{ route('admin.applications.index') }}" class="btn secondary">Back to Applications</a>
        </div>
    </div>

    @if($hasLocation)
        <div class="admin-app-map-modal" id="applicationMapModal" aria-hidden="true">
            <div class="admin-app-map-modal__backdrop" data-close-map-modal></div>
            <div class="admin-app-map-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="applicationMapModalTitle">
                <div class="admin-app-map-modal__shell">
                    <div class="admin-app-map-modal__head">
                        <div>
                            <h3 id="applicationMapModalTitle">Pinned Service Location</h3>
                            <p class="admin-app-helper">Review the saved pin and confirm the exact install site when needed.</p>
                        </div>
                        <button type="button" class="admin-app-map-modal__close" data-close-map-modal aria-label="Close pinned location map">&times;</button>
                    </div>

                    <div id="adminApplicationMap" class="js-readonly-location-map admin-app-map" data-lat="{{ $application->lat }}" data-lng="{{ $application->lng }}"></div>

                    <div class="admin-app-map-modal__meta">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Latitude</span>
                            <div class="admin-app-value">{{ number_format((float) $application->lat, 6) }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Longitude</span>
                            <div class="admin-app-value">{{ number_format((float) $application->lng, 6) }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Address</span>
                            <div class="admin-app-value">{{ $fullAddress }}</div>
                        </div>
                    </div>

                    <div>
                        @if($osmUrl)
                            <a href="{{ $osmUrl }}" target="_blank" rel="noopener noreferrer" class="btn secondary admin-app-map-link">Open in OpenStreetMap</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
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

            const decisionForm = document.getElementById('applicationDecisionForm');
            const rejectionReasonField = document.getElementById('rejection_reason');
            const rejectionReasonWrap = document.getElementById('rejectionReasonField');
            const statusButtons = document.querySelectorAll('[data-status-action]');
            const mapModal = document.getElementById('applicationMapModal');
            const mapOpeners = document.querySelectorAll('[data-open-map-modal]');
            const mapClosers = document.querySelectorAll('[data-close-map-modal]');
            const technicianSelect = document.getElementById('technician_id');
            const installationDateInput = document.getElementById('installation_date');
            const installationTimeSelect = document.getElementById('installation_time');
            const installationTimeHint = document.getElementById('installationTimeHint');
            const schedulingConfig = {
                duration: {{ \App\Models\Installation::SLOT_DURATION_MINUTES }},
                slots: @json($availableTimeSlots),
                bookings: @json($activeScheduleMatrix),
            };

            const timeToMinutes = function (timeValue) {
                const [hours, minutes] = String(timeValue || '0:0').split(':').map(Number);
                return (hours * 60) + minutes;
            };

            const formatSlotLabel = function (timeValue) {
                const [hours, minutes] = String(timeValue).split(':').map(Number);
                const period = hours >= 12 ? 'PM' : 'AM';
                const displayHours = hours % 12 || 12;
                return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`;
            };

            const slotConflicts = function (slotTime, existingTime) {
                const newStart = timeToMinutes(slotTime);
                const newEnd = newStart + schedulingConfig.duration;
                const existingStart = timeToMinutes(existingTime);
                const existingEnd = existingStart + schedulingConfig.duration;

                return newStart < existingEnd && newEnd > existingStart;
            };

            const refreshInstallationSlots = function () {
                if (!installationTimeSelect) {
                    return;
                }

                const technicianId = technicianSelect ? technicianSelect.value : '';
                const installationDate = installationDateInput ? installationDateInput.value : '';
                const selectedValue = installationTimeSelect.value;
                const dayBookings = technicianId && installationDate
                    ? (schedulingConfig.bookings?.[technicianId]?.[installationDate] || [])
                    : [];

                let availableCount = 0;

                Array.from(installationTimeSelect.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        option.textContent = 'Select time slot';
                        return;
                    }

                    const slotValue = option.value;
                    const blocked = dayBookings.some(function (booking) {
                        return slotConflicts(slotValue, booking.time);
                    });

                    option.textContent = blocked
                        ? `${formatSlotLabel(slotValue)} (Unavailable)`
                        : formatSlotLabel(slotValue);
                    option.disabled = blocked;
                    option.hidden = blocked;

                    if (!blocked) {
                        availableCount += 1;
                    }
                });

                if (selectedValue) {
                    const currentOption = Array.from(installationTimeSelect.options).find(function (option) {
                        return option.value === selectedValue;
                    });

                    if (!currentOption || currentOption.disabled) {
                        installationTimeSelect.value = '';
                    }
                }

                if (!installationTimeHint) {
                    return;
                }

                if (!technicianId || !installationDate) {
                    installationTimeHint.textContent = 'Choose a technician and date to load available one-hour slots.';
                    return;
                }

                if (availableCount === 0) {
                    installationTimeHint.textContent = 'No available one-hour slots for this technician on the selected date.';
                    return;
                }

                installationTimeHint.textContent = `${availableCount} available one-hour slot${availableCount === 1 ? '' : 's'} for this technician on the selected date.`;
            };

            if (mapModal && mapOpeners.length) {
                const openMapModal = function () {
                    mapModal.classList.add('is-open');
                    mapModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('admin-app-map-open');
                    initReadonlyMaps();

                    const mapElement = document.getElementById('adminApplicationMap');
                    if (mapElement && mapElement._readonlyLeafletMap) {
                        setTimeout(function () {
                            mapElement._readonlyLeafletMap.invalidateSize();
                        }, 120);
                    }
                };

                const closeMapModal = function () {
                    mapModal.classList.remove('is-open');
                    mapModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('admin-app-map-open');
                };

                mapOpeners.forEach(function (button) {
                    button.addEventListener('click', openMapModal);
                });

                mapClosers.forEach(function (button) {
                    button.addEventListener('click', closeMapModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && mapModal.classList.contains('is-open')) {
                        closeMapModal();
                    }
                });
            }

            if (technicianSelect && installationDateInput && installationTimeSelect) {
                technicianSelect.addEventListener('change', refreshInstallationSlots);
                installationDateInput.addEventListener('change', refreshInstallationSlots);
                refreshInstallationSlots();
            }

            if (decisionForm && rejectionReasonField && rejectionReasonWrap && statusButtons.length) {
                const toggleRejectionRequirement = function (status) {
                    const isRejected = status === 'rejected';
                    rejectionReasonField.required = isRejected;
                    rejectionReasonField.setAttribute('aria-required', isRejected ? 'true' : 'false');
                    rejectionReasonWrap.classList.toggle('is-hidden', !isRejected && rejectionReasonField.value.trim() === '');
                };

                statusButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        toggleRejectionRequirement(button.value);
                    });
                });

                rejectionReasonField.addEventListener('input', function () {
                    if (rejectionReasonField.required) {
                        rejectionReasonWrap.classList.remove('is-hidden');
                    }
                });

                toggleRejectionRequirement('{{ old('status', $application->status) }}');
            }
        });
    </script>
@endpush
