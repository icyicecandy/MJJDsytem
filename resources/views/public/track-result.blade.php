<x-app-layout>
    <x-slot name="header">
        <h2>Application Tracking Result</h2>
        <p>Reference: {{ $application->reference_no }}</p>
    </x-slot>

    @php
        $steps = ['submitted', 'under_review', 'approved', 'scheduled', 'installed'];
        $currentIndex = array_search($application->status, $steps, true);
        $currentIndex = $currentIndex === false ? null : $currentIndex;
    @endphp

    <div class="tracking-shell">
        <div class="tracking-card">
            <h3>Current Status</h3>
            <p><span class="badge {{ $application->status }}">{{ strtoupper(str_replace('_', ' ', $application->status)) }}</span></p>
            <div class="tracking-grid mt-1">
                <div class="tracking-meta"><strong>Reference Number</strong>{{ $application->reference_no }}</div>
                <div class="tracking-meta"><strong>Plan</strong>{{ $application->plan_code }}</div>
                <div class="tracking-meta"><strong>Service Area</strong>{{ $application->barangay }}, {{ $application->municipality }}</div>
                <div class="tracking-meta"><strong>Submitted</strong>{{ $application->created_at->displayDateTime() }}</div>
            </div>
        </div>

        <div class="tracking-card">
            <h3>Progress</h3>
            <div class="progress-list">
                @foreach($steps as $index => $step)
                    <div class="progress-step {{ $currentIndex !== null && $index <= $currentIndex ? 'is-complete' : '' }}">
                        <span class="progress-dot"></span>
                        <span>{{ ucfirst(str_replace('_', ' ', $step)) }}</span>
                    </div>
                @endforeach
                @if($application->status === 'rejected')
                    <div class="progress-step is-alert">
                        <span class="progress-dot"></span>
                        <span>Rejected</span>
                    </div>
                @endif
            </div>
        </div>

        @if($application->installation)
            <div class="tracking-card">
                <h3>Installation Schedule</h3>
                <div class="tracking-grid">
                    <div class="tracking-meta"><strong>Date</strong>{{ $application->installation->installation_date }}</div>
                    <div class="tracking-meta"><strong>Time</strong>{{ $application->installation->installation_time ?? 'TBA' }}</div>
                </div>
            </div>
        @endif

        <a href="{{ route('track.form') }}" class="btn secondary">Track Another Application</a>
    </div>
</x-app-layout>
