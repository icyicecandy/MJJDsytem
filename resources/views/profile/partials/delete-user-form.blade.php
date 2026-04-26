<section class="panel-card profile-panel">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"></path></svg></span>
            <div>
                <div class="panel-title">Delete Account</div>
                <div class="panel-subtitle">Permanently remove your portal account.</div>
            </div>
        </div>
    </div>

    <div class="panel-body profile-side-stack">
        <article class="status-card">
            <strong>Account tools</strong>
            <p>Export your current account data or flag the account for deletion review before taking permanent action.</p>
            <div class="profile-actions profile-actions-tight">
                <div class="btn-group">
                    <a href="{{ route('profile.export') }}" class="btn secondary">Export profile</a>
                    <form method="post" action="{{ route('profile.deletion-request') }}">
                        @csrf
                        <button type="submit" class="btn secondary">Request deletion review</button>
                    </form>
                </div>
            </div>
            @if (session('status') === 'deletion-requested')
                <p class="field-help">Deletion review request recorded.</p>
            @endif
        </article>

        <article class="status-card status-card-danger">
            <strong>Permanent action</strong>
            <p>This signs you out and permanently removes your account. Enter your current password to continue.</p>
            <form method="post" action="{{ route('profile.destroy') }}" class="profile-form compact-form" id="deleteAccountForm">
                @csrf
                @method('delete')
                <div class="profile-field">
                    <label for="delete_account_password">Current password <span class="field-badge required">Required</span></label>
                    <input id="delete_account_password" name="password" type="password" autocomplete="current-password" required>
                    @error('password', 'userDeletion')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="profile-actions profile-actions-tight">
                    <p class="field-help">This cannot be undone.</p>
                    <button type="submit" class="btn danger" data-submit-button data-default-label="Delete account" data-loading-label="Deleting...">Delete account</button>
                </div>
            </form>
        </article>
    </div>
</section>
