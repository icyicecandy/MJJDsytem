@extends('layouts.app')

@section('title','Applications')

@push('styles')
<style>
    .admin-application-live-status {
        min-height: 1.1rem;
        margin: 0.55rem 0 0;
        color: var(--color-text-muted);
        font-size: 0.8rem;
    }
    .admin-application-live-status.is-error {
        color: #b42318;
    }
    [data-application-results] {
        display: grid;
        gap: 1rem;
        transition: opacity 0.18s ease;
    }
    [data-application-results][aria-busy="true"],
    [data-application-status-nav][aria-busy="true"] {
        opacity: 0.55;
        pointer-events: none;
    }
</style>
@endpush

@section('content')
@php
    $statuses = [
        '' => 'All statuses',
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'cancellation_requested' => 'Cancellation Requested',
        'rejected' => 'Rejected',
        'scheduled' => 'Scheduled',
        'reschedule' => 'Reschedule',
        'installed' => 'Installed',
        'activated' => 'Activated',
        'cancelled' => 'Cancelled',
    ];
    $emptyMessage = ($search ?? '') !== ''
        ? 'No applications matched your search.'
        : (($status ?? '') !== '' ? 'No applications found for the selected progress filter.' : 'No applications found.');
    $progressCards = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'scheduled' => 'Scheduled',
        'reschedule' => 'Reschedule',
        'cancellation_requested' => 'Cancel Request',
    ];
@endphp
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Applications</h1>
            <p>Review submitted service requests, monitor approval flow, and open each application for scheduling.</p>
        </div>
    </section>

    <section class="card ticket-queue-overview application-progress-overview">
        <div class="ticket-queue-overview__head">
            <form method="GET" action="{{ route('admin.applications.index') }}" class="toolbar-row ticket-filter-toolbar application-filter-toolbar" role="search" data-mobile-filter data-live-application-search>
                <div class="toolbar-field grow ticket-filter-search">
                    <label for="application_search">Search Applications</label>
                    <input
                        id="application_search"
                        type="search"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Reference number, customer, contact, email, or plan"
                        autocomplete="off"
                        aria-controls="applicationResults"
                        data-live-application-search-input
                    >
                </div>

                <div class="toolbar-field">
                    <label for="application_status_filter">Status</label>
                    <select id="application_status_filter" name="status" data-live-application-status>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="toolbar-actions">
                    <button type="submit" class="btn">Apply</button>
                    <button
                        type="button"
                        class="btn secondary"
                        data-live-application-reset
                        @if(blank($status ?? null) && blank($search ?? null)) hidden @endif
                    >Reset</button>
                </div>
            </form>
            <p class="admin-application-live-status" data-live-application-search-status aria-live="polite">Results update automatically.</p>
        </div>

        @php
            $progressFilterParams = array_filter(['search' => $search ?? ''], fn ($value) => $value !== '');
        @endphp
        <nav class="ticket-queue-overview__grid application-progress-grid" aria-label="Filter applications by progress" data-application-status-nav aria-busy="false">
            @foreach($progressCards as $progressValue => $progressLabel)
                <a
                    href="{{ route('admin.applications.index', array_merge($progressFilterParams, ['status' => $progressValue])) }}"
                    class="ticket-queue-metric {{ ($status ?? '') === $progressValue ? 'is-active' : '' }}"
                    data-application-status-card="{{ $progressValue }}"
                    @if(($status ?? '') === $progressValue) aria-current="page" @endif
                >
                    <span class="ticket-queue-metric__label">{{ $progressLabel }}</span>
                    <strong class="ticket-queue-metric__value">{{ $applicationSummary[$progressValue] ?? 0 }}</strong>
                </a>
            @endforeach
        </nav>
    </section>

    <div id="applicationResults" data-application-results aria-busy="false">
        <section class="card table-card">
            <div class="table-wrap" tabindex="0" role="region" aria-label="Application records">
                <table class="telecom-table admin-app-table" data-mobile-cards>
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Customer</th>
                            <th>Contact No.</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $app)
                        @php
                            $isReschedule = $app->installation
                                && ($app->installation->status === \App\Models\Installation::STATUS_RESCHEDULE_REQUIRED
                                    || $app->installation->hasPendingCustomerRescheduleRequest());
                        @endphp
                        <tr>
                            <td class="ref-cell"><span class="record-strong record-reference">{{ $app->reference_no }}</span></td>
                            <td class="customer-cell">{{ $app->user->name }}</td>
                            <td class="contact-cell">{{ $app->mobile_number ?: 'Not provided' }}</td>
                            <td class="plan-cell">{{ $app->plan_code }}</td>
                            <td class="status-cell">
                                @if($isReschedule)
                                    <span class="badge reschedule">Reschedule</span>
                                @else
                                    <span class="badge {{ $app->status }}">{{ ucfirst(str_replace('_', ' ', $app->status)) }}</span>
                                @endif
                            </td>
                            <td class="submitted-cell">{{ $app->created_at->displayDateTime('m/d/y h:i A') }}</td>
                            <td class="actions-cell"><a href="{{ route('admin.applications.show',$app) }}" class="btn secondary">Open</a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state compact">{{ $emptyMessage }}</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($applications->hasPages())
            <section class="card toolbar-card">
                <x-ui.pagination :paginator="$applications" />
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
    <script defer src="{{ asset('js/admin-applications.js') }}?v={{ filemtime(public_path('js/admin-applications.js')) }}"></script>
@endpush
