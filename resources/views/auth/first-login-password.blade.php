@extends('layouts.app')

@section('title', 'Create Your Password')
@section('hide_global_footer', '1')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/first-login-password.css') }}?v={{ filemtime(public_path('css/first-login-password.css')) }}">
@endpush

@section('content')
<div class="first-password-gate">
    <div class="first-password-gate__backdrop" aria-hidden="true"></div>

    <section class="first-password-dialog" role="dialog" aria-modal="true" aria-labelledby="firstPasswordTitle" aria-describedby="firstPasswordDescription">
        <div class="first-password-dialog__brand">
            <span class="first-password-dialog__brand-mark" aria-hidden="true">{{ auth()->user()->initials ?: 'MT' }}</span>
            <div>
                <strong>MJJD Telecom</strong>
                <span>Technician account security</span>
            </div>
        </div>

        <div class="first-password-dialog__body">
            <p class="first-password-dialog__eyebrow">Required first-login step</p>
            <h1 id="firstPasswordTitle">Create your private password</h1>
            <p id="firstPasswordDescription">
                Welcome, {{ auth()->user()->displayFirstName() }}. Your emailed account key is temporary.
                Replace it now before accessing the technician portal.
            </p>

            <div class="first-password-dialog__notice">
                <span aria-hidden="true">1</span>
                <div>
                    <strong>Enter the temporary account key from your email</strong>
                    <p>Then choose a new password known only to you.</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="first-password-dialog__error" role="alert">
                    <strong>Please check the highlighted field.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('technician.password.change.update') }}" class="first-password-form">
                @csrf
                @method('PUT')

                <label class="first-password-field">
                    <span>Temporary account key</span>
                    <span class="first-password-control">
                        <input
                            type="password"
                            name="current_password"
                            required
                            autofocus
                            autocomplete="current-password"
                            aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                        >
                        <button type="button" data-password-visibility aria-label="Show temporary account key">Show</button>
                    </span>
                    @error('current_password')
                        <small class="first-password-field__error">{{ $message }}</small>
                    @enderror
                </label>

                <div class="first-password-form__grid">
                    <label class="first-password-field">
                        <span>New password</span>
                        <span class="first-password-control">
                            <input
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            >
                            <button type="button" data-password-visibility aria-label="Show new password">Show</button>
                        </span>
                        @error('password')
                            <small class="first-password-field__error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="first-password-field">
                        <span>Confirm new password</span>
                        <span class="first-password-control">
                            <input
                                type="password"
                                name="password_confirmation"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" data-password-visibility aria-label="Show password confirmation">Show</button>
                        </span>
                    </label>
                </div>

                <div class="first-password-requirements" aria-label="Password requirements">
                    <strong>Your new password must include:</strong>
                    <ul>
                        <li>At least 8 characters</li>
                        <li>Uppercase and lowercase letters</li>
                        <li>At least one number and symbol</li>
                        <li>A value different from the temporary key</li>
                    </ul>
                </div>

                <button type="submit" class="first-password-submit">
                    Set New Password and Continue
                    <span aria-hidden="true">’</span>
                </button>
            </form>
        </div>

        <footer class="first-password-dialog__footer">
            <p>You cannot access technician records until this security step is complete.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        </footer>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('first-password-required');

    document.querySelectorAll('[data-password-visibility]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = button.parentElement.querySelector('input');
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            button.textContent = isVisible ? 'Show' : 'Hide';
            button.setAttribute('aria-label', (isVisible ? 'Show ' : 'Hide ') + input.name.replaceAll('_', ' '));
        });
    });
});
</script>
@endpush
