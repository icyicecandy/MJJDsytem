<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Ticket Status Updated</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="margin-bottom: 0.5rem;">Ticket Status Updated</h1>
    <p>Your MJJD Telecom support ticket has a new status update.</p>
    <p><strong>Tracking Number:</strong> {{ $ticket->publicReference() }}</p>
    <p><strong>Subject:</strong> {{ $ticket->subject }}</p>
    <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</p>
    @if($ticket->assignedStaff)
        <p><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff->name }} ({{ ucfirst($ticket->assignedStaff->role) }})</p>
    @endif
    <p>Please log in to the customer portal if you need to review the ticket conversation or add a follow-up reply.</p>
    <p>Thank you,<br>MJJD Telecom</p>
</body>
</html>
