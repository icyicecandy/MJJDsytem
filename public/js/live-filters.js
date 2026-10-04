(() => {
    'use strict';

    const forms = Array.from(document.querySelectorAll('form[data-live-filter]'));
    if (!forms.length) {
        return;
    }

    const states = new WeakMap();

    const prepareResponsiveCards = scope => {
        scope.querySelectorAll('table[data-mobile-cards]').forEach(table => {
            const labels = Array.from(table.querySelectorAll('thead th')).map(header => header.textContent.trim());

            table.querySelectorAll('tbody tr').forEach(row => {
                const cells = Array.from(row.children).filter(cell => cell.tagName === 'TD');
                if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
                    row.classList.add('mobile-card-empty');
                    return;
                }

                cells.forEach((cell, index) => {
                    if (!cell.hasAttribute('data-mobile-label')) {
                        cell.setAttribute('data-mobile-label', labels[index] || 'Details');
                    }
                });

                if (table.hasAttribute('data-mobile-cards-expandable')
                    && row.querySelector('[data-mobile-secondary]')
                    && !row.querySelector('.mobile-card-toggle')) {
                    const toggleCell = document.createElement('td');
                    toggleCell.className = 'mobile-card-toggle';
                    toggleCell.setAttribute('data-mobile-label', 'More');
                    const toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.textContent = 'More details';
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.addEventListener('click', () => {
                        const expanded = row.classList.toggle('is-expanded');
                        toggle.setAttribute('aria-expanded', String(expanded));
                        toggle.textContent = expanded ? 'Less details' : 'More details';
                    });
                    toggleCell.appendChild(toggle);
                    row.appendChild(toggleCell);
                }
            });

            table.classList.add('mobile-card-ready');
            table.closest('.table-wrap')?.classList.add('mobile-card-wrap');
        });
    };

    const filterControls = form => Array.from(form.elements).filter(control => {
        if (!control.name || control.disabled || control.type === 'hidden' || control.type === 'submit' || control.type === 'button') {
            return false;
        }
        return true;
    });

    const hasActiveFilters = form => filterControls(form).some(control => {
        if (control.type === 'checkbox' || control.type === 'radio') {
            return control.checked;
        }
        if (control.tagName === 'SELECT') {
            return control.selectedIndex > 0;
        }
        return String(control.value || '').trim() !== '';
    });

    const createStatus = form => {
        let status = form.parentElement?.querySelector(':scope > [data-live-filter-status]');
        if (status) {
            return status;
        }

        status = document.createElement('p');
        status.className = 'live-filter-status';
        status.setAttribute('data-live-filter-status', '');
        status.setAttribute('aria-live', 'polite');
        status.textContent = 'Results update automatically.';
        form.insertAdjacentElement('afterend', status);
        return status;
    };

    const createReset = form => {
        let reset = form.querySelector('[data-live-filter-reset]');
        if (reset) {
            return reset;
        }

        const actions = form.querySelector('.toolbar-actions, .system-log-filter-actions, .notifications-mobile-page-actions, .chat-ui__filter-actions');
        if (!actions) {
            return null;
        }

        const existingReset = actions.querySelector('a.btn.secondary, a.chat-ui__button--secondary');
        if (existingReset) {
            existingReset.setAttribute('data-live-filter-reset', '');
            return existingReset;
        }

        const submit = form.querySelector('[type="submit"]');
        reset = document.createElement('button');
        reset.type = 'button';
        reset.setAttribute('data-live-filter-reset', '');
        reset.textContent = 'Reset';
        reset.className = submit?.classList.contains('chat-ui__button')
            ? 'chat-ui__button chat-ui__button--secondary'
            : 'btn secondary';
        actions.appendChild(reset);
        return reset;
    };

    const setFeedback = (state, message, isError = false) => {
        state.status.textContent = message;
        state.status.classList.toggle('is-error', isError);
    };

    const syncReset = state => {
        if (state.reset) {
            state.reset.hidden = !hasActiveFilters(state.form);
        }
    };

    const requestUrl = form => {
        const url = new URL(form.action || window.location.href, window.location.href);
        const parameters = new URLSearchParams(new FormData(form));
        parameters.delete('page');

        for (const [key, value] of Array.from(parameters.entries())) {
            if (String(value).trim() === '') {
                parameters.delete(key);
            }
        }

        url.search = parameters.toString();
        return url;
    };

    const syncSupplementaryRegions = documentFragment => {
        document.querySelectorAll('[data-live-filter-sync]').forEach(current => {
            const key = current.getAttribute('data-live-filter-sync');
            const next = Array.from(documentFragment.querySelectorAll('[data-live-filter-sync]'))
                .find(candidate => candidate.getAttribute('data-live-filter-sync') === key);
            if (next) {
                current.replaceWith(next);
            }
        });
    };

    const refresh = async (state, requestedUrl = null) => {
        window.clearTimeout(state.timer);
        state.controller?.abort();
        state.controller = new AbortController();
        const currentRequest = ++state.requestNumber;
        const url = requestedUrl || requestUrl(state.form);
        let results = document.querySelector(state.target);

        if (!results) {
            window.location.assign(url);
            return;
        }

        results.setAttribute('aria-busy', 'true');
        syncReset(state);
        setFeedback(state, 'Updating results...');

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.controller.signal,
            });

            if (!response.ok) {
                throw new Error('Filter request failed with status ' + response.status + '.');
            }

            const html = await response.text();
            if (currentRequest !== state.requestNumber) {
                return;
            }

            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            const nextResults = documentFragment.querySelector(state.target);
            if (!nextResults) {
                throw new Error('The filtered result region was not found.');
            }

            if (results.hasAttribute('data-live-filter-preserve-node')) {
                results.innerHTML = nextResults.innerHTML;
            } else {
                results.replaceWith(nextResults);
                results = nextResults;
            }
            results.setAttribute('aria-busy', 'false');
            syncSupplementaryRegions(documentFragment);
            prepareResponsiveCards(results);
            window.history.replaceState({}, '', url);
            syncReset(state);
            setFeedback(state, hasActiveFilters(state.form) ? 'Filtered results updated.' : 'Showing all results.');
            document.dispatchEvent(new CustomEvent('live-filter:updated', {
                detail: { form: state.form, results, url },
            }));
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            results.setAttribute('aria-busy', 'false');
            setFeedback(state, 'Live filtering could not update. Use the Apply button to try again.', true);
        }
    };

    const resetForm = state => {
        filterControls(state.form).forEach(control => {
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = false;
            } else if (control.tagName === 'SELECT') {
                control.selectedIndex = 0;
            } else {
                control.value = '';
            }
        });
        state.form.querySelector('input[type="search"], input[name="search"]')?.focus();
        refresh(state);
    };

    forms.forEach(form => {
        const target = form.getAttribute('data-live-filter-target');
        if (!target || !document.querySelector(target)) {
            return;
        }

        const state = {
            form,
            target,
            status: createStatus(form),
            reset: createReset(form),
            timer: null,
            controller: null,
            requestNumber: 0,
        };
        states.set(form, state);
        syncReset(state);

        form.addEventListener('input', event => {
            const control = event.target;
            if (!control.matches('input[type="search"], input[name="search"], input[data-live-filter-search]')) {
                return;
            }
            syncReset(state);
            window.clearTimeout(state.timer);
            state.timer = window.setTimeout(() => refresh(state), 350);
        });

        form.addEventListener('change', event => {
            const control = event.target;
            if (!control.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) {
                return;
            }
            refresh(state);
        });

        form.addEventListener('submit', event => {
            event.preventDefault();
            refresh(state);
        });

        state.reset?.addEventListener('click', event => {
            event.preventDefault();
            resetForm(state);
        });
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('[data-live-filter-results] a[href]');
        if (!link || !link.closest('[data-live-filter-results]')) {
            return;
        }

        const results = link.closest('[data-live-filter-results]');
        const state = Array.from(forms).map(form => states.get(form)).find(candidate => candidate && document.querySelector(candidate.target) === results);
        if (!state) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || !url.searchParams.has('page')) {
            return;
        }

        event.preventDefault();
        refresh(state, url);
    });
})();