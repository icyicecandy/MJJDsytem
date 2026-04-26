@push('styles')
<style>
    .tech-install-shell {
        display: grid;
        gap: 1.25rem;
    }
    .tech-install-kpi {
        display: block;
        padding: 1rem 1.1rem;
        border-radius: 1rem;
        background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.94));
        border: 1px solid rgba(148, 163, 184, 0.18);
        text-decoration: none;
        color: inherit;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    }
    .tech-install-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 28px rgba(37, 99, 235, 0.10);
        border-color: rgba(37, 99, 235, 0.26);
    }
    .tech-install-kpi.active {
        background: linear-gradient(180deg, rgba(232, 240, 255, 0.98), rgba(219, 234, 254, 0.94));
        border-color: rgba(37, 99, 235, 0.34);
        box-shadow: 0 14px 28px rgba(37, 99, 235, 0.12);
    }
    .tech-install-kpi-label {
        display: block;
    }
    .tech-install-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .tech-install-kpi strong {
        display: block;
        font-size: 1.5rem;
        margin-top: 0.35rem;
    }
    .tech-install-list .record-muted {
        margin-top: 0.15rem;
    }
    .job-ref {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.78rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #475569;
    }
    @media (max-width: 768px) {
        .tech-install-kpi {
            width: 100%;
        }
    }
</style>
@endpush

<x-app-layout>
    @php
        $activeInstallations = $selectedGroup === 'completed' ? $completedInstallations : $scheduledInstallations;
    @endphp

    <div class="tech-page tech-install-shell">
        <section class="card tech-page-header">
            <div>
                <h1>My Installations</h1>
                <p>Open active jobs first, keep references visible, and move through each installation with a more operational field workflow.</p>
            </div>
        </section>

        <section class="tech-install-kpis">
            <a href="{{ route('technician.installations.index', ['group' => 'scheduled']) }}" class="tech-install-kpi card {{ $selectedGroup === 'scheduled' ? 'active' : '' }}">
                <span class="record-muted tech-install-kpi-label">Scheduled</span>
                <strong>{{ $scheduledInstallations->count() }}</strong>
            </a>
            <a href="{{ route('technician.installations.index', ['group' => 'completed']) }}" class="tech-install-kpi card {{ $selectedGroup === 'completed' ? 'active' : '' }}">
                <span class="record-muted tech-install-kpi-label">Installed</span>
                <strong>{{ $completedInstallations->count() }}</strong>
            </a>
        </section>

        <section class="card table-card tech-install-list">
            <div class="table-wrap">
                <table class="telecom-table tech-table">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Schedule</th>
                            <th class="col-actions text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeInstallations as $installation)
                            @php
                                $application = $installation->application;
                            @endphp
                            <tr>
                                <td>
                                    <div class="record-strong">{{ $installation->displayJobReference() }}</div>
                                    <div class="record-muted">Application Ref: {{ $application->reference_no ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    @if($application)
                                        <div class="record-strong">{{ trim($application->first_name . ' ' . $application->last_name) }}</div>
                                        <div class="record-muted">{{ $application->barangay ?? 'Service area pending' }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($application)
                                        <div class="record-strong">{{ $application->mobile_number ?: 'Not provided' }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $installation->status ?? 'info' }}">{{ $installation->displayStatusLabel() }}</span>
                                </td>
                                <td>
                                    @if($installation->installation_date)
                                        <div class="record-strong">{{ $installation->installation_date->format('F d, Y') }}</div>
                                        <div class="record-muted">{{ $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A') : 'Time to be confirmed' }}</div>
                                    @else
                                        <div class="record-strong">No schedule yet</div>
                                        <div class="record-muted">Awaiting confirmation</div>
                                    @endif
                                </td>
                                <td class="actions-cell text-end">
                                    <a href="{{ route('technician.installations.show', $installation->id) }}" class="btn secondary">
                                        {{ $installation->isCompleted() ? 'Review' : 'Open Job' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state compact">
                                        {{ $selectedGroup === 'completed' ? 'No completed installation jobs yet.' : 'No scheduled or pending jobs assigned yet.' }}
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>


