(function () {
    function initReadonlyLocationMaps() {
        if (typeof window.L === 'undefined') {
            return;
        }

        document.querySelectorAll('.js-readonly-location-map').forEach((element) => {
            const lat = parseFloat(element.dataset.lat);
            const lng = parseFloat(element.dataset.lng);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                return;
            }

            if (element._readonlyLeafletMap) {
                setTimeout(() => element._readonlyLeafletMap.invalidateSize(), 120);
                return;
            }

            const map = L.map(element, {
                center: [lat, lng],
                zoom: 16,
                scrollWheelZoom: false,
                dragging: true,
                zoomControl: true,
                attributionControl: true,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lng]).addTo(map);
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

    function initNotificationCenter() {
        const body = document.body;
        const feedUrl = body?.dataset.notificationsFeed;
        const markAllUrl = body?.dataset.notificationsMarkAll;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const notifDropdownList = document.getElementById('notifDropdownList');
        const notifUnreadLabel = document.getElementById('notifUnreadLabel');
        const notifDot = document.querySelector('.notif-dot');
        const notifMarkAllForm = document.getElementById('notifMarkAllForm');

        if (!feedUrl || !notifDropdownList || !notifUnreadLabel || !notifDot) {
            return;
        }

        let isFetching = false;

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

        if (notifMarkAllForm && markAllUrl && csrfToken) {
            notifMarkAllForm.addEventListener('submit', async function (event) {
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

    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const hamburger = document.querySelector('.hamburger');
        const profileToggle = document.getElementById('profileToggle');
        const profileDropdown = document.getElementById('profileDropdown') || document.getElementById('profileMenu');
        const notifToggle = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const chatToggle = document.getElementById('chatToggle');

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
