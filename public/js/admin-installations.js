(() => {
    const form = document.querySelector('[data-live-installation-search]');
    const input = form?.querySelector('[data-live-installation-search-input]');
    const clearButton = form?.querySelector('[data-live-installation-search-clear]');
    const status = document.querySelector('[data-live-installation-search-status]');
    let results = document.querySelector('[data-installation-results]');
    let statusNav = document.querySelector('[data-installation-status-nav]');

    if (!form || !input || !results) {
        return;
    }

    let debounceTimer = null;
    let requestController = null;
    let requestNumber = 0;
    let lastAutomaticRefreshAt = 0;

    const setStatus = (message, isError = false) => {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };

    const prepareResponsiveCards = scope => {
        const table = scope.querySelector('table[data-mobile-cards]');
        if (!table) {
            return;
        }

        const labels = Array.from(table.querySelectorAll('thead th'))
            .map(header => header.textContent.trim());

        table.querySelectorAll('tbody tr').forEach(row => {
            const cells = Array.from(row.children).filter(cell => cell.tagName === 'TD');
            if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
                row.classList.add('mobile-card-empty');
                return;
            }

            cells.forEach((cell, index) => {
                cell.setAttribute('data-mobile-label', labels[index] || 'Details');
            });
        });

        table.classList.add('mobile-card-ready');
        table.closest('.table-wrap')?.classList.add('mobile-card-wrap');
    };

    const requestUrl = () => {
        const url = new URL(form.action, window.location.origin);
        const parameters = new URLSearchParams(new FormData(form));
        parameters.delete('page');

        for (const [key, value] of Array.from(parameters.entries())) {
            if (value.trim() === '') {
                parameters.delete(key);
            }
        }

        url.search = parameters.toString();
        return url;
    };

    const refreshResults = async ({ quiet = false } = {}) => {
        window.clearTimeout(debounceTimer);
        debounceTimer = null;
        requestController?.abort();
        requestController = new AbortController();
        const currentRequest = ++requestNumber;
        const url = requestUrl();
        const term = input.value.trim();

        if (!quiet) {
            results.setAttribute('aria-busy', 'true');
            statusNav?.setAttribute('aria-busy', 'true');
        }
        clearButton?.toggleAttribute('hidden', term === '');
        if (!quiet) {
            setStatus(term === '' ? 'Loading installations...' : 'Searching for "' + term + '"...');
        }

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
                signal: requestController.signal,
            });

            if (!response.ok) {
                throw new Error('Search request failed with status ' + response.status + '.');
            }

            const html = await response.text();
            if (currentRequest !== requestNumber) {
                return;
            }

            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            const nextResults = documentFragment.querySelector('[data-installation-results]');
            const nextStatusNav = documentFragment.querySelector('[data-installation-status-nav]');
            if (!nextResults) {
                throw new Error('The installation results could not be read.');
            }

            results.replaceWith(nextResults);
            results = nextResults;
            results.setAttribute('aria-busy', 'false');

            if (statusNav && nextStatusNav) {
                statusNav.replaceWith(nextStatusNav);
                statusNav = nextStatusNav;
                statusNav.setAttribute('aria-busy', 'false');
            }

            prepareResponsiveCards(results);
            window.history.replaceState({}, '', url);
            if (!quiet) {
                setStatus(term === ''
                    ? 'Installation results updated.'
                    : 'Results updated for "' + term + '".');
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            results.setAttribute('aria-busy', 'false');
            statusNav?.setAttribute('aria-busy', 'false');
            if (!quiet) {
                setStatus('Live search could not update. Press Search to try again.', true);
            }
        }
    };

    const refreshVisiblePage = () => {
        const now = Date.now();
        if (document.hidden || document.activeElement === input || now - lastAutomaticRefreshAt < 1500) {
            return;
        }

        lastAutomaticRefreshAt = now;
        refreshResults({ quiet: true });
    };

    const setProgressFilter = link => {
        const targetUrl = new URL(link.href, window.location.origin);
        form.querySelectorAll('[data-installation-filter-param]').forEach(field => field.remove());

        ['group', 'status'].forEach(name => {
            const value = targetUrl.searchParams.get(name);
            if (!value) {
                return;
            }

            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.value = value;
            field.dataset.installationFilterParam = '';
            form.appendChild(field);
        });
    };

    input.addEventListener('input', () => {
        clearButton?.toggleAttribute('hidden', input.value.trim() === '');
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(refreshResults, 350);
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        refreshResults();
    });

    clearButton?.addEventListener('click', () => {
        input.value = '';
        input.focus();
        refreshResults();
    });

    document.addEventListener('click', event => {
        const statusCard = event.target.closest('[data-installation-status-card]');
        if (!statusCard) {
            return;
        }

        event.preventDefault();
        setProgressFilter(statusCard);
        refreshResults();
    });

    window.addEventListener('focus', refreshVisiblePage);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshVisiblePage();
        }
    });
    window.setInterval(refreshVisiblePage, 15000);
})();
