@extends('layouts.app')

@section('title','Applications')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Applications</h1>
            <p>Review submitted service requests, monitor approval flow, and open each application for scheduling.</p>
        </div>
    </section>

    <section class="card toolbar-card">
        @php $statuses = ['' => 'All','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','scheduled'=>'Scheduled','installed'=>'Installed']; @endphp
        <form method="GET" action="{{ route('admin.applications.index') }}" class="toolbar-row">
            <div class="toolbar-field">
                <label for="application_status_filter">Status</label>
                <select id="application_status_filter" name="status">
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(request('status', '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="toolbar-field grow">
                <label for="application_search">Search</label>
                <input
                    id="application_search"
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Ref #, customer, contact no., email, plan"
                >
            </div>

            <div class="toolbar-actions">
                <button class="btn">Filter</button>
                @if(request()->filled('status') || request()->filled('search'))
                    <a href="{{ route('admin.applications.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table admin-app-table">
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
                    <tr>
                        <td class="ref-cell"><span class="record-strong">{{ $app->reference_no }}</span></td>
                        <td class="customer-cell">{{ $app->user->name }}</td>
                        <td class="contact-cell">{{ $app->mobile_number ?: 'Not provided' }}</td>
                        <td class="plan-cell">{{ $app->plan_code }}</td>
                        <td class="status-cell"><span class="badge {{ $app->status }}">{{ ucfirst(str_replace('_', ' ', $app->status)) }}</span></td>
                        <td class="submitted-cell">{{ $app->created_at->displayDateTime('m/d/y h:i A') }}</td>
                        <td class="actions-cell"><a href="{{ route('admin.applications.show',$app) }}" class="btn secondary">Open</a></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state compact">No applications found for the selected status.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
