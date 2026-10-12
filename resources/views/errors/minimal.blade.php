<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} - {{ $title }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:1.25rem;background:linear-gradient(145deg,#061938,#12316d);color:#061d4d;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}.error-card{width:min(42rem,100%);background:#fff;border-radius:1.25rem;padding:clamp(1.5rem,5vw,3rem);box-shadow:0 1.5rem 4rem #0005}.brand{color:#1356d8;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.code{display:inline-block;margin-top:1.5rem;border-radius:999px;background:#eef4ff;color:#1243a5;font-weight:800;padding:.35rem .75rem}h1{font-size:clamp(1.8rem,5vw,3rem);line-height:1.1;margin:.8rem 0}p{color:#405477}.actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}a,button{border:0;border-radius:.7rem;padding:.8rem 1rem;font:inherit;font-weight:700;cursor:pointer}a{background:#174bc2;color:#fff;text-decoration:none}button{background:#eef3fb;color:#0b2b64}@media(max-width:420px){.actions>*{width:100%;text-align:center}}
    </style>
</head>
<body>
    <main class="error-card">
        <div class="brand">MJJD Telecom</div>
        <span class="code">Error {{ $status }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <button type="button" onclick="history.back()">Go back</button>
            <a href="{{ url('/') }}">Return to home</a>
        </div>
    </main>
</body>
</html>
