<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;background:#f3f6fb;font-family:Arial,sans-serif;color:#102a56;line-height:1.6;">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px;">
        <div style="background:#0b2555;color:#fff;padding:18px 24px;border-radius:14px 14px 0 0;">
            <strong style="font-size:20px;">MJJD Telecom</strong>
            <div style="font-size:12px;opacity:.8;">Customer Relationship Management System</div>
        </div>
        <div style="background:#fff;border:1px solid #dbe4f0;border-top:0;padding:28px 24px;border-radius:0 0 14px 14px;">
            <p style="margin:0 0 6px;color:#2459d3;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Customer update</p>
            <h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;color:#071d49;">{{ $heading }}</h1>
            <p style="margin:0 0 18px;">{{ $messageText }}</p>

            <div style="background:#f7f9fc;border:1px solid #e0e7f1;border-radius:10px;padding:15px 18px;margin-bottom:20px;">
                <div><strong>Reference:</strong> {{ $reference }}</div>
                @foreach($details as $label => $value)
                    @if(filled($value))
                        <div><strong>{{ $label }}:</strong> {{ $value }}</div>
                    @endif
                @endforeach
            </div>

            <a href="{{ $actionUrl }}" style="display:inline-block;background:#1746b5;color:#fff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:9px;">{{ $actionLabel }}</a>

            <p style="margin:24px 0 0;color:#5a6b85;font-size:13px;">If the button does not open, sign in to the MJJD Telecom customer portal and review the referenced record.</p>
            <p style="margin:20px 0 0;">Thank you,<br>MJJD Telecom</p>
        </div>
    </div>
</body>
</html>
