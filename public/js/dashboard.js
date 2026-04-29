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

        return `<a href="${escapeHtml(notification.url)}" class="notifications-page-item notifications-compact-item${unreadClass}" data-notification-item data-notification-unread="${unreadValue}">
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

        if (!feedUrl || !notifDropdownList || !notifUnreadLabel || !notifDot) {
            return;
        }

        let isFetching = false;
        let overlayFilter = 'all';

        function applyOverlayFilter() {
            if (!notificationsOverlayList) {
                return;
            }

            notificationsOverlayList.querySelectorAll('[data-notification-item]').forEach((item) => {
                const isUnread = item.getAttribute('data-notification-unread') === '1';
                const show = overlayFilter === 'all' || isUnread;
                item.style.display = show ? '' : 'none';
            });
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

        function openNotificationsOverlay() {
            if (!notificationsOverlay) {
                return;
            }

            notificationsOverlay.classList.add('is-open');
            notificationsOverlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('ticket-overlay-open');
        }

        function closeNotificationsOverlay() {
            if (!notificationsOverlay) {
                return;
            }

            notificationsOverlay.classList.remove('is-open');
            notificationsOverlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ticket-overlay-open');
        }

        overlayOpeners.forEach((button) => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                const notifDropdown = document.getElementById('notifDropdown');
                if (notifDropdown) {
                    notifDropdown.style.display = 'none';
                }
                openNotificationsOverlay();
            });
        });

        overlayClosers.forEach((button) => {
            button.addEventListener('click', closeNotificationsOverlay);
        });

        overlayFilterButtons.forEach((button) => {
            button.addEventListener('click', function () {
                overlayFilter = button.getAttribute('data-notifications-filter') || 'all';
                overlayFilterButtons.forEach((filterButton) => {
                    filterButton.classList.toggle('is-active', filterButton === button);
                });
                applyOverlayFilter();
            });
        });

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

    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const hamburger = document.querySelector('.hamburger');
        const profileToggle = document.getElementById('profileToggle');
        const profileDropdown = document.getElementById('profileDropdown') || document.getElementById('profileMenu');
        const notifToggle = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const chatToggle = document.getElementById('chatToggle');
        const notificationsOverlay = document.getElementById('notificationsOverlay');

        function toggleSidebar() {
            if (!sidebar) return;
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                sidebar.classList.add('open');
            } else {
                sidebar.classList.add('collapsed');
                sidebar.classList.remove('open');
            }
        }

        if (toggle) {
            toggle.addEventListener('click', toggleSidebar);
        }

        if (hamburger) {
            hamburger.addEventListener('click', toggleSidebar);
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
        toggleDropdown(notifToggle, notifDropdown);

        if (chatToggle) {
            chatToggle.addEventListener('click', () => {
                window.location.href = chatToggle.dataset.chatUrl || '/chat';
            });
        }

        document.addEventListener('click', function () {
            if (profileDropdown) profileDropdown.style.display = 'none';
            if (notifDropdown) notifDropdown.style.display = 'none';
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && notificationsOverlay && notificationsOverlay.classList.contains('is-open')) {
                notificationsOverlay.classList.remove('is-open');
                notificationsOverlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('ticket-overlay-open');
            }
        });

        const links = document.querySelectorAll('.sidebar-item a');
        links.forEach(link => {
            if (link.href === window.location.href) {
                link.parentElement.classList.add('active');
            }
        });

        document.querySelectorAll('.chart[data-chart]').forEach(el => {
            const type = el.dataset.chart;
            el.textContent = type.replace(/-/g, ' ').toUpperCase();
        });

        initReadonlyLocationMaps();
        initNotificationCenter();
    });
})();
