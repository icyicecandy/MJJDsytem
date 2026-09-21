@if($user->isAdmin())
    <section class="panel-card profile-panel">
        <div class="panel-header">
            <div class="panel-title-wrap">
                <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v5"></path><path d="M14 11v5"></path></svg></span>
                <div>
                    <div class="panel-title">Delete Account</div>
                    <div class="panel-subtitle">Permanent account action.</div>
                </div>
            </div>
        </div>

        <div class="panel-body profile-side-stack">
            <article class="status-card status-card-danger">
                <strong>Permanent action</strong>
                <p>This signs you out and permanently removes your account. Enter your current password to continue.</p>
                <form method="post" action="{{ route('profile.destroy') }}" class="profile-form compact-form" id="deleteAccountForm">
                    @csrf
                    @method('delete')
                    <div class="profile-field">
                        <label for="delete_account_password">Current password <span class="field-badge required">Required</span></label>
                        <div class="profile-password-control">
                            <input id="delete_account_password" name="password" type="password" autocomplete="current-password" required>
                            <button type="button" class="show-password profile-password-toggle" data-target="delete_account_password" aria-label="Show password" title="Show password">Show</button>
                        </div>
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
@endif
