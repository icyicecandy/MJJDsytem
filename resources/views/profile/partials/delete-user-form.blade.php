<section class="panel-card profile-panel">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"></path></svg></span>
            <div>
                <div class="panel-title">Privacy Actions</div>
                <div class="panel-subtitle">Export your account data, request deletion follow-up, or permanently remove your account.</div>
            </div>
        </div>
        @if (session('status') === 'deletion-requested')
            <span class="badge pending">Request logged</span>
        @endif
    </div>

    <div class="panel-body profile-side-stack">
        <article class="status-card">
            <strong>Download your account data</strong>
            <p>Export your profile, tickets, installation records, and subscription data as a JSON file.</p>
            <a href="{{ route('profile.export') }}" class="btn secondary">Download export</a>
        </article>

        <article class="status-card">
            <strong>Request account deletion</strong>
            <p>Need support before removing your account? Send a deletion request first and keep access while the team reviews it.</p>
            @if($user->deletion_requested_at)
                <p class="field-help is-success">Request recorded on {{ $user->deletion_requested_at->format('M d, Y h:i A') }}.</p>
            @endif
            <form method="post" action="{{ route('profile.deletion.request') }}">
                @csrf
                <button type="submit" class="btn secondary">Request deletion review</button>
            </form>
        </article>

        <article class="status-card status-card-danger">
            <strong>Delete account permanently</strong>
            <p>This action signs you out and permanently removes your account. Enter your current password to continue.</p>
            <form method="post" action="{{ route('profile.destroy') }}" class="profile-form compact-form" id="deleteAccountForm">
                @csrf
                @method('delete')
                <div class="profile-field">
                    <label for="delete_account_password">Current password <span class="field-badge required">Required</span></label>
                    <input id="delete_account_password" name="password" type="password" autocomplete="current-password" required>
                    @error('password', 'userDeletion')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="profile-actions profile-actions-tight">
                    <p class="field-help">Make sure you have downloaded anything you need before you continue.</p>
                    <button type="submit" class="btn danger" data-submit-button data-default-label="Delete account" data-loading-label="Deleting...">Delete account</button>
                </div>
            </form>
        </article>
    </div>
</section>
