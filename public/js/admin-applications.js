(() => {
    const form = document.querySelector('[data-live-application-search]');
    const input = form?.querySelector('[data-live-application-search-input]');
    const statusFilter = form?.querySelector('[data-live-application-status]');
    const resetButton = form?.querySelector('[data-live-application-reset]');
    const liveStatus = document.querySelector('[data-live-application-search-status]');
    let results = document.querySelector('[data-application-results]');
    let statusNav = document.querySelector('[data-application-status-nav]');

    if (!form || !input || !statusFilter || !results) {
        return;
    }

    let debounceTimer = null;
    let requestController = null;
    let requestNumber = 0;

    const hasFilters = () => input.value.trim() !== '' || statusFilter.value !== '';

    const setStatus = (message, isError = false) => {
        if (!liveStatus) {
            return;
        }

        liveStatus.textContent = message;
        liveStatus.classList.toggle('is-error', isError);
    };

    const updateResetButton = () => {
        resetButton?.toggleAttribute('hidden', !hasFilters());
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

    const filterLabel = () => {
        const term = input.value.trim();
        const selectedStatus = statusFilter.options[statusFilter.selectedIndex]?.text || 'All';

        if (term !== '' && statusFilter.value !== '') {
            return 'Searching for "' + term + '" in ' + selectedStatus + ' applications';
        }
        if (term !== '') {
            return 'Searching for "' + term + '"';
        }
        if (statusFilter.value !== '') {
            return 'Loading ' + selectedStatus + ' applications';
        }
        return 'Loading all applications';
    };

    const refreshResults = async () => {
        window.clearTimeout(debounceTimer);
        requestController?.abort();
        requestController = new AbortController();
        const currentRequest = ++requestNumber;
        const url = requestUrl();

        results.setAttribute('aria-busy', 'true');
        statusNav?.setAttribute('aria-busy', 'true');
        updateResetButton();
        setStatus(filterLabel() + '...');

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
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
            const nextResults = documentFragment.querySelector('[data-application-results]');
            const nextStatusNav = documentFragment.querySelector('[data-application-status-nav]');
            if (!nextResults) {
                throw new Error('The application results could not be read.');
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
            setStatus(hasFilters() ? 'Application results updated.' : 'Showing all applications.');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            results.setAttribute('aria-busy', 'false');
            statusNav?.setAttribute('aria-busy', 'false');
            setStatus('Live filtering could not update. Press Apply to try again.', true);
        }
    };

    input.addEventListener('input', () => {
        updateResetButton();
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(refreshResults, 350);
    });

    statusFilter.addEventListener('change', refreshResults);

    form.addEventListener('submit', event => {
        event.preventDefault();
        refreshResults();
    });

    resetButton?.addEventListener('click', () => {
        input.value = '';
        statusFilter.value = '';
        input.focus();
        refreshResults();
    });

    document.addEventListener('click', event => {
        const statusCard = event.target.closest('[data-application-status-card]');
        if (!statusCard) {
            return;
        }

        event.preventDefault();
        statusFilter.value = statusCard.dataset.applicationStatusCard || '';
        refreshResults();
    });
})();
