@php
    $loginErrors = $errors->getBag('login');
    $loginModalActive = session('auth_modal') === 'login' || $loginErrors->any();
@endphp

<div class="login-modal" id="loginModal" aria-hidden="true">
    <div class="login-modal-backdrop" data-close-login-modal></div>
    <div class="login-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
        <button type="button" class="login-modal-close" aria-label="Close login" data-close-login-modal>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
            </svg>
        </button>

        <div class="login-modal-shell">
            <div class="login-modal-intro">
                <h2 id="loginModalTitle">Welcome back</h2>
                <p>Sign in to manage your account, applications, and service updates without leaving the homepage.</p>
            </div>

            <x-auth-session-status class="login-modal-status" :status="session('status')" />

            @if (session('google_auth_error'))
                <div class="alert alert-error">{{ session('google_auth_error') }}</div>
            @endif

            <div class="form-group login-modal-submit-row login-modal-social-row">
                <a href="{{ route('auth.google.redirect', ['mode' => 'login']) }}" class="btn secondary auth-social-btn">
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
                <span>or sign in with your email</span>
            </div>

            <form method="POST" action="{{ route('login') }}" class="login-modal-form login-modal-auth-form">
                @csrf
                <div class="form-group">
                    <label for="modal_email">Email Address</label>
                    <input id="modal_email" type="email" name="email" value="{{ $loginModalActive ? old('email') : '' }}" required autofocus placeholder="you&#64;example.com" />
                    @error('email', 'login')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group password-group">
                    <div class="field-head">
                        <label for="modal_password">Password</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="field-link">Forgot password?</a>
                        @endif
                    </div>
                    <input id="modal_password" type="password" name="password" required placeholder="Enter your password" />
                    <button
                        type="button"
                        class="show-password"
                        data-target="modal_password"
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
                    @error('password', 'login')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group checkbox-group login-modal-options-row">
                    <label>
                        <input type="checkbox" name="remember" {{ $loginModalActive && old('remember') ? 'checked' : '' }} /> Keep me signed in
                    </label>
                </div>

                <div class="form-group login-modal-submit-row login-modal-primary-action">
                    <button type="submit" class="btn">Sign In</button>
                </div>

                <p class="small-text login-modal-footer">Need an account? <a href="{{ url('/') }}" data-switch-modal="register">Create one here</a></p>
            </form>
        </div>
    </div>
</div>
