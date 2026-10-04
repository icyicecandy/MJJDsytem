@php
    $registerErrors = $errors->getBag('register');
    $registerModalActive = session('auth_modal') === 'register' || $registerErrors->any();
@endphp

<div class="login-modal register-modal" id="registerModal" aria-hidden="true">
    <div class="login-modal-backdrop" data-close-register-modal></div>
    <div class="login-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="registerModalTitle">
        <button type="button" class="login-modal-close" aria-label="Close registration" data-close-register-modal>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
            </svg>
        </button>

        <div class="login-modal-shell register-modal-shell">
            <div class="login-modal-intro register-modal-intro">
                <h2 id="registerModalTitle">Create your account</h2>
                <p>Register to manage your account, applications, and service updates without leaving the homepage.</p>
            </div>

            @if (session('google_auth_error'))
                <div class="alert alert-error">{{ session('google_auth_error') }}</div>
            @endif

            <div class="register-modal-social-row">
                <a href="{{ route('auth.google.redirect', ['mode' => 'register']) }}" class="btn secondary auth-social-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                        <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.3-1.9 3.1l3.1 2.4c1.8-1.7 2.8-4.1 2.8-6.9 0-.7-.1-1.4-.2-2H12Z"/>
                        <path fill="#34A853" d="M6.5 14.3l-.7.6-2.5 2c1.6 3.1 4.8 5.1 8.7 5.1 2.5 0 4.7-.8 6.3-2.3l-3.1-2.4c-.9.6-2 .9-3.2.9-2.4 0-4.5-1.6-5.2-3.9Z"/>
                        <path fill="#4A90E2" d="M3.3 7.1A10 10 0 0 0 3.3 17l3.2-2.6a6 6 0 0 1 0-4.8Z"/>
                        <path fill="#FBBC05" d="M12 5.8c1.4 0 2.7.5 3.7 1.4l2.8-2.8C16.7 2.8 14.5 2 12 2 8.1 2 4.9 4 3.3 7.1l3.2 2.6c.7-2.3 2.8-3.9 5.5-3.9Z"/>
                    </svg>
                    <span>Continue with Google</span>
                </a>
            </div>

            <div class="auth-divider" aria-hidden="true">
                <span>or create an account with your email</span>
            </div>

            <form method="POST" action="{{ route('register') }}" class="register-auth-form" id="registerModalForm" novalidate>
                @csrf
                <input type="hidden" name="role" value="customer" />

                @if ($registerErrors->any())
                    <div class="register-error-banner" role="alert">
                        <svg viewBox="0 0 24 24" class="register-error-banner-icon" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <span>Please complete the highlighted fields.</span>
                    </div>
                @endif

                <div class="register-auth-grid">
                    <div class="register-field {{ $registerErrors->has('first_name') ? 'has-error' : '' }}">
                        <label for="register_modal_first_name">First Name</label>
                        <input id="register_modal_first_name" type="text" name="first_name" value="{{ $registerModalActive ? old('first_name') : '' }}" required autofocus placeholder="Jane" maxlength="255" />
                        <span class="error" id="registerFirstNameLiveError" @unless ($registerErrors->has('first_name')) hidden @endunless>{{ $registerErrors->first('first_name') }}</span>
                    </div>
                    <div class="register-field {{ $registerErrors->has('last_name') ? 'has-error' : '' }}">
                        <label for="register_modal_last_name">Last Name</label>
                        <input id="register_modal_last_name" type="text" name="last_name" value="{{ $registerModalActive ? old('last_name') : '' }}" required placeholder="Doe" maxlength="255" />
                        <span class="error" id="registerLastNameLiveError" @unless ($registerErrors->has('last_name')) hidden @endunless>{{ $registerErrors->first('last_name') }}</span>
                    </div>
                </div>

                <div class="register-field {{ $registerErrors->has('email') ? 'has-error' : '' }}">
                    <label for="register_modal_email">Email Address</label>
                        <input id="register_modal_email" type="email" name="email" value="{{ $registerModalActive ? old('email') : '' }}" required placeholder="you&#64;example.com" maxlength="255" />
                    <span class="error" id="registerEmailLiveError" @unless ($registerErrors->has('email')) hidden @endunless>{{ $registerErrors->first('email') }}</span>
                </div>

                <div class="register-auth-grid register-password-grid">
                    <div class="register-field password-group {{ $registerErrors->has('password') ? 'has-error' : '' }}">
                        <label for="register_modal_password">Password</label>
                        <input id="register_modal_password" type="password" name="password" required placeholder="Create a password" />
                        <button
                            type="button"
                            class="register-password-toggle show-password"
                            data-target="register_modal_password"
                            aria-label="Show password"
                            title="Show password"
                        >
                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 5C6.5 5 2.1 8.4 1 12c1.1 3.6 5.5 7 11 7s9.9-3.4 11-7c-1.1-3.6-5.5-7-11-7Zm0 11.2A4.2 4.2 0 1 1 12 7.8a4.2 4.2 0 0 1 0 8.4Zm0-6.7A2.5 2.5 0 1 0 12 14.5a2.5 2.5 0 0 0 0-5Z"/>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="m3.3 4.5 16.2 15-1.1 1.2-3-2.8A12.8 12.8 0 0 1 12 19c-5.5 0-9.9-3.4-11-7 .5-1.8 1.9-3.5 3.8-4.7L2.2 5.7l1.1-1.2Zm7 6.5 3.2 3a2.5 2.5 0 0 1-3.2-3Zm1.7-6c5.5 0 9.9 3.4 11 7-.4 1.4-1.4 2.8-2.8 4l-2.2-2c.7-.6 1.3-1.3 1.6-2-1.1-2.7-4.6-5.3-8.6-5.3-1 0-2 .2-2.9.5L6.4 5.9c1.7-.6 3.6-.9 5.6-.9Zm-.5 2.8a4.2 4.2 0 0 1 4.1 5l-1.6-1.5a2.5 2.5 0 0 0-2.8-2.7l-1.6-1.5c.6-.2 1.2-.3 1.9-.3Z"/>
                            </svg>
                        </button>
                        @error('password', 'register')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="register-field password-group {{ $registerErrors->has('password_confirmation') ? 'has-error' : '' }}">
                        <label for="register_modal_password_confirmation">Confirm Password</label>
                        <input id="register_modal_password_confirmation" type="password" name="password_confirmation" required placeholder="Confirm your password" />
                        <button
                            type="button"
                            class="register-password-toggle show-password"
                            data-target="register_modal_password_confirmation"
                            aria-label="Show password"
                            title="Show password"
                        >
                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 5C6.5 5 2.1 8.4 1 12c1.1 3.6 5.5 7 11 7s9.9-3.4 11-7c-1.1-3.6-5.5-7-11-7Zm0 11.2A4.2 4.2 0 1 1 12 7.8a4.2 4.2 0 0 1 0 8.4Zm0-6.7A2.5 2.5 0 1 0 12 14.5a2.5 2.5 0 0 0 0-5Z"/>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="m3.3 4.5 16.2 15-1.1 1.2-3-2.8A12.8 12.8 0 0 1 12 19c-5.5 0-9.9-3.4-11-7 .5-1.8 1.9-3.5 3.8-4.7L2.2 5.7l1.1-1.2Zm7 6.5 3.2 3a2.5 2.5 0 0 1-3.2-3Zm1.7-6c5.5 0 9.9 3.4 11 7-.4 1.4-1.4 2.8-2.8 4l-2.2-2c.7-.6 1.3-1.3 1.6-2-1.1-2.7-4.6-5.3-8.6-5.3-1 0-2 .2-2.9.5L6.4 5.9c1.7-.6 3.6-.9 5.6-.9Zm-.5 2.8a4.2 4.2 0 0 1 4.1 5l-1.6-1.5a2.5 2.5 0 0 0-2.8-2.7l-1.6-1.5c.6-.2 1.2-.3 1.9-.3Z"/>
                            </svg>
                        </button>
                        @error('password_confirmation', 'register')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <section class="register-modal-consent-wrap">
                    <div class="consent-form-group">
                        @include('partials.consent-agreement', [
                            'consentPrefix' => 'register_modal_consent',
                            'formId' => 'registerModalForm',
                            'helperText' => 'Review and accept the Terms and Privacy Notice to continue.',
                            'sectionClass' => 'register-consent-section',
                            'panelClass' => 'register-consent-panel',
                            'optionClass' => 'register-consent-option',
                            'optionMainClass' => 'register-consent-option-main',
                            'optionTextClass' => 'register-consent-option-text',
                            'linkClass' => 'register-consent-link',
                            'errorClass' => 'register-consent-error',
                            'validationBag' => 'register'
                        ])
                    </div>
                </section>

                <div class="register-modal-submit-row">
                    <button type="submit" class="btn">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M15 11a4 4 0 1 0-3.999-4A4 4 0 0 0 15 11Zm-8 1a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm8 2c-2.33 0-7 1.17-7 3.5V20h14v-2.5C22 15.17 17.33 14 15 14Zm-8 0c-.29 0-.62.02-.97.05A4.73 4.73 0 0 1 8 17.5V20H2v-1.5C2 16.57 5.57 14 7 14Zm13-5h-2V7h-2v2h-2v2h2v2h2v-2h2Z"/>
                        </svg>
                        <span>Create Account</span>
                    </button>
                </div>

                <p class="small-text register-modal-footer">Already have an account? <a href="{{ url('/') }}" data-switch-modal="login">Sign in</a></p>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('registerModalForm');
                if (!form) return;

                const firstNameInput = document.getElementById('register_modal_first_name');
                const lastNameInput = document.getElementById('register_modal_last_name');
                const emailInput = document.getElementById('register_modal_email');
                const firstNameError = document.getElementById('registerFirstNameLiveError');
                const lastNameError = document.getElementById('registerLastNameLiveError');
                const emailError = document.getElementById('registerEmailLiveError');
                const emailCheckUrl = @json(route('register.email-check'));
                const hasServerErrors = @json($registerErrors->any());
                const namePattern = /^[\p{L}][\p{L}\p{M}\s'.-]*$/u;
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                let emailCheckTimer = null;
                let latestEmailRequest = 0;

                function normalizeEmailValue(value) {
                    return String(value || '')
                        .replace(/[\u200B-\u200D\uFEFF]/g, '')
                        .replace(/\s+/g, '')
                        .trim();
                }

                function setLiveError(field, errorElement, message) {
                    if (!field) return;
                    field.setCustomValidity(message || '');
                    field.classList.toggle('is-invalid', Boolean(message));
                    if (errorElement) {
                        errorElement.textContent = message || '';
                        errorElement.hidden = !message;
                    }
                }

                function validateNameField(field, errorElement, label) {
                    if (!field) return true;

                    const value = field.value.trim();
                    if (value === '') {
                        setLiveError(field, errorElement, '');
                        return true;
                    }

                    if (!namePattern.test(value)) {
                        setLiveError(field, errorElement, `${label} may contain letters, spaces, apostrophes, periods, and hyphens.`);
                        return false;
                    }

                    setLiveError(field, errorElement, '');
                    return true;
                }

                function validateEmailFormat() {
                    if (!emailInput) return true;

                    const value = normalizeEmailValue(emailInput.value);
                    if (emailInput.value !== value) {
                        emailInput.value = value;
                    }

                    if (value === '') {
                        setLiveError(emailInput, emailError, '');
                        return true;
                    }

                    if (!emailPattern.test(value)) {
                        setLiveError(emailInput, emailError, 'Please enter a valid email address.');
                        return false;
                    }

                    setLiveError(emailInput, emailError, '');
                    return true;
                }

                function runEmailDuplicateCheck() {
                    if (!emailInput || !validateEmailFormat()) {
                        return Promise.resolve(false);
                    }

                    const value = normalizeEmailValue(emailInput.value);
                    emailInput.value = value;
                    if (value === '') {
                        setLiveError(emailInput, emailError, '');
                        return Promise.resolve(true);
                    }

                    const requestId = ++latestEmailRequest;

                    return fetch(`${emailCheckUrl}?email=${encodeURIComponent(value)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    })
                        .then(response => response.ok ? response.json() : Promise.reject())
                        .then(data => {
                            if (requestId !== latestEmailRequest) {
                                return true;
                            }

                            const message = data?.email?.duplicate
                                ? (data.email.message || 'This email address is already registered. Please sign in instead.')
                                : '';

                            setLiveError(emailInput, emailError, message);
                            return !message;
                        })
                        .catch(() => true);
                }

                function scheduleEmailDuplicateCheck() {
                    clearTimeout(emailCheckTimer);
                    emailCheckTimer = setTimeout(function () {
                        runEmailDuplicateCheck();
                    }, 350);
                }

                function focusFirstInvalidField() {
                    const modal = document.getElementById('registerModal');
                    const firstInvalid = form.querySelector('.register-field.has-error input, .register-consent-panel.has-error [data-consent-open], .register-consent-panel.has-error [data-consent-error]');

                    if (!modal || !firstInvalid || !modal.classList.contains('is-open')) {
                        return;
                    }

                    firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    window.setTimeout(function () {
                        firstInvalid.focus({ preventScroll: true });
                    }, 180);
                }

                if (firstNameInput) {
                    firstNameInput.addEventListener('input', function () {
                        validateNameField(firstNameInput, firstNameError, 'First name');
                    });
                    firstNameInput.addEventListener('blur', function () {
                        validateNameField(firstNameInput, firstNameError, 'First name');
                    });
                }

                if (lastNameInput) {
                    lastNameInput.addEventListener('input', function () {
                        validateNameField(lastNameInput, lastNameError, 'Last name');
                    });
                    lastNameInput.addEventListener('blur', function () {
                        validateNameField(lastNameInput, lastNameError, 'Last name');
                    });
                }

                if (emailInput) {
                    emailInput.addEventListener('input', function () {
                        const normalizedValue = normalizeEmailValue(emailInput.value);
                        if (emailInput.value !== normalizedValue) {
                            emailInput.value = normalizedValue;
                        }
                        validateEmailFormat();
                        if (emailInput.value !== '' && validateEmailFormat()) {
                            scheduleEmailDuplicateCheck();
                        } else {
                            clearTimeout(emailCheckTimer);
                        }
                    });

                    emailInput.addEventListener('blur', function () {
                        emailInput.value = normalizeEmailValue(emailInput.value);
                        validateEmailFormat();
                        runEmailDuplicateCheck();
                    });
                }

                form.addEventListener('submit', function (event) {
                    const namesValid = [
                        validateNameField(firstNameInput, firstNameError, 'First name'),
                        validateNameField(lastNameInput, lastNameError, 'Last name'),
                    ].every(Boolean);

                    const emailFormatValid = validateEmailFormat();

                    if (!namesValid || !emailFormatValid) {
                        event.preventDefault();
                        const invalidField = [firstNameInput, lastNameInput, emailInput].find(function (field) {
                            return field && !field.checkValidity();
                        });
                        if (invalidField) {
                            invalidField.reportValidity();
                        }
                        return;
                    }

                    event.preventDefault();
                    runEmailDuplicateCheck().then(function (emailAvailable) {
                        if (!emailAvailable) {
                            emailInput.reportValidity();
                            return;
                        }

                        form.submit();
                    });
                });

                if (hasServerErrors) {
                    window.setTimeout(focusFirstInvalidField, 80);
                }
            });
        </script>
    @endpush
@endonce
