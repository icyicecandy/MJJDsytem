(function () {
    function initConsentSections() {
        const sections = Array.from(document.querySelectorAll('[data-consent-section]'));
        if (!sections.length) {
            return;
        }

        let activeModal = null;
        let lastTrigger = null;

        function getFocusable(dialog) {
            return Array.from(dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
                .filter((element) => !element.hasAttribute('disabled') && !element.getAttribute('aria-hidden'));
        }

        function getLockCheckbox(lockId) {
            return document.querySelector(`[data-consent-checkbox][data-consent-lock-id="${lockId}"]`);
        }

        function setUnlocked(lockId) {
            const checkbox = getLockCheckbox(lockId);
            if (!checkbox) {
                return;
            }

            checkbox.disabled = false;
            checkbox.dataset.consentUnlocked = 'true';
        }

        function closeModal(modal) {
            if (!modal) {
                return;
            }

            modal.hidden = true;
            modal.classList.remove('is-open');
            document.body.classList.remove('consent-modal-open');
            document.removeEventListener('keydown', handleModalKeydown);
            activeModal = null;

            if (lastTrigger) {
                lastTrigger.focus();
                lastTrigger = null;
            }
        }

        function trapFocus(dialog, event) {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = getFocusable(dialog);
            if (!focusable.length) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const current = document.activeElement;

            if (event.shiftKey && current === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && current === last) {
                event.preventDefault();
                first.focus();
            }
        }

        function handleModalKeydown(event) {
            if (!activeModal) {
                return;
            }

            const dialog = activeModal.querySelector('.consent-modal-dialog');
            if (!dialog) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(activeModal);
                return;
            }

            trapFocus(dialog, event);
        }

        function syncScrollUnlock(modal) {
            const body = modal.querySelector('[data-consent-scroll-body]');
            const lockId = modal.dataset.consentLockId;
            const checkbox = getLockCheckbox(lockId);
            if (!body || !lockId || !checkbox) {
                return;
            }

            const alreadyUnlocked = checkbox.dataset.consentUnlocked === 'true' || !checkbox.disabled;
            if (alreadyUnlocked) {
                setUnlocked(lockId);
                return;
            }

            const reachedEnd = body.scrollTop + body.clientHeight >= body.scrollHeight - 8;
            if (reachedEnd) {
                setUnlocked(lockId);
            }
        }

        function openModal(modal, trigger) {
            if (!modal) {
                return;
            }

            if (activeModal && activeModal !== modal) {
                closeModal(activeModal);
            }

            activeModal = modal;
            lastTrigger = trigger || null;
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.classList.add('consent-modal-open');
            document.addEventListener('keydown', handleModalKeydown);
            syncScrollUnlock(modal);

            const focusable = getFocusable(modal.querySelector('.consent-modal-dialog'));
            const target = focusable[0] || modal.querySelector('.consent-modal-dialog');
            if (target) {
                target.focus();
            }
        }

        document.querySelectorAll('[data-consent-modal]').forEach((modal) => {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });

        document.querySelectorAll('[data-consent-open]').forEach((trigger) => {
            trigger.addEventListener('click', function () {
                const modal = document.getElementById(this.dataset.consentOpen);
                openModal(modal, this);
            });
        });

        document.querySelectorAll('[data-consent-modal]').forEach((modal) => {
            const body = modal.querySelector('[data-consent-scroll-body]');
            if (body) {
                body.addEventListener('scroll', function () {
                    syncScrollUnlock(modal);
                });
                window.requestAnimationFrame(() => syncScrollUnlock(modal));
            }

            modal.querySelectorAll('[data-consent-close]').forEach((closer) => {
                closer.addEventListener('click', function () {
                    closeModal(modal);
                });
            });
        });

        sections.forEach((section) => {
            const formId = section.dataset.consentForm;
            const form = formId ? document.getElementById(formId) : section.closest('form');
            const panel = section.querySelector('[data-consent-panel]');
            const error = section.querySelector('[data-consent-error]');
            const checkboxes = Array.from(section.querySelectorAll('[data-consent-checkbox]'));
            const message = section.dataset.consentMessage || '';

            if (!form || !panel || !error || !checkboxes.length) {
                return;
            }

            function hasAllChecked() {
                return checkboxes.every((checkbox) => checkbox.checked);
            }

            function clearState() {
                panel.classList.remove('has-error');
                error.textContent = '';
                error.classList.remove('is-visible');
                checkboxes.forEach((checkbox) => {
                    checkbox.setAttribute('aria-invalid', 'false');
                    const option = checkbox.closest('[data-consent-option]');
                    if (option) {
                        option.classList.remove('is-invalid');
                    }
                });
            }

            function showError() {
                panel.classList.add('has-error');
                error.textContent = message;
                error.classList.add('is-visible');
                checkboxes.forEach((checkbox) => {
                    const invalid = !checkbox.checked;
                    checkbox.setAttribute('aria-invalid', invalid ? 'true' : 'false');
                    const option = checkbox.closest('[data-consent-option]');
                    if (option) {
                        option.classList.toggle('is-invalid', invalid);
                    }
                });
                const firstMissing = checkboxes.find((checkbox) => !checkbox.checked);
                if (firstMissing) {
                    firstMissing.focus();
                } else {
                    error.focus();
                }
            }

            checkboxes.forEach((checkbox) => {
                if (checkbox.checked || checkbox.dataset.consentUnlocked === 'true') {
                    setUnlocked(checkbox.dataset.consentLockId);
                }
            });

            if (hasAllChecked()) {
                clearState();
            }

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('click', function (event) {
                    if (checkbox.disabled) {
                        event.preventDefault();
                    }
                });

                checkbox.addEventListener('change', function () {
                    if (hasAllChecked()) {
                        clearState();
                    } else if (error.classList.contains('is-visible')) {
                        showError();
                    }
                });
            });

            form.addEventListener('submit', function (event) {
                if (!hasAllChecked()) {
                    event.preventDefault();
                    showError();
                    return;
                }

                clearState();

                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                }
            });
        });
    }

    window.initConsentSections = initConsentSections;
    window.addEventListener('DOMContentLoaded', initConsentSections);
})();

