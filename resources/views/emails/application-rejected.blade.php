<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Application Rejected</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    @php
        $customerName = $application->user?->name ?: trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : 'Customer';
        $rejectionReason = $application->rejection_reason ?: 'No specific reason was recorded.';
    @endphp

    <h1 style="margin-bottom: 0.5rem;">Application Rejected</h1>
    <p>Hello {{ $customerName }},</p>
    <p>Your MJJD Telecom installation application has been marked as <strong>Rejected</strong>.</p>
    <p><strong>Reference Number:</strong> {{ $application->reference_no }}</p>
    <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $application->status)) }}</p>
    <p><strong>Rejection Reason:</strong> {{ $rejectionReason }}</p>
    <p>Please review the latest notes in your customer portal before submitting another application.</p>
    <p>Thank you,<br>MJJD Telecom</p>
</body>
</html>
