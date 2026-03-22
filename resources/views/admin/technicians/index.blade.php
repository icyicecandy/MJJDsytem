@extends('layouts.app')

@section('title','Technicians')

@php
    $statusLabels = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'on_duty' => 'On Duty',
        'off_duty' => 'Off Duty',
        'on_leave' => 'On Leave',
    ];
@endphp

@push('styles')
<style>
    .admin-technicians-table th:nth-child(1) { width: 23%; }
    .admin-technicians-table th:nth-child(2) { width: 17%; }
    .admin-technicians-table th:nth-child(3) { width: 16%; }
    .admin-technicians-table th:nth-child(4) { width: 12%; }
    .admin-technicians-table th:nth-child(5) { width: 14%; }
    .admin-technicians-table th:nth-child(6) { width: 10%; }
    .admin-technicians-table th:nth-child(7) { width: 8%; }
    .admin-technicians-table .record-stack {
        display: grid;
        gap: 0.2rem;
    }
    .admin-technicians-table .record-muted {
        font-size: 0.84rem;
        line-height: 1.35;
    }
    .admin-technicians-table .availability-dot {
        width: 0.65rem;
        height: 0.65rem;
        border-radius: 999px;
        display: inline-block;
        margin-right: 0.45rem;
        vertical-align: middle;
        background: #94a3b8;
    }
    .admin-technicians-table .availability-dot.available { background: #22c55e; }
    .admin-technicians-table .availability-dot.assigned { background: #3b82f6; }
    .admin-technicians-table .availability-dot.on_duty { background: #14b8a6; }
    .admin-technicians-table .availability-dot.off_duty { background: #94a3b8; }
    .admin-technicians-table .availability-dot.on_leave { background: #f59e0b; }
    .admin-technicians-table .next-schedule {
        white-space: nowrap;
    }
</style>
@endpush

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Technicians</h1>
            <p>Review technician records, core contact details, workload, availability, and assigned installation schedules from one admin operations list.</p>
        </div>
    </section>

    <section class="card toolbar-card">
        <form method="GET" action="{{ route('admin.technicians.index') }}" class="toolbar-row">
            <div class="toolbar-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="toolbar-field grow">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Technician name, email, phone, shift, status">
            </div>

            <div class="toolbar-actions">
                <button class="btn">Filter</button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('admin.technicians.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card table-card">
        <div class="table-card-head">
            <p>This list highlights each technician's profile details, assignment load, schedule visibility, and direct access to the full record page.</p>
        </div>
        <div class="table-wrap">
            <table class="telecom-table admin-technicians-table">
                <thead>
                    <tr>
                        <th>Technician</th>
                        <th>Contact Details</th>
                        <th>Address</th>
                        <th>Shift</th>
                        <th>Status / Availability</th>
                        <th>Workload</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($technicians as $technician)
                        @php
                            $statusValue = $technician->technician_status ?: 'available';
                            $statusLabel = $statusLabels[$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
                            $nextInstallation = $technician->installations->first();
                        @endphp
                        <tr>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $technician->name }}</span>
                                    <span class="record-muted">{{ $technician->email ?: 'No email on file' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $technician->phone ?: 'No contact number' }}</span>
                                    <span class="record-muted">Chat: {{ $technician->chat_available ? 'Available' : 'Unavailable' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="record-muted">{{ $technician->address ?: 'No address recorded' }}</span>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $technician->technician_shift ?: 'Not set' }}</span>
                                    <span class="record-muted next-schedule">
                                        @if($nextInstallation && $nextInstallation->installation_date)
                                            Next: {{ $nextInstallation->installation_date->format('M d, Y') }}
                                        @else
                                            No upcoming schedule
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong"><span class="availability-dot {{ $statusValue }}"></span>{{ $statusLabel }}</span>
                                    <span class="record-muted">{{ $technician->is_online ? 'Online now' : 'Offline' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $technician->scheduled_installations_count }} scheduled</span>
                                    <span class="record-muted">{{ $technician->open_tickets_count }} open tickets · {{ $technician->completed_installations_count }} completed</span>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.technicians.show', $technician) }}" class="btn secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><div class="empty-state compact">No technician records matched the current filter.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card toolbar-card">
        {{ $technicians->links() }}
    </section>
</div>
@endsection
