@extends('layouts.app')

@section('title','Installations')

@push('styles')
<style>
    .installation-filter-toolbar {
        grid-template-columns: minmax(18rem, 2fr) repeat(5, minmax(8rem, 1fr)) minmax(11.5rem, auto);
    }
    .installation-filter-toolbar .toolbar-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-self: end;
        width: 100%;
        flex-wrap: nowrap;
    }
    .installation-filter-toolbar .toolbar-actions .btn {
        width: 100%;
        white-space: nowrap;
    }
    @media (max-width: 1260px) and (min-width: 768px) {
        .installation-filter-toolbar {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
        .installation-filter-toolbar .ticket-filter-search,
        .installation-filter-toolbar .filter-date-range {
            grid-column: span 3;
        }
        .installation-filter-toolbar > .toolbar-field:not(.ticket-filter-search) {
            grid-column: span 2;
        }
        .installation-filter-toolbar .toolbar-actions {
            grid-column: span 2;
        }
    }
    .admin-install-live-status {
        min-height: 1.1rem;
        margin: 0.55rem 0 0;
        color: var(--color-text-muted);
        font-size: 0.8rem;
    }
    .admin-install-live-status.is-error {
        color: #b42318;
    }
    [data-installation-results] {
        display: grid;
        gap: 1rem;
        transition: opacity 0.18s ease;
    }
    [data-installation-results][aria-busy="true"],
    [data-installation-status-nav][aria-busy="true"] {
        opacity: 0.55;
        pointer-events: none;
    }
    .admin-installations-table th:nth-child(2) { width: 14%; }
    .admin-installations-table th:nth-child(4) { width: 15%; }
    .admin-installations-table th:nth-child(5) { width: 16%; }
    .admin-installations-table th:nth-child(6) { width: 14%; }
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
    @media (max-width: 767px) {
        .installation-filter-toolbar {
            grid-template-columns: minmax(0, 1fr);
        }
        .installation-filter-toolbar .filter-date-range {
            grid-column: 1 / -1;
        }
        .installation-filter-toolbar .toolbar-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            width: 100%;
            justify-content: flex-start;
        }
    }
    @media (max-width: 520px) {
        .installation-filter-toolbar .toolbar-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .installation-filter-toolbar .toolbar-actions .btn {
            width: 100%;
        }
    }
    @media (max-width: 980px) {
        .admin-installations-table .address-preview {
            max-width: 18rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $emptyMessage = $search !== ''
        ? 'No installations matched your search.'
        : match ($status) {
            'in_progress' => 'No in-progress installation jobs yet.',
            'reschedule_required' => 'No installation jobs currently require rescheduling.',
            'activated' => 'No activated installation jobs yet.',
            'completed' => 'No installed installation jobs yet.',
            default => match ($selectedGroup) {
                'completed' => 'No installed installation jobs yet.',
                'scheduled' => 'No scheduled installation jobs yet.',
                default => 'No installation records yet.',
            },
        };
    $activeFilterParameters = $status
        ? ['status' => $status]
        : ['group' => $selectedGroup];
@endphp
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Installations</h1>
            <p>Monitor field schedules, technician assignments, and completion status from one cleaner operational list.</p>
        </div>
    </section>

    <section class="card ticket-queue-overview installation-queue-overview">
        <div class="ticket-queue-overview__head">
            <form method="GET" action="{{ route('admin.installations.index') }}" class="toolbar-row ticket-filter-toolbar installation-filter-toolbar" role="search" data-live-installation-search>
                @foreach($activeFilterParameters as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-installation-filter-param>
                @endforeach
                <div class="toolbar-field grow ticket-filter-search">
                    <label for="installation_search">Search Installations</label>
                    <input
                        id="installation_search"
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Job number, customer, contact, address, plan, technician, or status"
                        autocomplete="off"
                        aria-controls="installationResults"
                        data-live-installation-search-input
                    >
                </div>
                <div class="filter-date-range" role="group" aria-labelledby="installation_date_range_label">
                    <span id="installation_date_range_label" class="filter-date-range__label">Scheduled date</span>
                    <div class="filter-date-range__fields">
                        <div class="toolbar-field"><label for="installation_date_from">From</label><input id="installation_date_from" type="date" name="date_from" value="{{ $dateFrom }}"></div>
                        <div class="toolbar-field"><label for="installation_date_to">To</label><input id="installation_date_to" type="date" name="date_to" value="{{ $dateTo }}"></div>
                    </div>
                </div>
                <div class="toolbar-field"><label for="installation_technician">Technician</label><select id="installation_technician" name="technician_id"><option value="">All technicians</option>@foreach($filterTechnicians as $technician)<option value="{{ $technician->id }}" @selected((int) $technicianId === $technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
                <label class="toolbar-field"><span>Rescheduled</span><select name="rescheduled"><option value="0">All records</option><option value="1" @selected($rescheduledOnly)>Rescheduled only</option></select></label>
                <label class="toolbar-field"><span>Action</span><select name="requires_action"><option value="0">All records</option><option value="1" @selected($requiresAction)>Requires action</option></select></label>
                <div class="toolbar-actions">
                    <button type="submit" class="btn">Search</button>
                    <button type="button" class="btn secondary" data-live-installation-search-clear @if($search === '' && blank($dateFrom) && blank($dateTo) && blank($technicianId) && ! $rescheduledOnly && ! $requiresAction) hidden @endif>Reset</button>
                </div>
            </form>
            <p class="admin-install-live-status" data-live-installation-search-status aria-live="polite">Results update automatically.</p>
            <x-active-filter-summary :labels="[
                $status ? 'Status: '.str_replace('_', ' ', ucfirst($status)) : null,
                $search ? 'Search: '.$search : null,
                $dateFrom ? 'From: '.$dateFrom : null,
                $dateTo ? 'To: '.$dateTo : null,
                $technicianId ? 'Technician: '.optional($filterTechnicians->firstWhere('id', $technicianId))->name : null,
                $rescheduledOnly ? 'Rescheduled only' : null,
                $requiresAction ? 'Requires action' : null,
            ]" :reset-url="route('admin.installations.index')" />
        </div>

        <nav class="ticket-queue-overview__grid installation-progress-grid" aria-label="Filter installations by status" data-installation-status-nav aria-busy="false">
            <a
                href="{{ route('admin.installations.index', array_filter(array_merge(request()->except(['status', 'group', 'page']), ['group' => 'all']))) }}"
                class="ticket-queue-metric {{ ! $status && $selectedGroup === 'all' ? 'is-active' : '' }}"
                data-installation-status-card
                @if(! $status && $selectedGroup === 'all') aria-current="page" @endif
            >
                <span class="ticket-queue-metric__label">All</span>
                <strong class="ticket-queue-metric__value">{{ $allCount }}</strong>
            </a>
            <a
                href="{{ route('admin.installations.index', array_filter(array_merge(request()->except(['status', 'group', 'page']), ['group' => 'scheduled']))) }}"
                class="ticket-queue-metric {{ ! $status && $selectedGroup === 'scheduled' ? 'is-active' : '' }}"
                data-installation-status-card
                @if(! $status && $selectedGroup === 'scheduled') aria-current="page" @endif
            >
                <span class="ticket-queue-metric__label">Scheduled</span>
                <strong class="ticket-queue-metric__value">{{ $scheduledCount }}</strong>
            </a>
            <a
                href="{{ route('admin.installations.index', array_filter(array_merge(request()->except(['status', 'group', 'page']), ['status' => 'in_progress']))) }}"
                class="ticket-queue-metric {{ $status === 'in_progress' ? 'is-active' : '' }}"
                data-installation-status-card
                @if($status === 'in_progress') aria-current="page" @endif
            >
                <span class="ticket-queue-metric__label">In Progress</span>
                <strong class="ticket-queue-metric__value">{{ $inProgressCount }}</strong>
            </a>
            <a
                href="{{ route('admin.installations.index', array_filter(array_merge(request()->except(['status', 'group', 'page']), ['status' => 'reschedule_required']))) }}"
                class="ticket-queue-metric {{ $status === 'reschedule_required' ? 'is-active' : '' }}"
                data-installation-status-card
                @if($status === 'reschedule_required') aria-current="page" @endif
            >
                <span class="ticket-queue-metric__label">Reschedule</span>
                <strong class="ticket-queue-metric__value">{{ $rescheduleCount }}</strong>
            </a>
            <a
                href="{{ route('admin.installations.index', array_filter(array_merge(request()->except(['status', 'group', 'page']), ['group' => 'completed']))) }}"
                class="ticket-queue-metric {{ ! $status && $selectedGroup === 'completed' ? 'is-active' : '' }}"
                data-installation-status-card
                @if(! $status && $selectedGroup === 'completed') aria-current="page" @endif
            >
                <span class="ticket-queue-metric__label">Installed</span>
                <strong class="ticket-queue-metric__value">{{ $completedCount }}</strong>
            </a>
            <a
                href="{{ route('admin.installations.index', array_filter(array_merge(request()->except(['status', 'group', 'page']), ['status' => 'activated']))) }}"
                class="ticket-queue-metric {{ $status === 'activated' ? 'is-active' : '' }}"
                data-installation-status-card
                @if($status === 'activated') aria-current="page" @endif
            >
                <span class="ticket-queue-metric__label">Activated</span>
                <strong class="ticket-queue-metric__value">{{ $activatedCount }}</strong>
            </a>
        </nav>
    </section>

    <div id="installationResults" data-installation-results aria-busy="false">
    <section class="card table-card">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable records table">
            <table class="telecom-table admin-installations-table" data-mobile-cards>
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Contact</th>
                        <th>Address</th>
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
                                ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i'))
                                : null;
                            $statusLabel = ucfirst(str_replace('_', ' ', $installation->status));
                        @endphp
                        <tr>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong record-reference">{{ method_exists($installation, 'displayJobReference') ? $installation->displayJobReference() : '#'.$installation->id }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $customerName }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="record-strong">{{ $application?->planDisplayName() ?: 'No plan' }}</span>
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
                                    <a href="{{ route('admin.applications.show', $application) }}" class="btn secondary">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state compact">{{ $emptyMessage }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if(method_exists($installations, 'hasPages') && $installations->hasPages())
        <section class="card toolbar-card">
            <x-ui.pagination :paginator="$installations" />
        </section>
    @endif
    </div>
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('js/admin-installations.js') }}?v={{ filemtime(public_path('js/admin-installations.js')) }}"></script>
@endpush
