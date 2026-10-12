@extends('layouts.app')

@section('title', 'My Availability')

@php($historyIsActive = request('tab') === 'history' && ! $errors->any())

@push('styles')
<style>
    .availability-page { width:min(100%,1120px); margin-inline:auto; }
    .availability-hero { display:flex; align-items:center; justify-content:space-between; gap:1.25rem; padding:1.35rem 1.5rem; }
    .availability-hero__copy { display:flex; align-items:center; gap:1rem; min-width:0; }
    .availability-hero__icon { display:grid; place-items:center; flex:0 0 auto; width:3.2rem; height:3.2rem; border-radius:1rem; background:#e8f0ff; color:#1746b5; }
    .availability-hero__icon svg { width:1.55rem; height:1.55rem; }
    .availability-hero h1 { margin:0; color:#071d49; font-size:clamp(1.55rem,2.4vw,2rem); }
    .availability-hero p { max-width:760px; margin:.25rem 0 0; color:#60708a; line-height:1.5; }
    .availability-workspace { overflow:hidden; padding:0; }
    .availability-tabs { display:flex; gap:.35rem; padding:.65rem; border-bottom:1px solid #dce5f2; background:#f7f9fc; }
    .availability-tab { display:flex; align-items:center; justify-content:center; gap:.55rem; min-height:2.85rem; padding:.68rem 1.05rem; border:1px solid transparent; border-radius:.75rem; color:#526785; font-size:.9rem; font-weight:800; text-decoration:none; transition:.16s ease; }
    .availability-tab:hover { background:#fff; border-color:#d7e1f0; color:#123d9e; }
    .availability-tab[aria-selected="true"] { background:#fff; border-color:#bfcdea; color:#123d9e; box-shadow:0 .35rem 1rem rgba(28,55,105,.08); }
    .availability-tab__count { display:grid; place-items:center; min-width:1.5rem; height:1.5rem; padding:0 .38rem; border-radius:999px; background:#e8eef8; color:#365176; font-size:.7rem; }
    .availability-tab[aria-selected="true"] .availability-tab__count { background:#dce8ff; color:#1746b5; }
    .availability-panel { padding:1.5rem; }
    .availability-panel[hidden] { display:none!important; }
    .availability-panel__intro { margin-bottom:1.35rem; padding-bottom:1rem; border-bottom:1px solid #e4eaf3; }
    .availability-panel__intro h2 { margin:0; color:#071d49; font-size:1.35rem; }
    .availability-panel__intro p { max-width:720px; margin:.3rem 0 0; color:#60708a; line-height:1.5; }
    .availability-form { display:grid; gap:1.25rem; }
    .availability-form-section { display:grid; gap:1rem; padding:1.15rem; border:1px solid #dce5f2; border-radius:1rem; background:#fbfcfe; }
    .availability-form-section__head { display:flex; align-items:flex-start; gap:.75rem; }
    .availability-form-section__number { display:grid; place-items:center; flex:0 0 auto; width:1.8rem; height:1.8rem; border-radius:.55rem; background:#1746b5; color:#fff; font-size:.78rem; font-weight:900; }
    .availability-form-section__head h3 { margin:0; color:#102a56; font-size:1rem; }
    .availability-form-section__head p { margin:.15rem 0 0; color:#71819a; font-size:.8rem; line-height:1.4; }
    .availability-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    .availability-field { display:grid; gap:.42rem; min-width:0; }
    .availability-field label { color:#183667; font-size:.8rem; font-weight:800; }
    .availability-optional { color:#71819a; font-size:.72rem; font-weight:700; }
    .availability-field input:not([type="file"]),.availability-field select,.availability-field textarea { width:100%; min-width:0; border:1px solid #c8d5e8; border-radius:.75rem; background:#fff; color:#0b2555; padding:.78rem .85rem; font:inherit; transition:border-color .15s ease,box-shadow .15s ease; }
    .availability-field input:focus,.availability-field select:focus,.availability-field textarea:focus { outline:0; border-color:#4d76da; box-shadow:0 0 0 .2rem rgba(36,89,211,.12); }
    .availability-field textarea { min-height:7rem; resize:vertical; }
    .availability-help { margin:0; color:#71819a; font-size:.76rem; line-height:1.45; }
    .availability-upload { position:relative; display:flex; align-items:center; gap:.85rem; min-height:4.25rem; padding:.7rem .8rem; border:1px dashed #9fb4d6; border-radius:.8rem; background:#fff; cursor:pointer; }
    .availability-upload:hover { border-color:#4d76da; background:#f8faff; }
    .availability-upload input { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
    .availability-upload__button { flex:0 0 auto; padding:.6rem .8rem; border-radius:.6rem; background:#e8f0ff; color:#1746b5; font-size:.78rem; font-weight:900; }
    .availability-upload__copy { display:grid; min-width:0; gap:.1rem; }
    .availability-upload__copy strong { color:#183667; font-size:.82rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .availability-upload__copy span { color:#71819a; font-size:.72rem; }
    .availability-form-footer { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .availability-privacy { display:flex; align-items:flex-start; gap:.55rem; max-width:680px; color:#526785; font-size:.78rem; line-height:1.5; }
    .availability-privacy svg { flex:0 0 auto; width:1rem; height:1rem; margin-top:.15rem; color:#2459d3; }
    .availability-form-footer .btn { min-width:11rem; }
    .availability-list { display:grid; gap:1rem; }
    .availability-request { border:1px solid #dbe4f0; border-radius:1rem; background:#fff; padding:1.1rem; box-shadow:0 .4rem 1.1rem rgba(15,35,75,.04); }
    .availability-request__head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
    .availability-request__head h3 { margin:0; color:#071d49; }
    .availability-request__head small { color:#64748b; }
    .availability-status { display:inline-flex; align-items:center; border-radius:999px; padding:.35rem .65rem; font-size:.7rem; font-weight:900; text-transform:uppercase; letter-spacing:.04em; background:#e8eef8; color:#365176; }
    .availability-status.pending { background:#fff3d6; color:#9a4b00; }.availability-status.approved { background:#dcfce7; color:#14743b; }.availability-status.clarification_requested { background:#e8f0ff; color:#1746b5; }.availability-status.rejected { background:#fee8e8; color:#b42318; }
    .availability-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; margin-top:1rem; }
    .availability-meta div { border-radius:.75rem; background:#f7f9fc; padding:.75rem; }
    .availability-meta span { display:block; color:#64748b; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
    .availability-meta strong { display:block; margin-top:.2rem; color:#102a56; }
    .availability-note { margin-top:.9rem; border-left:3px solid #2459d3; background:#f5f8ff; padding:.75rem .85rem; color:#30496e; }
    .availability-actions { display:flex; flex-wrap:wrap; gap:.65rem; margin-top:1rem; }
    .availability-resubmit { margin-top:1rem; border-top:1px solid #e2e8f0; padding-top:1rem; }.availability-resubmit summary { cursor:pointer; color:#1746b5; font-weight:800; }
    .availability-empty { display:grid; place-items:center; min-height:13rem; padding:2rem; border:1px dashed #c9d6e8; border-radius:1rem; background:#f9fbfe; text-align:center; }
    .availability-empty__icon { display:grid; place-items:center; width:3.25rem; height:3.25rem; margin:0 auto .75rem; border-radius:1rem; background:#e8f0ff; color:#1746b5; }.availability-empty__icon svg { width:1.55rem; height:1.55rem; }
    .availability-empty h3 { margin:0; color:#102a56; }.availability-empty p { max-width:430px; margin:.35rem 0 0; color:#71819a; }
    @media(max-width:700px){
        .availability-page{width:100%}.availability-hero{align-items:flex-start;padding:1rem}.availability-hero__icon{width:2.7rem;height:2.7rem}.availability-hero .btn{display:none}
        .availability-tabs{position:sticky;top:var(--navbar-height,72px);z-index:5}.availability-tab{flex:1;padding:.62rem .55rem}.availability-panel{padding:1rem}
        .availability-form-grid,.availability-meta{grid-template-columns:1fr}.availability-form-section{padding:1rem}.availability-form-footer{align-items:stretch;flex-direction:column}
        .availability-form-footer .btn,.availability-actions .btn{width:100%;text-align:center}.availability-request__head{align-items:flex-start;flex-direction:column}
    }
</style>
@endpush

@section('content')
<div class="page-stack availability-page">
    <section class="card availability-hero">
        <div class="availability-hero__copy"><span class="availability-hero__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="17" rx="2"/><path d="m9 16 2 2 4-5"/></svg></span><div><h1>My Availability</h1><p>Report planned leave or an emergency and follow the administrator's decision from one place.</p></div></div>
        <a href="{{ route('technician.dashboard') }}" class="btn secondary">Back to Dashboard</a>
    </section>

    <section class="card availability-workspace" data-availability-workspace>
        <nav class="availability-tabs" role="tablist" aria-label="Availability sections">
            <a href="{{ route('technician.unavailability.index',['tab'=>'new']) }}" class="availability-tab" role="tab" id="availabilityNewTab" aria-controls="availabilityNewPanel" aria-selected="{{ $historyIsActive ? 'false' : 'true' }}" data-availability-tab="new">New Request</a>
            <a href="{{ route('technician.unavailability.index',['tab'=>'history']) }}" class="availability-tab" role="tab" id="availabilityHistoryTab" aria-controls="availabilityHistoryPanel" aria-selected="{{ $historyIsActive ? 'true' : 'false' }}" data-availability-tab="history">Request History <span class="availability-tab__count">{{ $requests->total() }}</span></a>
        </nav>

        <div class="availability-panel" id="availabilityNewPanel" role="tabpanel" aria-labelledby="availabilityNewTab" data-availability-panel="new" @if($historyIsActive) hidden @endif>
            <div class="availability-panel__intro"><h2>Report unavailability</h2><p>A temporary scheduling hold begins after submission. Existing jobs remain unchanged until the administrator reviews their coverage.</p></div>
            <form method="POST" action="{{ route('technician.unavailability.store',['tab'=>'new']) }}" enctype="multipart/form-data" class="availability-form" data-unsaved-warning>
                @csrf
                <section class="availability-form-section">
                    <div class="availability-form-section__head"><span class="availability-form-section__number">1</span><div><h3>Unavailable period</h3><p>Select the exact start and end of the period to protect.</p></div></div>
                    <div class="availability-form-grid">
                        <div class="availability-field"><label for="requested_start_at">Start date and time</label><input id="requested_start_at" name="requested_start_at" type="datetime-local" value="{{ old('requested_start_at') }}" required>@error('requested_start_at')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="availability-field"><label for="requested_end_at">End date and time</label><input id="requested_end_at" name="requested_end_at" type="datetime-local" value="{{ old('requested_end_at') }}" required>@error('requested_end_at')<span class="field-error">{{ $message }}</span>@enderror</div>
                    </div>
                </section>
                <section class="availability-form-section">
                    <div class="availability-form-section__head"><span class="availability-form-section__number">2</span><div><h3>Reason and supporting information</h3><p>Provide enough detail for the administrator to decide and plan coverage.</p></div></div>
                    <div class="availability-field"><label for="category">Reason category</label><select id="category" name="category" required><option value="">Select a category</option>@foreach($categories as $value=>$label)<option value="{{ $value }}" @selected(old('category')===$value)>{{ $label }}</option>@endforeach</select>@error('category')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="availability-field"><label for="reason">Operational reason</label><textarea id="reason" name="reason" required placeholder="Briefly explain why you will be unavailable and any timing details the administrator should know.">{{ old('reason') }}</textarea><p class="availability-help">This explanation is visible only to authorized staff.</p>@error('reason')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="availability-field"><label for="evidence">Supporting file <span class="availability-optional">Optional</span></label><label class="availability-upload" for="evidence"><input id="evidence" name="evidence" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" data-availability-file><span class="availability-upload__button">Choose File</span><span class="availability-upload__copy"><strong data-availability-file-name>No file selected</strong><span>JPG, PNG, WebP, or PDF · maximum 5 MB</span></span></label>@error('evidence')<span class="field-error">{{ $message }}</span>@enderror</div>
                </section>
                <div class="availability-form-footer"><div class="availability-privacy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Your reason and proof remain private. Customers receive only schedule-related information if their appointment changes.</span></div><button class="btn" type="submit" data-confirm="Submit this unavailability request and place a temporary scheduling hold on the selected period?" data-confirm-title="Report unavailability?" data-confirm-label="Submit Request" data-loading-label="Submitting...">Submit Request</button></div>
            </form>
        </div>

        <div class="availability-panel" id="availabilityHistoryPanel" role="tabpanel" aria-labelledby="availabilityHistoryTab" data-availability-panel="history" @unless($historyIsActive) hidden @endunless>
            <div class="availability-panel__intro"><h2>Request history</h2><p>Review decisions, approved periods, administrator notes, and requests that need clarification.</p></div>
            <div class="availability-list">
                @forelse($requests as $unavailability)
                    <article class="availability-request">
                        <div class="availability-request__head"><div><h3>{{ $unavailability->reference() }}</h3><small>Submitted {{ $unavailability->created_at->displayDateTime('M d, Y g:i A') }}</small></div><span class="availability-status {{ $unavailability->status }}">{{ $unavailability->statusLabel() }}</span></div>
                        <div class="availability-meta"><div><span>Requested period</span><strong>{{ $unavailability->requested_start_at->format('M d, Y g:i A') }} &ndash; {{ $unavailability->requested_end_at->format('M d, Y g:i A') }}</strong></div><div><span>Category</span><strong>{{ $unavailability->categoryLabel() }}</strong></div>@if($unavailability->status===\App\Models\TechnicianUnavailabilityRequest::STATUS_APPROVED)<div><span>Approved period</span><strong>{{ $unavailability->effectiveStartAt()->format('M d, Y g:i A') }} &ndash; {{ $unavailability->effectiveEndAt()->format('M d, Y g:i A') }}</strong></div>@endif<div><span>Coverage review</span><strong>{{ $unavailability->affected_installations_count }} overlapping {{ \Illuminate\Support\Str::plural('installation',$unavailability->affected_installations_count) }}</strong></div></div>
                        <p><strong>Reason:</strong> {{ $unavailability->reason }}</p>
                        @if($unavailability->admin_note)<div class="availability-note"><strong>Administrator note:</strong> {{ $unavailability->admin_note }}</div>@endif
                        <div class="availability-actions">@if($unavailability->evidence_path)<a class="btn secondary" href="{{ route('technician.unavailability.evidence',$unavailability) }}">Download My Evidence</a>@endif</div>
                        @if($unavailability->status===\App\Models\TechnicianUnavailabilityRequest::STATUS_CLARIFICATION)
                            <details class="availability-resubmit" @if($errors->any()) open @endif><summary>Update and resubmit this request</summary><form method="POST" action="{{ route('technician.unavailability.resubmit',['unavailability'=>$unavailability,'tab'=>'history']) }}" enctype="multipart/form-data" class="availability-form" data-unsaved-warning>@csrf @method('PATCH')<div class="availability-form-grid"><div class="availability-field"><label>Start date and time</label><input name="requested_start_at" type="datetime-local" value="{{ old('requested_start_at',$unavailability->requested_start_at->format('Y-m-d\TH:i')) }}" required></div><div class="availability-field"><label>End date and time</label><input name="requested_end_at" type="datetime-local" value="{{ old('requested_end_at',$unavailability->requested_end_at->format('Y-m-d\TH:i')) }}" required></div></div><div class="availability-field"><label>Reason category</label><select name="category" required>@foreach($categories as $value=>$label)<option value="{{ $value }}" @selected(old('category',$unavailability->category)===$value)>{{ $label }}</option>@endforeach</select></div><div class="availability-field"><label>Updated explanation</label><textarea name="reason" required>{{ old('reason',$unavailability->reason) }}</textarea></div><div class="availability-field"><label>Replace supporting file <span class="availability-optional">Optional</span></label><input name="evidence" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"></div><button class="btn" type="submit" data-confirm="Return this updated request to the administrator for review?" data-loading-label="Resubmitting...">Resubmit Request</button></form></details>
                        @endif
                    </article>
                @empty
                    <div class="availability-empty"><div><span class="availability-empty__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="17" rx="2"/></svg></span><h3>No requests yet</h3><p>Your submitted requests and administrator decisions will appear here.</p></div></div>
                @endforelse
            </div>
            {{ $requests->appends(['tab'=>'history'])->links() }}
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',function(){
    const workspace=document.querySelector('[data-availability-workspace]');if(!workspace)return;
    const tabs=Array.from(workspace.querySelectorAll('[data-availability-tab]'));const panels=Array.from(workspace.querySelectorAll('[data-availability-panel]'));
    const activate=function(name,updateUrl){tabs.forEach(tab=>tab.setAttribute('aria-selected',tab.dataset.availabilityTab===name?'true':'false'));panels.forEach(panel=>panel.hidden=panel.dataset.availabilityPanel!==name);if(updateUrl&&window.history?.replaceState){const url=new URL(window.location.href);url.searchParams.set('tab',name);window.history.replaceState({},'',url);}};
    tabs.forEach(function(tab){tab.addEventListener('click',function(event){event.preventDefault();activate(tab.dataset.availabilityTab,true);tab.focus();});tab.addEventListener('keydown',function(event){if(!['ArrowLeft','ArrowRight'].includes(event.key))return;event.preventDefault();const index=tabs.indexOf(tab);const next=event.key==='ArrowRight'?(index+1)%tabs.length:(index-1+tabs.length)%tabs.length;activate(tabs[next].dataset.availabilityTab,true);tabs[next].focus();});});
    const fileInput=workspace.querySelector('[data-availability-file]');const fileName=workspace.querySelector('[data-availability-file-name]');fileInput?.addEventListener('change',function(){fileName.textContent=fileInput.files?.[0]?.name||'No file selected';});
});
</script>
@endpush
