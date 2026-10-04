<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Technician Account Access</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="margin-bottom: 0.5rem;">Technician Account Created</h1>

    <p>Hello {{ $technician->displayFirstName() ?: $technician->name }},</p>

    <p>Your MJJD Telecom technician account has been created. Use the account details below to sign in.</p>

    <p>
        <strong>Email:</strong> {{ $technician->email }}<br>
        <strong>Account Key:</strong> {{ $temporaryPassword }}<br>
        <strong>Technician ID:</strong> {{ $technician->displayTechnicianRecordId() }}
    </p>

    <p><a href="{{ $loginUrl }}">Sign in to MJJD Telecom</a></p>

    <p><strong>This is a temporary account key.</strong> After signing in, you must create a new private password before the technician portal becomes available.</p>

    <p>Keep this key private. It is used only to complete your first sign-in and required password change.</p>

    <p>Thank you,<br>MJJD Telecom</p>
</body>
</html>
