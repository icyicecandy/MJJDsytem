@extends('layouts.staff-security')

@section('title', 'Save recovery codes')

@section('content')
<div class="security-card security-card-compact">
    <div class="security-icon security-icon-success" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"></path></svg>
    </div>
    <div class="security-heading">
        <span class="security-eyebrow">Two-factor authentication enabled</span>
        <h1>Save your recovery codes</h1>
        <p>Store these codes somewhere private. Each code can sign you in once if your authenticator device is unavailable.</p>
    </div>

    <div class="security-recovery-codes" id="recoveryCodes">
        @foreach ($codes as $code)
            <code>{{ $code }}</code>
        @endforeach
    </div>

    <div class="security-action-row">
        <button type="button" class="security-secondary-button" data-copy-recovery>Copy codes</button>
        <button type="button" class="security-secondary-button" onclick="window.print()">Print</button>
    </div>

    <div class="security-alert security-alert-warning">
        <strong>These codes are shown only now.</strong>
        <span>You can replace them later from Profile Settings, which invalidates every previous recovery code.</span>
    </div>

    <a href="{{ route('dashboard') }}" class="security-primary-button">I saved my codes — continue</a>
</div>
@endsection

@push('scripts')
<script>
    document.querySelector('[data-copy-recovery]')?.addEventListener('click', async function () {
        const codes = Array.from(document.querySelectorAll('#recoveryCodes code')).map((item) => item.textContent.trim()).join('\n');
        await navigator.clipboard.writeText(codes);
        this.textContent = 'Copied';
    });
</script>
@endpush
