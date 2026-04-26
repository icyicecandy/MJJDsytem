<link rel="stylesheet" href="{{ asset('build/assets/css/header.css') }}">

<header class="top-header">
    <div class="container">
        <div class="logo">
            <img src="{{ asset('build/assets/images/MJJDLOGO.png') }}" alt="MJJD Telecom Logo">
            <div class="logo-text">
                <h2>TELECOM</h2>
                <span>Customer Service Portal</span>
            </div>
        </div>

        <nav class="nav-links">
            <a href="#plans">Plans</a>
            <a href="#steps">How to Apply</a>
            <a href="#support">Support</a>
            <a href="{{ route('track.form') }}">Track Application</a>
            <a href="{{ url('/') }}">Login</a>
            <a href="{{ route('customer.apply') }}" class="btn-primary">Apply Now</a>
        </nav>
    </div>
</header>


