@extends('layouts.app')

@section('title','Customers')

@php
    $statusLabels = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'scheduled' => 'Scheduled',
        'cancellation_requested' => 'Cancellation Requested',
        'installed' => 'Installed',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'activated' => 'Activated',
        'without_application' => 'No Application',
    ];
    $isTechnicianView = auth()->user()?->isTechnician() ?? false;
    $customerIndexRoute = $isTechnicianView ? 'technician.customers.index' : 'admin.customers.index';
    $customerShowRoute = $isTechnicianView ? 'technician.customers.show' : 'admin.customers.show';
@endphp

@push('styles')
<style>
    .admin-customers-table th:nth-child(1) { width: 13%; }
    .admin-customers-table th:nth-child(2) { width: 14%; }
    .admin-customers-table th:nth-child(3) { width: 14%; }
    .admin-customers-table th:nth-child(4) { width: 10%; }
    .admin-customers-table th:nth-child(5) { width: 19%; }
    .admin-customers-table th:nth-child(6) { width: 22%; }
    .admin-customers-table th:nth-child(7) { width: 8%; }
    .admin-customers-table .record-stack {
        display: grid;
        gap: 0.2rem;
    }
    .admin-customers-table .record-muted {
        font-size: 0.84rem;
        line-height: 1.35;
    }
</style>
@endpush

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Customers</h1>
            <p>Review customer records, contact details, application status, service location, and support activity from one shared operations list.</p>
        </div>
    </section>

    <section class="card toolbar-card">
        <form method="GET" action="{{ route($customerIndexRoute) }}" class="toolbar-row customer-filter-toolbar" data-mobile-filter data-live-filter data-live-filter-target="#customerResults">
            <div class="toolbar-field">
                <label for="status">Application Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="toolbar-field grow">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Customer ID, first name, last name, email, phone, plan, location, reference">
            </div>

            <div class="toolbar-field"><label for="customer_account_status">Account</label><select name="account_status" id="customer_account_status"><option value="">All accounts</option><option value="active" @selected($accountStatus === 'active')>Active</option><option value="disabled" @selected($accountStatus === 'disabled')>Disabled</option></select></div>
            <div class="filter-date-range" role="group" aria-labelledby="customer_date_range_label">
                <span id="customer_date_range_label" class="filter-date-range__label">Registered date</span>
                <div class="filter-date-range__fields">
                    <div class="toolbar-field"><label for="customer_date_from">From</label><input type="date" name="date_from" id="customer_date_from" value="{{ $dateFrom }}"></div>
                    <div class="toolbar-field"><label for="customer_date_to">To</label><input type="date" name="date_to" id="customer_date_to" value="{{ $dateTo }}"></div>
                </div>
            </div>
            <label class="toolbar-field"><span>Action</span><select name="requires_action"><option value="0">All records</option><option value="1" @selected($requiresAction)>Requires action</option></select></label>

            <div class="toolbar-actions">
                <button class="btn">Filter</button>
                @if($search !== '' || $status !== '' || $accountStatus !== '' || filled($dateFrom) || filled($dateTo) || $requiresAction)
                    <a href="{{ route($customerIndexRoute) }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
        <x-active-filter-summary :labels="[
            $status ? 'Application: '.($statusLabels[$status] ?? $status) : null,
            $search ? 'Search: '.$search : null,
            $accountStatus ? 'Account: '.ucfirst($accountStatus) : null,
            $dateFrom ? 'From: '.$dateFrom : null,
            $dateTo ? 'To: '.$dateTo : null,
            $requiresAction ? 'Requires action' : null,
        ]" :reset-url="route($customerIndexRoute)" />
    </section>

    <div id="customerResults" data-live-filter-results aria-busy="false">
    <section class="card table-card">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable records table">
            <table class="telecom-table admin-customers-table" data-mobile-cards>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Plan</th>
                        <th>Contact Details</th>
                        <th>Service Area</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        @php
                            $application = $customer->application;
                            $serviceAddress = $application?->fullServiceAddress() ?: ($customer->address ?: 'No service address recorded');
                            $firstName = $application?->first_name ?: $customer->displayFirstName();
                            $lastName = $application?->last_name ?: $customer->displayLastName();
                            $contactNumber = $customer->phone ?: ($application?->mobile_number ?: 'No contact number');
                            $contactEmail = $customer->email ?: ($application?->email ?: 'No email on file');
                        @endphp
                        <tr>
                            <td>
                                <span class="record-strong">{{ $customer->displayCustomerRecordId() }}</span>
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
                                <span class="record-strong">{{ $application?->plan_code ?: 'No plan' }}</span>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $contactNumber }}</span>
                                    <span class="record-muted">{{ $contactEmail }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $application?->municipality ?: 'Location pending' }}</span>
                                    <span class="record-muted">{{ $serviceAddress }}</span>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route($customerShowRoute, $customer) }}" class="btn secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><div class="empty-state compact">No customer records matched the current filter.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card toolbar-card">
        <x-ui.pagination :paginator="$customers" />
    </section>
    </div>
</div>
@endsection
