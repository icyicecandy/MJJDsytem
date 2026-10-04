@extends('layouts.app')

@section('title','Customer Details')

@php
    $statusLabels = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'scheduled' => 'Scheduled',
        'cancellation_requested' => 'Cancellation Requested',
        'installed' => 'Installed',
        'activated' => 'Activated',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ];
    $applicationStatusLabel = $application
        ? ($statusLabels[$application->status] ?? ucfirst(str_replace('_', ' ', $application->status)))
        : 'No Application';
    $ticketStatusLabel = $latestTicket?->displayStatusLabel() ?? 'No Tickets';
    $contactNumber = $customer->phone ?: ($application?->mobile_number ?: 'No contact number on file');
    $contactEmail = $customer->email ?: ($application?->email ?: 'No email on file');
    $isTechnicianView = auth()->user()?->isTechnician() ?? false;
    $customerIndexRoute = $isTechnicianView ? 'technician.customers.index' : 'admin.customers.index';
    $customerPrintRoute = $isTechnicianView ? 'technician.customers.print' : 'admin.reports.print.customer';
@endphp

@section('content')
<div class="page-stack">
    <section class="telecom-hero">
        <h1>{{ $customer->name }}</h1>
        <p>Review customer information, account contact details, service application status, installation progress, and support ticket history from one shared service view.</p>
        <div class="telecom-chip-row">
            <span class="telecom-chip">Customer ID: {{ $customer->displayCustomerRecordId() }}</span>
            <span class="telecom-chip">Account: {{ $customer->isDisabled() ? 'Disabled' : 'Active' }}</span>
            <span class="telecom-chip">Application: {{ $applicationStatusLabel }}</span>
            <span class="telecom-chip">Open Tickets: {{ $customer->open_tickets_count }}</span>
        </div>
        <div class="telecom-hero-actions">
            <a href="{{ route($customerPrintRoute, $customer) }}" class="btn">Print Customer Summary</a>
        </div>
    </section>

    <section class="metrics-grid">
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Application Status</div>
                    <div class="metric-value">{{ $applicationStatusLabel }}</div>
                    <div class="metric-meta">{{ $application?->reference_no ?: 'No service application is linked yet' }}</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h8"></path><path d="M8 9h2"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Total Tickets</div>
                    <div class="metric-value">{{ $customer->tickets_count }}</div>
                    <div class="metric-meta">Support tickets created by this customer</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Open Tickets</div>
                    <div class="metric-value">{{ $customer->open_tickets_count }}</div>
                    <div class="metric-meta">Current service concerns still needing action</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3"></path></svg></div>
            </div>
        </article>
        <article class="metric-card">
            <div class="metric-head">
                <div>
                    <div class="metric-label">Installation</div>
                    <div class="metric-value">{{ $installation?->displayStatusLabel() ?? 'None' }}</div>
                    <div class="metric-meta">{{ $installation?->installation_date?->format('F d, Y') ?? 'No installation schedule is linked yet' }}</div>
                </div>
                <div class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></div>
            </div>
        </article>
    </section>

    <section class="panel-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
                    <div>
                        <div class="panel-title">Customer Information</div>
                        <div class="panel-subtitle">Core profile details and contact summary</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="apply-review-grid">
                    <div class="tracking-meta"><strong>Customer ID</strong><span>{{ $customer->displayCustomerRecordId() }}</span></div>
                    <div class="tracking-meta"><strong>Full Name</strong><span>{{ $customer->name }}</span></div>
                    <div class="tracking-meta"><strong>Contact Details</strong><span>{{ $contactNumber }}<br><span class="record-muted">{{ $contactEmail }}</span></span></div>
                    <div class="tracking-meta"><strong>Address</strong><span>{{ $customer->address ?: ($application?->fullServiceAddress() ?: 'No address on file') }}</span></div>
                    <div class="tracking-meta"><strong>Date of Birth</strong><span>{{ $customer->date_of_birth?->format('M d, Y') ?? 'Not provided' }}</span></div>
                    <div class="tracking-meta"><strong>Gender</strong><span>{{ $customer->gender ? ucfirst($customer->gender) : 'Not provided' }}</span></div>
                    <div class="tracking-meta"><strong>Account Created</strong><span>{{ $customer->created_at?->format('M d, Y') ?? 'Unknown' }}</span></div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg></span>
                    <div>
                        <div class="panel-title">Customer Activity Snapshot</div>
                        <div class="panel-subtitle">Quick view of service and support context</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="quick-link-grid">
                    <div class="quick-link"><strong>{{ $applicationStatusLabel }}</strong><span>Current application status for this customer.</span></div>
                    <div class="quick-link"><strong>{{ $installation?->displayStatusLabel() ?? 'No installation' }}</strong><span>{{ $installation ? 'Latest linked installation status.' : 'No installation has been scheduled yet.' }}</span></div>
                    <div class="quick-link"><strong>{{ $customer->open_tickets_count }}</strong><span>Open ticket workload currently tied to this customer.</span></div>
                    <div class="quick-link"><strong>{{ $ticketStatusLabel }}</strong><span>{{ $latestTicket ? 'Most recent support ticket status.' : 'No support tickets have been submitted.' }}</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path></svg></span>
                <div>
                    <div class="panel-title">Application Details</div>
                    <div class="panel-subtitle">Submitted service request and installation assessment data</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($application)
                <div class="apply-review-grid">
                    <div class="tracking-meta"><strong>Reference</strong><span>{{ $application->reference_no ?? 'No reference' }}</span></div>
                    <div class="tracking-meta"><strong>Applicant Name</strong><span>{{ trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')) ?: $customer->name }}</span></div>
                    <div class="tracking-meta"><strong>Application Email</strong><span>{{ $application->email ?: 'No email recorded' }}</span></div>
                    <div class="tracking-meta"><strong>Mobile Number</strong><span>{{ $application->mobile_number ?: 'No mobile number recorded' }}</span></div>
                    <div class="tracking-meta"><strong>Plan</strong><span>{{ $application->plan_code ?: 'No plan selected' }}</span></div>
                    <div class="tracking-meta"><strong>Status</strong><span>{{ $applicationStatusLabel }}</span></div>
                    <div class="tracking-meta"><strong>Serviceability</strong><span>{{ $application->serviceability_status ? ucfirst(str_replace('_', ' ', $application->serviceability_status)) : 'Not assessed' }}</span></div>
                    <div class="tracking-meta"><strong>Slot Availability</strong><span>{{ $application->slot_availability_status ? ucfirst(str_replace('_', ' ', $application->slot_availability_status)) : 'Not assessed' }}</span></div>
                    <div class="tracking-meta"><strong>Service Address</strong><span>{{ $application->fullServiceAddress() ?: 'No service address recorded' }}</span></div>
                    <div class="tracking-meta"><strong>Coordinates</strong><span>{{ $application->lat && $application->lng ? $application->lat . ', ' . $application->lng : 'No coordinates recorded' }}</span></div>
                    <div class="tracking-meta"><strong>Submitted</strong><span>{{ $application->created_at?->format('M d, Y h:i A') ?? 'Unknown' }}</span></div>
                    <div class="tracking-meta"><strong>Assessed</strong><span>{{ $application->assessed_at?->format('M d, Y h:i A') ?? 'Not assessed' }}</span></div>
                </div>
                @unless($isTechnicianView)
                    <div class="form-actions">
                        <a href="{{ route('admin.applications.show', $application) }}" class="btn secondary">Open Application</a>
                    </div>
                @endunless
            @else
                <div class="empty-state">No service application is linked to this customer account yet.</div>
            @endif
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14.7 6.3a4 4 0 0 0-5.65 5.65L3 18v3h3l6.05-6.05A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3z"></path></svg></span>
                <div>
                    <div class="panel-title">Installation Record</div>
                    <div class="panel-subtitle">Technician assignment, schedule, and completion details</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($installation)
                <div class="apply-review-grid">
                    <div class="tracking-meta"><strong>Job</strong><span>{{ $installation->displayJobReference() }}</span></div>
                    <div class="tracking-meta"><strong>Technician</strong><span>{{ $installation->technician?->name ?? 'Unassigned' }}</span></div>
                    <div class="tracking-meta"><strong>Status</strong><span>{{ $installation->displayStatusLabel() }}</span></div>
                    <div class="tracking-meta"><strong>Assigned At</strong><span>{{ $installation->assigned_at?->format('M d, Y h:i A') ?? 'Not recorded' }}</span></div>
                    <div class="tracking-meta"><strong>Schedule Date</strong><span>{{ $installation->installation_date?->format('M d, Y') ?? 'Not scheduled' }}</span></div>
                    <div class="tracking-meta"><strong>Schedule Time</strong><span>{{ $installation->installation_time ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i')) : 'Time to be confirmed' }}</span></div>
                    <div class="tracking-meta"><strong>Completed At</strong><span>{{ $installation->completed_at?->format('M d, Y h:i A') ?? 'Not completed' }}</span></div>
                    <div class="tracking-meta"><strong>Activation Date</strong><span>{{ $installation->activation_date?->format('M d, Y') ?? 'Not activated' }}</span></div>
                    <div class="tracking-meta"><strong>Remarks</strong><span>{{ $installation->technician_remarks ?: 'No technician remarks recorded' }}</span></div>
                </div>
            @else
                <div class="empty-state">No installation record is linked to this customer application yet.</div>
            @endif
        </div>
    </section>

    <section class="panel-card">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4z"></path><path d="M13 5v14"></path></svg></span>
                <div>
                    <div class="panel-title">Support Tickets</div>
                    <div class="panel-subtitle">Recent support concerns submitted by this customer</div>
                </div>
            </div>
        </div>
        <div class="panel-body">
            @if($customer->tickets->count())
                <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="telecom-table" data-mobile-cards data-mobile-cards-expandable>
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Assigned Technician</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customer->tickets as $ticket)
                                <tr>
                                    <td>
                                        <div class="record-stack">
                                            <span class="record-strong record-reference">{{ $ticket->publicReference() }}</span>
                                            <span class="record-muted">{{ \App\Models\Ticket::resolveSubjectLabel($ticket->subject) }}</span>
                                        </div>
                                    </td>
                                    <td data-mobile-secondary>{{ $ticket->assignedStaff->name ?? 'Unassigned queue' }}</td>
                                    <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                                    <td data-mobile-secondary>{{ $ticket->created_at?->format('M d, Y') ?? 'Unknown' }}</td>
                                    <td>
                                        @if(! $isTechnicianView)
                                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn secondary">View</a>
                                        @elseif($ticket->assigned_staff_id === auth()->id())
                                            <a href="{{ route('technician.tickets.show', $ticket) }}" class="btn secondary">View</a>
                                        @else
                                            <span class="record-muted">Summary only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">No support tickets have been submitted by this customer.</div>
            @endif
        </div>
    </section>

    @unless($isTechnicianView)
        <section class="panel-card">
            <div class="panel-header">
                <div class="panel-title-wrap">
                    <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z"></path><path d="M9.5 11.5 11 13l3.5-3.5"></path></svg></span>
                    <div>
                        <div class="panel-title">Account Access</div>
                        <div class="panel-subtitle">Admin-only access controls for this customer account</div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                @if(session('status') === 'customer-disabled')
                    <div class="empty-state compact">Customer account has been disabled.</div>
                @elseif(session('status') === 'customer-enabled')
                    <div class="empty-state compact">Customer account has been enabled.</div>
                @endif
                <div class="quick-link-grid">
                    <div class="quick-link">
                        <strong>{{ $customer->isDisabled() ? 'Disabled' : 'Active' }}</strong>
                        <span>{{ $customer->isDisabled() ? 'This customer cannot sign in or use customer services.' : 'This customer can sign in and use customer services.' }}</span>
                    </div>
                    <div class="quick-link">
                        <strong>{{ $customer->disabled_at?->format('M d, Y h:i A') ?? 'No disable record' }}</strong>
                        <span>{{ $customer->disabledBy ? 'Disabled by '.$customer->disabledBy->name : 'No admin disable action is recorded.' }}</span>
                    </div>
                    <div class="quick-link">
                        <strong>Disable Reason</strong>
                        <span>{{ $customer->disabled_reason ?: 'No disable reason recorded.' }}</span>
                    </div>
                </div>
                <div class="form-actions">
                    @if($customer->isDisabled())
                        <form method="POST" action="{{ route('admin.customers.enable', $customer) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn">Enable Account</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.customers.disable', $customer) }}" class="admin-disable-account-form">
                            @csrf
                            @method('PATCH')
                            <div class="apply-field full-span">
                                <label for="customer_disabled_reason">Disable reason</label>
                                <textarea id="customer_disabled_reason" name="disabled_reason" rows="3" required maxlength="1000" placeholder="State clearly why this account will be disabled.">{{ old('disabled_reason') }}</textarea>
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
    @endunless

    <section class="card">
        <a href="{{ route($customerIndexRoute) }}" class="btn secondary">Back</a>
    </section>
</div>
@endsection
