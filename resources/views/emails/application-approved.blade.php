<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Application Approved</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="margin-bottom: 0.5rem;">Application Approved</h1>
    <p>Your MJJD Telecom installation application has been approved.</p>
    <p><strong>Reference Number:</strong> {{ $application->reference_no }}</p>
    <p><strong>Plan:</strong> {{ $application->plan_code }}</p>
    <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $application->status)) }}</p>
    <p>Our team can now proceed with technician assignment and installation scheduling. Please monitor your customer portal for the next update.</p>
    <p>Thank you,<br>MJJD Telecom</p>
</body>
</html>
