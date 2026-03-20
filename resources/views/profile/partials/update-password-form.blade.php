<section class="panel-card profile-panel profile-security-panel">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z"></path><path d="M9.5 11.5 11 13l3.5-3.5"></path></svg></span>
            <div>
                <div class="panel-title">Security</div>
                <div class="panel-subtitle">Check verification status, update your password, and review two-factor availability.</div>
            </div>
        </div>
        @if (session('status') === 'password-updated')
            <span class="badge active">Password updated</span>
        @endif
    </div>

    <div class="panel-body profile-side-stack">
        <div class="status-card-grid">
            <article class="status-card">
                <strong>Email verification</strong>
                <span class="badge {{ $user->email_verified_at ? 'active' : 'pending' }}">{{ $user->email_verified_at ? 'Verified' : 'Pending' }}</span>
                <p>{{ $user->email_verified_at ? 'Your email is verified and can receive account notices.' : 'Verify your email to keep account recovery and alerts active.' }}</p>
                @if(! $user->email_verified_at)
                    <form method="post" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn secondary">Resend verification email</button>
                    </form>
                    @if (session('status') === 'verification-link-sent')
                        <p class="field-help is-success">A fresh verification link was sent to your email address.</p>
                    @endif
                @endif
            </article>

            <article class="status-card">
                <strong>Phone verification</strong>
                @if($user->phone)
                    <span class="badge {{ $user->phone_verified_at ? 'active' : 'pending' }}">{{ $user->phone_verified_at ? 'Verified' : 'Pending' }}</span>
                    <p>{{ $user->phone_verified_at ? 'Your saved phone number is verified.' : 'Your phone number is saved, but verification is still pending.' }}</p>
                @else
                    <span class="badge">No phone number</span>
                    <p>Add a phone number in your profile form if you want SMS notifications and phone verification status.</p>
                @endif
            </article>
        </div>

        <form method="post" action="{{ route('password.update') }}" class="profile-form compact-form" id="passwordForm" data-unsaved-warning="You have an unfinished password change.">
            @csrf
            @method('put')

            <div class="profile-field">
                <label for="update_password_current_password">Current password <span class="field-badge required">Required</span></label>
                <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" required>
                @error('current_password', 'updatePassword')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="profile-field">
                <label for="update_password_password">New password <span class="field-badge required">Required</span></label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password" required data-password-input>
                <div class="field-meta-row password-meta-row">
                    <p class="field-help">Use at least 12 characters with uppercase, lowercase, number, and symbol.</p>
                    <span class="password-strength" data-password-strength>Too weak</span>
                </div>
                @error('password', 'updatePassword')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="profile-field">
                <label for="update_password_password_confirmation">Confirm new password <span class="field-badge required">Required</span></label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-password-confirm>
                <p class="field-help" data-password-match>Re-enter the new password exactly.</p>
            </div>

            <div class="profile-actions profile-actions-tight">
                <p class="field-help">Password updates take effect immediately after a successful save.</p>
                <div class="btn-group">
                    <button type="reset" class="btn secondary">Clear</button>
                    <button type="submit" class="btn" data-submit-button data-default-label="Update password" data-loading-label="Updating...">Update password</button>
                </div>
            </div>
        </form>

        <article class="status-card status-card-muted">
            <strong>Two-factor authentication</strong>
            @if($twoFactorAvailable)
                <span class="badge active">Available</span>
                <p>Two-factor controls are available in this project.</p>
            @else
                <span class="badge">Unavailable</span>
                <p>This app does not currently have a two-factor authentication package or configuration enabled, so no toggle is shown yet.</p>
            @endif
        </article>
    </div>
</section>
