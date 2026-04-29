@extends('layouts.app')

@section('title','Technicians')

@php
    $statusLabels = [
        'active' => 'Active',
        'disabled' => 'Disabled',
    ];
@endphp

@push('styles')
<style>
    .admin-technicians-table th,
    .admin-technicians-table td {
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }
    .admin-technicians-table th:nth-child(1) { width: 12%; }
    .admin-technicians-table th:nth-child(2) { width: 12%; }
    .admin-technicians-table th:nth-child(3) { width: 15%; }
    .admin-technicians-table th:nth-child(4) { width: 14%; }
    .admin-technicians-table th:nth-child(5) { width: 15%; }
    .admin-technicians-table th:nth-child(6) { width: 14%; }
    .admin-technicians-table th:nth-child(7) { width: 10%; }
    .admin-technicians-table th:nth-child(8),
    .admin-technicians-table td.actions-cell {
        width: 8%;
        white-space: nowrap;
    }
    .admin-technicians-table .record-stack {
        display: grid;
        gap: 0.2rem;
    }
    .admin-technicians-table .record-muted {
        font-size: 0.84rem;
        line-height: 1.35;
    }
    .admin-technicians-table .status-pill {
        display: inline-flex;
        align-items: center;
        justify-self: start;
        width: fit-content;
        border: 1px solid transparent;
        border-radius: 999px;
        padding: 0.26rem 0.68rem;
        font-size: 0.82rem;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }
    .admin-technicians-table .status-pill--active {
        color: #15803d;
        background: #dcfce7;
        border-color: #bbf7d0;
    }
    .admin-technicians-table .status-pill--disabled {
        color: #475569;
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
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
            <p>Review technician records, core contact details, account state, and direct access to the full technician profile from one admin operations list.</p>
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
                <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Technician ID, first name, last name, email, phone, shift, status">
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
        <div class="table-wrap">
            <table class="telecom-table admin-technicians-table">
                <thead>
                    <tr>
                        <th>Technician ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Contact Details</th>
                        <th>Address</th>
                        <th>Shift</th>
                        <th>Status</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($technicians as $technician)
                        @php
                            $statusValue = $technician->isDisabled() ? 'disabled' : 'active';
                            $statusLabel = $statusLabels[$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
                            $nextInstallation = $technician->installations->first();
                            $chatStatusLabel = $technician->chatAvailabilityLabel();
                            $firstName = $technician->displayFirstName();
                            $lastName = $technician->displayLastName();
                            $shiftParts = array_map('trim', explode(',', $technician->displayTechnicianShift(), 2));
                            $shiftTime = $shiftParts[0] ?? $technician->displayTechnicianShift();
                            $shiftDays = $shiftParts[1] ?? '';
                        @endphp
                        <tr>
                            <td>
                                <span class="record-strong">{{ $technician->displayTechnicianRecordId() }}</span>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $firstName !== '' ? $firstName : 'N/A' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $lastName !== '' ? $lastName : 'N/A' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $technician->phone ?: 'No contact number' }}</span>
                                    <span class="record-muted">{{ $technician->email ?: 'No email on file' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="record-muted">{{ $technician->address ?: 'No address recorded' }}</span>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-muted">{{ $shiftTime }}</span>
                                    @if($shiftDays !== '')
                                        <span class="record-muted">{{ $shiftDays }}</span>
                                    @endif
                                    @if($nextInstallation && $nextInstallation->installation_date)
                                        <span class="record-muted next-schedule">
                                            Next: {{ $nextInstallation->installation_date->format('M d, Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="status-pill status-pill--{{ $statusValue }}">{{ $statusLabel }}</span>
                                    <span class="record-muted">Chat: {{ $chatStatusLabel }}</span>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.technicians.show', $technician) }}" class="btn secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8"><div class="empty-state compact">No technician records matched the current filter.</div></td>
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
