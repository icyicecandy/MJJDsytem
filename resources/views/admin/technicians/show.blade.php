@extends('layouts.app')

@section('title','Technician Details')

@php
    $statusLabels = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'on_duty' => 'On Duty',
        'off_duty' => 'Off Duty',
        'on_leave' => 'On Leave',
    ];
    $statusValue = $technician->technician_status ?: 'available';
    $statusLabel = $statusLabels[$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
    $chatStatusLabel = $technician->chatAvailabilityLabel();
    $accountStatusLabel = $technician->isDisabled() ? 'Disabled' : 'Active';
    $employmentTypeLabel = $technician->displayTechnicianEmploymentType();
@endphp

@section('content')
<div class="page-stack">
    <section class="telecom-hero">
        <h1>{{ $technician->name }}</h1>
        <p>Review technician information, contact details, current availability, assigned installation routes, open support workload, and next available schedule window from one admin view.</p>
        <div class="telecom-chip-row">
            <span class="telecom-chip">Technician ID: {{ $technician->displayTechnicianRecordId() }}</span>
            <span class="telecom-chip">Shift: {{ $technician->displayTechnicianShift() }}</span>
            <span class="telecom-chip">Type: {{ $employmentTypeLabel }}</span>
            <span class="telecom-chip">Status: {{ $statusLabel }}</span>
            <span class="telecom-chip">Account: {{ $accountStatusLabel }}</span>
            <span class="telecom-chip">Chat: {{ $chatStatusLabel }}</span>
        </div>
    </section>

    <section class="metrics-grid">
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Scheduled Installations</div>
                    <div class="metric-value">{{ $technician->scheduled_installations_count }}</div>
                    <div class="metric-meta">Active field work still on the calendar</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Completed Installations</div>
                    <div class="metric-value">{{ $technician->completed_installations_count }}</div>
                    <div class="metric-meta">Jobs finished and recorded by this technician</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Open Tickets</div>
                    <div class="metric-value">{{ $technician->open_tickets_count }}</div>
                    <div class="metric-meta">Current service concerns assigned to this technician</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Next Free Window</div>
                    <div class="metric-value">{{ $upcomingInstallation?->installation_date?->format('M d') ?? 'Open' }}</div>
                    <div class="metric-meta">{{ $upcomingInstallation ? 'Next scheduled job on ' . $upcomingInstallation->installation_date->format('F d, Y') : 'No upcoming installation currently assigned' }}</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path></svg></div>
            </div>
        </article>
    </section>

    <section class="panel-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
                    <div>
                        <div class="panel-title">Technician Information</div>
                        <div class="panel-subtitle">Core profile details and work availability summary</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="apply-review-grid">
                    <div class="tracking-meta"><strong>Technician ID</strong><span>{{ $technician->displayTechnicianRecordId() }}</span></div>
                    <div class="tracking-meta"><strong>Full Name</strong><span>{{ $technician->name }}</span></div>
                    <div class="tracking-meta"><strong>Email</strong><span>{{ $technician->email ?: 'No email on file' }}</span></div>
                    <div class="tracking-meta"><strong>Contact Number</strong><span>{{ $technician->phone ?: 'No contact number on file' }}</span></div>
                    <div class="tracking-meta"><strong>Address</strong><span>{{ $technician->address ?: 'No address on file' }}</span></div>
                    <div class="tracking-meta"><strong>Shift</strong><span>{{ $technician->displayTechnicianShift() }}</span></div>
                    <div class="tracking-meta"><strong>Employment Type</strong><span>{{ $employmentTypeLabel }}</span></div>
                    <div class="tracking-meta"><strong>Status</strong><span>{{ $statusLabel }}</span></div>
                    <div class="tracking-meta"><strong>Account Status</strong><span>{{ $accountStatusLabel }}</span></div>
                    <div class="tracking-meta"><strong>Online Presence</strong><span>{{ $technician->hasRecentChatActivity() ? 'Online' : 'Offline' }}</span></div>
                    <div class="tracking-meta"><strong>Live Chat Availability</strong><span>{{ $chatStatusLabel }}</span></div>
                    <div class="tracking-meta"><strong>Disabled At</strong><span>{{ $technician->disabled_at?->format('M d, Y h:i A') ?? 'Not disabled' }}</span></div>
                    <div class="tracking-meta"><strong>Disabled By</strong><span>{{ $technician->disabledBy?->name ?? 'N/A' }}</span></div>
                    <div class="tracking-meta full-width"><strong>Disable Reason</strong><span>{{ $technician->disabled_reason ?: 'No disable reason recorded' }}</span></div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg></span>
                    <div>
                        <div class="panel-title">Current Workload Snapshot</div>
                        <div class="panel-subtitle">Quick view of assignments and remaining capacity</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="quick-link-grid">
                    <div class="quick-link"><strong>{{ $technician->installations_count }}</strong><span>Total installation records linked to this technician.</span></div>
                    <div class="quick-link"><strong>{{ $technician->scheduled_installations_count }}</strong><span>Upcoming jobs still waiting for completion.</span></div>
                    <div class="quick-link"><strong>{{ $technician->open_tickets_count }}</strong><span>Open ticket workload currently assigned.</span></div>
                    <div class="quick-link"><strong>{{ $upcomingInstallation ? $upcomingInstallation->installation_date->format('M d, Y') : 'Open schedule' }}</strong><span>{{ $upcomingInstallation ? 'Next scheduled installation date.' : 'No upcoming assignment blocks the schedule.' }}</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path></svg></span>
                <div>
                    <div class="panel-title">Assigned Installations</div>
                    <div class="panel-subtitle">Customer, location, schedule, and route details for each assigned installation</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($technician->installations->count())
                <div class="table-wrap">
                    <table class="telecom-table">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Customer</th>
                                <th>Location</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($technician->installations as $installation)
                                @php
                                    $application = $installation->application;
                                    $customerName = $application ? trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')) : 'Unknown customer';
                                    $address = $application ? $application->fullServiceAddress() : 'No address available';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="record-stack">
                                            <span class="record-strong">{{ $installation->displayJobReference() }}</span>
                                            <span class="record-muted">{{ $application->reference_no ?? 'No application reference' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="record-stack">
                                            <span class="record-strong">{{ $customerName }}</span>
                                            <span class="record-muted">{{ $application->mobile_number ?? ($application->email ?? 'No contact info') }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="record-stack">
                                            <span class="record-strong">{{ $application?->municipality ?: 'Location pending' }}</span>
                                            <span class="record-muted">{{ $address }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="record-stack">
                                            <span class="record-strong">{{ $installation->installation_date?->format('M d, Y') ?? 'Not scheduled' }}</span>
                                            <span class="record-muted">{{ $installation->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('h:i A') : 'Time to be confirmed' }}</span>
                                        </div>
                                    </td>
                                    <td><span class="badge {{ $installation->status }}">{{ $installation->displayStatusLabel() }}</span></td>
                                    <td>
                                        @if($application)
                                            <a href="{{ route('admin.applications.show', $application) }}" class="btn secondary">View</a>
                                        @else
                                            <span class="record-muted">No detail</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">No installations are currently assigned to this technician.</div>
            @endif
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></span>
                <div>
                    <div class="panel-title">Assigned Tickets</div>
                    <div class="panel-subtitle">Additional support work currently attached to this technician</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($technician->assignedTickets->count())
                <div class="table-wrap">
                    <table class="telecom-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($technician->assignedTickets as $ticket)
                                <tr>
                                    <td>
                                        <div class="record-stack">
                                            <span class="record-strong">{{ $ticket->publicReference() }}</span>
                                            <span class="record-muted">{{ $ticket->subject }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $ticket->user->name ?? 'Unknown customer' }}</td>
                                    <td><span class="badge {{ $ticket->status }}">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span></td>
                                    <td>{{ $ticket->created_at?->displayRelative() }}</td>
                                    <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="btn secondary">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">No support tickets are currently assigned to this technician.</div>
            @endif
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z"></path><path d="M9.5 11.5 11 13l3.5-3.5"></path></svg></span>
                <div>
                    <div class="panel-title">Account Access</div>
                    <div class="panel-subtitle">Admin-only access controls for this technician account</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if(session('status') === 'technician-disabled')
                <div class="empty-state compact">Technician account has been disabled.</div>
            @elseif(session('status') === 'technician-enabled')
                <div class="empty-state compact">Technician account has been enabled.</div>
            @endif

            <div class="quick-link-grid">
                <div class="quick-link">
                    <strong>{{ $accountStatusLabel }}</strong>
                    <span>{{ $technician->isDisabled() ? 'This technician cannot sign in or access assigned work.' : 'This technician can sign in and use technician tools.' }}</span>
                </div>
                <div class="quick-link">
                    <strong>{{ $technician->disabled_at?->format('M d, Y h:i A') ?? 'No disable record' }}</strong>
                    <span>{{ $technician->disabledBy ? 'Disabled by ' . $technician->disabledBy->name : 'No admin disable action is recorded.' }}</span>
                </div>
                <div class="quick-link">
                    <strong>Disable Reason</strong>
                    <span>{{ $technician->disabled_reason ?: 'No disable reason recorded.' }}</span>
                </div>
            </div>

            <div class="form-actions">
                @if($technician->isDisabled())
                    <form method="POST" action="{{ route('admin.technicians.enable', $technician) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn">Enable Account</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.technicians.disable', $technician) }}" class="admin-disable-account-form">
                        @csrf
                        @method('PATCH')
                        <div class="apply-field full-span">
                            <label for="disabled_reason">Disable reason</label>
                            <textarea id="disabled_reason" name="disabled_reason" rows="3" required maxlength="1000" placeholder="State clearly why this account will be disabled.">{{ old('disabled_reason') }}</textarea>
                            @error('disabled_reason')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn danger">Disable Account</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section class="card">
        <a href="{{ route('admin.technicians.index') }}" class="btn secondary">Back</a>
    </section>
</div>
@endsection

