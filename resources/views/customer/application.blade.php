@extends('layouts.app')

@section('title','My Application')

@section('content')
<div class="page-cluster content-narrow">
    <section class="card section-title-card">
        <div>
            <h1>My Application</h1>
            <p>Review your latest service application, current progress status, and the key details used for processing.</p>
        </div>
    </section>

    <section class="card">
        @if($application)
            @php
                $status = $application->status;
                $installation = $application->installation;
                $statusLabel = ucfirst(str_replace('_', ' ', $status));
                $stepIndexMap = [
                    'submitted' => 1,
                    'under_review' => 2,
                    'approved' => 3,
                    'scheduled' => 4,
                    'cancellation_requested' => 4,
                    'installed' => 5,
                    'activated' => 6,
                ];
                $currentStepIndex = $stepIndexMap[$status] ?? 0;
                $progressSteps = [
                    'submitted' => ['title' => 'Application Submitted', 'description' => 'Your request is now in the queue for initial review.'],
                    'under_review' => ['title' => 'Under Review', 'description' => 'Our team is checking your details and serviceability.'],
                    'approved' => ['title' => 'Approved', 'description' => 'Your application passed review and is ready for scheduling.'],
                    'scheduled' => ['title' => 'Installation Scheduled', 'description' => 'A technician schedule has been prepared for your installation.'],
                    'installed' => ['title' => 'Installed', 'description' => 'Your service installation has been completed.'],
                    'activated' => ['title' => 'Activated', 'description' => 'Your service has been activated.'],
                ];
                $statusSummary = match ($status) {
                    'submitted' => 'Your application has been received and is waiting for review.',
                    'under_review' => 'Your application is currently being reviewed by MJJD Telecom.',
                    'approved' => 'Your application is approved and ready for installation scheduling.',
                    'scheduled' => 'Your installation has been scheduled. Please prepare for the visit.',
                    'cancellation_requested' => 'Your cancellation request is waiting for admin review.',
                    'installed' => 'Your line has been installed successfully.',
                    'activated' => 'Your service is activated and ready to use.',
                    'rejected' => 'Your application was not approved. Review the reason below before reapplying.',
                    'cancelled' => 'Your application was cancelled.',
                    default => 'Your application is being processed.',
                };
                $canRequestCancellation = in_array($status, ['submitted', 'under_review', 'approved', 'scheduled'], true);
                $rescheduleAvailability = $rescheduleAvailability ?? [];
                $hasRescheduleAvailability = $rescheduleAvailability !== [];
                $hasPendingRescheduleRequest = $installation?->hasPendingCustomerRescheduleRequest() ?? false;
                $scheduledStart = $installation?->scheduledStartAt();
                $canRequestReschedule = $status === 'scheduled'
                    && $installation?->status === \App\Models\Installation::STATUS_SCHEDULED
                    && ! $hasPendingRescheduleRequest
                    && $scheduledStart
                    && $scheduledStart->gt(now()->addDay())
                    && $hasRescheduleAvailability;
                $rescheduleBlockedByTime = $status === 'scheduled'
                    && $installation?->status === \App\Models\Installation::STATUS_SCHEDULED
                    && ! $hasPendingRescheduleRequest
                    && $scheduledStart
                    && $scheduledStart->lte(now()->addDay());
                $rescheduleUnavailableNoSlots = $status === 'scheduled'
                    && $installation?->status === \App\Models\Installation::STATUS_SCHEDULED
                    && ! $hasPendingRescheduleRequest
                    && $scheduledStart
                    && $scheduledStart->gt(now()->addDay())
                    && ! $hasRescheduleAvailability;
                $selectedRescheduleDate = old('reschedule_preferred_date');
                $selectedRescheduleTime = old('reschedule_preferred_time');
                $selectedRescheduleDateSlots = $selectedRescheduleDate
                    ? data_get($rescheduleAvailability, $selectedRescheduleDate . '.slots', [])
                    : [];
                $latestRescheduleRejected = $installation?->reschedule_reviewed_at && filled($installation->reschedule_rejection_reason);
                $fullAddress = collect([
                    $application->street_landmark,
                    $application->barangay,
                    $application->municipality,
                ])->filter()->implode(', ');
            @endphp

            <div class="application-status-shell">
                <section class="application-status-hero">
                    <div>
                        <span class="application-status-kicker">Current Application Status</span>
                        <h2>{{ $statusLabel }}</h2>
                        <p>{{ $statusSummary }}</p>
                    </div>
                    <div class="application-status-badge-wrap">
                        <span class="badge {{ $status }}">{{ $statusLabel }}</span>
                        <small>Reference {{ $application->reference_no }}</small>
                    </div>
                </section>

                <div class="tracking-grid application-detail-grid">
                    <div class="tracking-meta"><strong>Reference</strong>{{ $application->reference_no }}</div>
                    <div class="tracking-meta"><strong>Submitted At</strong>{{ $application->created_at->displayDateTime('F d, Y h:i A') }}</div>
                    <div class="tracking-meta"><strong>Plan</strong>{{ $application->plan_code }}</div>
                    <div class="tracking-meta"><strong>Service Area</strong>{{ $application->barangay }}, {{ $application->municipality }}</div>
                    <div class="tracking-meta"><strong>Address</strong>{{ $fullAddress ?: 'Not provided' }}</div>
                </div>

                @if($installation)
                    <div class="application-highlight-row">
                        <div class="tracking-meta">
                            <strong>Installation Date</strong>
                            {{ \Illuminate\Support\Carbon::parse($installation->installation_date)->format('F j, Y') }}
                        </div>
                        <div class="tracking-meta">
                            <strong>Installation Time</strong>
                            {{ \Illuminate\Support\Carbon::parse($installation->installation_time)->format('g:i A') }}
                        </div>
                        <div class="tracking-meta">
                            <strong>Activation Date</strong>
                            {{ $installation->activation_date?->format('F j, Y') ?? 'Not activated yet' }}
                        </div>
                    </div>
                @endif

                @if($status === 'rejected')
                    <div class="application-alert-card is-danger">
                        <strong>Rejection Reason</strong>
                        <p>{{ $application->rejection_reason ?? 'No reason was recorded for this application.' }}</p>
                    </div>
                @endif

                @if($status === 'cancellation_requested')
                    <div class="application-alert-card">
                        <strong>Cancellation Requested</strong>
                        <p>Your installation remains scheduled until an admin approves the cancellation request.</p>
                    </div>
                @endif

                @if($hasPendingRescheduleRequest)
                    <div class="application-alert-card">
                        <strong>Reschedule Requested</strong>
                        <p>
                            Preferred schedule:
                            {{ $installation->reschedule_preferred_date?->format('F j, Y') ?? 'Date not set' }}
                            at
                            {{ $installation->reschedule_preferred_time ? \Illuminate\Support\Carbon::parse($installation->reschedule_preferred_time)->format('g:i A') : 'time not set' }}.
                            Admin review is required before your installation schedule changes.
                        </p>
                    </div>
                @elseif($latestRescheduleRejected)
                    <div class="application-alert-card is-danger">
                        <strong>Reschedule Request Rejected</strong>
                        <p>{{ $installation->reschedule_rejection_reason }}</p>
                    </div>
                @endif

                <section class="application-progress-card">
                    <div class="application-progress-head">
                        <div>
                            <h3>Application Progress</h3>
                            <p>Track where your service request is in the approval and installation process.</p>
                        </div>
                    </div>

                    @if($status === 'rejected')
                        <div class="application-rejected-state">
                            <div class="application-rejected-icon">!</div>
                            <div>
                                <strong>Application Stopped at Review</strong>
                                <p>The workflow did not continue because this application was rejected.</p>
                            </div>
                        </div>
                    @endif

                    <div class="application-progress-list">
                        @foreach($progressSteps as $stepKey => $step)
                            @php
                                $stepNumber = $loop->iteration;
                                $isComplete = $status !== 'rejected' && $stepNumber < $currentStepIndex;
                                $isActive = $status !== 'rejected' && $stepKey === $status;
                            @endphp
                            <article class="application-progress-item {{ $isComplete ? 'is-complete' : '' }} {{ $isActive ? 'is-active' : '' }}">
                                <div class="application-progress-marker">
                                    @if($isComplete)
                                        &#10003;
                                    @else
                                        {{ $stepNumber }}
                                    @endif
                                </div>
                                <div class="application-progress-copy">
                                    <h4>{{ $step['title'] }}</h4>
                                    <p>{{ $step['description'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="application-status-shell">
                    @error('reschedule')
                        <div class="application-alert-card is-danger">
                            <strong>Reschedule Request</strong>
                            <p>{{ $message }}</p>
                        </div>
                    @enderror

                    @if($canRequestReschedule)
                        <form method="POST" action="{{ route('customer.application.reschedule') }}" class="application-alert-card">
                            @csrf
                            @method('PATCH')

                            <strong>Request Reschedule</strong>
                            <p>Choose your preferred new installation date and time. Your current schedule stays active until admin approves the request.</p>

                            <div class="apply-form-grid mt-3">
                                <div class="apply-field">
                                    <label for="reschedule_preferred_date">Preferred date</label>
                                    <select id="reschedule_preferred_date" name="reschedule_preferred_date" required data-reschedule-date>
                                        <option value="">Select available date</option>
                                        @foreach($rescheduleAvailability as $dateValue => $dateAvailability)
                                            <option value="{{ $dateValue }}" @selected($selectedRescheduleDate === $dateValue)>{{ $dateAvailability['label'] ?? $dateValue }}</option>
                                        @endforeach
                                    </select>
                                    @error('reschedule_preferred_date')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="apply-field">
                                    <label for="reschedule_preferred_time">Preferred time</label>
                                    <select id="reschedule_preferred_time" name="reschedule_preferred_time" required data-reschedule-time {{ $selectedRescheduleDate ? '' : 'disabled' }}>
                                        <option value="">Select time slot</option>
                                        @foreach($selectedRescheduleDateSlots as $timeSlot)
                                            <option value="{{ $timeSlot['value'] }}" @selected($selectedRescheduleTime === $timeSlot['value'])>{{ $timeSlot['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-muted mt-1" data-reschedule-slot-hint>Choose a date to see available time slots.</p>
                                    @error('reschedule_preferred_time')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="apply-field full-span">
                                    <label for="reschedule_reason">Reason</label>
                                    <textarea id="reschedule_reason" name="reschedule_reason" rows="3" placeholder="Tell us why you need a new schedule." required>{{ old('reschedule_reason') }}</textarea>
                                    @error('reschedule_reason')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn secondary">Request Reschedule</button>
                            </div>
                        </form>
                    @elseif($rescheduleBlockedByTime)
                        <div class="application-alert-card">
                            <strong>Reschedule Window Closed</strong>
                            <p>Online reschedule requests must be made more than 24 hours before the installation visit. Please contact support for urgent changes.</p>
                        </div>
                    @elseif($rescheduleUnavailableNoSlots)
                        <div class="application-alert-card">
                            <strong>No Available Reschedule Slots</strong>
                            <p>No technician has an open installation slot in the next 30 days. Please contact support for urgent changes.</p>
                        </div>
                    @endif

                    @error('cancellation')
                        <div class="application-alert-card is-danger">
                            <strong>Cancellation Request</strong>
                            <p>{{ $message }}</p>
                        </div>
                    @enderror

                    @if($canRequestCancellation)
                        <form method="POST" action="{{ route('customer.application.cancel') }}" class="application-alert-card">
                            @csrf
                            @method('PATCH')

                            <strong>Request Cancellation</strong>
                            <p>Scheduled installation cancellations are sent to admin for review.</p>
                            <textarea name="cancellation_reason" rows="3" placeholder="Reason for cancellation" required>{{ old('cancellation_reason') }}</textarea>
                            @error('cancellation_reason')
                                <span class="error">{{ $message }}</span>
                            @enderror
                            <div>
                                <button type="submit" class="btn danger">Request Cancellation</button>
                            </div>
                        </form>
                    @endif
                </section>
            </div>
        @else
            <div class="empty-state">No application found.</div>
        @endif
    </section>

    <section class="card">
        <a href="{{ route('customer.dashboard') }}" class="btn secondary">Back</a>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dateSelect = document.querySelector('[data-reschedule-date]');
        const timeSelect = document.querySelector('[data-reschedule-time]');
        const slotHint = document.querySelector('[data-reschedule-slot-hint]');

        if (!dateSelect || !timeSelect) {
            return;
        }

        const availability = @json($rescheduleAvailability ?? []);
        const initialDate = @json(old('reschedule_preferred_date'));
        const initialTime = @json(old('reschedule_preferred_time'));

        function setTimeOptions(preferredTime) {
            const selectedDate = dateSelect.value;
            const slots = availability[selectedDate]?.slots || [];

            timeSelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = slots.length ? 'Select time slot' : 'No available time slots';
            timeSelect.appendChild(placeholder);

            slots.forEach(function (slot) {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.label;
                option.selected = preferredTime && preferredTime === slot.value;
                timeSelect.appendChild(option);
            });

            timeSelect.disabled = slots.length === 0;

            if (slotHint) {
                slotHint.textContent = selectedDate
                    ? `${slots.length} available time slot${slots.length === 1 ? '' : 's'} for this date.`
                    : 'Choose a date to see available time slots.';
            }
        }

        dateSelect.addEventListener('change', function () {
            setTimeOptions('');
        });

        if (initialDate && availability[initialDate]) {
            dateSelect.value = initialDate;
            setTimeOptions(initialTime);
        } else {
            setTimeOptions('');
        }
    });
</script>
@endpush
