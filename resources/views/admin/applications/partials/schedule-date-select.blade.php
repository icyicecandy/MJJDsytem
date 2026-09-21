@php
    $selectedDateValue = (string) ($selectedDate ?? '');
    $minimumDate = now()->toDateString();
    $maximumDate = now()->addDays(89)->toDateString();
@endphp
<input
    id="installation_date"
    name="installation_date"
    type="date"
    min="{{ $minimumDate }}"
    max="{{ $maximumDate }}"
    value="{{ $selectedDateValue }}"
    data-installation-date
    {{ !empty($required) ? 'required' : '' }}
>
<p class="admin-app-helper">Choose a Monday-Friday date from the calendar. Availability is checked for the selected technician and time.</p>
