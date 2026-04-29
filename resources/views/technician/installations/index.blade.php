@push('styles')
<style>
    .tech-install-shell {
        display: grid;
        gap: 1.25rem;
    }
    .tech-install-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
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
    .tech-install-kpi strong {
        display: block;
        font-size: 1.5rem;
        margin-top: 0.35rem;
    }
    .admin-installations-table {
        table-layout: fixed;
    }
    .admin-installations-table th:nth-child(1) {
        width: 11%;
    }
    .admin-installations-table th:nth-child(2) {
        width: 13%;
    }
    .admin-installations-table th:nth-child(3) {
        width: 10%;
    }
    .admin-installations-table th:nth-child(4) {
        width: 15%;
    }
    .admin-installations-table th:nth-child(5) {
        width: 21%;
    }
    .admin-installations-table th:nth-child(6) {
        width: 13%;
    }
    .admin-installations-table th:nth-child(7) {
        width: 9%;
    }
    .admin-installations-table th:nth-child(8) {
        width: 8%;
    }
    .admin-installations-table th,
    .admin-installations-table td {
        padding: 0.68rem 0.55rem;
    }
    .admin-installations-table .record-stack {
        display: grid;
        gap: 0.14rem;
        min-width: 0;
    }
    .admin-installations-table .record-stack .record-strong,
    .admin-installations-table .record-stack .record-muted {
        display: block;
    }
    .admin-installations-table .record-strong {
        font-size: 0.95rem;
        line-height: 1.25;
    }
    .admin-installations-table .record-muted {
        font-size: 0.78rem;
        line-height: 1.28;
    }
    .admin-installations-table .address-preview {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .admin-installations-table .schedule-cell {
        white-space: nowrap;
    }
    .admin-installations-table .actions-cell {
        text-align: right;
        white-space: nowrap;
        padding-left: 0.35rem;
    }
    .admin-installations-table .actions-cell .btn {
        padding: 0.46rem 0.62rem;
        font-size: 0.82rem;
    }
    .admin-installations-table .badge {
        white-space: nowrap;
    }
    @media (max-width: 980px) {
        .admin-installations-table .address-preview {
            max-width: 18rem;
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
                <span class="record-muted tech-install-kpi-label">Installed / Activated</span>
                <strong>{{ $completedInstallations->count() }}</strong>
            </a>
        </section>

        <section class="card table-card">
            <div class="table-wrap">
                <table class="telecom-table admin-installations-table">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Customer</th>
                            <th>Plan</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeInstallations as $installation)
                            @php
                                $application = $installation->application;
                                $customerName = $application
                                    ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? ''))
                                    : 'N/A';
                                $address = $application
                                    ? collect([
                                        $application->barangay,
                                        $application->municipality,
                                        $application->province,
                                    ])->filter()->implode(', ')
                                    : null;
                                $contactNumber = $application?->mobile_number;
                                $contactEmail = $application?->email;
                                $scheduleDate = $installation->installation_date?->format('M d, Y');
                                $scheduleTime = $installation->installation_time
                                    ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A')
                                    : null;
                                $statusLabel = ucfirst(str_replace('_', ' ', $installation->status));
                            @endphp
                            <tr>
                                <td>
                                    <div class="record-stack">
                                        <span class="record-strong">{{ $installation->displayJobReference() }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="record-stack">
                                        <span class="record-strong">{{ $customerName }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="record-strong">{{ $application?->plan_code ?: 'No plan' }}</span>
                                </td>
                                <td>
                                    <div class="record-stack">
                                        <span class="record-strong">{{ $contactNumber ?: 'No contact number' }}</span>
                                        <span class="record-muted">{{ $contactEmail ?: 'No email recorded' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="record-muted address-preview">{{ $address ?: 'Address not available' }}</span>
                                </td>
                                <td class="schedule-cell">
                                    <div class="record-stack">
                                        <span class="record-strong">{{ $scheduleDate ?? 'Not scheduled' }}</span>
                                        <span class="record-muted">{{ $scheduleTime ?? 'Time to be confirmed' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $installation->status ?? 'info' }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="actions-cell">
                                    <a href="{{ route('technician.installations.show', $installation->id) }}" class="btn secondary">
                                        {{ $installation->isFinalized() ? 'Review' : 'Open Job' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state compact">
                                        {{ $selectedGroup === 'completed' ? 'No completed or activated installation jobs yet.' : 'No scheduled or pending jobs assigned yet.' }}
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


