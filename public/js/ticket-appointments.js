(() => {
    const configNode = document.getElementById('ticketScheduleConfig');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent);
    const minutes = value => { const [h, m] = value.split(':').map(Number); return h * 60 + m; };
    document.querySelectorAll('[data-ticket-appointment-form]').forEach(form => {
        const tech = form.querySelector('[name=technician_id]');
        const date = form.querySelector('[name=appointment_date]');
        const time = form.querySelector('[name=appointment_time]');
        const identity = form.querySelector('[name=appointment_ticket_id]');
        const hint = form.querySelector('[data-appointment-hint]');
        const refresh = () => {
            const assigned = Boolean(tech.value) && !tech.disabled;
            date.required = time.required = assigned;
            date.disabled = time.disabled = !assigned;
            let available = 0;
            for (const option of time.options) {
                if (!option.value) continue;
                option.dataset.label ||= option.textContent.trim();
                const start = minutes(option.value);
                const past = date.value && (date.value < config.today || (date.value === config.today && option.value <= config.time));
                const conflict = config.bookings.some(booking => String(booking.technician_id) === tech.value
                    && booking.date === date.value
                    && !(booking.kind === 'ticket' && String(booking.id) === identity.value)
                    && start < minutes(booking.time) + 60 && start + 60 > minutes(booking.time));
                option.disabled = Boolean(past || conflict);
                option.textContent = option.dataset.label + (option.disabled ? ' (Unavailable)' : '');
                if (!option.disabled) available++;
            }
            if (time.selectedOptions[0]?.disabled) time.value = '';
            hint.textContent = !assigned ? 'Choose a technician to schedule this ticket.'
                : !date.value ? 'Choose a date to see available times.'
                : available ? 'Availability is checked again when you save.' : 'No available slots. Choose another date or technician.';
        };
        tech.addEventListener('change', refresh);
        date.addEventListener('change', refresh);
        form.addEventListener('appointment:refresh', refresh);
        form.addEventListener('submit', refresh);
        refresh();
    });
})();
