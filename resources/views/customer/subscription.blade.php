@extends('layouts.app')

@section('title','My Subscription')

@section('content')
<div class="card" style="max-width:600px;margin:auto;">
    <h2 class="text-center mb-1">Subscription Details</h2>
    @if($subscription || $installation)
        <p>Plan Name: {{ $installation->package_name ?? optional($subscription)->plan_name ?? 'N/A' }}</p>
        <p>Activation Date: {{ $installation?->activation_date?->format('F d, Y') ?? (optional($subscription)->activated_at ? $subscription->activated_at->format('F d, Y') : '-') }}</p>
        <p>Installation Status: <span class="badge">{{ ucfirst(str_replace('_', ' ', $installation->status ?? 'pending')) }}</span></p>
        <p>Subscription Status: <span class="badge">{{ ucfirst(optional($subscription)->status ?? 'pending') }}</span></p>
        <p>Expiration Date: {{ optional($subscription)->expires_at ? $subscription->expires_at->format('F d, Y') : '-' }}</p>
        <p>Days Remaining: {{ optional($subscription)->expires_at ? now()->diffInDays($subscription->expires_at, false) : '-' }}</p>
    @else
        <p>You do not have an active subscription.</p>
    @endif
</div>
@endsection
