@extends('layouts.app')

@section('title','Installations')

@push('styles')
<style>
    .admin-installations-table {
        table-layout: auto;
    }
    .admin-installations-table th:nth-child(1) {
        width: 18%;
    }
    .admin-installations-table th:nth-child(2) {
        width: 17%;
    }
    .admin-installations-table th:nth-child(3) {
        width: 24%;
    }
    .admin-installations-table th:nth-child(4) {
        width: 14%;
    }
    .admin-installations-table th:nth-child(5) {
        width: 16%;
    }
    .admin-installations-table th:nth-child(6) {
        width: 8%;
    }
    .admin-installations-table th:nth-child(7) {
        width: 1%;
    }
    .admin-installations-table th,
    .admin-installations-table td {
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
    }
    .admin-installations-table .record-stack {
        display: grid;
        gap: 0.2rem;
        min-width: 0;
    }
    .admin-installations-table .record-stack .record-strong,
    .admin-installations-table .record-stack .record-muted {
        display: block;
    }
    .admin-installations-table .record-muted {
        font-size: 0.84rem;
        line-height: 1.35;
    }
    .admin-installations-table .address-preview {
        max-width: 26rem;
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
    }
    .admin-installations-table .badge {
        white-space: nowrap;
    }
    .table-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.15rem 0.2rem;
    }
    .table-card-head p {
        margin: 0;
        color: var(--color-text-muted);
        font-size: 0.9rem;
    }
    @media (max-width: 980px) {
        .admin-installations-table .address-preview {
            max-width: 18rem;
        }
    }
</style>
@endpush

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Installations</h1>
            <p>Monitor field schedules, technician assignments, and completion status from one cleaner operational list.</p>
        </div>
    </section>

    <section class="card toolbar-card">
        <form method="GET" action="{{ route('admin.installations.index') }}" class="toolbar-row">
            <div class="toolbar-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div class="toolbar-field grow">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" value="{{ $search }}"
                       placeholder="Installation ID, app ref, customer, technician">
            </div>

            <div class="toolbar-actions">
                <button class="btn">Filter</button>
                @if(request()->filled('status') || request()->filled('search'))
                    <a href="{{ route('admin.installations.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card table-card">
        <div class="table-card-head">
            <p>Primary list view keeps scheduling and assignment data visible. Address, package, activation, and completion details remain in the existing detail page.</p>
        </div>
        <div class="table-wrap">
            <table class="telecom-table admin-installations-table">
                <thead>
                    <tr>
                        <th>Job / Installation</th>
                        <th>Application Reference</th>
                        <th>Customer</th>
                        <th>Technician</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($installations as $installation)
                        @php
                            $application = $installation->application;
                            $customerName = $application
                                ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? ''))
                                : 'N/A';
                            $address = $application
                                ? trim(collect([
                                    $application->street_landmark,
                                    $application->barangay,
                                    $application->municipality,
                                ])->filter()->implode(', '))
                                : null;
                            $scheduleDate = $installation->installation_date?->format('M d, Y');
                            $scheduleTime = $installation->installation_time
                                ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A')
                                : null;
                            $statusLabel = ucfirst(str_replace('_', ' ', $installation->status));
                        @endphp
                        <tr>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ method_exists($installation, 'displayJobReference') ? $installation->displayJobReference() : '#'.$installation->id }}</span>
                                    <span class="record-muted">Internal ID: #{{ $installation->id }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    @if($application)
                                        <a href="{{ route('admin.applications.show', $application) }}" class="record-strong">{{ $application->reference_no ?? 'N/A' }}</a>
                                        <span class="record-muted">Application record</span>
                                    @else
                                        <span class="record-strong">N/A</span>
                                        <span class="record-muted">No linked application</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $customerName }}</span>
                                    <span class="record-muted address-preview">{{ $address ?: 'Address not available' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $installation->technician->name ?? 'Unassigned' }}</span>
                                    <span class="record-muted">{{ $installation->technician?->email ?? 'Awaiting assignment' }}</span>
                                </div>
                            </td>
                            <td class="schedule-cell">
                                <div class="record-stack">
                                    <span class="record-strong">{{ $scheduleDate ?? 'Not scheduled' }}</span>
                                    <span class="record-muted">{{ $scheduleTime ?? 'Time to be confirmed' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $installation->status }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="actions-cell">
                                @if($application)
                                    <a href="{{ route('admin.applications.show', $application) }}" class="btn secondary">Open</a>
                                @else
                                    <span class="record-muted">No detail</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state compact">No installations matched the current filter.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if(method_exists($installations, 'links'))
        <section class="card toolbar-card">
            {{ $installations->links() }}
        </section>
    @endif
</div>
@endsection
