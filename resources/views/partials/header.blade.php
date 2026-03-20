<link rel="stylesheet" href="{{ asset('build/assets/css/header.css') }}">

<header class="top-header">
    <div class="container">

        <div class="logo">
            <img src="{{ asset('build/assets/images/MJJDLOGO.png') }}" alt="MJJD Telecom Logo">



            <div class="logo-text">
                <h2>TELECOM</h2>
                <span>In Partnership with Surf2Sawa</span>
            </div>
        </div>

        <nav class="nav-links">
            <a href="#">Plans</a>
            <a href="#">Coverage</a>
            <a href="#">How to Apply</a>
            <a href="#">How to Pay</a>
            <a href="#">Login</a>
            <a href="{{ route('customer.apply') }}" class="btn-primary">Apply Now</a>

        </nav>

    </div>
</header>
