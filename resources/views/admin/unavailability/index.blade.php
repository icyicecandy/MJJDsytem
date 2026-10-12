@extends('layouts.app')

@section('title', 'Technician Availability Requests')

@push('styles')
<style>
    .availability-toolbar { display:grid; grid-template-columns:minmax(12rem,.35fr) minmax(18rem,1fr) auto; gap:.85rem; align-items:end; }
    .availability-toolbar label { display:grid; gap:.4rem; color:#183667; font-size:.82rem; font-weight:800; }
    .availability-toolbar input, .availability-toolbar select { width:100%; border:1px solid #ccd8ec; border-radius:.72rem; padding:.78rem .85rem; font:inherit; }
    .availability-toolbar__actions { display:flex; gap:.6rem; }
    .availability-status { display:inline-flex; border-radius:999px; padding:.34rem .62rem; font-size:.72rem; font-weight:900; text-transform:uppercase; background:#e8eef8; color:#365176; }
    .availability-status.pending { background:#fff3d6;color:#9a4b00; }
    .availability-status.approved { background:#dcfce7;color:#14743b; }
    .availability-status.clarification_requested { background:#e8f0ff;color:#1746b5; }
    .availability-status.rejected { background:#fee8e8;color:#b42318; }
    .technician-management-heading h1 { margin-bottom:.35rem; }
    .technician-management-heading p { max-width:780px; margin:0; }
    .technician-management-navigation { display:flex; gap:.45rem; margin-top:1.2rem; padding-top:1rem; border-top:1px solid #e2e8f0; }
    .technician-management-navigation__item { display:inline-flex; min-height:2.7rem; align-items:center; justify-content:center; gap:.5rem; padding:.62rem .9rem; border:1px solid transparent; border-radius:.7rem; color:#526785; font-size:.9rem; font-weight:800; text-decoration:none; }
    .technician-management-navigation__item:hover { border-color:#cbd8eb; background:#f8fafc; color:#123d9e; }
    .technician-management-navigation__item.is-active { border-color:#bfd0ef; background:#eaf1ff; color:#1746b5; }
    .technician-management-navigation__count { display:inline-grid; min-width:1.5rem; height:1.5rem; place-items:center; padding:0 .38rem; border-radius:999px; background:#fff; color:#1746b5; font-size:.72rem; line-height:1; }
    .availability-list-heading { margin-bottom:1.15rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0; }
    .availability-list-heading h2 { margin:0; color:#071d49; font-size:1.25rem; }
    .availability-list-heading p { margin:.3rem 0 0; color:#60708a; line-height:1.5; }
    @media(max-width:760px){
        .availability-toolbar{grid-template-columns:1fr;}
        .availability-toolbar__actions,.availability-toolbar__actions .btn{width:100%;}
        .technician-management-navigation{display:grid;grid-template-columns:1fr;}
        .technician-management-navigation__item{justify-content:flex-start;}
    }
</style>
@endpush

@section('content')
<div class="page-stack">
    <section class="card technician-management-heading">
        <div>
            <h1>Technician Management</h1>
            <p>Manage technician accounts, work schedules, contact information, and reported availability.</p>
        </div>
        <nav class="technician-management-navigation" aria-label="Technician management sections">
            <a href="{{ route('admin.technicians.index') }}" class="technician-management-navigation__item">Technician Directory</a>
            <a href="{{ route('admin.unavailability.index') }}" class="technician-management-navigation__item is-active" aria-current="page">
                Availability Requests
                @if($pendingUnavailabilityCount > 0)
                    <span class="technician-management-navigation__count" aria-label="{{ $pendingUnavailabilityCount }} pending requests">{{ $pendingUnavailabilityCount }}</span>
                @endif
            </a>
        </nav>
    </section>
    <section class="card toolbar-card">
        <div class="availability-list-heading">
            <h2>Availability Requests</h2>
            <p>Review staff-reported absences, protect new schedules, and resolve coverage for jobs that were already assigned.</p>
        </div>
        <form method="GET" action="{{ route('admin.unavailability.index') }}" class="availability-toolbar">
            <label>Status<select name="status"><option value="">All statuses</option>@foreach(['pending'=>'Pending review','clarification_requested'=>'Clarification requested','approved'=>'Approved','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Search technician<input name="search" value="{{ $search }}" placeholder="Name, email, or technician ID"></label>
            <div class="availability-toolbar__actions"><button class="btn">Apply Filters</button><a class="btn secondary" href="{{ route('admin.unavailability.index') }}">Clear</a></div>
        </form>
    </section>
    <section class="card table-card">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Technician availability requests">
            <table class="telecom-table" data-mobile-cards>
                <thead><tr><th>Reference</th><th>Technician</th><th>Period</th><th>Reason</th><th>Coverage</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($requests as $unavailability)
                        <tr>
                            <td><span class="record-strong">{{ $unavailability->reference() }}</span><div class="record-muted">{{ $unavailability->created_at->format('M d, Y g:i A') }}</div></td>
                            <td><span class="record-strong">{{ $unavailability->technician->name }}</span><div class="record-muted">{{ $unavailability->technician->displayTechnicianRecordId() }}</div></td>
                            <td>{{ $unavailability->effectiveStartAt()->format('M d, Y g:i A') }}<div class="record-muted">to {{ $unavailability->effectiveEndAt()->format('M d, Y g:i A') }}</div></td>
                            <td>{{ $unavailability->categoryLabel() }}</td>
                            <td><strong>{{ $unavailability->affected_installations_count }}</strong> assigned {{ \Illuminate\Support\Str::plural('job', $unavailability->affected_installations_count) }}</td>
                            <td><span class="availability-status {{ $unavailability->status }}">{{ $unavailability->statusLabel() }}</span></td>
                            <td><a class="btn secondary" href="{{ route('admin.unavailability.show', $unavailability) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state">No technician availability requests match the selected filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </section>
</div>
@endsection
