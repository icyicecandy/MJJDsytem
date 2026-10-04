<section class="panel-card profile-panel staff-security-settings">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>
            <div>
                <div class="panel-title">Staff account security</div>
                <div class="panel-subtitle">Manage your authenticator, recovery codes, and trusted browsers.</div>
            </div>
        </div>
        <span class="badge active">Two-factor authentication enabled</span>
    </div>

    <div class="panel-body staff-security-settings-grid">
        <div class="staff-security-summary">
            <article class="status-card">
                <strong>Authenticator app</strong>
                <p>Required for {{ $user->isAdmin() ? 'administrator' : 'technician' }} access. It was enabled {{ $user->two_factor_confirmed_at?->diffForHumans() }}.</p>
                <span class="staff-security-count">{{ count($user->two_factor_recovery_codes ?? []) }} recovery codes remaining</span>
            </article>

            <details class="staff-security-disclosure" {{ $errors->recoveryCodes->any() ? 'open' : '' }}>
                <summary>Generate new recovery codes</summary>
                <form method="POST" action="{{ route('staff.two-factor.recovery-codes.regenerate') }}" class="profile-form compact-form">
                    @csrf
                    <p class="field-help">This immediately invalidates every recovery code generated previously.</p>
                    <div class="profile-field">
                        <label for="recovery_current_password">Current password</label>
                        <input id="recovery_current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password', 'recoveryCodes')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="profile-field">
                        <label for="recovery_security_code">Authenticator or recovery code</label>
                        <input id="recovery_security_code" name="code" type="text" autocomplete="one-time-code" maxlength="32" required>
                        @error('code', 'recoveryCodes')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn">Replace recovery codes</button>
                </form>
            </details>

            <details class="staff-security-disclosure" {{ $errors->replaceAuthenticator->any() ? 'open' : '' }}>
                <summary>Replace authenticator app</summary>
                <form method="POST" action="{{ route('staff.two-factor.replace') }}" class="profile-form compact-form">
                    @csrf
                    <p class="field-help">Your current authenticator, recovery codes, and trusted browsers will be revoked before the new QR code is shown.</p>
                    <div class="profile-field">
                        <label for="replace_current_password">Current password</label>
                        <input id="replace_current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password', 'replaceAuthenticator')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="profile-field">
                        <label for="replace_security_code">Authenticator or recovery code</label>
                        <input id="replace_security_code" name="code" type="text" autocomplete="one-time-code" maxlength="32" required>
                        @error('code', 'replaceAuthenticator')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn secondary">Start secure replacement</button>
                </form>
            </details>
        </div>

        <div class="staff-trusted-devices">
            <div class="profile-subpanel-head">
                <h3>Trusted browsers</h3>
                <p>These browsers may skip the authenticator code until their 30-day trust expires. Your password is still required.</p>
            </div>

            @forelse($trustedDevices as $device)
                <article class="trusted-device-card">
                    <div>
                        <div class="trusted-device-title">
                            <strong>{{ $device->name }}</strong>
                            @if($currentTrustedDeviceId === $device->id)<span class="badge active">This browser</span>@endif
                        </div>
                        <p>Last used {{ $device->last_used_at?->diffForHumans() ?? 'not yet' }} · Expires {{ $device->expires_at->format('M d, Y') }}</p>
                        <small>Last recorded IP: {{ $device->ip_address ?: 'Unavailable' }}</small>
                    </div>
                    <form method="POST" action="{{ route('staff.trusted-devices.revoke', $device) }}" class="trusted-device-revoke-form">
                        @csrf
                        @method('DELETE')
                        <label class="sr-only" for="device_password_{{ $device->id }}">Current password</label>
                        <input id="device_password_{{ $device->id }}" name="current_password" type="password" autocomplete="current-password" placeholder="Current password" required>
                        <button type="submit" class="btn secondary">Revoke</button>
                    </form>
                </article>
            @empty
                <div class="status-card status-card-muted">
                    <strong>No trusted browsers</strong>
                    <p>You will enter an authenticator code after your password on the next sign-in.</p>
                </div>
            @endforelse

            @if($trustedDevices->isNotEmpty())
                <form method="POST" action="{{ route('staff.trusted-devices.revoke-all') }}" class="trusted-devices-revoke-all">
                    @csrf
                    @method('DELETE')
                    <label for="all_devices_current_password">Current password</label>
                    <div>
                        <input id="all_devices_current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Current password" required>
                        <button type="submit" class="btn secondary">Revoke all</button>
                    </div>
                    @error('current_password', 'trustedDevice')<p class="field-error">{{ $message }}</p>@enderror
                </form>
            @endif
        </div>
    </div>
</section>
