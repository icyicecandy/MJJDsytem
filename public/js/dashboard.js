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
    });
})();
