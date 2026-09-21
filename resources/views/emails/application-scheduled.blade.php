<p>Hello {{ $application->first_name ?: 'Customer' }},</p>

<p>Your MJJD Telecom application has been scheduled for installation.</p>

@if($application->installation)
    <p>
        <strong>Reference:</strong> {{ $application->reference_no }}<br>
        <strong>Installation Date:</strong> {{ $application->installation->installation_date?->format('F d, Y') ?? 'To be confirmed' }}<br>
        <strong>Installation Time:</strong> {{ $application->installation->installation_time ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($application->installation->installation_time)->format('H:i')) : 'To be confirmed' }}
    </p>
@else
    <p><strong>Reference:</strong> {{ $application->reference_no }}</p>
@endif

<p>You can sign in to your customer portal to review the latest application and installation details.</p>

<p>Thank you,<br>MJJD Telecom</p>
