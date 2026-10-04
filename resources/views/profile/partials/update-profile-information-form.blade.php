@php
    $savedProfileValues = [
        'name' => $user->name, 'username' => $user->username ?? '', 'email' => $user->email,
        'phone' => $user->phone ?? '', 'address' => $user->address ?? '',
        'date_of_birth' => $user->date_of_birth?->toDateString() ?? '', 'gender' => $user->gender ?? '',
        'avatar' => '', 'remove_avatar' => '0',
    ];
@endphp
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
        <form id="profileForm" data-saved-values="{{ json_encode($savedProfileValues) }}" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="profile-form" data-unsaved-warning="You have unsaved profile changes.">
            @csrf
            @method('patch')

            <div class="profile-section-grid">
                <div class="profile-field profile-field-full">
                    <label for="avatar">Profile photo</label>
                    <div class="profile-avatar-editor">
                        <div class="profile-avatar-preview" data-avatar-preview data-fallback="{{ $user->initials }}">
                            @if($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }} avatar" data-avatar-image>
                            @else
                                <span data-avatar-fallback>{{ $user->initials }}</span>
                            @endif
                        </div>
                        <div class="profile-avatar-controls">
                            <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" data-avatar-input>
                            <p class="field-help">JPG, PNG, or WebP, up to 2 MB.</p>
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
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required minlength="2" maxlength="255" autocomplete="name" data-validate="name">
                    @error('name')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="username">Username <span class="field-badge required">Required</span></label>
                    <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="30" autocapitalize="off" spellcheck="false" data-validate="username" data-username-check-url="{{ route('profile.username.check') }}">
                    <p class="field-help" data-username-message>Lowercase letters, numbers, dots, hyphens, and underscores only.</p>
                    @error('username')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="email">Email address <span class="field-badge required">Required</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" data-validate="email">
                    <p class="field-help">{{ $user->email_verified_at ? 'Verified' : 'Changing your email will require re-verification.' }}</p>
                    @error('email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" inputmode="tel" autocomplete="tel" data-validate="phone">
                    <p class="field-help">Example: +639123456789</p>
                    @error('phone')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="date_of_birth">Date of birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" max="{{ now()->subDay()->toDateString() }}" value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}">
                    @error('date_of_birth')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="profile-field">
                    <label for="gender">Gender</label>
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
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" maxlength="500" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                    <p class="field-help">Keep your address current for service applications and support records.</p>
                    @error('address')
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
