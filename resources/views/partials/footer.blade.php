<link rel="stylesheet" href="{{ asset('build/assets/css/footer.css') }}">
<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-col brand">
            <div class="footer-logo">
                <img src="{{ asset('build/assets/images/MJJDLOGO.png') }}" alt="MJJD Telecom Logo">
                <h2>MJJD Telecom</h2>
            </div>

            <p>
                Supporting customer applications, installation scheduling, ticketing, and live chat for MJJD Telecom clients in Pangasinan.
            </p>

            <button class="partner-btn">Customer Support Portal</button>
        </div>

        <div class="footer-col">
            <h3>Portal</h3>
            <a href="{{ route('customer.apply') }}">Apply for Service</a>
            <a href="{{ route('track.form') }}">Track Application</a>
            <a href="{{ route('track.ticket.form') }}">Track Ticket</a>
            <a href="{{ url('/') }}">Customer Login</a>
        </div>

        <div class="footer-col">
            <h3>Support</h3>
            <a href="#steps">Application Steps</a>
            <a href="#support">Contact Support</a>
            <a href="{{ url('/') }}">Live Chat</a>
            <a href="{{ url('/') }}">Customer Portal</a>
        </div>

        <div class="footer-col">
            <h3>Contact Us</h3>
            <p>Bayambang, Pangasinan</p>
            <p>+63 985 075 7176</p>
            <p>support&#64;mjjdtelecom.com</p>
        </div>
    </div>

    <div class="footer-bottom">
        &copy; 2026 MJJD Telecom. All rights reserved.
    </div>
</footer>


