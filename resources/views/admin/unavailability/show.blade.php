@extends('layouts.app')

@section('title', 'Review Technician Availability')

@php
    $reviewIsOpen = in_array($unavailability->status, [
        \App\Models\TechnicianUnavailabilityRequest::STATUS_PENDING,
        \App\Models\TechnicianUnavailabilityRequest::STATUS_CLARIFICATION,
    ], true);
    $affectedCount = $affectedInstallations->count();
    $coverageIsResolved = $affectedCount === 0;
@endphp

@push('styles')
<style>
    .availability-review-page { width:min(1180px,100%); margin:0 auto; }
    .availability-review-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:1.25rem; align-items:center; }
    .availability-review-hero h1 { margin:.2rem 0 .35rem; font-size:clamp(1.55rem,3vw,2rem); }
    .availability-review-hero p { max-width:780px; margin:0; color:#60708a; line-height:1.5; }
    .availability-review-hero__actions { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; justify-content:flex-end; }
    .availability-status { display:inline-flex; align-items:center; border-radius:999px; padding:.42rem .75rem; font-size:.73rem; font-weight:900; text-transform:uppercase; background:#e8eef8; color:#365176; }
    .availability-status.pending{background:#fff3d6;color:#9a4b00}.availability-status.approved{background:#dcfce7;color:#14743b}.availability-status.clarification_requested{background:#e8f0ff;color:#1746b5}.availability-status.rejected{background:#fee8e8;color:#b42318}

    .availability-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.65rem; padding:.75rem; }
    .availability-step { display:grid; grid-template-columns:2rem minmax(0,1fr); gap:.7rem; align-items:center; padding:.8rem; border:1px solid #e0e7f1; border-radius:.85rem; background:#f8fafc; }
    .availability-step__number { display:grid; width:2rem; height:2rem; place-items:center; border-radius:999px; background:#e5eaf2; color:#526785; font-size:.8rem; font-weight:900; }
    .availability-step strong { display:block; color:#17345f; font-size:.88rem; }
    .availability-step span:last-child { display:block; margin-top:.1rem; color:#71809a; font-size:.75rem; }
    .availability-step.is-complete { border-color:#bde9d0; background:#f1fbf5; }
    .availability-step.is-complete .availability-step__number { background:#159447; color:#fff; }
    .availability-step.is-current { border-color:#afc8ff; background:#edf4ff; box-shadow:0 6px 16px rgba(37,99,235,.08); }
    .availability-step.is-current .availability-step__number { background:#2155d6; color:#fff; }
    .availability-step.is-blocked { border-color:#f3d19b; background:#fffaf0; }
    .availability-step.is-blocked .availability-step__number { background:#d97706; color:#fff; }

    .availability-section { padding:1.35rem; }
    .availability-section__head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0; }
    .availability-section__head h2 { margin:0; color:#071d49; font-size:1.35rem; }
    .availability-section__head p { max-width:760px; margin:.3rem 0 0; color:#60708a; line-height:1.5; }
    .availability-count { display:inline-flex; align-items:center; min-height:2rem; padding:.35rem .65rem; border-radius:999px; background:#edf2fa; color:#365176; font-size:.76rem; font-weight:900; white-space:nowrap; }
    .availability-count.has-conflicts { background:#fff0dc; color:#9a4b00; }

    .availability-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; }
    .availability-summary__item { min-width:0; padding:.85rem; border:1px solid #e0e7f1; border-radius:.8rem; background:#f8fafc; }
    .availability-summary__item span { display:block; color:#64748b; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .availability-summary__item strong { display:block; margin-top:.25rem; color:#102a56; line-height:1.4; overflow-wrap:anywhere; }
    .availability-summary__item.is-wide { grid-column:span 2; }
    .availability-notes { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; margin-top:.75rem; }
    .availability-note { padding:.9rem; border:1px solid #c9ddff; border-radius:.8rem; background:#f1f6ff; color:#274b80; line-height:1.55; overflow-wrap:anywhere; }
    .availability-note.is-admin { border-color:#dde3ec; background:#f8fafc; color:#425778; }
    .availability-note strong { display:block; margin-bottom:.2rem; color:#17345f; }

    .coverage-guidance { display:flex; gap:.75rem; align-items:flex-start; margin-bottom:1rem; padding:.85rem 1rem; border:1px solid #f0c987; border-radius:.8rem; background:#fff8e9; color:#774500; line-height:1.5; }
    .coverage-guidance.is-clear { border-color:#bde9d0; background:#f1fbf5; color:#17653a; }
    .coverage-guidance__icon { display:grid; flex:0 0 1.75rem; width:1.75rem; height:1.75rem; place-items:center; border-radius:999px; background:#d97706; color:#fff; font-weight:900; }
    .coverage-guidance.is-clear .coverage-guidance__icon { background:#159447; }
    .coverage-list { display:grid; gap:.9rem; }
    .coverage-job { border:1px solid #dbe4f0; border-radius:.95rem; padding:1rem; background:#fff; }
    .coverage-job__head { display:flex; justify-content:space-between; gap:.8rem; align-items:flex-start; }
    .coverage-job__head h3 { margin:0; color:#071d49; font-size:1.08rem; }
    .coverage-job__meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.65rem; margin-top:.8rem; }
    .coverage-job__meta div { min-width:0; border-radius:.65rem; padding:.72rem; background:#f8fafc; color:#425778; overflow-wrap:anywhere; }
    .coverage-job__meta strong { color:#17345f; }
    .coverage-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; margin-top:.85rem; padding-top:.85rem; border-top:1px solid #e7ecf3; }
    .coverage-option { display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; min-width:0; padding:.85rem; border:1px solid #dbe4f0; border-radius:.8rem; background:#fbfcfe; }
    .coverage-option strong { color:#17345f; }
    .coverage-option p { margin:0; color:#64748b; font-size:.82rem; line-height:1.45; }
    .coverage-option .btn { margin-top:auto; }
    .coverage-reschedule-form { display:grid; gap:.55rem; width:100%; }
    .coverage-reschedule-form input { width:100%; border:1px solid #ccd8ec; border-radius:.7rem; padding:.72rem .8rem; font:inherit; }

    .availability-decision-layout { display:grid; grid-template-columns:minmax(16rem,.7fr) minmax(0,1.3fr); gap:1.25rem; align-items:start; }
    .availability-decision-copy h2 { margin:0; color:#071d49; font-size:1.35rem; }
    .availability-decision-copy p { margin:.4rem 0 0; color:#60708a; line-height:1.55; }
    .decision-readiness { margin-top:1rem; padding:.85rem; border:1px solid #bde9d0; border-radius:.8rem; background:#f1fbf5; color:#17653a; line-height:1.45; }
    .decision-readiness.is-blocked { border-color:#f0c987; background:#fff8e9; color:#774500; }
    .availability-review-form { display:grid; gap:1rem; }
    .availability-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.85rem; }
    .availability-field { display:grid; gap:.4rem; }
    .availability-field label { color:#183667; font-size:.8rem; font-weight:800; }
    .availability-field input,.availability-field textarea { width:100%; min-width:0; border:1px solid #ccd8ec; border-radius:.72rem; padding:.78rem .85rem; font:inherit; }
    .availability-field textarea { min-height:6.5rem; resize:vertical; }
    .availability-field-error { padding:.75rem .85rem; border:1px solid #fecaca; border-radius:.75rem; background:#fef2f2; color:#b42318; font-weight:700; }
    .availability-decision-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.65rem; padding-top:.25rem; }
    .availability-decision-actions .btn[disabled] { cursor:not-allowed; opacity:.5; box-shadow:none; }
    .availability-completed { margin-top:1rem; padding:1rem; border:1px solid #dbe4f0; border-radius:.85rem; background:#f8fafc; color:#425778; }
    .availability-completed strong { color:#17345f; }

    @media(max-width:900px){
        .availability-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
        .availability-decision-layout{grid-template-columns:1fr}
    }
    @media(max-width:700px){
        .availability-review-hero{grid-template-columns:1fr}
        .availability-review-hero__actions{justify-content:flex-start}
        .availability-steps,.availability-summary,.availability-notes,.coverage-job__meta,.coverage-options,.availability-form-grid{grid-template-columns:1fr}
        .availability-summary__item.is-wide{grid-column:auto}
        .availability-section__head{display:grid}
        .availability-decision-actions{display:grid;grid-template-columns:1fr}
        .availability-decision-actions .btn,.availability-review-hero__actions .btn{width:100%;text-align:center}
    }
</style>
@endpush

@section('content')
<div class="page-stack availability-review-page">
    <section class="card availability-section availability-review-hero">
        <div>
            <span class="section-kicker">Technician availability review</span>
            <h1>{{ $unavailability->reference() }}</h1>
            <p>Review the request, resolve schedule coverage, and record the final decision.</p>
        </div>
        <div class="availability-review-hero__actions">
            <span class="availability-status {{ $unavailability->status }}">{{ $unavailability->statusLabel() }}</span>
            <a class="btn secondary" href="{{ route('admin.unavailability.index') }}">Return to Requests</a>
        </div>
    </section>

    <section class="card availability-steps" aria-label="Availability review progress">
        <div class="availability-step is-complete"><span class="availability-step__number">1</span><span><strong>Review request</strong><span>Request details received</span></span></div>
        <div class="availability-step {{ $coverageIsResolved ? 'is-complete' : 'is-blocked' }}"><span class="availability-step__number">2</span><span><strong>Resolve coverage</strong><span>{{ $coverageIsResolved ? 'No affected jobs remain' : $affectedCount.' '.str('job')->plural($affectedCount).' require action' }}</span></span></div>
        <div class="availability-step {{ $reviewIsOpen ? ($coverageIsResolved ? 'is-current' : '') : 'is-complete' }}"><span class="availability-step__number">3</span><span><strong>Final decision</strong><span>{{ $reviewIsOpen ? ($coverageIsResolved ? 'Ready for administrator decision' : 'Waiting for coverage') : 'Decision recorded' }}</span></span></div>
    </section>

    <section class="card availability-section">
        <div class="availability-section__head"><div><h2>1. Request details</h2><p>Confirm the requested dates and private operational information.</p></div></div>
        <div class="availability-summary">
            <div class="availability-summary__item"><span>Technician</span><strong>{{ $unavailability->technician->name }} &middot; {{ $unavailability->technician->displayTechnicianRecordId() }}</strong></div>
            <div class="availability-summary__item"><span>Submitted</span><strong>{{ $unavailability->created_at->displayDateTime('M d, Y g:i A') }}</strong></div>
            <div class="availability-summary__item is-wide"><span>Requested period</span><strong>{{ $unavailability->requested_start_at->format('M d, Y g:i A') }} &ndash; {{ $unavailability->requested_end_at->format('M d, Y g:i A') }}</strong></div>
            <div class="availability-summary__item"><span>Category</span><strong>{{ $unavailability->categoryLabel() }}</strong></div>
            <div class="availability-summary__item"><span>Supporting file</span><strong>{{ $unavailability->evidence_path ? $unavailability->evidence_original_name : 'No file submitted' }}</strong>@if($unavailability->evidence_path)<a href="{{ route('admin.unavailability.evidence', $unavailability) }}">Download private evidence</a>@endif</div>
            @if($unavailability->status === \App\Models\TechnicianUnavailabilityRequest::STATUS_APPROVED)
                <div class="availability-summary__item is-wide"><span>Approved period</span><strong>{{ $unavailability->effectiveStartAt()->format('M d, Y g:i A') }} &ndash; {{ $unavailability->effectiveEndAt()->format('M d, Y g:i A') }}</strong></div>
            @endif
        </div>
        <div class="availability-notes">
            <div class="availability-note"><strong>Private operational reason</strong>{{ $unavailability->reason }}</div>
            @if($unavailability->admin_note)<div class="availability-note is-admin"><strong>Administrator note</strong>{{ $unavailability->admin_note }}</div>@endif
        </div>
    </section>

    <section class="card availability-section">
        <div class="availability-section__head">
            <div><h2>2. Resolve affected installations</h2><p>Keep the appointment by assigning another available technician, or move it to the reschedule queue when no replacement can cover the current slot.</p></div>
            <span class="availability-count {{ $affectedCount > 0 ? 'has-conflicts' : '' }}">{{ $affectedCount }} {{ str('job')->plural($affectedCount) }} requiring action</span>
        </div>

        @if($affectedCount > 0)
            <div class="coverage-guidance"><span class="coverage-guidance__icon">!</span><div><strong>Coverage must be completed before approval.</strong><br>Resolve every job below. The approval action becomes available when this list is clear.</div></div>
        @else
            <div class="coverage-guidance is-clear"><span class="coverage-guidance__icon">&#10003;</span><div><strong>Coverage is complete.</strong><br>No currently assigned installation overlaps this period.</div></div>
        @endif

        <div class="coverage-list">
            @foreach($affectedInstallations as $installation)
                <article class="coverage-job">
                    <div class="coverage-job__head"><div><h3>{{ $installation->displayJobReference() }}</h3><span class="record-muted">{{ $installation->application?->reference_no }}</span></div><span class="badge {{ $installation->status }}">{{ $installation->displayStatusLabel() }}</span></div>
                    <div class="coverage-job__meta">
                        <div><strong>Customer</strong><br>{{ $installation->application ? trim($installation->application->first_name.' '.$installation->application->last_name) : 'N/A' }}</div>
                        <div><strong>Schedule</strong><br>{{ $installation->installation_date?->format('M d, Y') }} &middot; {{ $installation->installation_time ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i')) : 'TBD' }}</div>
                        <div><strong>Current technician</strong><br>{{ $installation->technician?->name ?? 'Unassigned' }}</div>
                    </div>
                    <div class="coverage-options">
                        <div class="coverage-option"><strong>Keep the current appointment</strong><p>Open the application and assign an available replacement technician for the same schedule.</p><a class="btn secondary" href="{{ route('admin.applications.show', $installation->application) }}#schedule">Reassign Technician</a></div>
                        <div class="coverage-option">
                            <strong>Change the appointment</strong><p>Use this when no replacement can keep the current schedule. The customer will be notified.</p>
                            <form class="coverage-reschedule-form" method="POST" action="{{ route('admin.unavailability.require-reschedule', [$unavailability, $installation]) }}">
                                @csrf @method('PATCH')
                                <input name="coverage_note" required minlength="10" maxlength="1000" aria-label="Reason for requiring rescheduling" placeholder="Enter an operational reason">
                                <button class="btn danger" type="submit" data-confirm="Move this installation to the reschedule queue? The customer will receive a schedule update without private staff details." data-confirm-title="Require a new schedule?" data-confirm-label="Move to Queue" data-loading-label="Updating...">Require Rescheduling</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="card availability-section">
        <div class="availability-decision-layout">
            <div class="availability-decision-copy">
                <h2>3. Administrator decision</h2>
                @if($reviewIsOpen)
                    <p>Approve the requested period after coverage is complete, request more information, or reject the request and release its scheduling hold.</p>
                    <div class="decision-readiness {{ $coverageIsResolved ? '' : 'is-blocked' }}"><strong>{{ $coverageIsResolved ? 'Ready for decision' : 'Approval temporarily unavailable' }}</strong><br>{{ $coverageIsResolved ? 'All affected installations have been handled.' : 'Resolve '.$affectedCount.' affected '.str('installation')->plural($affectedCount).' before approving this request.' }}</div>
                @else
                    <p>The administrator decision and review time are recorded below.</p>
                    <div class="availability-completed"><strong>{{ $unavailability->statusLabel() }}</strong><br>{{ $unavailability->reviewer?->name ?? 'Administrator' }} recorded this decision {{ $unavailability->reviewed_at?->displayRelative() }}.</div>
                @endif
            </div>

            @if($reviewIsOpen)
                <form method="POST" action="{{ route('admin.unavailability.decision', $unavailability) }}" class="availability-review-form" data-unsaved-warning>
                    @csrf @method('PATCH')
                    @error('decision')<div class="availability-field-error">{{ $message }}</div>@enderror
                    <div class="availability-form-grid">
                        <div class="availability-field"><label for="approved_start_at">Approved start</label><input id="approved_start_at" name="approved_start_at" type="datetime-local" value="{{ old('approved_start_at', $unavailability->requested_start_at->format('Y-m-d\TH:i')) }}"></div>
                        <div class="availability-field"><label for="approved_end_at">Approved end</label><input id="approved_end_at" name="approved_end_at" type="datetime-local" value="{{ old('approved_end_at', $unavailability->requested_end_at->format('Y-m-d\TH:i')) }}"></div>
                    </div>
                    <div class="availability-field"><label for="admin_note">Decision note</label><textarea id="admin_note" name="admin_note" placeholder="Required when requesting clarification or rejecting the request; optional for approval.">{{ old('admin_note') }}</textarea></div>
                    <div class="availability-decision-actions">
                        <button class="btn secondary" name="decision" value="clarification" data-confirm="Return this request to the technician for clarification while keeping the temporary scheduling hold?" data-confirm-title="Request clarification?" data-confirm-label="Send Request">Request Clarification</button>
                        <button class="btn danger" name="decision" value="reject" data-confirm="Reject this request and release its scheduling hold?" data-confirm-title="Reject unavailability?" data-confirm-label="Reject">Reject Request</button>
                        <button class="btn success" name="decision" value="approve" {{ $coverageIsResolved ? '' : 'disabled' }} aria-disabled="{{ $coverageIsResolved ? 'false' : 'true' }}" data-confirm="Approve this period and block it from new assignments?" data-confirm-title="Approve unavailability?" data-confirm-label="Approve">Approve Unavailability</button>
                    </div>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
