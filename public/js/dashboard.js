(function () {
    function setReadonlyMapEmpty(element) {
        if (!element) {
            return;
        }

        if (element._readonlyLeafletMap) {
            element._readonlyLeafletMap.remove();
            element._readonlyLeafletMap = null;
        }

        element.classList.add('is-empty');
        element.textContent = element.dataset.emptyLabel || 'No pinned location available.';
    }

    function readonlyMapIsVisible(element) {
        return Boolean(element.offsetWidth && element.offsetHeight);
    }

    function buildReadonlyMapPopup(element) {
        const popupNode = document.createElement('div');
        popupNode.textContent = element.dataset.popup || 'Pinned service location';

        return popupNode;
    }

    function initReadonlyLocationMaps() {
        if (typeof window.L === 'undefined') {
            return;
        }

        document.querySelectorAll('.js-readonly-location-map').forEach((element) => {
            const lat = parseFloat(element.dataset.lat);
            const lng = parseFloat(element.dataset.lng);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                setReadonlyMapEmpty(element);
                return;
            }

            if (element._readonlyLeafletMap) {
                if (readonlyMapIsVisible(element)) {
                    setTimeout(() => element._readonlyLeafletMap.invalidateSize(), 120);
                }
                return;
            }

            if (!readonlyMapIsVisible(element)) {
                return;
            }

            element.classList.remove('is-empty');
            element.textContent = '';

            const map = L.map(element, {
                center: [lat, lng],
                zoom: 16,
                scrollWheelZoom: false,
                dragging: true,
                zoomControl: false,
                attributionControl: true,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lng])
                .addTo(map)
                .bindPopup(buildReadonlyMapPopup(element))
                .openPopup();
            element._readonlyLeafletMap = map;
            setTimeout(() => map.invalidateSize(), 120);
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderNotificationItem(notification) {
        const unreadClass = notification.is_unread ? ' is-unread' : '';
        const message = notification.message
            ? `<span class="notif-message">${escapeHtml(notification.message)}</span>`
            : '';
        const time = notification.time ? `<span class="notif-time">${escapeHtml(notification.time)}</span>` : '';

        return `<a href="${escapeHtml(notification.url)}" class="notif-item${unreadClass}">
            <span class="notif-title">${escapeHtml(notification.title)}</span>
            ${message}
            ${time}
        </a>`;
    }

    function renderOverlayNotificationItem(notification) {
        const unreadClass = notification.is_unread ? ' is-unread' : '';
        const unreadValue = notification.is_unread ? '1' : '0';
        const message = notification.message
            ? `<p class="notifications-page-message">${escapeHtml(notification.message)}</p>`
            : '';
        const time = notification.time ? `<div class="notifications-page-time">${escapeHtml(notification.time)}</div>` : '';

        return `<a href="${escapeHtml(notification.url)}" class="notifications-page-item notifications-compact-item${unreadClass}" data-notification-item data-notification-unread="${unreadValue}" data-notification-date="${escapeHtml(notification.date_key || '')}" data-notification-date-label="${escapeHtml(notification.date_label || 'Earlier')}">
            <div class="notifications-compact-dot-wrap">
                <span class="notifications-compact-dot${notification.is_unread ? ' is-unread' : ''}"></span>
            </div>
            <div class="notifications-page-main">
                <div class="notifications-page-title-row">
                    <span class="notifications-page-title">${escapeHtml(notification.title)}</span>
                </div>
                ${message}
                ${time}
            </div>
        </a>`;
    }

    function initNotificationCenter() {
        const body = document.body;
        const feedUrl = body?.dataset.notificationsFeed;
        const markAllUrl = body?.dataset.notificationsMarkAll;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const notifDropdownList = document.getElementById('notifDropdownList');
        const notifUnreadLabel = document.getElementById('notifUnreadLabel');
        const notifDot = document.querySelector('.notif-dot');
        const notifMarkAllForms = document.querySelectorAll('[data-notif-mark-all]');
        const notificationsOverlay = document.getElementById('notificationsOverlay');
        const notificationsOverlayList = document.getElementById('notificationsOverlayList');
        const overlayOpeners = document.querySelectorAll('[data-open-notifications-overlay]');
        const overlayClosers = document.querySelectorAll('[data-close-notifications-overlay]');
        const overlayFilterButtons = document.querySelectorAll('[data-notifications-filter]');
        const overlaySearch = document.querySelector('[data-notifications-search]');
        const overlayMobileFilter = document.querySelector('[data-notifications-mobile-filter]');
        const overlayFilterEmpty = document.getElementById('notificationsOverlayFilterEmpty');
        const notifToggle = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const mobileNotifications = window.matchMedia ? window.matchMedia('(max-width: 1024px)') : null;

        if (!feedUrl || !notifDropdownList || !notifUnreadLabel || !notifDot) {
            return;
        }

        let isFetching = false;
        let overlayFilter = 'all';
        let overlayTrigger = null;

        groupOverlayNotifications();

        function groupOverlayNotifications() {
            if (!notificationsOverlayList) return;
            notificationsOverlayList.querySelectorAll('[data-notification-date-heading]').forEach(heading => heading.remove());
            let previousDate = null;
            notificationsOverlayList.querySelectorAll('[data-notification-item]').forEach(item => {
                const date = item.dataset.notificationDate || 'earlier';
                if (date === previousDate) return;
                previousDate = date;
                const heading = document.createElement('div');
                heading.className = 'notifications-date-heading';
                heading.dataset.notificationDateHeading = date;
                heading.textContent = item.dataset.notificationDateLabel || 'Earlier';
                notificationsOverlayList.insertBefore(heading, item);
            });
        }
        function applyOverlayFilter() {
            if (!notificationsOverlayList) {
                return;
            }

            const query = String(overlaySearch?.value || '').trim().toLocaleLowerCase();
            let visibleCount = 0;
            const items = notificationsOverlayList.querySelectorAll('[data-notification-item]');

            items.forEach((item) => {
                const isUnread = item.getAttribute('data-notification-unread') === '1';
                const matchesStatus = overlayFilter === 'all' || isUnread;
                const matchesSearch = query === '' || item.textContent.toLocaleLowerCase().includes(query);
                const show = matchesStatus && matchesSearch;
                item.style.display = show ? '' : 'none';
                if (show) visibleCount += 1;
            });

            notificationsOverlayList.querySelectorAll('[data-notification-date-heading]').forEach(heading => {
                let sibling = heading.nextElementSibling;
                let hasVisibleItem = false;
                while (sibling && !sibling.hasAttribute('data-notification-date-heading')) {
                    if (sibling.hasAttribute('data-notification-item') && sibling.style.display !== 'none') {
                        hasVisibleItem = true;
                        break;
                    }
                    sibling = sibling.nextElementSibling;
                }
                heading.hidden = !hasVisibleItem;
            });
            if (overlayFilterEmpty) {
                overlayFilterEmpty.hidden = items.length === 0 || visibleCount > 0;
            }
        }

        function setOverlayFilter(value) {
            overlayFilter = value === 'unread' ? 'unread' : 'all';
            overlayFilterButtons.forEach((button) => {
                button.classList.toggle('is-active', button.getAttribute('data-notifications-filter') === overlayFilter);
            });
            if (overlayMobileFilter) overlayMobileFilter.value = overlayFilter;
            applyOverlayFilter();
        }

        function applyNotificationState(payload) {
            const unreadCount = Number(payload.unread_count || 0);
            notifUnreadLabel.textContent = unreadCount > 0 ? `${unreadCount} unread` : 'All caught up';
            notifDot.classList.toggle('is-hidden', unreadCount <= 0);

            if (Array.isArray(payload.notifications) && payload.notifications.length > 0) {
                notifDropdownList.innerHTML = payload.notifications
                    .map(renderNotificationItem)
                    .join('');
            } else {
                notifDropdownList.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
            }

            if (notificationsOverlayList) {
                const overlayNotifications = Array.isArray(payload.all_notifications) && payload.all_notifications.length > 0
                    ? payload.all_notifications
                    : (Array.isArray(payload.notifications) ? payload.notifications : []);

                notificationsOverlayList.innerHTML = overlayNotifications.length > 0
                    ? overlayNotifications.map(renderOverlayNotificationItem).join('')
                    : '<div class="notif-empty">No notifications yet.</div>';

                groupOverlayNotifications();
                applyOverlayFilter();
            }
        }

        async function refreshNotifications() {
            if (isFetching) {
                return;
            }

            isFetching = true;
            try {
                const response = await fetch(feedUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                applyNotificationState(payload);
            } catch (error) {
                console.error('Notification refresh failed.', error);
            } finally {
                isFetching = false;
            }
        }

        if (notifMarkAllForms.length && markAllUrl && csrfToken) {
            notifMarkAllForms.forEach((form) => {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    try {
                        const response = await fetch(markAllUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        });

                        if (response.ok) {
                            refreshNotifications();
                        }
                    } catch (error) {
                        console.error('Mark all as read failed.', error);
                    }
                });
            });
        }

        function setDropdownOpen(open) {
            if (!notifDropdown) return;
            notifDropdown.style.display = open ? 'block' : 'none';
            notifToggle?.setAttribute('aria-expanded', String(open));
        }

        function openNotificationsOverlay(trigger = null) {
            if (!notificationsOverlay) {
                return;
            }

            overlayTrigger = trigger || notifToggle;
            setDropdownOpen(false);
            notificationsOverlay.classList.add('is-open');
            notificationsOverlay.setAttribute('aria-hidden', 'false');
            notificationsOverlay.removeAttribute('inert');
            notifToggle?.setAttribute('aria-expanded', 'true');
            document.body.classList.add('ticket-overlay-open');
            window.requestAnimationFrame(() => {
                const firstControl = mobileNotifications?.matches && overlaySearch
                    ? overlaySearch
                    : notificationsOverlay.querySelector('.notifications-overlay__close');
                firstControl?.focus();
            });
        }

        function closeNotificationsOverlay(restoreFocus = false) {
            if (!notificationsOverlay) {
                return;
            }

            notificationsOverlay.classList.remove('is-open');
            notificationsOverlay.setAttribute('aria-hidden', 'true');
            notificationsOverlay.setAttribute('inert', '');
            notifToggle?.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('ticket-overlay-open');
            if (restoreFocus && overlayTrigger) overlayTrigger.focus();
        }

        if (notifToggle && notifDropdown) {
            notifToggle.addEventListener('click', function (event) {
                event.stopPropagation();
                if (mobileNotifications?.matches) {
                    event.preventDefault();
                    openNotificationsOverlay(notifToggle);
                    return;
                }
                setDropdownOpen(notifDropdown.style.display !== 'block');
            });
        }

        overlayOpeners.forEach((button) => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                openNotificationsOverlay(button);
            });
        });

        overlayClosers.forEach((button) => {
            button.addEventListener('click', () => closeNotificationsOverlay(true));
        });

        overlayFilterButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setOverlayFilter(button.getAttribute('data-notifications-filter') || 'all');
            });
        });
        overlayMobileFilter?.addEventListener('change', () => setOverlayFilter(overlayMobileFilter.value));
        overlaySearch?.addEventListener('input', applyOverlayFilter);

        notificationsOverlay?.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closeNotificationsOverlay(true);
                return;
            }
            if (event.key !== 'Tab') return;

            const focusable = Array.from(notificationsOverlay.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), a[href]'))
                .filter((element) => element.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        if (mobileNotifications) {
            const syncNotificationViewport = () => {
                setDropdownOpen(false);
                if (notificationsOverlay?.classList.contains('is-open')) closeNotificationsOverlay(false);
            };
            if (mobileNotifications.addEventListener) {
                mobileNotifications.addEventListener('change', syncNotificationViewport);
            } else {
                mobileNotifications.addListener(syncNotificationViewport);
            }
        }

        refreshNotifications();
        window.setInterval(() => {
            if (!document.hidden) {
                refreshNotifications();
            }
        }, 15000);
    }

    window.initReadonlyLocationMaps = initReadonlyLocationMaps;
    window.refreshReadonlyLocationMaps = function () {
        initReadonlyLocationMaps();
    };

    window.addEventListener('readonly-maps:refresh', function () {
        initReadonlyLocationMaps();
    });

    function initMobileChatFilters() {
        if (!window.matchMedia) return;
        const mobileChatFilters = window.matchMedia('(max-width: 768px)');
        document.querySelectorAll('form[data-chat-list-filter]').forEach(form => {
            const scope = form.querySelector('[data-chat-filter-scope]');
            scope?.addEventListener('change', () => {
                if (mobileChatFilters.matches) form.requestSubmit();
            });
        });
    }

    function initMobileFilterDrawers() {
        const forms = Array.from(document.querySelectorAll('form[data-mobile-filter]'));
        if (!forms.length || !window.matchMedia) return;

        const mobileViewport = window.matchMedia('(max-width: 767px)');

        forms.forEach((form, index) => {
            const home = document.createComment(`mobile-filter-home-${index + 1}`);
            form.parentNode.insertBefore(home, form);

            const activeNames = new Set();
            new FormData(form).forEach((value, name) => {
                const normalized = String(value || '').trim();
                if (normalized !== '' && normalized !== 'all' && !['_token', '_method', 'page'].includes(name)) {
                    activeNames.add(name);
                }
            });

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'mobile-filter-trigger';
            trigger.setAttribute('aria-expanded', 'false');
            trigger.innerHTML = `
                <span class="mobile-filter-trigger__label">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"></path></svg>
                    <span>Filters</span>
                </span>
                ${activeNames.size ? `<span class="mobile-filter-trigger__count">${activeNames.size} active</span>` : '<span class="mobile-filter-trigger__hint">Search and refine</span>'}
            `;
            home.parentNode.insertBefore(trigger, home);

            const overlayId = `mobileFilterOverlay${index + 1}`;
            const titleId = `${overlayId}Title`;
            trigger.setAttribute('aria-controls', overlayId);
            trigger.setAttribute('aria-label', activeNames.size ? `Filters, ${activeNames.size} active` : 'Open filters');

            const overlay = document.createElement('div');
            overlay.id = overlayId;
            overlay.className = 'mobile-filter-overlay';
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('inert', '');
            overlay.innerHTML = `
                <button type="button" class="mobile-filter__backdrop" tabindex="-1" aria-label="Close filters"></button>
                <section class="mobile-filter__sheet" role="dialog" aria-modal="true" aria-labelledby="${titleId}">
                    <header class="mobile-filter__header">
                        <div>
                            <span class="mobile-filter__eyebrow">Refine results</span>
                            <h2 id="${titleId}">Filters</h2>
                        </div>
                        <button type="button" class="mobile-filter__close" aria-label="Close filters">&times;</button>
                    </header>
                    <div class="mobile-filter__body"></div>
                </section>
            `;
            document.body.appendChild(overlay);

            const sheetBody = overlay.querySelector('.mobile-filter__body');
            const closeButton = overlay.querySelector('.mobile-filter__close');
            const backdrop = overlay.querySelector('.mobile-filter__backdrop');

            function setOpen(open, restoreFocus = false) {
                const shouldOpen = Boolean(open && mobileViewport.matches);
                overlay.classList.toggle('is-open', shouldOpen);
                overlay.setAttribute('aria-hidden', String(!shouldOpen));
                if (shouldOpen) {
                    overlay.removeAttribute('inert');
                } else {
                    overlay.setAttribute('inert', '');
                }
                trigger.setAttribute('aria-expanded', String(shouldOpen));
                document.body.classList.toggle('mobile-filter-open', shouldOpen);

                if (shouldOpen) {
                    const firstControl = form.querySelector('select, input:not([type="hidden"]), button, a[href]');
                    window.requestAnimationFrame(() => (firstControl || closeButton).focus());
                } else if (restoreFocus && mobileViewport.matches) {
                    trigger.focus();
                }
            }

            function syncForViewport() {
                setOpen(false);
                if (mobileViewport.matches) {
                    if (form.parentNode !== sheetBody) sheetBody.appendChild(form);
                } else if (home.parentNode && form.previousSibling !== home) {
                    home.parentNode.insertBefore(form, home.nextSibling);
                }
            }

            trigger.addEventListener('click', () => setOpen(true));
            closeButton.addEventListener('click', () => setOpen(false, true));
            backdrop.addEventListener('click', () => setOpen(false, true));
            overlay.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    setOpen(false, true);
                    return;
                }
                if (event.key !== 'Tab') return;

                const focusable = Array.from(overlay.querySelectorAll('button:not([disabled]):not([tabindex="-1"]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), a[href]'))
                    .filter(element => element.offsetParent !== null);
                if (!focusable.length) return;

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            if (mobileViewport.addEventListener) {
                mobileViewport.addEventListener('change', syncForViewport);
            } else {
                mobileViewport.addListener(syncForViewport);
            }
            syncForViewport();
        });
    }

    function initResponsiveTableCards() {
        document.querySelectorAll('table[data-mobile-cards]').forEach(table => {
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

                if (table.hasAttribute('data-mobile-cards-expandable') && row.querySelector('[data-mobile-secondary]')) {
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
    }

    document.addEventListener('DOMContentLoaded', function () {
        const validationSummary = document.querySelector('[data-validation-summary]');
        if (validationSummary) {
            const firstError = document.querySelector('.field-error, .error, [aria-invalid="true"]');
            let firstField = firstError?.matches?.('input, select, textarea, button') ? firstError : null;
            if (!firstField && firstError) {
                const container = firstError.closest('.apply-field, .profile-field, .technician-create-field, .form-group');
                firstField = container?.querySelector('input:not([type="hidden"]), select, textarea, button');
            }
            if (firstField) {
                firstField.setAttribute('aria-invalid', 'true');
                window.setTimeout(() => {
                    firstField.focus({ preventScroll: true });
                    firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 120);
            }
        }

        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const hamburger = document.querySelector('.hamburger');
        const profileToggle = document.getElementById('profileToggle');
        const profileDropdown = document.getElementById('profileDropdown') || document.getElementById('profileMenu');
        const notifToggle = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const chatToggle = document.getElementById('chatToggle');
        const notificationsOverlay = document.getElementById('notificationsOverlay');
        const sidebarBreakpoint = window.matchMedia ? window.matchMedia('(max-width: 1024px)') : null;
        const guestMenuBreakpoint = window.matchMedia ? window.matchMedia('(max-width: 900px)') : null;

        // Keep the public navigation behavior aligned with its 900px CSS breakpoint.
        const guestMenuToggle = document.getElementById('guestMenuToggle');
        const guestNavigation = document.getElementById('guestNavigation');
        if (guestMenuToggle && guestNavigation && guestMenuBreakpoint) {
            function setGuestMenuOpen(open, restoreFocus = false) {
                const mobile = guestMenuBreakpoint.matches;
                const expanded = mobile && open;
                guestNavigation.classList.toggle('is-open', expanded);
                guestNavigation.inert = mobile && !expanded;
                guestMenuToggle.setAttribute('aria-expanded', String(expanded));
                guestMenuToggle.setAttribute('aria-label', expanded ? 'Close navigation' : 'Open navigation');
                if (restoreFocus && mobile) guestMenuToggle.focus();
            }
            guestMenuToggle.addEventListener('click', () => {
                setGuestMenuOpen(!guestNavigation.classList.contains('is-open'));
            });
            guestNavigation.addEventListener('click', event => {
                if (event.target.closest('a') && guestMenuBreakpoint.matches) setGuestMenuOpen(false);
            });
            document.addEventListener('click', event => {
                if (!guestNavigation.contains(event.target) && !guestMenuToggle.contains(event.target)) {
                    setGuestMenuOpen(false);
                }
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && guestNavigation.classList.contains('is-open')) {
                    setGuestMenuOpen(false, true);
                }
            });
            guestNavigation.addEventListener('focusout', () => {
                setTimeout(() => {
                    if (!guestNavigation.contains(document.activeElement) && document.activeElement !== guestMenuToggle) {
                        setGuestMenuOpen(false);
                    }
                }, 0);
            });
            if (guestMenuBreakpoint.addEventListener) {
                guestMenuBreakpoint.addEventListener('change', () => setGuestMenuOpen(false));
            } else if (guestMenuBreakpoint.addListener) {
                guestMenuBreakpoint.addListener(() => setGuestMenuOpen(false));
            }
            setGuestMenuOpen(false);
        }
        function sidebarCanToggle() {
            return !sidebarBreakpoint || sidebarBreakpoint.matches;
        }

        function syncSidebarButton(expanded) {
            const value = expanded ? 'true' : 'false';
            if (toggle) {
                toggle.setAttribute('aria-expanded', value);
            }
            if (hamburger) {
                hamburger.setAttribute('aria-expanded', value);
            }
        }

        function setSidebarOpen(isOpen) {
            if (!sidebar) return;

            if (!sidebarCanToggle()) {
                sidebar.inert = false;
                sidebar.classList.remove('open', 'collapsed');
                syncSidebarButton(true);
                return;
            }

            sidebar.inert = !isOpen;
            sidebar.classList.toggle('open', isOpen);
            sidebar.classList.remove('collapsed');
            syncSidebarButton(isOpen);
        }

        function syncSidebarForViewport() {
            if (!sidebar) return;
            setSidebarOpen(sidebarCanToggle() && sidebar.classList.contains('open'));
        }

        function toggleSidebar(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (!sidebar || !sidebarCanToggle()) {
                syncSidebarForViewport();
                return;
            }

            setSidebarOpen(!sidebar.classList.contains('open'));
        }

        if (toggle) {
            toggle.addEventListener('click', toggleSidebar);
        }

        if (hamburger) {
            hamburger.addEventListener('click', toggleSidebar);
        }

        syncSidebarForViewport();

        if (sidebarBreakpoint) {
            if (sidebarBreakpoint.addEventListener) {
                sidebarBreakpoint.addEventListener('change', syncSidebarForViewport);
            } else if (sidebarBreakpoint.addListener) {
                sidebarBreakpoint.addListener(syncSidebarForViewport);
            }
        }

        function toggleDropdown(button, dropdown) {
            if (!button || !dropdown) return;
            button.addEventListener('click', function (e) {
                e.stopPropagation();
                const isVisible = dropdown.style.display === 'block';
                dropdown.style.display = isVisible ? 'none' : 'block';
            });
        }

        toggleDropdown(profileToggle, profileDropdown);

        if (chatToggle) {
            chatToggle.addEventListener('click', () => {
                window.location.href = chatToggle.dataset.chatUrl || '/chat';
            });
        }

        document.addEventListener('click', function (event) {
            if (profileDropdown) profileDropdown.style.display = 'none';
            if (notifDropdown) notifDropdown.style.display = 'none';
            if (notifToggle && (!notificationsOverlay || !notificationsOverlay.classList.contains('is-open'))) {
                notifToggle.setAttribute('aria-expanded', 'false');
            }
            if (sidebar && sidebarCanToggle() && sidebar.classList.contains('open')) {
                const target = event.target;
                const clickedSidebar = sidebar.contains(target);
                const clickedToggle = (toggle && toggle.contains(target)) || (hamburger && hamburger.contains(target));
                if (!clickedSidebar && !clickedToggle) {
                    setSidebarOpen(false);
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar && sidebarCanToggle() && sidebar.classList.contains('open')) {
                setSidebarOpen(false);
                if (toggle) toggle.focus();
            }
            if (event.key === 'Escape' && notificationsOverlay && notificationsOverlay.classList.contains('is-open')) {
                notificationsOverlay.classList.remove('is-open');
                notificationsOverlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('ticket-overlay-open');
            }
        });

        const links = document.querySelectorAll('.sidebar-item a');
        links.forEach(link => {
            link.addEventListener('click', () => {
                if (sidebarCanToggle()) {
                    setSidebarOpen(false);
                }
            });

            if (link.href === window.location.href) {
                link.parentElement.classList.add('active');
            }
        });

        document.querySelectorAll('.chart[data-chart]').forEach(el => {
            const type = el.dataset.chart;
            el.textContent = type.replace(/-/g, ' ').toUpperCase();
        });

        initMobileChatFilters();
        initMobileFilterDrawers();
        initResponsiveTableCards();
        initReadonlyLocationMaps();
        initNotificationCenter();
    });
})();
