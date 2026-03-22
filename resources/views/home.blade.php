@extends('layouts.app')

@section('title','Home')

@section('content')
<script>
    // redirect after load based on role
    document.addEventListener('DOMContentLoaded', function() {
        var role = '{{ auth()->check() ? auth()->user()->role : '' }}';
        if(role === 'customer') {
            window.location = '{{ route('customer.dashboard') }}';
        } else if(role === 'technician') {
            window.location = '{{ route('technician.dashboard') }}';
        } else if(role === 'admin') {
            window.location = '{{ route('admin.dashboard') }}';
            window.location = '{{ route('admin.dashboard') }}';
        }
    });
</script>
<div class="text-center mt-5">
    <h1>Welcome to MJJD Telecom</h1>
</div>
@endsection
