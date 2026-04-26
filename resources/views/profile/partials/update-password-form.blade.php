<section class="panel-card profile-panel profile-security-panel">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z"></path><path d="M9.5 11.5 11 13l3.5-3.5"></path></svg></span>
            <div>
                <div class="panel-title">Password</div>
                <div class="panel-subtitle">Change your password when needed.</div>
            </div>
        </div>
        @if (session('status') === 'password-updated')
            <span class="badge active">Password updated</span>
        @endif
    </div>

    <div class="panel-body profile-side-stack">
        <form method="post" action="{{ route('password.update') }}" class="profile-form compact-form" id="passwordForm" data-unsaved-warning="You have an unfinished password change.">
            @csrf
            @method('put')

            <div class="profile-field">
                <label for="update_password_current_password">Current password <span class="field-badge required">Required</span></label>
                <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" required>
                @error('current_password', 'updatePassword')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="profile-field">
                <label for="update_password_password">New password <span class="field-badge required">Required</span></label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password" required data-password-input>
                <div class="field-meta-row password-meta-row">
                    <p class="field-help">Use a strong password you do not use elsewhere.</p>
                    <span class="password-strength" data-password-strength>Too weak</span>
                </div>
                @error('password', 'updatePassword')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="profile-field">
                <label for="update_password_password_confirmation">Confirm new password <span class="field-badge required">Required</span></label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-password-confirm>
                <p class="field-help" data-password-match>Re-enter the new password exactly.</p>
            </div>

            <div class="profile-actions profile-actions-tight">
                <p class="field-help">Your new password takes effect immediately after saving.</p>
                <div class="btn-group">
                    <button type="reset" class="btn secondary">Clear</button>
                    <button type="submit" class="btn" data-submit-button data-default-label="Update password" data-loading-label="Updating...">Update password</button>
                </div>
            </div>
        </form>
    </div>
</section>
