@extends('layouts.app')

@section('title', 'System Settings')

@push('styles')
<style>
    .settings-page { display: grid; gap: 1.15rem; max-width: 1500px; margin-inline: auto; }
    .settings-hero { position: relative; isolation: isolate; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 2rem; min-height: 190px; padding: 2rem; border: 1px solid rgba(119, 151, 225, .38); border-radius: 1.35rem; color: #fff; background: linear-gradient(125deg, #071a43 0%, #123777 55%, #1e50b5 100%); box-shadow: 0 18px 40px rgba(10, 35, 85, .16); }
    .settings-hero::before { content: ''; position: absolute; inset: 0; z-index: -1; opacity: .28; background: radial-gradient(circle at 82% 20%, #7da5ff 0 2px, transparent 3px), radial-gradient(circle at 72% 65%, #7da5ff 0 2px, transparent 3px), linear-gradient(115deg, transparent 58%, rgba(255,255,255,.16)); background-size: 54px 54px, 72px 72px, auto; }
    .settings-eyebrow { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .65rem; color: #a9c6ff; font-size: .76rem; font-weight: 850; letter-spacing: .13em; text-transform: uppercase; }
    .settings-eyebrow::before { content: ''; width: 1.8rem; height: 2px; border-radius: 999px; background: #63e6be; }
    .settings-hero h1 { margin: 0 0 .55rem; color: #fff; font-size: clamp(2rem, 3.4vw, 3rem); line-height: 1.05; }
    .settings-hero p { margin: 0; max-width: 760px; color: rgba(238, 244, 255, .84); line-height: 1.6; }
    .settings-hero-status { flex: 0 0 265px; display: grid; gap: .8rem; padding: 1.1rem; border: 1px solid rgba(255,255,255,.2); border-radius: 1rem; background: rgba(7, 24, 61, .44); backdrop-filter: blur(10px); }
    .settings-hero-status span { display: flex; align-items: center; gap: .6rem; font-weight: 750; }
    .settings-hero-status i { width: .62rem; height: .62rem; border-radius: 50%; background: #46e6ae; box-shadow: 0 0 0 5px rgba(70,230,174,.13); }
    .settings-hero-status small { color: rgba(238,244,255,.72); line-height: 1.45; }
    .settings-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: .7rem; border: 1px solid #d8e2f2; border-radius: 1rem; background: rgba(255,255,255,.94); box-shadow: 0 10px 28px rgba(17, 42, 91, .08); }
    .settings-nav button { display: grid; grid-template-columns: 2.65rem minmax(0, 1fr) auto; gap: .75rem; align-items: center; min-width: 0; padding: .8rem 1rem; border: 1px solid transparent; border-radius: .8rem; background: transparent; color: #536482; text-align: left; cursor: pointer; }
    .settings-nav button:hover { background: #f3f7ff; border-color: #d2def4; }
    .settings-nav button.is-active { color: #102d70; border-color: #9bb7f3; background: linear-gradient(135deg, #eef4ff, #fff); box-shadow: inset 0 0 0 1px rgba(36,84,211,.12); }
    .settings-nav-icon { display: grid; place-items: center; width: 2.65rem; height: 2.65rem; border-radius: .72rem; color: #2454d3; background: #e9f0ff; }
    .settings-nav-icon svg { width: 1.25rem; height: 1.25rem; }
    .settings-nav-copy { display: grid; gap: .1rem; min-width: 0; }
    .settings-nav-copy strong { color: #0a2559; font-size: .94rem; }
    .settings-nav-copy small { overflow: hidden; color: #6a7894; font-size: .77rem; text-overflow: ellipsis; white-space: nowrap; }
    .settings-nav-count { min-width: 2rem; padding: .25rem .55rem; border-radius: 999px; color: #2454d3; background: #e9f0ff; font-size: .76rem; font-weight: 850; text-align: center; }
    .settings-section { scroll-margin-top: 6.5rem; }
    .settings-section-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.1rem; }
    .settings-section-head h2 { margin: 0 0 .3rem; font-size: 1.45rem; }
    .settings-section-head p { margin: 0; color: #526485; max-width: 760px; }
    .settings-panel { padding: 1.45rem; border: 1px solid #dbe4f1; border-radius: 1.1rem; box-shadow: 0 12px 30px rgba(20, 44, 85, .07); }
    .settings-section-kicker { display: block; margin-bottom: .3rem; color: #2454d3; font-size: .72rem; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
    .settings-section-action { white-space: nowrap; }
    .settings-overview { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .7rem; margin-bottom: 1rem; }
    .settings-overview-card { display: grid; gap: .16rem; padding: .85rem 1rem; border: 1px solid #dce5f3; border-radius: .8rem; background: #f8faff; }
    .settings-overview-card small { color: #657495; font-size: .75rem; font-weight: 750; text-transform: uppercase; letter-spacing: .05em; }
    .settings-overview-card strong { color: #0a2559; font-size: 1.2rem; }
    .settings-note { display: flex; gap: .7rem; align-items: flex-start; padding: .85rem 1rem; border: 1px solid #bfd3fa; border-radius: .8rem; background: #f0f5ff; color: #173d80; font-size: .92rem; line-height: 1.5; }
    .settings-plan-list { display: grid; gap: 1rem; margin-top: 1rem; }
    .settings-plan { border: 1px solid #d9e2f1; border-radius: 1rem; background: #fff; overflow: hidden; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
    .settings-plan:hover { border-color: #a9bfea; transform: translateY(-1px); box-shadow: 0 10px 24px rgba(28, 60, 117, .08); }
    .settings-plan[open] { border-color: #8eadeb; box-shadow: 0 12px 28px rgba(36, 84, 211, .1); }
    .settings-plan summary { display: grid; grid-template-columns: 3.1rem minmax(0, 1fr) auto auto; align-items: center; gap: .9rem; padding: 1rem 1.1rem; cursor: pointer; list-style: none; }
    .settings-plan summary::-webkit-details-marker { display: none; }
    .settings-plan-mark { display: grid; place-items: center; width: 3.1rem; height: 3.1rem; border-radius: .85rem; color: #1745b2; background: linear-gradient(135deg, #e7efff, #f7f9ff); font-size: .82rem; font-weight: 900; }
    .settings-plan-title { display: grid; gap: .15rem; }
    .settings-plan-title strong { font-size: 1.05rem; color: #071d49; }
    .settings-plan-title small { color: #657495; line-height: 1.4; }
    .settings-status { border-radius: 999px; padding: .3rem .7rem; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
    .settings-status--active { color: #08783e; background: #dcfce7; }
    .settings-status--inactive { color: #526075; background: #edf1f7; }
    .settings-chevron { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; color: #1f4db7; background: #eff4ff; font-size: 1.15rem; transition: transform .2s ease; }
    .settings-plan[open] .settings-chevron { transform: rotate(90deg); }
    .settings-plan-form { border-top: 1px solid #d9e2f1; padding: 1.15rem; }
    .settings-plan-availability { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 0 1.15rem 1.15rem; padding: 1rem; border: 1px solid #dce4f1; border-radius: .8rem; background: #f8faff; }
    .settings-plan-availability div { display: grid; gap: .2rem; }
    .settings-plan-availability strong { color: #0b285f; }
    .settings-plan-availability span { color: #657495; font-size: .83rem; line-height: 1.45; }
    .settings-plan-availability form { flex: 0 0 auto; }
    .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .settings-field { display: grid; gap: .4rem; min-width: 0; }
    .settings-field--wide { grid-column: 1 / -1; }
    .settings-field label, .settings-toggle-title { font-weight: 750; color: #0b234f; }
    .settings-field input, .settings-field textarea, .settings-field select { width: 100%; }
    .settings-field textarea { min-height: 105px; resize: vertical; }
    .settings-help { color: #657495; font-size: .84rem; line-height: 1.45; }
    .settings-error { color: #c51f2b; font-size: .84rem; }
    .settings-toggle-row { display: flex; flex-wrap: wrap; gap: 1.2rem; align-items: center; padding: .85rem 1rem; border: 1px solid #d9e2f1; border-radius: .75rem; background: #f8faff; }
    .settings-check { display: inline-flex; align-items: center; gap: .55rem; font-weight: 700; }
    .settings-check input { width: 1.1rem; height: 1.1rem; }
    .settings-form-actions { display: flex; justify-content: flex-end; gap: .65rem; margin-top: 1.1rem; }
    .settings-create { margin-top: 1rem; border: 1px dashed #87a8e8; border-radius: 1rem; background: linear-gradient(135deg, #f3f7ff, #fff); }
    .settings-create summary { cursor: pointer; padding: 1rem 1.1rem; font-weight: 800; color: #1646ad; list-style: none; }
    .settings-create summary::-webkit-details-marker { display: none; }
    .settings-create form { border-top: 1px solid #d9e2f1; padding: 1.15rem; }
    .settings-business-layout { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(270px, .75fr); gap: 1.1rem; align-items: start; }
    .settings-business-form { min-width: 0; }
    .settings-preview { position: sticky; top: 6rem; overflow: hidden; border: 1px solid #d3def0; border-radius: 1rem; background: #fff; box-shadow: 0 12px 26px rgba(23, 52, 105, .08); }
    .settings-preview-head { padding: 1rem 1.1rem; color: #fff; background: linear-gradient(135deg, #0a2254, #194caa); }
    .settings-preview-head span { display: block; margin-bottom: .2rem; color: #a9c6ff; font-size: .7rem; font-weight: 850; letter-spacing: .1em; text-transform: uppercase; }
    .settings-preview-head strong { font-size: 1.05rem; }
    .settings-preview-list { display: grid; padding: .4rem 1rem 1rem; }
    .settings-preview-row { display: grid; gap: .2rem; padding: .8rem .15rem; border-bottom: 1px solid #e2e8f3; }
    .settings-preview-row:last-child { border-bottom: 0; }
    .settings-preview-row small { color: #6b7891; font-size: .7rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .settings-preview-row span { overflow-wrap: anywhere; color: #102a5d; font-weight: 700; line-height: 1.4; }
    @media (max-width: 820px) {
        .settings-hero, .settings-section-head { display: grid; }
        .settings-hero { min-height: 0; padding: 1.4rem; }
        .settings-hero-status { width: 100%; }
        .settings-nav { grid-template-columns: 1fr; }
        .settings-overview { grid-template-columns: 1fr; }
        .settings-grid { grid-template-columns: 1fr; }
        .settings-field--wide { grid-column: auto; }
        .settings-plan summary { grid-template-columns: 2.75rem minmax(0, 1fr) auto; }
        .settings-plan-mark { width: 2.75rem; height: 2.75rem; }
        .settings-plan summary .settings-status { grid-column: 2; justify-self: start; }
        .settings-plan summary .settings-chevron { display: none; }
        .settings-form-actions .btn { flex: 1; }
        .settings-plan-availability { display: grid; }
        .settings-plan-availability .btn { width: 100%; }
        .settings-business-layout { grid-template-columns: 1fr; }
        .settings-preview { position: static; }
    }
    @media (max-width: 520px) {
        .settings-page { gap: .85rem; }
        .settings-panel { padding: 1rem; }
        .settings-nav button { grid-template-columns: 2.4rem minmax(0, 1fr) auto; padding: .7rem; }
        .settings-nav-icon { width: 2.4rem; height: 2.4rem; }
        .settings-nav-copy small { white-space: normal; }
        .settings-plan summary { padding: .85rem; }
    }
</style>
@endpush

@section('content')
@php
    $activePlanCount = $servicePlans->where('is_active', true)->count();
    $featuredPlanCount = $servicePlans->where('is_featured', true)->count();
    $businessErrorFields = ['business_name', 'phone', 'support_email', 'office_address', 'business_hours', 'facebook_url', 'map_url'];
    $initialSettingsTab = $errors->hasAny($businessErrorFields) ? 'business' : 'plans';
@endphp
<div class="settings-page" data-settings-workspace data-initial-tab="{{ $initialSettingsTab }}">
    <header class="settings-hero">
        <div>
            <span class="settings-eyebrow">Administration</span>
            <h1>System Settings</h1>
            <p>Manage the service information shown throughout the customer portal. Updates are recorded in System Logs and applied wherever the same information is displayed.</p>
        </div>
        <div class="settings-hero-status"><span><i aria-hidden="true"></i>Configuration is active</span><small>Changes are protected by confirmation prompts and recorded in System Logs.</small></div>
    </header>

    <nav class="settings-nav" aria-label="Settings sections" role="tablist">
        <button type="button" role="tab" data-settings-tab="plans" aria-controls="service-plans" aria-selected="true">
            <span class="settings-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h10"/><circle cx="18" cy="17" r="2"/></svg></span>
            <span class="settings-nav-copy"><strong>Service Plans</strong><small>Pricing, speeds, features, and availability</small></span>
            <span class="settings-nav-count">{{ $servicePlans->count() }}</span>
        </button>
        <button type="button" role="tab" data-settings-tab="business" aria-controls="business-information" aria-selected="false">
            <span class="settings-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 13h6M9 17h6"/></svg></span>
            <span class="settings-nav-copy"><strong>Business Information</strong><small>Public contact details and office information</small></span>
            <span class="settings-nav-count">1</span>
        </button>
    </nav>

    <section class="card settings-panel settings-section" id="service-plans" role="tabpanel" data-settings-panel="plans">
        <div class="settings-section-head">
            <div>
                <span class="settings-section-kicker">Service catalog</span>
                <h2>Service Plans</h2>
                <p>Edit the plans offered on the landing page and application form. Inactive plans remain in historical records but cannot be selected for new applications.</p>
            </div>
            <button type="button" class="btn settings-section-action" data-open-new-plan>+ Add Service Plan</button>
        </div>

        <div class="settings-overview" aria-label="Service plan summary">
            <div class="settings-overview-card"><small>Total plans</small><strong>{{ $servicePlans->count() }}</strong></div>
            <div class="settings-overview-card"><small>Available to customers</small><strong>{{ $activePlanCount }}</strong></div>
            <div class="settings-overview-card"><small>Featured plans</small><strong>{{ $featuredPlanCount }}</strong></div>
        </div>

        <div class="settings-note">
            <strong>Historical record protection:</strong>
            <span>Every submitted application stores the plan name, speed, fee, description, and features that applied at submission. Editing a plan will not rewrite those records.</span>
        </div>

        <div class="settings-plan-list">
            @foreach($servicePlans as $plan)
                <details class="settings-plan" @if((string) request('plan') === (string) $plan->id || ($errors->any() && old('editing_plan_id') == $plan->id)) open @endif>
                    <summary>
                        <span class="settings-plan-mark">{{ $plan->speed_mbps }}M</span>
                        <span class="settings-plan-title">
                            <strong>{{ $plan->displayName() }}</strong>
                            <small>{{ $plan->code }} &middot; {{ $plan->formattedInstallationFee() }} informational installation fee</small>
                        </span>
                        <span class="settings-status {{ $plan->is_active ? 'settings-status--active' : 'settings-status--inactive' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                        <span class="settings-chevron" aria-hidden="true">&rsaquo;</span>
                    </summary>
                    <form class="settings-plan-form" method="POST" action="{{ route('admin.settings.plans.update', $plan) }}" data-unsaved-warning>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="editing_plan_id" value="{{ $plan->id }}">
                        <div class="settings-grid">
                            <div class="settings-field">
                                <label>Plan code</label>
                                <input value="{{ $plan->code }}" disabled>
                                <span class="settings-help">The code is locked because existing applications use it as a permanent reference.</span>
                            </div>
                            <div class="settings-field">
                                <label for="plan_name_{{ $plan->id }}">Plan name</label>
                                <input id="plan_name_{{ $plan->id }}" name="name" value="{{ old('editing_plan_id') == $plan->id ? old('name') : $plan->name }}" maxlength="100" required>
                            </div>
                            <div class="settings-field">
                                <label for="plan_speed_{{ $plan->id }}">Speed (Mbps)</label>
                                <input id="plan_speed_{{ $plan->id }}" name="speed_mbps" type="number" min="1" max="10000" value="{{ old('editing_plan_id') == $plan->id ? old('speed_mbps') : $plan->speed_mbps }}" required>
                            </div>
                            <div class="settings-field">
                                <label for="plan_fee_{{ $plan->id }}">Installation fee (PHP)</label>
                                <input id="plan_fee_{{ $plan->id }}" name="installation_fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('editing_plan_id') == $plan->id ? old('installation_fee') : $plan->installation_fee }}" required>
                                <span class="settings-help">Displayed for information only; no billing or payment is processed by the system.</span>
                            </div>
                            <div class="settings-field settings-field--wide">
                                <label for="plan_description_{{ $plan->id }}">Customer description</label>
                                <textarea id="plan_description_{{ $plan->id }}" name="description" maxlength="500" required>{{ old('editing_plan_id') == $plan->id ? old('description') : $plan->description }}</textarea>
                            </div>
                            <div class="settings-field settings-field--wide">
                                <label for="plan_features_{{ $plan->id }}">Plan features</label>
                                <textarea id="plan_features_{{ $plan->id }}" name="features_text" maxlength="2000" required>{{ old('editing_plan_id') == $plan->id ? old('features_text') : implode("\n", $plan->features ?? []) }}</textarea>
                                <span class="settings-help">Enter one customer-facing feature per line, up to 10.</span>
                            </div>
                            <div class="settings-field">
                                <label for="plan_badge_{{ $plan->id }}">Optional badge</label>
                                <input id="plan_badge_{{ $plan->id }}" name="badge" value="{{ old('editing_plan_id') == $plan->id ? old('badge') : $plan->badge }}" maxlength="40" placeholder="Example: Most Popular">
                            </div>
                            <div class="settings-field">
                                <label for="plan_order_{{ $plan->id }}">Display order</label>
                                <input id="plan_order_{{ $plan->id }}" name="display_order" type="number" min="0" max="9999" value="{{ old('editing_plan_id') == $plan->id ? old('display_order') : $plan->display_order }}" required>
                            </div>
                            <div class="settings-field settings-field--wide">
                                <div class="settings-toggle-row">
                                    <input type="hidden" name="is_active" value="{{ $plan->is_active ? '1' : '0' }}">
                                    <input type="hidden" name="is_featured" value="0">
                                    <label class="settings-check"><input type="checkbox" name="is_featured" value="1" @checked((bool) (old('editing_plan_id') == $plan->id ? old('is_featured') : $plan->is_featured))> Featured plan styling</label>
                                </div>
                            </div>
                        </div>
                        @if($errors->any() && old('editing_plan_id') == $plan->id)<p class="settings-error">{{ $errors->first() }}</p>@endif
                        <div class="settings-form-actions">
                            <button class="btn" type="submit" data-confirm="Save these service plan changes? New applications and public plan displays will use the updated information. Existing applications keep their submitted plan snapshot." data-confirm-title="Update service plan?" data-confirm-label="Save Plan" data-loading-label="Saving...">Save Plan</button>
                        </div>
                    </form>
                    <div class="settings-plan-availability">
                        <div>
                            <strong>Plan availability</strong>
                            <span>{{ $plan->is_active ? 'Active plans appear on the public website and can be selected in new applications.' : 'This plan is hidden from new applications. Existing customer and report records remain available.' }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.settings.plans.availability', $plan) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="editing_plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="is_active" value="{{ $plan->is_active ? '0' : '1' }}">
                            @if($plan->is_active)
                                <button type="submit" class="btn danger" data-confirm="Deactivate this plan? It will disappear from the landing page and new customer applications, while all historical records remain unchanged." data-confirm-title="Deactivate service plan?" data-confirm-label="Deactivate Plan" data-loading-label="Deactivating...">Deactivate Plan</button>
                            @else
                                <button type="submit" class="btn" data-confirm="Activate this plan and make it available on the landing page and new customer applications?" data-confirm-title="Activate service plan?" data-confirm-label="Activate Plan" data-loading-label="Activating...">Activate Plan</button>
                            @endif
                        </form>
                    </div>
                </details>
            @endforeach
        </div>

        <details class="settings-create" id="new-service-plan" @if(old('editing_plan_id') === 'new') open @endif>
            <summary>+ Add a new service plan</summary>
            <form method="POST" action="{{ route('admin.settings.plans.store') }}" data-unsaved-warning>
                @csrf
                <input type="hidden" name="editing_plan_id" value="new">
                <div class="settings-grid">
                    <div class="settings-field"><label for="new_plan_code">Plan code</label><input id="new_plan_code" name="code" value="{{ old('editing_plan_id') === 'new' ? old('code') : '' }}" maxlength="40" placeholder="Example: S2S_150" required><span class="settings-help">Uppercase letters, numbers, hyphens, and underscores only. This becomes permanent after creation.</span></div>
                    <div class="settings-field"><label for="new_plan_name">Plan name</label><input id="new_plan_name" name="name" value="{{ old('editing_plan_id') === 'new' ? old('name') : '' }}" maxlength="100" required></div>
                    <div class="settings-field"><label for="new_plan_speed">Speed (Mbps)</label><input id="new_plan_speed" name="speed_mbps" type="number" min="1" max="10000" value="{{ old('editing_plan_id') === 'new' ? old('speed_mbps') : '' }}" required></div>
                    <div class="settings-field"><label for="new_plan_fee">Installation fee (PHP)</label><input id="new_plan_fee" name="installation_fee" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('editing_plan_id') === 'new' ? old('installation_fee') : '' }}" required></div>
                    <div class="settings-field settings-field--wide"><label for="new_plan_description">Customer description</label><textarea id="new_plan_description" name="description" maxlength="500" required>{{ old('editing_plan_id') === 'new' ? old('description') : '' }}</textarea></div>
                    <div class="settings-field settings-field--wide"><label for="new_plan_features">Plan features</label><textarea id="new_plan_features" name="features_text" maxlength="2000" placeholder="One feature per line" required>{{ old('editing_plan_id') === 'new' ? old('features_text') : '' }}</textarea></div>
                    <div class="settings-field"><label for="new_plan_badge">Optional badge</label><input id="new_plan_badge" name="badge" value="{{ old('editing_plan_id') === 'new' ? old('badge') : '' }}" maxlength="40"></div>
                    <div class="settings-field"><label for="new_plan_order">Display order</label><input id="new_plan_order" name="display_order" type="number" min="0" max="9999" value="{{ old('editing_plan_id') === 'new' ? old('display_order', 30) : 30 }}" required></div>
                    <div class="settings-field settings-field--wide"><div class="settings-toggle-row"><input type="hidden" name="is_active" value="0"><label class="settings-check"><input type="checkbox" name="is_active" value="1" checked> Available for new applications</label><input type="hidden" name="is_featured" value="0"><label class="settings-check"><input type="checkbox" name="is_featured" value="1"> Featured plan styling</label></div></div>
                </div>
                @if($errors->any() && old('editing_plan_id') === 'new')<p class="settings-error">{{ $errors->first() }}</p>@endif
                <div class="settings-form-actions"><button class="btn" type="submit" data-confirm="Add this service plan to the public catalog and customer application form?" data-confirm-title="Add service plan?" data-confirm-label="Add Plan" data-loading-label="Adding...">Add Plan</button></div>
            </form>
        </details>
    </section>

    <section class="card settings-panel settings-section" id="business-information" role="tabpanel" data-settings-panel="business">
        <div class="settings-section-head">
            <div>
                <span class="settings-section-kicker">Public identity</span>
                <h2>Business Information</h2>
                <p>Maintain the official contact and office details displayed on the public landing page.</p>
            </div>
        </div>
        <div class="settings-business-layout">
        <form class="settings-business-form" method="POST" action="{{ route('admin.settings.business.update') }}" data-unsaved-warning>
            @csrf
            @method('PATCH')
            <div class="settings-grid">
                <div class="settings-field"><label for="business_name">Business name</label><input id="business_name" name="business_name" value="{{ old('business_name', $businessSetting->business_name) }}" maxlength="120" required>@error('business_name')<span class="settings-error">{{ $message }}</span>@enderror</div>
                <div class="settings-field"><label for="business_phone">Phone number</label><input id="business_phone" name="phone" value="{{ old('phone', $businessSetting->phone) }}" maxlength="40" required>@error('phone')<span class="settings-error">{{ $message }}</span>@enderror</div>
                <div class="settings-field"><label for="support_email">Support email</label><input id="support_email" name="support_email" type="email" value="{{ old('support_email', $businessSetting->support_email) }}" maxlength="255" required>@error('support_email')<span class="settings-error">{{ $message }}</span>@enderror</div>
                <div class="settings-field"><label for="business_hours">Business hours</label><input id="business_hours" name="business_hours" value="{{ old('business_hours', $businessSetting->business_hours) }}" maxlength="255" required>@error('business_hours')<span class="settings-error">{{ $message }}</span>@enderror</div>
                <div class="settings-field settings-field--wide"><label for="office_address">Office address</label><textarea id="office_address" name="office_address" maxlength="500" required>{{ old('office_address', $businessSetting->office_address) }}</textarea>@error('office_address')<span class="settings-error">{{ $message }}</span>@enderror</div>
                <div class="settings-field"><label for="facebook_url">Facebook page URL <span class="settings-help">Optional</span></label><input id="facebook_url" name="facebook_url" type="url" value="{{ old('facebook_url', $businessSetting->facebook_url) }}" maxlength="255" placeholder="https://facebook.com/..."></div>
                <div class="settings-field"><label for="map_url">Map link <span class="settings-help">Optional</span></label><input id="map_url" name="map_url" type="url" value="{{ old('map_url', $businessSetting->map_url) }}" maxlength="255" placeholder="https://maps.google.com/..."></div>
            </div>
            <div class="settings-form-actions"><button class="btn" type="submit" data-confirm="Publish these business contact details on the public landing page?" data-confirm-title="Update business information?" data-confirm-label="Publish Changes" data-loading-label="Saving...">Save Business Information</button></div>
        </form>
        <aside class="settings-preview" aria-label="Public business information preview">
            <div class="settings-preview-head"><span>Landing page preview</span><strong data-preview-business-name>{{ old('business_name', $businessSetting->business_name) }}</strong></div>
            <div class="settings-preview-list">
                <div class="settings-preview-row"><small>Phone</small><span data-preview-phone>{{ old('phone', $businessSetting->phone) }}</span></div>
                <div class="settings-preview-row"><small>Support email</small><span data-preview-support-email>{{ old('support_email', $businessSetting->support_email) }}</span></div>
                <div class="settings-preview-row"><small>Business hours</small><span data-preview-business-hours>{{ old('business_hours', $businessSetting->business_hours) }}</span></div>
                <div class="settings-preview-row"><small>Office location</small><span data-preview-office-address>{{ old('office_address', $businessSetting->office_address) }}</span></div>
            </div>
        </aside>
        </div>
    </section>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const workspace = document.querySelector('[data-settings-workspace]');
    if (!workspace) return;

    const tabs = Array.from(workspace.querySelectorAll('[data-settings-tab]'));
    const panels = Array.from(workspace.querySelectorAll('[data-settings-panel]'));
    const tabFromHash = () => window.location.hash === '#business-information' ? 'business' : (window.location.hash === '#service-plans' || window.location.hash === '#new-service-plan' ? 'plans' : null);
    const activate = (name, updateUrl) => {
        tabs.forEach(tab => {
            const active = tab.dataset.settingsTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.settingsPanel !== name; });
        if (updateUrl) history.replaceState(null, '', name === 'business' ? '#business-information' : '#service-plans');
    };

    activate(tabFromHash() || workspace.dataset.initialTab || 'plans', false);
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.settingsTab, true)));
    window.addEventListener('hashchange', () => activate(tabFromHash() || 'plans', false));

    const newPlan = workspace.querySelector('#new-service-plan');
    const openNewPlan = workspace.querySelector('[data-open-new-plan]');
    if (newPlan && openNewPlan) {
        if (window.location.hash === '#new-service-plan') newPlan.open = true;
        openNewPlan.addEventListener('click', () => {
            newPlan.open = true;
            history.replaceState(null, '', '#new-service-plan');
            newPlan.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.setTimeout(() => newPlan.querySelector('input:not([type="hidden"])')?.focus(), 350);
        });
    }

    const previewBindings = {
        business_name: '[data-preview-business-name]',
        phone: '[data-preview-phone]',
        support_email: '[data-preview-support-email]',
        business_hours: '[data-preview-business-hours]',
        office_address: '[data-preview-office-address]'
    };
    Object.entries(previewBindings).forEach(([name, selector]) => {
        const input = workspace.querySelector('[name="' + name + '"]');
        const output = workspace.querySelector(selector);
        if (!input || !output) return;
        input.addEventListener('input', () => { output.textContent = input.value.trim() || 'Not provided'; });
    });
});
</script>
@endpush
@endsection
