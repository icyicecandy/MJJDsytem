(function () {
    'use strict';

    if (document.body?.classList.contains('is-landing')) return;

    const focusableSelector = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function visible(element) {
        return Boolean(element && !element.hidden && element.getAttribute('aria-hidden') !== 'true' &&
            (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
    }

    function initSubmitFeedback() {
        const restore = (form) => {
            if (!form?.dataset.uiSubmitting) return;
            delete form.dataset.uiSubmitting;
            form.querySelectorAll('[data-ui-disabled-by-submit]').forEach(button => {
                button.disabled = false;
                button.removeAttribute('data-ui-disabled-by-submit');
                const original = button.getAttribute('data-ui-original-html');
                if (original !== null) {
                    button.innerHTML = original;
                    button.removeAttribute('data-ui-original-html');
                }
            });
        };

        document.querySelectorAll('form').forEach(form => {
            const method = String(form.getAttribute('method') || 'get').toLowerCase();
            if (method === 'get' || form.matches('[data-no-submit-lock], [data-notif-mark-all]') || form.target) return;

            form.addEventListener('submit', event => {
                window.setTimeout(() => {
                    if (event.defaultPrevented || form.dataset.uiSubmitting === 'true') return;
                    form.dataset.uiSubmitting = 'true';
                    const submitter = event.submitter || form.querySelector('[type="submit"]');
                    form.querySelectorAll('[type="submit"]').forEach(button => {
                        button.setAttribute('data-ui-disabled-by-submit', '');
                        button.disabled = true;
                    });
                    if (submitter) {
                        submitter.setAttribute('data-ui-original-html', submitter.innerHTML);
                        const label = submitter.dataset.loadingLabel || 'Working...';
                        submitter.innerHTML = '<span class="ui-button-spinner" aria-hidden="true"></span><span>' + label + '</span>';
                    }
                }, 0);
            });
        });

        window.addEventListener('pageshow', () => {
            document.querySelectorAll('form[data-ui-submitting="true"]').forEach(restore);
        });
    }

    function formSignature(form) {
        const data = new FormData(form);
        const pairs = [];
        data.forEach((value, key) => {
            pairs.push([key, value instanceof File ? value.name + ':' + value.size : String(value)]);
        });
        return JSON.stringify(pairs);
    }

    function initUnsavedWarnings() {
        document.querySelectorAll('form[data-unsaved-warning]').forEach(form => {
            let baseline = formSignature(form);
            let dirty = false;
            const update = () => { dirty = formSignature(form) !== baseline; };
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            form.addEventListener('submit', () => { dirty = false; });
            form.addEventListener('ui:mark-saved', () => {
                baseline = formSignature(form);
                dirty = false;
            });
            window.addEventListener('beforeunload', event => {
                if (!dirty || form.dataset.uiSubmitting === 'true') return;
                event.preventDefault();
                event.returnValue = '';
            });
        });
    }

    let activeDialog = null;
    let dialogTrigger = null;

    function dialogContainer(dialog) {
        return dialog.closest([
            '.admin-ticket-modal', '.customer-ticket-modal', '.technician-account-modal',
            '.photo-gallery-modal', '.admin-app-map-modal', '.notifications-overlay',
            '.chat-ui__modal', '.auth-modal'
        ].join(',')) || dialog;
    }

    function findOpenDialog() {
        return Array.from(document.querySelectorAll('[role="dialog"][aria-modal="true"]')).reverse().find(dialog => {
            const container = dialogContainer(dialog);
            return visible(dialog) && container.getAttribute('aria-hidden') !== 'true';
        }) || null;
    }

    function syncDialog() {
        const next = findOpenDialog();
        if (next === activeDialog) return;
        if (activeDialog && !next && dialogTrigger?.isConnected) {
            dialogTrigger.focus({ preventScroll: true });
        }
        activeDialog = next;
        document.body.classList.toggle('ui-dialog-open', Boolean(activeDialog));
        if (activeDialog) {
            activeDialog.setAttribute('tabindex', activeDialog.getAttribute('tabindex') || '-1');
            window.setTimeout(() => {
                if (!activeDialog) return;
                const target = Array.from(activeDialog.querySelectorAll(focusableSelector)).find(visible) || activeDialog;
                if (!activeDialog.contains(document.activeElement)) target.focus({ preventScroll: true });
            }, 40);
        }
    }

    function initDialogs() {
        document.addEventListener('click', event => {
            const trigger = event.target.closest('button, a');
            if (trigger && !trigger.closest('[role="dialog"]')) dialogTrigger = trigger;
            window.setTimeout(syncDialog, 0);
        }, true);

        document.addEventListener('keydown', event => {
            if (!activeDialog) {
                syncDialog();
                if (!activeDialog) return;
            }
            if (event.key === 'Escape') {
                const container = dialogContainer(activeDialog);
                const close = container.querySelector([
                    '[data-close-modal]', '[data-close-notifications-overlay]',
                    '.admin-ticket-modal__close', '.customer-ticket-modal__close',
                    '.technician-account-modal__close', '.photo-gallery-modal__close',
                    '.admin-app-map-modal__close', '.chat-ui__modal-close',
                    'button[aria-label^="Close"]'
                ].join(','));
                if (close) {
                    event.preventDefault();
                    close.click();
                    window.setTimeout(syncDialog, 0);
                }
                return;
            }
            if (event.key !== 'Tab') return;
            const items = Array.from(activeDialog.querySelectorAll(focusableSelector)).filter(visible);
            if (!items.length) {
                event.preventDefault();
                activeDialog.focus();
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        const observer = new MutationObserver(() => window.requestAnimationFrame(syncDialog));
        document.querySelectorAll('[role="dialog"][aria-modal="true"]').forEach(dialog => {
            observer.observe(dialogContainer(dialog), { attributes: true, attributeFilter: ['class', 'aria-hidden', 'hidden'] });
        });
        syncDialog();
    }

    function createToastRegion() {
        let region = document.querySelector('.ui-toast-region');
        if (region) return region;
        region = document.createElement('div');
        region.className = 'ui-toast-region';
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-atomic', 'false');
        document.body.appendChild(region);
        return region;
    }

    function toast(message, options) {
        const settings = Object.assign({ type: 'info', duration: 5000 }, options || {});
        const item = document.createElement('div');
        item.className = 'ui-toast ui-toast--' + settings.type;
        item.setAttribute('role', settings.type === 'error' ? 'alert' : 'status');

        const copy = document.createElement('div');
        copy.className = 'ui-toast__message';
        copy.textContent = message;
        item.appendChild(copy);

        if (settings.actionLabel && typeof settings.onAction === 'function') {
            const action = document.createElement('button');
            action.type = 'button';
            action.className = 'btn-link';
            action.textContent = settings.actionLabel;
            action.addEventListener('click', () => {
                settings.onAction();
                item.remove();
            });
            item.appendChild(action);
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'ui-toast__close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '�';
        close.addEventListener('click', () => item.remove());
        item.appendChild(close);
        createToastRegion().appendChild(item);

        if (settings.duration > 0) window.setTimeout(() => item.remove(), settings.duration);
        return item;
    }

    window.MJJDUI = Object.assign(window.MJJDUI || {}, { toast });

    document.addEventListener('mjjd:request-error', event => {
        toast(event.detail?.message || 'The request could not be completed. Please try again.', {
            type: 'error',
            actionLabel: typeof event.detail?.retry === 'function' ? 'Retry' : null,
            onAction: event.detail?.retry
        });
    });

    function initImageZoom() {
        const preview = document.getElementById('idPhotoPreview');
        if (!preview) return;
        preview.tabIndex = 0;
        preview.setAttribute('role', 'button');
        preview.setAttribute('aria-label', 'Open or close full-screen photo preview');
        const toggle = () => {
            const zoomed = preview.classList.toggle('is-zoomed');
            document.body.classList.toggle('ui-dialog-open', zoomed);
        };
        preview.addEventListener('click', toggle);
        preview.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggle();
            } else if (event.key === 'Escape' && preview.classList.contains('is-zoomed')) {
                event.preventDefault();
                toggle();
            }
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        initSubmitFeedback();
        initUnsavedWarnings();
        initDialogs();
        initImageZoom();

        document.querySelectorAll('.flash-stack .alert').forEach(alert => {
            alert.setAttribute('role', alert.classList.contains('alert-error') ? 'alert' : 'status');
        });
    });
}());
