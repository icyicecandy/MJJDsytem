@extends('layouts.app')

@section('title','System Logs')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>System Logs</h1>
            <p>Latest recorded admin, technician, customer, and system activity.</p>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y h:i A') }}</td>
                            <td><span class="record-strong">{{ $log->displayUser() }}</span></td>
                            <td><span class="record-strong">{{ $log->action }}</span></td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"><div class="empty-state compact">No system logs recorded yet.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
