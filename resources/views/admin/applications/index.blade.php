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
        <div class="btn-group">
            @php $statuses = ['' => 'All','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','scheduled'=>'Scheduled','installed'=>'Installed']; @endphp
            @foreach($statuses as $key=>$label)
                <a href="{{ route('admin.applications.index', array_filter(['status'=>$key])) }}"
                   class="btn secondary {{ request('status')==$key ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table admin-app-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Customer</th>
                        <th>Address</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                    <tr>
                        <td><span class="record-strong">{{ $app->reference_no }}</span></td>
                        <td>{{ $app->user->name }}</td>
                        <td>{{ $app->street_landmark }}, {{ $app->barangay }}, {{ $app->municipality }}</td>
                        <td>{{ $app->plan_code }}</td>
                        <td><span class="badge {{ $app->status }}">{{ ucfirst(str_replace('_', ' ', $app->status)) }}</span></td>
                        <td>{{ $app->created_at->format('Y-m-d') }}</td>
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
