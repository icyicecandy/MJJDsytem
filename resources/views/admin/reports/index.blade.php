@extends('layouts.app')

@section('title','Reports')

@section('content')
<h1 class="card">Reports</h1>
<div class="card">
    <div class="chart" data-chart="monthly-applications"></div>
    <div class="chart" data-chart="technician-workload"></div>
    <div class="chart" data-chart="ticket-resolution"></div>
</div>
@endsection
