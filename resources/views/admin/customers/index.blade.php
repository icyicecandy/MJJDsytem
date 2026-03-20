@extends('layouts.app')

@section('title','Customers')

@section('content')
<div class="page-stack">
    <section class="card section-title-card">
        <div>
            <h1>Customers</h1>
            <p>Customer records are shown in the current module scaffold while the rest of the admin workspace remains connected.</p>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table class="telecom-table admin-customer-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Subscription</th>
                        <th>Tickets</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="record-strong">Jane Doe</span></td>
                        <td>jane@example.com</td>
                        <td>09123456789</td>
                        <td>Brgy X, City Y</td>
                        <td><span class="badge active">Active</span></td>
                        <td>2</td>
                        <td class="actions-cell"><a href="#" class="btn secondary">Open</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
