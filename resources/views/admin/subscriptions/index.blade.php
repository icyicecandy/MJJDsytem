@extends('layouts.app')

@section('title','Subscriptions')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Subscriptions</h1>
            <p>Review active service terms, track expiry dates, and filter customer subscriptions quickly.</p>
        </div>
    </section>

    <section class="card toolbar-card">
        <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="toolbar-row">
            <div class="toolbar-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                </select>
            </div>

            <div class="toolbar-field grow">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" value="{{ $search }}"
                       placeholder="Subscription ID, customer, email, app ref">
            </div>

            <div class="toolbar-actions">
                <button class="btn">Filter</button>
                @if(request()->filled('status') || request()->filled('search'))
                    <a href="{{ route('admin.subscriptions.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Application Ref</th>
                        <th>Plan/Service</th>
                        <th>Status</th>
                        <th>Activated</th>
                        <th>Expires</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $subscription)
                        @php
                            $user = $subscription->user;
                            $application = $user?->application;
                        @endphp
                        <tr>
                            <td><span class="record-strong">#{{ $subscription->id }}</span></td>
                            <td>{{ $user->name ?? 'N/A' }}</td>
                            <td>{{ $user->email ?? 'N/A' }}</td>
                            <td>{{ $application->reference_no ?? 'N/A' }}</td>
                            <td>{{ $application->plan_code ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $subscription->status }}">
                                    {{ ucfirst($subscription->status) }}
                                </span>
                            </td>
                            <td>
                                @if($subscription->activated_at)
                                    {{ \Carbon\Carbon::parse($subscription->activated_at)->format('Y-m-d') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($subscription->expires_at)
                                    {{ \Carbon\Carbon::parse($subscription->expires_at)->format('Y-m-d') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $subscription->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state compact">No subscriptions matched the current filter.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if(method_exists($subscriptions, 'links'))
        <section class="card toolbar-card">
            {{ $subscriptions->links() }}
        </section>
    @endif
</div>
@endsection

