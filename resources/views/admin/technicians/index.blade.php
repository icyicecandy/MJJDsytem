@extends('layouts.app')

@section('title','Technicians')

@php
    $statusLabels = [
        'active' => 'Active',
        'disabled' => 'Disabled',
    ];
    $employmentTypeOptions = $employmentTypeOptions ?? \App\Models\User::technicianEmploymentTypeOptions();
    $workDayOptions = $workDayOptions ?? \App\Models\User::technicianWorkDayOptions();
    $temporaryPassword = $temporaryPassword ?? old('temporary_password');
    $createFieldNames = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'technician_employment_type',
        'part_time_days',
        'temporary_password',
    ];
    $showCreateModal = collect($createFieldNames)->contains(fn ($field) => $errors->has($field));
@endphp

@push('styles')
<style>
    .admin-technicians-table th,
    .admin-technicians-table td {
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }
    .admin-technicians-table th:nth-child(1) { width: 12%; }
    .admin-technicians-table th:nth-child(2) { width: 12%; }
    .admin-technicians-table th:nth-child(3) { width: 15%; }
    .admin-technicians-table th:nth-child(4) { width: 14%; }
    .admin-technicians-table th:nth-child(5) { width: 15%; }
    .admin-technicians-table th:nth-child(6) { width: 14%; }
    .admin-technicians-table th:nth-child(7) { width: 10%; }
    .admin-technicians-table th:nth-child(8),
    .admin-technicians-table td.actions-cell {
        width: 8%;
        white-space: nowrap;
    }
    .admin-technicians-table .record-stack {
        display: grid;
        gap: 0.2rem;
    }
    .admin-technicians-table .record-muted {
        font-size: 0.84rem;
        line-height: 1.35;
    }
    .admin-technicians-table .status-pill {
        display: inline-flex;
        align-items: center;
        justify-self: start;
        width: fit-content;
        border: 1px solid transparent;
        border-radius: 999px;
        padding: 0.26rem 0.68rem;
        font-size: 0.82rem;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }
    .admin-technicians-table .status-pill--active {
        color: #15803d;
        background: #dcfce7;
        border-color: #bbf7d0;
    }
    .admin-technicians-table .status-pill--disabled {
        color: #475569;
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .admin-technicians-table .next-schedule {
        white-space: nowrap;
    }
    .admin-technicians-heading {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1.25rem;
        align-items: start;
    }
    .admin-technicians-heading h1 {
        margin-bottom: 0.35rem;
    }
    .admin-technicians-heading p {
        max-width: 780px;
        margin: 0;
    }
    .admin-technicians-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex: 0 0 auto;
    }
    .admin-technicians-navigation {
        display: flex;
        gap: 0.45rem;
        margin-top: 1.2rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }
    .admin-technicians-navigation__item {
        display: inline-flex;
        min-height: 2.7rem;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.62rem 0.9rem;
        border: 1px solid transparent;
        border-radius: 0.7rem;
        color: #526785;
        font-size: 0.9rem;
        font-weight: 800;
        text-decoration: none;
    }
    .admin-technicians-navigation__item:hover {
        border-color: #cbd8eb;
        background: #f8fafc;
        color: #123d9e;
    }
    .admin-technicians-navigation__item.is-active {
        border-color: #bfd0ef;
        background: #eaf1ff;
        color: #1746b5;
    }
    .admin-technicians-navigation__count {
        display: inline-grid;
        min-width: 1.5rem;
        height: 1.5rem;
        place-items: center;
        padding: 0 0.38rem;
        border-radius: 999px;
        background: #fff;
        color: #1746b5;
        font-size: 0.72rem;
        line-height: 1;
    }
    @media (max-width: 720px) {
        .admin-technicians-heading {
            grid-template-columns: 1fr;
        }
        .admin-technicians-header-actions,
        .admin-technicians-header-actions .btn {
            width: 100%;
        }
        .admin-technicians-navigation {
            display: grid;
            grid-template-columns: 1fr;
        }
        .admin-technicians-navigation__item {
            justify-content: flex-start;
        }
    }
    .technician-account-modal {
        position: fixed;
        inset: 0;
        z-index: 1600;
        display: none;
        place-items: center;
        padding: 1rem;
    }
    .technician-account-modal.is-open {
        display: grid;
    }
    .technician-account-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        backdrop-filter: blur(3px);
    }
    .technician-account-modal__dialog {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        width: min(780px, 100%);
        max-height: min(860px, calc(100dvh - 2rem));
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
    }
    .technician-account-modal__head {
        display: flex;
        flex: 0 0 auto;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .technician-account-modal__head > div {
        min-width: 0;
    }
    .technician-account-modal__head h2 {
        margin: 0;
        color: #0b1f3a;
        font-size: 1.18rem;
    }
    .technician-account-modal__head p {
        margin: 0.25rem 0 0;
        color: #64748b;
        overflow-wrap: anywhere;
    }
    .technician-account-modal__close {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 999px;
        padding: 0;
        border: 1px solid #dbe2ea;
        background: #fff;
        color: #0b1f3a;
        font-size: 1.4rem;
        line-height: 1;
        cursor: pointer;
    }
    .technician-create-form {
        display: grid;
        flex: 1 1 auto;
        gap: 1rem;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 1.25rem;
        scrollbar-gutter: stable;
    }
    .technician-create-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .technician-create-field {
        display: grid;
        gap: 0.4rem;
        min-width: 0;
    }
    .technician-create-field.full {
        grid-column: 1 / -1;
    }
    .technician-create-field label {
        color: #6b7280;
        font-size: 0.84rem;
        font-weight: 700;
    }
    .technician-create-field textarea {
        min-height: 96px;
    }
    .technician-create-field .field-error {
        color: #b91c1c;
        font-size: 0.84rem;
        font-weight: 700;
    }
    .technician-schedule-card {
        display: grid;
        gap: 0.85rem;
        padding: 0.9rem;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        background: #f8fafc;
    }
    .technician-schedule-line {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        color: #64748b;
        font-size: 0.9rem;
    }
    .technician-schedule-line strong {
        color: #0b1f3a;
    }
    .technician-day-picker {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.5rem;
    }
    .technician-day-chip {
        position: relative;
    }
    .technician-day-chip input {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
    }
    .technician-day-chip span {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 2.35rem;
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        background: #fff;
        color: #0b1f3a;
        font-size: 0.84rem;
        font-weight: 800;
    }
    .technician-day-chip input:checked + span {
        border-color: #2563eb;
        background: #dbeafe;
        color: #1d4ed8;
    }
    .technician-day-chip input:focus-visible + span {
        outline: 3px solid rgba(37, 99, 235, 0.22);
        outline-offset: 2px;
    }
    .technician-part-time-days[hidden] {
        display: none;
    }
    .technician-key-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: end;
    }
    .technician-create-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.65rem;
        padding-top: 0.25rem;
    }
    body.technician-account-modal-open {
        overflow: hidden;
    }
    @media (max-width: 760px) {
        .technician-create-grid,
        .technician-key-row {
            grid-template-columns: 1fr;
        }
        .technician-create-actions .btn,
        .technician-key-row .btn {
            width: 100%;
        }
        .technician-day-picker {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
</style>
@endpush

@section('content')
<div class="page-stack">
    <section class="card">
        <div class="admin-technicians-heading">
            <div>
                <h1>Technician Management</h1>
                <p>Manage technician accounts, work schedules, contact information, and reported availability.</p>
            </div>
            <div class="admin-technicians-header-actions">
                <button type="button" class="btn" data-open-technician-create>Add Technician</button>
            </div>
        </div>

        <nav class="admin-technicians-navigation" aria-label="Technician management sections">
            <a href="{{ route('admin.technicians.index') }}" class="admin-technicians-navigation__item is-active" aria-current="page">Technician Directory</a>
            <a href="{{ route('admin.unavailability.index') }}" class="admin-technicians-navigation__item">
                Availability Requests
                @if($pendingUnavailabilityCount > 0)
                    <span class="admin-technicians-navigation__count" aria-label="{{ $pendingUnavailabilityCount }} pending requests">{{ $pendingUnavailabilityCount }}</span>
                @endif
            </a>
        </nav>
    </section>

    <section class="card toolbar-card">
        <form method="GET" action="{{ route('admin.technicians.index') }}" class="toolbar-row" data-mobile-filter data-live-filter data-live-filter-target="#technicianResults">
            <div class="toolbar-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="toolbar-field grow">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Technician ID, first name, last name, email, phone, shift, status">
            </div>

            <div class="toolbar-actions">
                <button class="btn">Filter</button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('admin.technicians.index') }}" class="btn secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <div id="technicianResults" data-live-filter-results aria-busy="false">
    <section class="card table-card">
        <div class="table-wrap" tabindex="0" role="region" aria-label="Scrollable records table">
            <table class="telecom-table admin-technicians-table" data-mobile-cards>
                <thead>
                    <tr>
                        <th>Technician ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Contact Details</th>
                        <th>Address</th>
                        <th>Shift</th>
                        <th>Status</th>
                        <th class="col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($technicians as $technician)
                        @php
                            $statusValue = $technician->isDisabled() ? 'disabled' : 'active';
                            $statusLabel = $statusLabels[$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
                            $nextInstallation = $technician->installations->first();
                            $chatStatusLabel = $technician->chatAvailabilityLabel();
                            $firstName = $technician->displayFirstName();
                            $lastName = $technician->displayLastName();
                            $shiftParts = array_map('trim', explode(',', $technician->displayTechnicianShift(), 2));
                            $shiftTime = $shiftParts[0] ?? $technician->displayTechnicianShift();
                            $shiftDays = $shiftParts[1] ?? '';
                            $employmentType = $technician->displayTechnicianEmploymentType();
                        @endphp
                        <tr>
                            <td>
                                <span class="record-strong">{{ $technician->displayTechnicianRecordId() }}</span>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $firstName !== '' ? $firstName : 'N/A' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $lastName !== '' ? $lastName : 'N/A' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-strong">{{ $technician->phone ?: 'No contact number' }}</span>
                                    <span class="record-muted">{{ $technician->email ?: 'No email on file' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="record-muted">{{ $technician->address ?: 'No address recorded' }}</span>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="record-muted">{{ $shiftTime }}</span>
                                    @if($shiftDays !== '')
                                        <span class="record-muted">{{ $shiftDays }}</span>
                                    @endif
                                    <span class="record-muted">{{ $employmentType }}</span>
                                    @if($nextInstallation && $nextInstallation->installation_date)
                                        <span class="record-muted next-schedule">
                                            Next: {{ $nextInstallation->installation_date->format('M d, Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="record-stack">
                                    <span class="status-pill status-pill--{{ $statusValue }}">{{ $statusLabel }}</span>
                                    <span class="record-muted">Chat: {{ $chatStatusLabel }}</span>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <a href="{{ route('admin.technicians.show', $technician) }}" class="btn secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8"><div class="empty-state compact">No technician records matched the current filter.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card toolbar-card">
        <x-ui.pagination :paginator="$technicians" />
    </section>
    </div>

    <div class="technician-account-modal {{ $showCreateModal ? 'is-open' : '' }}" data-technician-create-modal aria-hidden="{{ $showCreateModal ? 'false' : 'true' }}">
        <div class="technician-account-modal__backdrop" data-close-technician-create></div>
        <div class="technician-account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="technicianCreateTitle">
            <div class="technician-account-modal__head">
                <div>
                    <h2 id="technicianCreateTitle">Add Technician</h2>
                    <p>Create technician login credentials and work schedule details.</p>
                </div>
                <button type="button" class="technician-account-modal__close" data-close-technician-create aria-label="Close add technician">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.technicians.store') }}" class="technician-create-form">
                @csrf
                <input type="hidden" name="_technician_create" value="1">

                <div class="technician-create-grid">
                    <div class="technician-create-field">
                        <label for="first_name">First Name</label>
                        <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name">
                        @error('first_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field">
                        <label for="last_name">Last Name</label>
                        <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name">
                        @error('last_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email">
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field">
                        <label for="phone">Contact No.</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="30" inputmode="tel" autocomplete="tel">
                        @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field full">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" required maxlength="255" autocomplete="street-address">{{ old('address') }}</textarea>
                        @error('address')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field">
                        <label for="technician_employment_type">Employment Type</label>
                        <select id="technician_employment_type" name="technician_employment_type" required data-employment-type>
                            @foreach($employmentTypeOptions as $value => $label)
                                <option value="{{ $value }}" {{ old('technician_employment_type', 'full_time') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('technician_employment_type')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field">
                        <label for="technician_shift_start">Shift Start</label>
                        <input id="technician_shift_start" name="technician_shift_start" type="time" value="{{ old('technician_shift_start', '08:00') }}" required>
                        @error('technician_shift_start')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="technician-create-field">
                        <label for="technician_shift_end">Shift End</label>
                        <input id="technician_shift_end" name="technician_shift_end" type="time" value="{{ old('technician_shift_end', '17:00') }}" required>
                        @error('technician_shift_end')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="technician-create-field">
                        <label for="technician_break_start">Break Start <span class="record-muted">(optional)</span></label>
                        <input id="technician_break_start" name="technician_break_start" type="time" value="{{ old('technician_break_start') }}">
                        @error('technician_break_start')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="technician-create-field">
                        <label for="technician_break_end">Break End <span class="record-muted">(optional)</span></label>
                        <input id="technician_break_end" name="technician_break_end" type="time" value="{{ old('technician_break_end') }}">
                        @error('technician_break_end')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="technician-create-field">
                        <label for="technician_max_active_tickets">Maximum Active Tickets</label>
                        <input id="technician_max_active_tickets" name="technician_max_active_tickets" type="number" min="1" max="20" value="{{ old('technician_max_active_tickets', 5) }}" required>
                        @error('technician_max_active_tickets')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="technician-create-field">
                        <label for="technician_unavailable_date">Initial Unavailable Date <span class="record-muted">(optional)</span></label>
                        <input id="technician_unavailable_date" name="technician_unavailable_dates[]" type="date" value="{{ old('technician_unavailable_dates.0') }}" min="{{ today()->toDateString() }}">
                        @error('technician_unavailable_dates.*')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field full">
                        <label>Schedule</label>
                        <div class="technician-schedule-card">
                            <div class="technician-schedule-line">
                                <span>Hours:</span>
                                <strong>{{ \App\Models\User::TECHNICIAN_WORKING_HOURS }}</strong>
                            </div>
                            <div class="technician-schedule-line" data-schedule-summary>
                                <span>Days:</span>
                                <strong>{{ old('technician_employment_type', 'full_time') === 'part_time' ? 'Select 1 to 3 days' : 'Mon - Fri' }}</strong>
                            </div>

                            <div class="technician-part-time-days" data-part-time-days {{ old('technician_employment_type', 'full_time') === 'part_time' ? '' : 'hidden' }}>
                                <div class="technician-day-picker">
                                    @foreach($workDayOptions as $value => $label)
                                        <label class="technician-day-chip">
                                            <input type="checkbox" name="part_time_days[]" value="{{ $value }}" {{ in_array($value, old('part_time_days', []), true) ? 'checked' : '' }} data-work-day>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @error('part_time_days')<span class="field-error">{{ $message }}</span>@enderror
                        @error('part_time_days.*')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="technician-create-field full">
                        <label for="temporary_password">Generated Key</label>
                        <div class="technician-key-row">
                            <input id="temporary_password" name="temporary_password" type="text" value="{{ old('temporary_password', $temporaryPassword) }}" required readonly data-technician-key>
                            <button type="button" class="btn secondary" data-generate-technician-key>Generate Key</button>
                        </div>
                        @error('temporary_password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="technician-create-actions">
                    <button type="button" class="btn secondary" data-close-technician-create>Cancel</button>
                    <button type="submit" class="btn">Create Technician</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.querySelector('[data-technician-create-modal]');
        const openers = document.querySelectorAll('[data-open-technician-create]');
        const closers = document.querySelectorAll('[data-close-technician-create]');
        const keyField = document.querySelector('[data-technician-key]');
        const generateButton = document.querySelector('[data-generate-technician-key]');
        const employmentType = document.querySelector('[data-employment-type]');
        const partTimeDays = document.querySelector('[data-part-time-days]');
        const workDayFields = Array.from(document.querySelectorAll('[data-work-day]'));
        const scheduleSummary = document.querySelector('[data-schedule-summary] strong');
        const groups = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%&*'
        ];

        function openModal() {
            if (!modal) return;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('technician-account-modal-open');
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('technician-account-modal-open');
        }

        function randomChar(source) {
            if (window.crypto && window.crypto.getRandomValues) {
                const values = new Uint32Array(1);
                window.crypto.getRandomValues(values);
                return source[values[0] % source.length];
            }

            return source[Math.floor(Math.random() * source.length)];
        }

        function shuffle(value) {
            const chars = value.split('');
            for (let index = chars.length - 1; index > 0; index -= 1) {
                const target = Math.floor(Math.random() * (index + 1));
                const current = chars[index];
                chars[index] = chars[target];
                chars[target] = current;
            }
            return chars.join('');
        }

        function generateKey() {
            if (!keyField) return;
            const pool = groups.join('');
            let key = groups.map(randomChar).join('');
            while (key.length < 14) {
                key += randomChar(pool);
            }
            keyField.value = shuffle(key);
        }

        function updateScheduleControls() {
            const isPartTime = employmentType && employmentType.value === 'part_time';
            const selectedDays = workDayFields.filter((field) => field.checked);

            if (partTimeDays) {
                partTimeDays.hidden = !isPartTime;
            }

            workDayFields.forEach(function (field) {
                field.disabled = !isPartTime || (!field.checked && selectedDays.length >= 3);
            });

            if (scheduleSummary) {
                if (!isPartTime) {
                    scheduleSummary.textContent = 'Mon - Fri';
                    return;
                }

                scheduleSummary.textContent = selectedDays.length
                    ? selectedDays.map((field) => field.nextElementSibling ? field.nextElementSibling.textContent.trim() : field.value).join(' / ')
                    : 'Select 1 to 3 days';
            }
        }

        openers.forEach(function (button) {
            button.addEventListener('click', openModal);
        });

        closers.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        if (generateButton) {
            generateButton.addEventListener('click', generateKey);
        }

        if (employmentType) {
            employmentType.addEventListener('change', updateScheduleControls);
        }

        workDayFields.forEach(function (field) {
            field.addEventListener('change', updateScheduleControls);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        if (modal && modal.classList.contains('is-open')) {
            document.body.classList.add('technician-account-modal-open');
        }

        updateScheduleControls();
    });
</script>
@endpush
