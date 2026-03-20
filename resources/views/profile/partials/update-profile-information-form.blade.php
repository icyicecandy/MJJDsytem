<section class="panel-card profile-panel">
    <div class="panel-header">
        <div class="panel-title-wrap">
            <span class="panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
            <div>
                <div class="panel-title">Profile Details</div>
                <div class="panel-subtitle">Required fields are marked clearly. Optional details help personalize your account and notifications.</div>
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

            <div class="profile-avatar-section">
                <div class="avatar-preview" data-avatar-preview>
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }} avatar" data-avatar-image>
                    @else
                        <span data-avatar-fallback>{{ $user->initials }}</span>
                    @endif
                </div>
                <div class="avatar-actions">
                    <div>
                        <h3>Profile photo</h3>
                        <p>Upload a JPG, PNG, or WebP image up to 2 MB. You can preview before saving.</p>
                    </div>
                    <div class="btn-group">
                        <label class="btn secondary" for="avatar">{{ $user->avatar_url ? 'Replace photo' : 'Upload photo' }}</label>
                        <button type="button" class="btn secondary" data-remove-avatar {{ $user->avatar_url ? '' : 'disabled' }}>Remove</button>
                    </div>
                    <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" data-avatar-input>
                    <input type="hidden" name="remove_avatar" value="0" data-remove-avatar-input>
                    <p class="field-help" data-avatar-file-name>No file selected.</p>
                    @error('avatar')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="profile-section-grid">
                <div class="profile-field">
                    <label for="name">Full name <span class="field-badge required">Required</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name" data-validate="name">
                    <p class="field-help">Use the name you want shown across the dashboard.</p>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field">
                    <label for="username">Username <span class="field-badge required">Required</span></label>
                    <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required maxlength="30" autocapitalize="off" spellcheck="false" data-validate="username" data-username-check-url="{{ route('profile.username.check') }}">
                    <p class="field-help" data-username-message>Lowercase letters, numbers, dots, hyphens, and underscores only.</p>
                    @error('username')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field profile-field-full">
                    <label for="bio">Bio <span class="field-badge optional">Optional</span></label>
                    <textarea id="bio" name="bio" rows="4" maxlength="280" data-bio-input>{{ old('bio', $user->bio) }}</textarea>
                    <div class="field-meta-row">
                        <p class="field-help">A short description for your account profile.</p>
                        <span class="field-counter" data-bio-counter>0 / 280</span>
                    </div>
                    @error('bio')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field">
                    <label for="email">Email address <span class="field-badge required">Required</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email" data-validate="email">
                    <p class="field-help">Changing your email will require re-verification.</p>
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field">
                    <label for="phone">Phone number <span class="field-badge optional">Optional</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" inputmode="tel" autocomplete="tel" data-validate="phone">
                    <p class="field-help">Include country code when available, for example +639123456789.</p>
                    @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field">
                    <label for="date_of_birth">Date of birth <span class="field-badge optional">Optional</span></label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}">
                    @error('date_of_birth')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field">
                    <label for="gender">Gender <span class="field-badge optional">Optional</span></label>
                    <select id="gender" name="gender">
                        <option value="">Select an option</option>
                        @foreach($genderOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('gender')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="profile-field profile-field-full">
                    <label for="address">Address <span class="field-badge optional">Optional</span></label>
                    <textarea id="address" name="address" rows="3" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                    @error('address')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="profile-subpanel">
                <div class="profile-subpanel-head">
                    <h3>Preferences</h3>
                    <p>Choose how the app feels and where date/time values should be based.</p>
                </div>
                <div class="profile-section-grid">
                    <div class="profile-field">
                        <label for="language">Language <span class="field-badge required">Required</span></label>
                        <select id="language" name="language">
                            @foreach($languageOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('language', $user->language ?? 'en') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="profile-field">
                        <label for="timezone">Time zone <span class="field-badge required">Required</span></label>
                        <select id="timezone" name="timezone">
                            @foreach($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected(old('timezone', $user->timezone ?? config('app.timezone')) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="profile-field profile-field-full">
                        <label>Theme preference <span class="field-badge required">Required</span></label>
                        <div class="choice-grid">
                            @foreach($themeOptions as $value => $label)
                                <label class="choice-card">
                                    <input type="radio" name="theme_preference" value="{{ $value }}" @checked(old('theme_preference', $user->theme_preference ?? 'system') === $value)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-subpanel">
                <div class="profile-subpanel-head">
                    <h3>Notifications and privacy</h3>
                    <p>Control where updates are sent and how visible your profile should be inside the app.</p>
                </div>
                <div class="toggle-grid">
                    <label class="toggle-row">
                        <span>
                            <strong>Email notifications</strong>
                            <small>Receive account and service updates by email.</small>
                        </span>
                        <input type="hidden" name="email_notifications" value="0">
                        <input type="checkbox" name="email_notifications" value="1" @checked(old('email_notifications', $user->email_notifications ?? true))>
                    </label>
                    <label class="toggle-row">
                        <span>
                            <strong>Push notifications</strong>
                            <small>Store your preference for browser or app push alerts.</small>
                        </span>
                        <input type="hidden" name="push_notifications" value="0">
                        <input type="checkbox" name="push_notifications" value="1" @checked(old('push_notifications', $user->push_notifications ?? false))>
                    </label>
                    <label class="toggle-row">
                        <span>
                            <strong>SMS notifications</strong>
                            <small>Allow SMS service alerts when a phone number is available.</small>
                        </span>
                        <input type="hidden" name="sms_notifications" value="0">
                        <input type="checkbox" name="sms_notifications" value="1" @checked(old('sms_notifications', $user->sms_notifications ?? false))>
                    </label>
                    <label class="toggle-row">
                        <span>
                            <strong>Data sharing</strong>
                            <small>Allow profile and product usage data to help improve service operations.</small>
                        </span>
                        <input type="hidden" name="data_sharing_enabled" value="0">
                        <input type="checkbox" name="data_sharing_enabled" value="1" @checked(old('data_sharing_enabled', $user->data_sharing_enabled ?? false))>
                    </label>
                </div>

                <div class="profile-section-grid compact-top">
                    <div class="profile-field profile-field-full">
                        <label>Profile visibility <span class="field-badge required">Required</span></label>
                        <div class="choice-grid two-up">
                            @foreach($visibilityOptions as $value => $label)
                                <label class="choice-card">
                                    <input type="radio" name="profile_visibility" value="{{ $value }}" @checked(old('profile_visibility', $user->profile_visibility ?? 'private') === $value)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-actions">
                <p class="field-help" data-save-state>Changes are saved to your account immediately after a successful update.</p>
                <div class="btn-group">
                    <button type="button" class="btn secondary" data-profile-cancel>Cancel</button>
                    <button type="submit" class="btn" data-save-button data-default-label="Save changes" data-loading-label="Saving..." disabled>Save changes</button>
                </div>
            </div>
        </form>
    </div>
</section>
