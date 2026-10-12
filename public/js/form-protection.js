(function () {
    'use strict';

    var activeConfirmation = null;

    function confirmationModal() {
        var existing = document.querySelector('[data-confirmation-modal]');
        if (existing) return existing;

        var modal = document.createElement('div');
        modal.className = 'ui-confirm-modal';
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('data-confirmation-modal', '');
        modal.innerHTML = [
            '<div class="ui-confirm-modal__backdrop" data-confirm-dismiss></div>',
            '<section class="ui-confirm-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="uiConfirmTitle" aria-describedby="uiConfirmMessage" tabindex="-1">',
            '  <button type="button" class="ui-confirm-modal__close" data-confirm-dismiss aria-label="Close confirmation">&times;</button>',
            '  <div class="ui-confirm-modal__icon" aria-hidden="true">!</div>',
            '  <div class="ui-confirm-modal__copy">',
            '    <span class="ui-confirm-modal__eyebrow">Confirmation required</span>',
            '    <h2 id="uiConfirmTitle">Confirm action</h2>',
            '    <p id="uiConfirmMessage"></p>',
            '  </div>',
            '  <div class="ui-confirm-modal__actions">',
            '    <button type="button" class="btn secondary" data-confirm-dismiss>Cancel</button>',
            '    <button type="button" class="btn" data-confirm-accept>Confirm</button>',
            '  </div>',
            '</section>'
        ].join('');
        document.body.appendChild(modal);

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-confirm-accept]')) finishConfirmation(true);
            else if (event.target.closest('[data-confirm-dismiss]')) finishConfirmation(false);
        });

        return modal;
    }

    function finishConfirmation(accepted) {
        if (!activeConfirmation) return;
        var state = activeConfirmation;
        activeConfirmation = null;
        state.modal.hidden = true;
        state.modal.setAttribute('aria-hidden', 'true');
        state.modal.classList.remove('is-danger');
        document.body.classList.remove('ui-confirm-open');
        state.resolve(accepted);
        if (!accepted && state.previousFocus && state.previousFocus.isConnected) state.previousFocus.focus();
    }

    function showConfirmation(message, trigger) {
        if (activeConfirmation) return Promise.resolve(false);
        var modal = confirmationModal();
        var danger = trigger && (trigger.classList.contains('danger') || trigger.value === 'rejected');
        var title = trigger && trigger.getAttribute('data-confirm-title');
        var confirmLabel = trigger && trigger.getAttribute('data-confirm-label');

        modal.querySelector('#uiConfirmTitle').textContent = title || (danger ? 'Confirm important action' : 'Confirm action');
        modal.querySelector('#uiConfirmMessage').textContent = message;
        modal.querySelector('[data-confirm-accept]').textContent = confirmLabel || (danger ? 'Yes, continue' : 'Confirm');
        modal.classList.toggle('is-danger', Boolean(danger));
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ui-confirm-open');

        return new Promise(function (resolve) {
            activeConfirmation = { modal: modal, resolve: resolve, previousFocus: trigger || document.activeElement };
            window.requestAnimationFrame(function () { modal.querySelector('[data-confirm-dismiss].btn').focus(); });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!activeConfirmation) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            finishConfirmation(false);
            return;
        }
        if (event.key !== 'Tab') return;
        var focusable = Array.from(activeConfirmation.modal.querySelectorAll('button:not([disabled])'));
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.addEventListener('click', function (event) {
        var dismiss = event.target.closest('[data-dismiss-alert]');
        if (!dismiss) return;
        var alert = dismiss.closest('.alert');
        if (alert) alert.remove();
    });

    var protectedForms = Array.from(document.querySelectorAll('form')).filter(function (form) {
        return form.matches('[data-protect-form], [data-unsaved-warning]') || form.querySelector('[data-confirm]');
    });

    protectedForms.forEach(function (form) {
        var dirty = false;
        var submitting = false;
        var confirmationPending = false;

        form.addEventListener('input', function () { dirty = true; });
        form.addEventListener('change', function () { dirty = true; });

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || form.querySelector('[type="submit"]');
            var confirmation = submitter && submitter.getAttribute('data-confirm');

            if (confirmation && form.dataset.confirmedOnce !== 'true') {
                event.preventDefault();
                if (confirmationPending) return;
                confirmationPending = true;
                showConfirmation(confirmation, submitter).then(function (accepted) {
                    confirmationPending = false;
                    if (!accepted) return;
                    form.dataset.confirmedOnce = 'true';
                    if (submitter && submitter.isConnected) form.requestSubmit(submitter);
                    else form.requestSubmit();
                });
                return;
            }
            delete form.dataset.confirmedOnce;

            if (submitting) {
                event.preventDefault();
                return;
            }

            submitting = true;
            dirty = false;
            form.setAttribute('aria-busy', 'true');

            if (submitter && submitter.name) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value;
                hidden.setAttribute('data-submitter-value', '');
                form.appendChild(hidden);
            }

            Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')).forEach(function (button) {
                button.dataset.originalLabel = button.tagName === 'INPUT' ? button.value : button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });

            if (submitter) {
                var savingLabel = submitter.getAttribute('data-submitting-text') || 'Saving...';
                if (submitter.tagName === 'INPUT') submitter.value = savingLabel;
                else submitter.textContent = savingLabel;
            }
        });

        if (form.hasAttribute('data-warn-unsaved') || form.hasAttribute('data-unsaved-warning')) {
            window.addEventListener('beforeunload', function (event) {
                if (!dirty || submitting) return;
                event.preventDefault();
                event.returnValue = '';
            });
        }

        window.addEventListener('pageshow', function () {
            submitting = false;
            confirmationPending = false;
            form.removeAttribute('aria-busy');
            delete form.dataset.confirmedOnce;
            form.querySelectorAll('[data-submitter-value]').forEach(function (input) { input.remove(); });
            Array.from(form.querySelectorAll('[data-original-label]')).forEach(function (button) {
                button.disabled = false;
                button.removeAttribute('aria-disabled');
                if (button.tagName === 'INPUT') button.value = button.dataset.originalLabel;
                else button.innerHTML = button.dataset.originalLabel;
                delete button.dataset.originalLabel;
            });
        });
    });

    window.MJJDConfirm = showConfirmation;
}());
