<x-app-layout>
    <div class="apply-wizard-shell apply-done-shell">
        <div class="apply-stepper apply-stepper-complete">
            <div class="apply-step is-complete"><span>?</span><label>Applicant</label></div>
            <div class="apply-step is-complete"><span>?</span><label>Location</label></div>
            <div class="apply-step is-complete"><span>?</span><label>Plan</label></div>
            <div class="apply-step is-complete"><span>?</span><label>Upload ID</label></div>
            <div class="apply-step is-complete"><span>?</span><label>Submitted</label></div>
        </div>

        <section class="panel-card apply-done-card">
            <div class="apply-done-icon">?</div>
            <h1>Application Submitted!</h1>
            <p>
                Thank you for choosing MJJD Telecom. Your reference number is
                <strong>{{ $reference ?? 'APP-XXXX-0000' }}</strong>. We will review your documents and contact you within 24-48 hours.
            </p>

            <div class="apply-done-actions">
                <a href="{{ route('customer.dashboard') }}" class="btn">Go to My Portal</a>
                <a href="/" class="apply-link-btn center-link">Return Home</a>
            </div>
        </section>
    </div>
</x-app-layout>
