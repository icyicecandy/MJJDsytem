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
                $statusLabel = ucfirst(str_replace('_', ' ', $status));
                $stepIndexMap = [
                    'submitted' => 1,
                    'under_review' => 2,
                    'approved' => 3,
                    'scheduled' => 4,
                    'installed' => 5,
                ];
                $currentStepIndex = $stepIndexMap[$status] ?? 0;
                $progressSteps = [
                    'submitted' => ['title' => 'Application Submitted', 'description' => 'Your request is now in the queue for initial review.'],
                    'under_review' => ['title' => 'Under Review', 'description' => 'Our team is checking your details and serviceability.'],
                    'approved' => ['title' => 'Approved', 'description' => 'Your application passed review and is ready for scheduling.'],
                    'scheduled' => ['title' => 'Installation Scheduled', 'description' => 'A technician schedule has been prepared for your installation.'],
                    'installed' => ['title' => 'Installed', 'description' => 'Your service installation has been completed.'],
                ];
                $statusSummary = match ($status) {
                    'submitted' => 'Your application has been received and is waiting for review.',
                    'under_review' => 'Your application is currently being reviewed by MJJD Telecom.',
                    'approved' => 'Your application is approved and ready for installation scheduling.',
                    'scheduled' => 'Your installation has been scheduled. Please prepare for the visit.',
                    'installed' => 'Your line has been installed successfully.',
                    'rejected' => 'Your application was not approved. Review the reason below before reapplying.',
                    default => 'Your application is being processed.',
                };
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
                    <div class="tracking-meta"><strong>Plan</strong>{{ $application->plan_code }}</div>
                    <div class="tracking-meta"><strong>Service Area</strong>{{ $application->barangay }}, {{ $application->municipality }}</div>
                    <div class="tracking-meta"><strong>Address</strong>{{ $fullAddress ?: 'Not provided' }}</div>
                </div>

                @if($application->installation)
                    <div class="application-highlight-row">
                        <div class="tracking-meta">
                            <strong>Installation Date</strong>
                            {{ \Illuminate\Support\Carbon::parse($application->installation->installation_date)->format('F j, Y') }}
                        </div>
                        <div class="tracking-meta">
                            <strong>Installation Time</strong>
                            {{ \Illuminate\Support\Carbon::parse($application->installation->installation_time)->format('g:i A') }}
                        </div>
                    </div>
                @endif

                @if($status === 'rejected')
                    <div class="application-alert-card is-danger">
                        <strong>Rejection Reason</strong>
                        <p>{{ $application->rejection_reason ?? 'No reason was recorded for this application.' }}</p>
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
            </div>
        @else
            <div class="empty-state">No application found.</div>
        @endif
    </section>
</div>
@endsection
