(function () {
    function sanitizeUsername(value) {
        return value
            .toLowerCase()
            .replace(/[^a-z0-9._-]+/g, '-')
            .replace(/[-_.]{2,}/g, '-')
            .replace(/^[-_.]+|[-_.]+$/g, '');
    }

    function isValidPersonName(value) {
        return /^[\p{L}][\p{L}\p{M}\s'.-]*$/u.test(value);
    }

    function getPasswordStrength(value) {
        let score = 0;

        if (value.length >= 8) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        if (score <= 1) return 'weak';
        if (score <= 3) return 'medium';
        return 'strong';
    }

    function setInlineMessage(field, message) {
        const container = field.closest('.profile-field');
        if (!container) return;

        let inline = container.querySelector('.field-error-inline');
        if (!message) {
            if (inline) inline.remove();
            return;
        }

        if (!inline) {
            inline = document.createElement('p');
            inline.className = 'field-error-inline';
            container.appendChild(inline);
        }

        inline.textContent = message;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const profileForm = document.getElementById('profileForm');
        const passwordForm = document.getElementById('passwordForm');
        const trackedForms = [profileForm, passwordForm].filter(Boolean);
        const saveButton = profileForm?.querySelector('[data-save-button]');
        const avatarInput = profileForm?.querySelector('[data-avatar-input]');
        const removeAvatarInput = profileForm?.querySelector('[data-remove-avatar-input]');
        const removeAvatarButton = profileForm?.querySelector('[data-remove-avatar]');
        const avatarPreview = profileForm?.querySelector('[data-avatar-preview]');
        const avatarFileName = profileForm?.querySelector('[data-avatar-file-name]');
        const usernameInput = profileForm?.querySelector('[data-validate="username"]');
        const usernameMessage = profileForm?.querySelector('[data-username-message]');
        const saveState = profileForm?.querySelector('[data-save-state]');
        const cancelButton = profileForm?.querySelector('[data-profile-cancel]');
        const passwordInput = passwordForm?.querySelector('[data-password-input]');
        const passwordConfirm = passwordForm?.querySelector('[data-password-confirm]');
        const passwordStrength = passwordForm?.querySelector('[data-password-strength]');
        const passwordMatch = passwordForm?.querySelector('[data-password-match]');
        let usernameTimer = null;
        let usernameRequest = 0;
        let avatarRequest = 0;
        let suppressBeforeUnload = false;

        const initialState = new Map();

        function getFormState(form) {
            const state = {};
            form.querySelectorAll('input, select, textarea').forEach((field) => {
                if (!field.name || field.disabled || (field.type === 'hidden' && field.name === '_token')) {
                    return;
                }

                if (field.type === 'file') {
                    state[field.name] = field.files.length ? field.files[0].name : '';
                    return;
                }

                if (field.type === 'checkbox' || field.type === 'radio') {
                    state[`${field.name}:${field.value}`] = field.checked;
                    return;
                }

                state[field.name] = field.value;
            });

            return JSON.stringify(state);
        }

        trackedForms.forEach((form) => {
            const state = JSON.parse(getFormState(form));
            if (form === profileForm && form.dataset.savedValues) {
                Object.assign(state, JSON.parse(form.dataset.savedValues));
            }
            initialState.set(form, JSON.stringify(state));
        });

        function isFormDirty(form) {
            return initialState.get(form) !== getFormState(form);
        }

        function updateSaveButton() {
            if (!profileForm || !saveButton) return;
            saveButton.disabled = !isFormDirty(profileForm);
            if (saveState) {
                saveState.textContent = saveButton.disabled
                    ? 'No unsaved changes yet.'
                    : 'Unsaved changes ready to save.';
            }
        }

        function validateField(field) {
            if (!field) return true;

            let message = '';
            const value = field.value.trim();

            switch (field.dataset.validate) {
                case 'name':
                    if (!value) {
                        message = 'Full name is required.';
                    } else if (value.length < 2) {
                        message = 'Full name must be at least 2 characters.';
                    } else if (!isValidPersonName(value)) {
                        message = 'Full name may contain letters, spaces, apostrophes, periods, and hyphens.';
                    }
                    break;
                case 'email':
                    if (!value) {
                        message = 'Email is required.';
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        message = 'Enter a valid email address.';
                    }
                    break;
                case 'phone':
                    if (value && !/^\+?[0-9]{10,15}$/.test(value.replace(/[\s()\-]+/g, ''))) {
                        message = 'Enter a valid phone number with 10 to 15 digits.';
                    }
                    break;
                case 'username': {
                    const sanitized = sanitizeUsername(value);
                    if (!sanitized) {
                        message = 'Username is required.';
                    } else if (sanitized.length < 3) {
                        message = 'Username must be at least 3 characters.';
                    } else if (!/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/.test(sanitized)) {
                        message = 'Only lowercase letters, numbers, dots, hyphens, and underscores are allowed.';
                    }
                    if (sanitized !== value && value !== '') {
                        field.value = sanitized;
                    }
                    break;
                }
                default:
                    break;
            }

            field.setCustomValidity(message);
            field.classList.toggle('is-invalid', !!message);
            field.classList.toggle('is-valid', !message && value !== '');
            field.setAttribute('aria-invalid', message ? 'true' : 'false');
            setInlineMessage(field, message);

            return !message;
        }

        function updateAvatarPreview(file) {
            if (!avatarPreview) return;
            const request = ++avatarRequest;

            if (!file) {
                const originalImage = avatarPreview.dataset.originalImage;
                const fallback = avatarPreview.dataset.originalFallback;
                avatarPreview.innerHTML = originalImage
                    ? `<img src="${originalImage}" alt="Avatar preview" data-avatar-image>`
                    : `<span data-avatar-fallback>${fallback || ''}</span>`;
                if (avatarFileName) avatarFileName.textContent = 'No file selected.';
                if (removeAvatarButton) {
                    removeAvatarButton.disabled = !originalImage;
                }
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                if (request !== avatarRequest) return;
                avatarPreview.innerHTML = `<img src="${event.target?.result}" alt="Avatar preview" data-avatar-image>`;
            };
            reader.readAsDataURL(file);
            if (avatarFileName) avatarFileName.textContent = file.name;
            removeAvatarButton?.removeAttribute('disabled');
        }

        if (avatarPreview) {
            const image = avatarPreview.querySelector('img');
            const fallback = avatarPreview.dataset.fallback || avatarPreview.textContent.trim();
            if (image) avatarPreview.dataset.originalImage = image.getAttribute('src') || '';
            avatarPreview.dataset.originalFallback = fallback;
        }

        profileForm?.querySelectorAll('[data-validate]').forEach((field) => {
            field.addEventListener('input', function () {
                validateField(field);
                updateSaveButton();
            });
            field.addEventListener('blur', function () {
                validateField(field);
            });
        });

        usernameInput?.addEventListener('input', function () {
            const request = ++usernameRequest;
            clearTimeout(usernameTimer);
            if (!validateField(usernameInput)) {
                if (usernameMessage) {
                    usernameMessage.textContent = 'Lowercase letters, numbers, dots, hyphens, and underscores only.';
                }
                return;
            }

            if (usernameMessage) usernameMessage.textContent = 'Checking username availability...';
            clearTimeout(usernameTimer);
            usernameTimer = window.setTimeout(async function () {
                try {
                    const response = await fetch(`${usernameInput.dataset.usernameCheckUrl}?username=${encodeURIComponent(usernameInput.value)}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const data = await response.json();
                    if (request !== usernameRequest) return;
                    const serverMessage = data.message || data.errors?.username?.[0] || 'Username check unavailable.';
                    usernameMessage.textContent = serverMessage;
                    usernameInput.classList.toggle('is-invalid', !response.ok || !data.available);
                    usernameInput.classList.toggle('is-valid', response.ok && !!data.available);
                } catch (error) {
                    if (request !== usernameRequest) return;
                    usernameMessage.textContent = 'Could not verify username right now.';
                }
            }, 350);
        });

        avatarInput?.addEventListener('change', function () {
            if (removeAvatarInput) removeAvatarInput.value = '0';
            const file = avatarInput.files[0];
            const invalid = file && (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || !file.size || file.size > 2 * 1024 * 1024);
            avatarInput.setCustomValidity(invalid ? 'Choose a JPG, PNG, or WebP image up to 2 MB.' : '');
            setInlineMessage(avatarInput, avatarInput.validationMessage);
            updateAvatarPreview(invalid ? null : file);
            updateSaveButton();
        });

        removeAvatarButton?.addEventListener('click', function () {
            avatarRequest++;
            if (avatarInput) { avatarInput.value = ''; avatarInput.setCustomValidity(''); setInlineMessage(avatarInput, ''); }
            if (removeAvatarInput) removeAvatarInput.value = '1';
            if (avatarPreview) {
                avatarPreview.innerHTML = `<span data-avatar-fallback>${avatarPreview.dataset.originalFallback || ''}</span>`;
            }
            if (avatarFileName) avatarFileName.textContent = 'Avatar will be removed when you save.';
            removeAvatarButton.setAttribute('disabled', 'disabled');
            updateSaveButton();
        });

        cancelButton?.addEventListener('click', function () {
            if (!profileForm) return;
            profileForm.reset();
            usernameRequest++;
            clearTimeout(usernameTimer);
            const saved = JSON.parse(initialState.get(profileForm));
            profileForm.querySelectorAll('input, select, textarea').forEach(field => {
                if (field.name in saved && field.type !== 'file') field.value = saved[field.name];
                field.setCustomValidity('');
            });
            if (removeAvatarInput) removeAvatarInput.value = '0';
            updateAvatarPreview(null);
            profileForm.querySelectorAll('.is-invalid, .is-valid').forEach((field) => {
                field.classList.remove('is-invalid', 'is-valid');
                field.setAttribute('aria-invalid', 'false');
            });
            profileForm.querySelectorAll('.field-error-inline').forEach((node) => node.remove());
            if (usernameMessage) {
                usernameMessage.textContent = 'Lowercase letters, numbers, dots, hyphens, and underscores only.';
            }
            updateSaveButton();
        });

        function updatePasswordStrength() {
            if (!passwordInput) return;
            const strength = getPasswordStrength(passwordInput.value);
            if (passwordStrength) {
                passwordStrength.textContent = strength === 'weak' ? 'Too weak' : strength === 'medium' ? 'Looks better' : 'Strong password';
                passwordStrength.classList.remove('is-weak', 'is-medium', 'is-strong');
                passwordStrength.classList.add(`is-${strength}`);
            }
        }
        passwordInput?.addEventListener('input', updatePasswordStrength);

        function updatePasswordMatch() {
            if (!passwordInput || !passwordConfirm || !passwordMatch) return;
            if (!passwordConfirm.value) {
                passwordMatch.textContent = 'Re-enter the new password exactly.';
                passwordMatch.classList.remove('is-success');
                return;
            }

            const isMatch = passwordInput.value === passwordConfirm.value;
            passwordMatch.textContent = isMatch ? 'Passwords match.' : 'Passwords do not match.';
            passwordMatch.classList.toggle('is-success', isMatch);
        }

        passwordForm?.addEventListener('reset', () => {
            window.setTimeout(() => { updatePasswordStrength(); updatePasswordMatch(); }, 0);
        });

        passwordInput?.addEventListener('input', updatePasswordMatch);
        passwordConfirm?.addEventListener('input', updatePasswordMatch);

        trackedForms.forEach((form) => {
            form.addEventListener('input', updateSaveButton);
            form.addEventListener('change', updateSaveButton);
            form.addEventListener('submit', function () {
                suppressBeforeUnload = true;
                const button = form.querySelector('[data-submit-button], [data-save-button]');
                if (button) {
                    button.disabled = true;
                    button.textContent = button.dataset.loadingLabel || 'Saving...';
                }
            });
        });

        window.addEventListener('beforeunload', function (event) {
            if (suppressBeforeUnload) return;
            const hasDirtyForm = trackedForms.some((form) => isFormDirty(form));
            if (!hasDirtyForm) return;
            event.preventDefault();
            event.returnValue = '';
        });

        updateSaveButton();
        updatePasswordStrength();
        updatePasswordMatch();
    });
})();
