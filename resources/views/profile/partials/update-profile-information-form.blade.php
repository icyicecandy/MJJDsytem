<section class="panel-card profile-panel">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
            <div>
                <div class="panel-title">Basic Information</div>
                <div class="panel-subtitle">Keep your contact details current.</div>
            </div>
        </div>
        @if (session('status') === 'profile-updated')
            <span class="badge active">Saved</span>
        @endif
    </div>

    <div class="panel-body">
        <form id="profileForm" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="profile-form" data-unsaved-warning="You have unsaved profile changes.">
            @csrf
            @method('patch')

            <div class="profile-section-grid">
                <div class="profile-field profile-field-full">
                    <label>Profile photo <span class="field-badge optional">Optional</span></label>
                    <div class="profile-avatar-editor">
                        <div class="profile-avatar-preview" data-avatar-preview>
                            @if($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }} avatar" data-avatar-image>
                            @else
                                <span data-avatar-fallback>{{ $user->initials }}</span>
                            @endif
                        </div>
                        <div class="profile-avatar-controls">
                            <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" data-avatar-input>
                            <input type="hidden" name="remove_avatar" value="{{ old('remove_avatar', '0') }}" data-remove-avatar-input>
                            <p class="field-help" data-avatar-file-name>{{ $user->avatar_url ? 'Current avatar in use.' : 'No file selected.' }}</p>
                            <div class="btn-group">
                                <button type="button" class="btn secondary" data-remove-avatar {{ $user->avatar_url ? '' : 'disabled' }}>Remove photo</button>
                            </div>
                            @error('avatar')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="profile-field">
                    <label for="name">Full name <span class="field-badge required">Required</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" pattern="^[A-Za-z][A-Za-z\s'\-]*$" autocomplete="name" data-validate="name">
                    @error('name')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="username">Username <span class="field-badge required">Required</span></label>
                    <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required maxlength="30" autocapitalize="off" spellcheck="false" data-validate="username" data-username-check-url="{{ route('profile.username.check') }}">
                    <p class="field-help" data-username-message>Lowercase letters, numbers, dots, hyphens, and underscores only.</p>
                    @error('username')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="email">Email address <span class="field-badge required">Required</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email" data-validate="email">
                    <p class="field-help">{{ $user->email_verified_at ? 'Verified' : 'Changing your email will require re-verification.' }}</p>
                    @error('email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="phone">Phone number <span class="field-badge optional">Optional</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" inputmode="tel" autocomplete="tel" data-validate="phone">
                    <p class="field-help">Example: +639123456789</p>
                    @error('phone')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="date_of_birth">Date of birth <span class="field-badge optional">Optional</span></label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}">
                    @error('date_of_birth')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="gender">Gender <span class="field-badge optional">Optional</span></label>
                    <select id="gender" name="gender">
                        <option value="">Prefer not to say</option>
                        <option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option>
                        <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                        <option value="non_binary" @selected(old('gender', $user->gender) === 'non_binary')>Non-binary</option>
                        <option value="prefer_not_to_say" @selected(old('gender', $user->gender) === 'prefer_not_to_say')>Prefer not to say</option>
                    </select>
                    @error('gender')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field profile-field-full">
                    <label for="bio">Bio <span class="field-badge optional">Optional</span></label>
                    <textarea id="bio" name="bio" rows="3" maxlength="280" data-bio-input>{{ old('bio', $user->bio) }}</textarea>
                    <div class="field-meta-row">
                        <p class="field-help">Short account note for support context.</p>
                        <span class="field-help" data-bio-counter>0 / 280</span>
                    </div>
                    @error('bio')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field profile-field-full">
                    <label for="address">Address <span class="field-badge optional">Optional</span></label>
                    <textarea id="address" name="address" rows="3" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                    <p class="field-help">Keep your address current for service applications and support records.</p>
                    @error('address')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="language">Language <span class="field-badge required">Required</span></label>
                    <select id="language" name="language" required>
                        <option value="en" @selected(old('language', $user->language ?? 'en') === 'en')>English</option>
                        <option value="fil" @selected(old('language', $user->language ?? 'en') === 'fil')>Filipino</option>
                    </select>
                    @error('language')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="timezone">Timezone <span class="field-badge required">Required</span></label>
                    <select id="timezone" name="timezone" required>
                        <option value="UTC" @selected(old('timezone', $user->timezone ?? config('app.timezone', 'UTC')) === 'UTC')>UTC</option>
                        <option value="Asia/Manila" @selected(old('timezone', $user->timezone ?? config('app.timezone', 'UTC')) === 'Asia/Manila')>Asia/Manila</option>
                        <option value="Asia/Singapore" @selected(old('timezone', $user->timezone ?? config('app.timezone', 'UTC')) === 'Asia/Singapore')>Asia/Singapore</option>
                    </select>
                    @error('timezone')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="theme_preference">Theme preference <span class="field-badge required">Required</span></label>
                    <select id="theme_preference" name="theme_preference" required>
                        <option value="system" @selected(old('theme_preference', $user->theme_preference ?? 'system') === 'system')>System</option>
                        <option value="light" @selected(old('theme_preference', $user->theme_preference ?? 'system') === 'light')>Light</option>
                        <option value="dark" @selected(old('theme_preference', $user->theme_preference ?? 'system') === 'dark')>Dark</option>
                    </select>
                    @error('theme_preference')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="profile_visibility">Profile visibility <span class="field-badge required">Required</span></label>
                    <select id="profile_visibility" name="profile_visibility" required>
                        <option value="private" @selected(old('profile_visibility', $user->profile_visibility ?? 'private') === 'private')>Private</option>
                        <option value="public" @selected(old('profile_visibility', $user->profile_visibility ?? 'private') === 'public')>Public</option>
                    </select>
                    @error('profile_visibility')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field profile-field-full">
                    <label>Notifications and privacy</label>
                    <div class="profile-checkbox-stack">
                        <input type="hidden" name="email_notifications" value="0">
                        <label><input type="checkbox" name="email_notifications" value="1" {{ old('email_notifications', $user->email_notifications ?? true) ? 'checked' : '' }}> Email notifications</label>

                        <input type="hidden" name="push_notifications" value="0">
                        <label><input type="checkbox" name="push_notifications" value="1" {{ old('push_notifications', $user->push_notifications ?? false) ? 'checked' : '' }}> Push notifications</label>

                        <input type="hidden" name="sms_notifications" value="0">
                        <label><input type="checkbox" name="sms_notifications" value="1" {{ old('sms_notifications', $user->sms_notifications ?? false) ? 'checked' : '' }}> SMS notifications</label>

                        <input type="hidden" name="data_sharing_enabled" value="0">
                        <label><input type="checkbox" name="data_sharing_enabled" value="1" {{ old('data_sharing_enabled', $user->data_sharing_enabled ?? false) ? 'checked' : '' }}> Allow data sharing for support improvement</label>
                    </div>
                    @error('email_notifications')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    @error('push_notifications')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    @error('sms_notifications')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    @error('data_sharing_enabled')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="profile-actions">
                <p class="field-help" data-save-state>Save when you finish editing your details.</p>
                <div class="btn-group">
                    <button type="button" class="btn secondary" data-profile-cancel>Cancel</button>
                    <button type="submit" class="btn" data-save-button data-default-label="Save changes" data-loading-label="Saving..." disabled>Save changes</button>
                </div>
            </div>
        </form>
    </div>
</section>
