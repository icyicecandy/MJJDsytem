@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .admin-app-review {
            display: grid;
            gap: 1.5rem;
            padding: 1.5rem;
        }
        .admin-app-header,
        .admin-app-card {
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }
        .admin-app-header {
            display: grid;
            gap: 1.25rem;
            padding: 1.5rem;
        }
        .admin-app-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
        }
        .admin-app-kicker,
        .admin-app-label,
        .admin-app-field label,
        .admin-app-inline-label,
        .admin-app-progress-step span {
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }
        .admin-app-header h1 {
            margin: 0.35rem 0 0.5rem;
            font-size: 1.5rem;
            line-height: 1.2;
            font-weight: 700;
            color: #0f172a;
        }
        .admin-app-subtitle,
        .admin-app-card p,
        .admin-app-helper,
        .admin-app-muted,
        .admin-app-progress-copy p {
            margin: 0;
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .admin-app-header-meta {
            display: grid;
            justify-items: end;
            gap: 0.5rem;
        }
        .admin-app-ref {
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 600;
        }
        .admin-app-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .admin-app-status--submitted { background: #dbeafe; color: #1d4ed8; }
        .admin-app-status--under_review { background: #fef3c7; color: #b45309; }
        .admin-app-status--approved,
        .admin-app-status--installed,
        .admin-app-status--completed { background: #dcfce7; color: #15803d; }
        .admin-app-status--activated { background: #ccfbf1; color: #0f766e; }
        .admin-app-status--rejected { background: #fee2e2; color: #b91c1c; }
        .admin-app-status--cancelled { background: #fee2e2; color: #b91c1c; }
        .admin-app-status--cancellation_requested { background: #ffedd5; color: #c2410c; }
        .admin-app-status--scheduled { background: #e0e7ff; color: #4338ca; }
        .admin-app-status--pending { background: #e2e8f0; color: #475569; }
        .admin-app-progress {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-app-progress-step {
            display: grid;
            gap: 0.55rem;
        }
        .admin-app-progress-line {
            position: relative;
            height: 0.5rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .admin-app-progress-line::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: scaleX(0);
            transform-origin: left center;
            background: linear-gradient(90deg, #0f766e, #22c55e);
        }
        .admin-app-progress-step.is-complete .admin-app-progress-line::after,
        .admin-app-progress-step.is-active .admin-app-progress-line::after {
            transform: scaleX(1);
        }
        .admin-app-progress-step.is-active .admin-app-progress-line::after {
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }
        .admin-app-progress-copy strong {
            display: block;
            margin-bottom: 0.25rem;
            color: #0f172a;
            font-size: 0.95rem;
        }
        .admin-app-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(340px, 0.92fr);
            gap: 1.5rem;
            align-items: start;
        }
        .admin-app-column {
            display: grid;
            gap: 1.25rem;
        }
        .admin-app-card {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .admin-app-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .admin-app-card-head h2,
        .admin-app-card-head h3 {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .admin-app-detail-grid,
        .admin-app-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }
        .admin-app-detail,
        .admin-app-stat {
            display: grid;
            gap: 0.3rem;
        }
        .admin-app-detail--full,
        .admin-app-stat--full {
            grid-column: 1 / -1;
        }
        .admin-app-value {
            color: #0f172a;
            font-size: 0.97rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .admin-app-stat {
            padding: 0.9rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #f8fafc;
        }
        .admin-app-location-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        .admin-app-form {
            display: grid;
            gap: 1rem;
        }
        .admin-app-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-app-field {
            display: grid;
            gap: 0.45rem;
        }
        .admin-app-field--full {
            grid-column: 1 / -1;
        }
        .admin-app-field input,
        .admin-app-field select,
        .admin-app-field textarea {
            width: 100%;
        }
        .admin-app-field textarea {
            min-height: 112px;
        }
        .admin-app-field textarea#rejection_reason {
            min-height: 88px;
        }
        .admin-app-field.is-hidden {
            display: none;
        }
        .admin-app-summary-box,
        .admin-app-empty {
            display: grid;
            gap: 0.85rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #f8fafc;
        }
        .admin-app-empty {
            justify-items: start;
        }
        .admin-app-reject-box {
            padding: 1rem;
            border: 1px solid #fecaca;
            border-radius: 1rem;
            background: #fff1f2;
        }
        .admin-app-reject-box strong {
            display: block;
            margin-bottom: 0.35rem;
            color: #991b1b;
        }
        .admin-app-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-app-actions--decision {
            grid-template-columns: repeat(3, max-content);
            justify-content: start;
            gap: 0.5rem;
        }
        .admin-app-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .admin-app-actions--decision .btn {
            width: auto;
            min-height: 2.1rem;
            min-width: 0;
            padding: 0.48rem 0.72rem;
            border-radius: 8px;
            font-size: 0.78rem;
            line-height: 1.15;
        }
        .admin-app-timeline {
            position: relative;
            display: grid;
            gap: 1rem;
            padding-left: 1rem;
        }
        .admin-app-timeline::before {
            content: "";
            position: absolute;
            top: 0.25rem;
            bottom: 0.25rem;
            left: 0.2rem;
            width: 1px;
            background: #cbd5e1;
        }
        .admin-app-timeline-item {
            position: relative;
            display: grid;
            gap: 0.3rem;
            padding-left: 1rem;
        }
        .admin-app-timeline-item::before {
            content: "";
            position: absolute;
            top: 0.3rem;
            left: -0.02rem;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #2563eb;
            box-shadow: 0 0 0 4px #eff6ff;
        }
        .admin-app-timeline-meta {
            font-size: 0.82rem;
            color: #64748b;
        }
        .admin-app-photo {
            display: grid;
            gap: 0.75rem;
        }
        .admin-app-photo img {
            display: block;
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 1rem;
            border: 1px solid #dbe2ea;
        }
        .admin-app-map-trigger {
            align-self: start;
        }
        .admin-app-map-link {
            white-space: nowrap;
        }
        .admin-app-map {
            position: relative;
            isolation: isolate;
            z-index: 0;
            height: 360px;
            border-radius: 1rem;
            border: 1px solid #dbe2ea;
            overflow: hidden;
        }
        .admin-app-map .leaflet-top,
        .admin-app-map .leaflet-bottom {
            z-index: 5;
        }
        .admin-app-map.is-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }
        .admin-app-map-modal {
            position: fixed;
            inset: 0;
            z-index: 120;
            display: none;
        }
        .admin-app-map-modal.is-open {
            display: block;
        }
        .admin-app-map-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(3px);
        }
        .admin-app-map-modal__dialog {
            position: relative;
            z-index: 1;
            width: min(820px, calc(100vw - 2rem));
            margin: 4vh auto;
            background: #fff;
            border-radius: 1.25rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
            overflow: hidden;
        }
        .admin-app-map-modal__shell {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .admin-app-map-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .admin-app-map-modal__head h3 {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .admin-app-map-modal__close {
            width: 2.25rem;
            height: 2.25rem;
            border: 0;
            border-radius: 999px;
            background: #eff6ff;
            color: #0f172a;
            font-size: 1.35rem;
            cursor: pointer;
        }
        .admin-app-map-modal__meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .admin-app-footer {
            display: flex;
            justify-content: flex-start;
        }
        body.admin-app-map-open {
            overflow: hidden;
        }
        @media (max-width: 1080px) {
            .admin-app-layout,
            .admin-app-progress {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .admin-app-review {
                padding: 1rem;
            }
            .admin-app-header,
            .admin-app-card {
                padding: 1rem;
            }
            .admin-app-header-top,
            .admin-app-card-head {
                display: grid;
            }
            .admin-app-header-meta {
                justify-items: start;
            }
            .admin-app-detail-grid,
            .admin-app-stat-grid,
            .admin-app-form-grid,
            .admin-app-actions,
            .admin-app-map-modal__meta {
                grid-template-columns: 1fr;
            }
            .admin-app-map-modal__dialog {
                width: calc(100vw - 1rem);
                margin: 2vh auto;
            }
        }

        .admin-app-review {
            gap: 2rem;
            padding: 1.5rem;
            background: #f6f8fb;
            color: #111827;
        }
        .admin-app-header,
        .admin-app-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(11, 31, 58, 0.06);
        }
        .admin-app-header {
            padding: 1.5rem;
        }
        .admin-app-header h1 {
            margin: 0.25rem 0 0.45rem;
            color: #0b1f3a;
            font-size: 2rem;
            line-height: 1.12;
            font-weight: 800;
            letter-spacing: 0;
        }
        .admin-app-kicker,
        .admin-app-label,
        .admin-app-field label,
        .admin-app-inline-label,
        .admin-app-progress-label {
            color: #6b7280;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .admin-app-subtitle,
        .admin-app-card p,
        .admin-app-helper,
        .admin-app-muted,
        .admin-app-progress-copy p,
        .admin-app-summary-note {
            color: #6b7280;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .admin-app-status {
            min-height: 2rem;
            padding: 0.42rem 0.8rem;
            border-radius: 999px;
            font-size: 0.76rem;
            letter-spacing: 0;
        }
        .admin-app-layout {
            grid-template-columns: minmax(0, 65fr) minmax(320px, 35fr);
            gap: 2rem;
            align-items: start;
        }
        .admin-app-column {
            gap: 1.5rem;
            align-content: start;
        }
        .admin-app-card {
            gap: 1.25rem;
            padding: 1.5rem;
        }
        .admin-app-card-head {
            align-items: center;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid #edf0f4;
        }
        .admin-app-card-head h2,
        .admin-app-card-head h3 {
            color: #0b1f3a;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0;
        }
        .admin-app-detail-grid,
        .admin-app-stat-grid,
        .admin-app-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-app-detail {
            min-width: 0;
            gap: 0.35rem;
        }
        .admin-app-value {
            color: #111827;
            font-size: 0.96rem;
            font-weight: 650;
            line-height: 1.45;
        }
        .admin-app-progress {
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0;
            overflow: hidden;
        }
        .admin-app-progress-step {
            min-width: 0;
            gap: 0.7rem;
        }
        .admin-app-progress-marker {
            display: grid;
            grid-template-columns: 2rem minmax(0, 1fr);
            align-items: center;
            gap: 0.6rem;
        }
        .admin-app-progress-dot {
            display: inline-flex;
            width: 2rem;
            height: 2rem;
            align-items: center;
            justify-content: center;
            border: 2px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            color: #6b7280;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0;
            box-shadow: 0 0 0 5px #f3f4f6;
        }
        .admin-app-progress-line {
            height: 2px;
            border-radius: 999px;
            background: #d1d5db;
            overflow: visible;
        }
        .admin-app-progress-line::after {
            content: none;
        }
        .admin-app-progress-step:last-child .admin-app-progress-line {
            visibility: hidden;
        }
        .admin-app-progress-step.is-complete .admin-app-progress-dot {
            border-color: #16a34a;
            background: #16a34a;
            color: #fff;
            box-shadow: 0 0 0 5px #dcfce7;
        }
        .admin-app-progress-step.is-active .admin-app-progress-dot {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 0 0 5px #dbeafe;
        }
        .admin-app-progress-step.is-complete .admin-app-progress-line {
            background: #16a34a;
        }
        .admin-app-progress-copy strong {
            margin-bottom: 0.2rem;
            color: #111827;
            font-size: 0.95rem;
            font-weight: 750;
        }
        .admin-app-progress-copy p {
            font-size: 0.84rem;
        }
        .admin-app-stat,
        .admin-app-mini-card {
            display: grid;
            gap: 0.4rem;
            min-width: 0;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
        }
        .admin-app-mini-card strong {
            color: #111827;
            font-size: 1rem;
            line-height: 1.35;
        }
        .admin-app-mini-card--status {
            grid-column: 1 / -1;
            border-color: rgba(11, 31, 58, 0.2);
            background: linear-gradient(135deg, #0b1f3a, #123a68);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }
        .admin-app-mini-card--status .admin-app-label,
        .admin-app-mini-card--status .admin-app-summary-note {
            color: #dbeafe;
        }
        .admin-app-mini-card--status strong {
            color: #fff;
            font-size: 1.35rem;
        }
        .admin-app-mini-card--full {
            grid-column: 1 / -1;
        }
        .admin-app-location-shell {
            display: grid;
            gap: 1rem;
        }
        .admin-app-map {
            height: 250px;
            border: 1px solid #dbe2ea;
            border-radius: 12px;
            overflow: hidden;
            background: #f8fafc;
        }
        .admin-app-map--modal {
            height: 420px;
        }
        .admin-app-location-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .admin-app-location-meta {
            color: #6b7280;
            font-size: 0.86rem;
            line-height: 1.5;
        }
        .admin-app-summary-box,
        .admin-app-empty {
            border-radius: 12px;
            background: #f9fafb;
        }
        .admin-app-timeline {
            gap: 1rem;
            padding-left: 1.15rem;
        }
        .admin-app-timeline::before {
            left: 0.28rem;
            background: #d1d5db;
        }
        .admin-app-timeline-item {
            gap: 0.25rem;
            padding-left: 1rem;
        }
        .admin-app-timeline-item::before {
            top: 0.28rem;
            left: -0.02rem;
            width: 0.62rem;
            height: 0.62rem;
            background: #2563eb;
            box-shadow: 0 0 0 4px #dbeafe;
        }
        .admin-app-timeline-meta {
            color: #6b7280;
            font-size: 0.82rem;
            font-weight: 650;
        }
        .admin-app-photo img {
            border-radius: 12px;
        }
        .admin-app-review .btn {
            display: inline-flex;
            min-height: 2.6rem;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            border: 1px solid transparent;
            border-radius: 10px;
            background: #0b1f3a;
            color: #fff;
            font-weight: 750;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .admin-app-review .btn:hover {
            background: #123a68;
            box-shadow: 0 10px 18px rgba(11, 31, 58, 0.14);
            transform: translateY(-1px);
        }
        .admin-app-review .btn.secondary {
            border-color: #cbd5e1;
            background: #fff;
            color: #0b1f3a;
        }
        .admin-app-review .btn.secondary:hover {
            border-color: #0b1f3a;
            background: #f8fafc;
        }
        .admin-app-review .btn.success {
            background: #15803d;
        }
        .admin-app-review .btn.success:hover {
            background: #166534;
        }
        .admin-app-review .btn.danger {
            background: #b91c1c;
        }
        .admin-app-review .btn.danger:hover {
            background: #991b1b;
        }
        @media (max-width: 1080px) {
            .admin-app-layout {
                grid-template-columns: 1fr;
            }
            .admin-app-progress {
                grid-template-columns: repeat(5, minmax(130px, 1fr));
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }
        }
        @media (max-width: 760px) {
            .admin-app-review {
                padding: 1rem;
            }
            .admin-app-header,
            .admin-app-card {
                padding: 1rem;
            }
            .admin-app-header-top,
            .admin-app-location-bar {
                display: grid;
                justify-items: start;
            }
            .admin-app-detail-grid,
            .admin-app-stat-grid,
            .admin-app-summary-grid,
            .admin-app-form-grid,
            .admin-app-actions {
                grid-template-columns: 1fr;
            }
            .admin-app-mini-card--status,
            .admin-app-mini-card--full {
                grid-column: auto;
            }
            .admin-app-map {
                height: 240px;
            }
            .admin-app-map--modal {
                height: 360px;
            }
            .admin-app-header h1 {
                font-size: 1.65rem;
            }
        }
    </style>
@endpush

<x-app-layout>
    @php
        $installation = $application->installation;
        $proofPhotos = $installation ? collect($installation->proofPhotos())->filter() : collect();
        $installationLogs = $installation ? $installation->logs : collect();
        $technicalDetails = $installation ? $installation->technicalDetails() : [];
        $hasTechnicalDetails = $installation ? $installation->hasTechnicalDetails() : false;
        $serviceabilityStatus = old('serviceability_status', $application->serviceability_status ?? 'pending');
        $slotAvailabilityStatus = old('slot_availability_status', $application->slot_availability_status ?? 'pending');
        $assessmentNotes = old('assessment_notes', $application->assessment_notes ?? '');
        $rejectionReason = old('rejection_reason', $application->rejection_reason ?? '');
        $selectedInstallationTime = old('installation_time', optional($installation)->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('H:i') : '');
        $selectedTechnicianId = old('technician_id', optional($installation)->technician_id);
        $selectedInstallationDate = old('installation_date', optional($installation)->installation_date ? optional($installation->installation_date)->format('Y-m-d') : '');
        $availableTimeSlots = \App\Models\Installation::AVAILABLE_TIME_SLOTS;
        $statusKey = $application->status;
        $fullName = trim($application->first_name . ' ' . $application->last_name);
        $fullAddress = $application->fullServiceAddress() ?: 'Not provided';
        $locationLat = is_numeric($application->lat) ? (float) $application->lat : null;
        $locationLng = is_numeric($application->lng) ? (float) $application->lng : null;
        $hasLocation = $locationLat !== null && $locationLng !== null;
        $osmUrl = $hasLocation
            ? 'https://www.openstreetmap.org/?mlat=' . $locationLat . '&mlon=' . $locationLng . '#map=18/' . $locationLat . '/' . $locationLng
            : null;
        $showRejectionReason = old('status', $application->status) === 'rejected' || filled($rejectionReason);
        $statusLabel = ucfirst(str_replace('_', ' ', $statusKey));
        $statusClass = match ($statusKey) {
            'submitted' => 'admin-app-status--submitted',
            'under_review' => 'admin-app-status--under_review',
            'approved' => 'admin-app-status--approved',
            'scheduled' => 'admin-app-status--scheduled',
            'cancellation_requested' => 'admin-app-status--cancellation_requested',
            'installed' => 'admin-app-status--installed',
            'completed' => 'admin-app-status--completed',
            'activated' => 'admin-app-status--activated',
            'rejected' => 'admin-app-status--rejected',
            'cancelled' => 'admin-app-status--cancelled',
            default => 'admin-app-status--pending',
        };
        $isReviewState = in_array($statusKey, ['submitted', 'under_review'], true);
        $isApprovedState = $statusKey === 'approved';
        $isScheduledState = $statusKey === 'scheduled';
        $isCancellationRequestedState = $statusKey === 'cancellation_requested';
        $isCompletedState = in_array($statusKey, ['installed', 'completed', 'activated'], true);
        $isRejectedState = $statusKey === 'rejected';
        $isCancelledState = $statusKey === 'cancelled';
        $isInstallationScheduled = $installation?->status === \App\Models\Installation::STATUS_SCHEDULED;
        $isRescheduleRequiredState = $installation?->status === \App\Models\Installation::STATUS_RESCHEDULE_REQUIRED;
        $hasPendingCustomerRescheduleRequest = $installation?->hasPendingCustomerRescheduleRequest() ?? false;
        $showReviewSummary = $application->assessed_at || filled($application->assessment_notes) || filled($application->serviceability_status) || filled($application->slot_availability_status) || filled($application->rejection_reason);
        $showAdminActionCard = $showReviewSummary || $isReviewState || $isApprovedState || $isRejectedState || $isInstallationScheduled || $isRescheduleRequiredState || $isCancellationRequestedState || $hasPendingCustomerRescheduleRequest;
        $submittedSummary = match ($statusKey) {
            'submitted' => 'This application is ready for manual review and decision.',
            'under_review' => 'Assessment is in progress. Review details can still be updated.',
            'approved' => 'The review is complete. The next step is assigning a technician and installation slot.',
            'scheduled' => $hasPendingCustomerRescheduleRequest
                ? 'The customer requested a new installation schedule. Review the preferred date and time before approving or rejecting.'
                : 'The application is approved and already scheduled for installation.',
            'cancellation_requested' => 'The customer requested cancellation. Admin review is required before changing the schedule.',
            'installed', 'completed' => 'Installation is complete and waiting for manual service activation.',
            'activated' => 'The customer service has been activated.',
            'rejected' => 'The review is complete and the application will not proceed to scheduling.',
            'cancelled' => 'This application was cancelled and the record is preserved for history.',
            default => 'Review the application details and current processing state.',
        };
        $stepIndexMap = [
            'submitted' => 1,
            'under_review' => 1,
            'approved' => 2,
            'scheduled' => 3,
            'cancellation_requested' => 3,
            'installed' => 4,
            'completed' => 4,
            'activated' => 5,
        ];
        $currentStepIndex = $stepIndexMap[$statusKey] ?? 0;
        $progressSteps = [
            'submitted' => ['label' => 'Submitted', 'description' => 'Application received'],
            'approved' => ['label' => 'Approved', 'description' => 'Review complete'],
            'scheduled' => ['label' => 'Scheduled', 'description' => 'Visit assigned'],
            'installed' => ['label' => 'Installed', 'description' => 'Job completed'],
            'activated' => ['label' => 'Activated', 'description' => 'Service active'],
        ];
        $activeScheduleMatrix = ($activeSchedules ?? collect())
            ->groupBy('technician_id')
            ->map(fn ($technicianSchedules) => $technicianSchedules
                ->groupBy(fn ($schedule) => optional($schedule->installation_date)->format('Y-m-d'))
                ->map(fn ($daySchedules) => $daySchedules
                    ->map(fn ($schedule) => [
                        'time' => \Carbon\Carbon::parse($schedule->installation_time)->format('H:i'),
                        'status' => $schedule->status,
                    ])
                    ->values()
                )
            );
        $reviewSummaryRows = [
            'Serviceability' => ucfirst(str_replace('_', ' ', $application->serviceability_status ?? 'pending')),
            'Slot availability' => ucfirst(str_replace('_', ' ', $application->slot_availability_status ?? 'pending')),
            'Assessed at' => $application->assessed_at?->displayDateTime('F d, Y h:i A') ?? 'Not recorded',
            'Assessed by' => $application->assessor?->name ?? 'Not recorded',
        ];
        $installationStatusLabel = $installation ? $installation->displayStatusLabel() : 'Not scheduled';
        $technicianName = $installation?->technician?->name ?? 'Not assigned';
        $technicianEmail = $installation?->technician?->email ?? 'Not recorded';
        $scheduleDateLabel = $installation?->installation_date?->format('F d, Y') ?? 'Not scheduled';
        $scheduleTimeLabel = $installation?->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('g:i A') : 'Time not set';
        $scheduleLabel = $installation && ($installation->installation_date || $installation->installation_time)
            ? $scheduleDateLabel . ' - ' . $scheduleTimeLabel
            : 'Not scheduled';
        $completedAtLabel = $installation?->completed_at?->displayDateTime('F d, Y h:i A') ?? 'Not completed yet';
        $activationDateLabel = $installation?->activation_date?->format('F d, Y') ?? 'Not activated yet';
        $selectedRescheduleDate = old('installation_date', $installation?->reschedule_preferred_date?->format('Y-m-d') ?: $selectedInstallationDate);
        $selectedRescheduleTime = old('installation_time', $installation?->reschedule_preferred_time ? \Carbon\Carbon::parse($installation->reschedule_preferred_time)->format('H:i') : $selectedInstallationTime);
        $reschedulePreferredLabel = $installation?->reschedule_preferred_date
            ? $installation->reschedule_preferred_date->format('F d, Y') . ' - ' . ($installation->reschedule_preferred_time ? \Carbon\Carbon::parse($installation->reschedule_preferred_time)->format('g:i A') : 'Time not set')
            : 'No preferred schedule recorded';
    @endphp

    <div class="admin-app-review">
        <section class="admin-app-header">
            <div class="admin-app-header-top">
                <div>
                    <div class="admin-app-kicker">Application Review</div>
                    <h1>Application Details</h1>
                    <p class="admin-app-subtitle">{{ $submittedSummary }}</p>
                </div>
                <div class="admin-app-header-meta">
                    <span class="admin-app-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    <div class="admin-app-ref">{{ $application->reference_no }}</div>
                </div>
            </div>
        </section>

        <div class="admin-app-layout">
            <div class="admin-app-column">
                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Progress Tracker</h2>
                    </div>

                    <div class="admin-app-progress" aria-label="Application progress">
                        @foreach($progressSteps as $stepKey => $step)
                            @php
                                $stepNumber = $loop->iteration;
                                $isComplete = ! $isRejectedState && $stepNumber < $currentStepIndex;
                                $isActive = ! $isRejectedState && $stepNumber === $currentStepIndex;
                                $stepStateLabel = $isComplete ? 'Completed' : ($isActive ? 'Current' : 'Pending');
                            @endphp
                            <div class="admin-app-progress-step {{ $isComplete ? 'is-complete' : '' }} {{ $isActive ? 'is-active' : '' }}">
                                <div class="admin-app-progress-marker">
                                    <span class="admin-app-progress-dot" aria-hidden="true">{{ $stepNumber }}</span>
                                    <span class="admin-app-progress-line" aria-hidden="true"></span>
                                </div>
                                <div class="admin-app-progress-copy">
                                    <span class="admin-app-progress-label">{{ $stepStateLabel }}</span>
                                    <strong>{{ $step['label'] }}</strong>
                                    <p>{{ $step['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Customer Information</h2>
                    </div>

                    <div class="admin-app-detail-grid">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Full name</span>
                            <div class="admin-app-value">{{ $fullName ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Selected plan</span>
                            <div class="admin-app-value">{{ $application->plan_code ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Email</span>
                            <div class="admin-app-value">{{ $application->email ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Submitted date</span>
                            <div class="admin-app-value">{{ $application->created_at->displayDateTime('F d, Y h:i A') }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Mobile number</span>
                            <div class="admin-app-value">{{ $application->mobile_number ?: 'Not provided' }}</div>
                        </div>
                    </div>
                </section>

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Valid ID</h2>
                    </div>

                    <x-photo-gallery
                        :photos="[
                            'Valid ID with 3 signatures' => $application->id_image_path,
                            'Valid ID held by customer' => $application->id_selfie_image_path,
                        ]"
                        title="Valid ID"
                        empty="No valid ID uploaded."
                        button-text="View ID"
                    />
                </section>

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Service Address</h2>
                    </div>

                    <div class="admin-app-detail-grid">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Region</span>
                            <div class="admin-app-value">{{ $application->region ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Province</span>
                            <div class="admin-app-value">{{ $application->province ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Municipality / City</span>
                            <div class="admin-app-value">{{ $application->municipality ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Barangay</span>
                            <div class="admin-app-value">{{ $application->barangay ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Full address</span>
                            <div class="admin-app-value">{{ $fullAddress }}</div>
                        </div>
                    </div>
                </section>

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Location Map</h2>
                    </div>

                    @if($hasLocation)
                        <div class="admin-app-location-shell">
                            <div id="adminApplicationInlineMap" class="js-readonly-location-map admin-app-map" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $fullAddress }}" data-empty-label="No pinned location available."></div>

                            <div class="admin-app-location-bar">
                                <div class="admin-app-location-meta">
                                    <span>Latitude: {{ number_format($locationLat, 6) }}</span>
                                    <span>Longitude: {{ number_format($locationLng, 6) }}</span>
                                </div>
                                <button type="button" class="btn secondary admin-app-map-trigger" data-open-map-modal>View in Map</button>
                            </div>
                        </div>
                    @else
                        <div class="admin-app-empty">
                            <span class="admin-app-inline-label">Location unavailable</span>
                            <p class="admin-app-helper">No pinned location available.</p>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="admin-app-column">
                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Installation Summary</h2>
                    </div>

                    <div class="admin-app-summary-grid">
                        <div class="admin-app-mini-card admin-app-mini-card--status">
                            <span class="admin-app-label">Status</span>
                            <strong>{{ $installationStatusLabel }}</strong>
                            <span class="admin-app-summary-note">Application {{ $statusLabel }} - {{ $application->reference_no }}</span>
                        </div>

                        <div class="admin-app-mini-card">
                            <span class="admin-app-label">Technician</span>
                            <strong>{{ $technicianName }}</strong>
                            <span class="admin-app-summary-note">{{ $technicianEmail }}</span>
                        </div>

                        <div class="admin-app-mini-card">
                            <span class="admin-app-label">Schedule</span>
                            <strong>{{ $scheduleLabel }}</strong>
                            <span class="admin-app-summary-note">Installation visit</span>
                        </div>

                        @if($hasPendingCustomerRescheduleRequest)
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Customer requested reschedule</span>
                                <strong>{{ $reschedulePreferredLabel }}</strong>
                                <span class="admin-app-summary-note">{{ $installation->reschedule_request_reason ?: 'No reason recorded.' }}</span>
                            </div>
                        @elseif($installation?->reschedule_reviewed_at && filled($installation->reschedule_rejection_reason))
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Latest reschedule rejection</span>
                                <strong>{{ $installation->reschedule_reviewed_at->displayDateTime('F d, Y h:i A') }}</strong>
                                <span class="admin-app-summary-note">{{ $installation->reschedule_rejection_reason }}</span>
                            </div>
                        @endif

                        <div class="admin-app-mini-card admin-app-mini-card--full">
                            <span class="admin-app-label">Completed at</span>
                            <strong>{{ $completedAtLabel }}</strong>
                        </div>

                        <div class="admin-app-mini-card admin-app-mini-card--full">
                            <span class="admin-app-label">Activation date</span>
                            <strong>{{ $activationDateLabel }}</strong>
                        </div>

                        @if($installation)
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Technician remarks</span>
                                <div class="admin-app-value">{{ $installation->technician_remarks ?: 'No technician remarks recorded.' }}</div>
                            </div>

                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Technical Installation Details</span>
                                @if($hasTechnicalDetails)
                                    <div class="admin-app-detail-grid">
                                        @foreach($technicalDetails as $label => $value)
                                            <div class="admin-app-detail">
                                                <span class="admin-app-label">{{ $label }}</span>
                                                <div class="admin-app-value">{{ $value ?: '-' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="admin-app-value">Technical details not yet recorded.</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>

                @if($showAdminActionCard)
                    <section class="admin-app-card">
                        <div class="admin-app-card-head">
                            <h2>
                                @if($isReviewState)
                                    Review Decision
                                @elseif($isApprovedState)
                                    Technician Scheduling
                                @elseif($isCancellationRequestedState)
                                    Cancellation Review
                                @elseif($hasPendingCustomerRescheduleRequest)
                                    Customer Reschedule Review
                                @elseif($isRescheduleRequiredState)
                                    Reschedule Installation
                                @elseif($isInstallationScheduled)
                                    Installation Controls
                                @else
                                    Review Outcome
                                @endif
                            </h2>
                        </div>

                        @if($showReviewSummary)
                            <div class="admin-app-summary-box">
                                <div class="admin-app-detail-grid">
                                    @foreach($reviewSummaryRows as $label => $value)
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">{{ $label }}</span>
                                            <div class="admin-app-value">{{ $value ?: 'Not recorded' }}</div>
                                        </div>
                                    @endforeach
                                    @if(filled($application->assessment_notes))
                                        <div class="admin-app-detail admin-app-detail--full">
                                            <span class="admin-app-label">Assessment notes</span>
                                            <div class="admin-app-value">{{ $application->assessment_notes }}</div>
                                        </div>
                                    @endif
                                </div>

                                @if($isRejectedState && filled($application->rejection_reason))
                                    <div class="admin-app-reject-box">
                                        <strong>Rejection Reason</strong>
                                        <p class="admin-app-helper">{{ $application->rejection_reason }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($isReviewState)
                            <form method="POST" action="{{ route('admin.applications.status', $application) }}" class="admin-app-form" id="applicationDecisionForm">
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field">
                                        <label for="serviceability_status">Serviceability</label>
                                        <select id="serviceability_status" name="serviceability_status" required>
                                            <option value="pending" @selected($serviceabilityStatus === 'pending')>Pending Assessment</option>
                                            <option value="serviceable" @selected($serviceabilityStatus === 'serviceable')>Serviceable</option>
                                            <option value="not_serviceable" @selected($serviceabilityStatus === 'not_serviceable')>Not Serviceable</option>
                                        </select>
                                        @error('serviceability_status')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="slot_availability_status">Slot availability</label>
                                        <select id="slot_availability_status" name="slot_availability_status" required>
                                            <option value="pending" @selected($slotAvailabilityStatus === 'pending')>Pending Check</option>
                                            <option value="available" @selected($slotAvailabilityStatus === 'available')>Available</option>
                                            <option value="full" @selected($slotAvailabilityStatus === 'full')>Full</option>
                                            <option value="not_applicable" @selected($slotAvailabilityStatus === 'not_applicable')>Not Applicable</option>
                                        </select>
                                        @error('slot_availability_status')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="assessment_notes">Assessment notes</label>
                                        <textarea id="assessment_notes" name="assessment_notes" rows="4" placeholder="Document serviceability checks, site constraints, or slot observations.">{{ $assessmentNotes }}</textarea>
                                        @error('assessment_notes')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full {{ $showRejectionReason ? '' : 'is-hidden' }}" id="rejectionReasonField">
                                        <label for="rejection_reason">Rejection reason</label>
                                        <textarea id="rejection_reason" name="rejection_reason" rows="3" placeholder="State clearly why the application is being rejected.">{{ $rejectionReason }}</textarea>
                                        <p class="admin-app-helper">Required when rejecting the application.</p>
                                        @error('rejection_reason')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @error('status')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div class="admin-app-actions admin-app-actions--decision">
                                    <button type="submit" name="status" value="approved" class="btn success" data-status-action="approved">Approve</button>
                                    <button type="submit" name="status" value="under_review" class="btn secondary" data-status-action="under_review">Save as Under Review</button>
                                    <button type="submit" name="status" value="rejected" class="btn danger" data-status-action="rejected">Reject</button>
                                </div>
                            </form>
                        @elseif($isApprovedState)
                            <form method="POST" action="{{ route('admin.applications.schedule', $application) }}" class="admin-app-form">
                                @csrf

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="technician_id">Technician</label>
                                        <select id="technician_id" name="technician_id" required>
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="admin-app-helper">Only technician accounts are available for assignment.</p>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_date">Installation date</label>
                                        <input id="installation_date" type="date" name="installation_date" value="{{ $selectedInstallationDate }}" required>
                                        @error('installation_date')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_time">Installation time</label>
                                        <select id="installation_time" name="installation_time" required>
                                            <option value="">Select time slot</option>
                                            @foreach($availableTimeSlots as $timeSlot)
                                                <option value="{{ $timeSlot }}" @selected($selectedInstallationTime === $timeSlot)>{{ \Carbon\Carbon::createFromFormat('H:i', $timeSlot)->format('g:i A') }}</option>
                                            @endforeach
                                        </select>
                                        @error('installation_time')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <p class="admin-app-helper" id="installationTimeHint">Choose a technician and date to load available one-hour slots.</p>

                                @error('schedule')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div>
                                    <button class="btn">Save Schedule</button>
                                </div>
                            </form>
                        @elseif($hasPendingCustomerRescheduleRequest && $installation)
                            <form method="POST" action="{{ route('admin.applications.reschedule', $application) }}" class="admin-app-form">
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-summary-box">
                                    <div class="admin-app-detail-grid">
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">Customer preferred schedule</span>
                                            <div class="admin-app-value">{{ $reschedulePreferredLabel }}</div>
                                        </div>
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">Requested at</span>
                                            <div class="admin-app-value">{{ $installation->reschedule_requested_at?->displayDateTime('F d, Y h:i A') ?? 'Not recorded' }}</div>
                                        </div>
                                        <div class="admin-app-detail admin-app-detail--full">
                                            <span class="admin-app-label">Customer reason</span>
                                            <div class="admin-app-value">{{ $installation->reschedule_request_reason ?: 'No reason recorded.' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="technician_id">Technician</label>
                                        <select id="technician_id" name="technician_id">
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="admin-app-helper">Approving the request updates the installation to the selected schedule.</p>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_date">Installation date</label>
                                        <input id="installation_date" type="date" name="installation_date" value="{{ $selectedRescheduleDate }}">
                                        @error('installation_date')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_time">Installation time</label>
                                        <select id="installation_time" name="installation_time">
                                            <option value="">Select time slot</option>
                                            @foreach($availableTimeSlots as $timeSlot)
                                                <option value="{{ $timeSlot }}" @selected($selectedRescheduleTime === $timeSlot)>{{ \Carbon\Carbon::createFromFormat('H:i', $timeSlot)->format('g:i A') }}</option>
                                            @endforeach
                                        </select>
                                        @error('installation_time')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="reschedule_rejection_reason">Rejection reason</label>
                                        <textarea id="reschedule_rejection_reason" name="reschedule_rejection_reason" rows="3" placeholder="Required when rejecting the reschedule request.">{{ old('reschedule_rejection_reason') }}</textarea>
                                        @error('reschedule_rejection_reason')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <p class="admin-app-helper" id="installationTimeHint">Choose a technician and date to load available one-hour slots.</p>

                                @error('reschedule')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div class="admin-app-actions">
                                    <button type="submit" name="decision" value="approve" class="btn success">Approve Reschedule</button>
                                    <button type="submit" name="decision" value="reject" class="btn danger">Reject Request</button>
                                </div>
                            </form>
                        @elseif($isCancellationRequestedState)
                            <form method="POST" action="{{ route('admin.applications.cancellation', $application) }}" class="admin-app-form">
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-field">
                                    <label for="cancellation_notes">Admin notes</label>
                                    <textarea id="cancellation_notes" name="cancellation_notes" rows="3" placeholder="Optional notes for the cancellation decision.">{{ old('cancellation_notes') }}</textarea>
                                    @error('cancellation_notes')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                @error('cancellation')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div class="admin-app-actions">
                                    <button type="submit" name="decision" value="approve" class="btn danger">Approve Cancellation</button>
                                    <button type="submit" name="decision" value="reject" class="btn secondary">Reject Request</button>
                                </div>
                            </form>
                        @elseif($isRescheduleRequiredState && $installation)
                            <form method="POST" action="{{ route('admin.installations.reschedule', $installation) }}" class="admin-app-form">
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="technician_id">Technician</label>
                                        <select id="technician_id" name="technician_id" required>
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_date">Installation date</label>
                                        <input id="installation_date" type="date" name="installation_date" value="{{ $selectedInstallationDate }}" required>
                                        @error('installation_date')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_time">Installation time</label>
                                        <select id="installation_time" name="installation_time" required>
                                            <option value="">Select time slot</option>
                                            @foreach($availableTimeSlots as $timeSlot)
                                                <option value="{{ $timeSlot }}" @selected($selectedInstallationTime === $timeSlot)>{{ \Carbon\Carbon::createFromFormat('H:i', $timeSlot)->format('g:i A') }}</option>
                                            @endforeach
                                        </select>
                                        @error('installation_time')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <p class="admin-app-helper" id="installationTimeHint">Choose a technician and date to load available one-hour slots.</p>

                                @error('reschedule')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div>
                                    <button class="btn">Reschedule Installation</button>
                                </div>
                            </form>
                        @elseif($isInstallationScheduled && $installation)
                            <form method="POST" action="{{ route('admin.installations.reassign', $installation) }}" class="admin-app-form">
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="technician_id">Reassign Technician</label>
                                        <select id="technician_id" name="technician_id" required>
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="admin-app-helper">The installation remains scheduled; only the assigned technician changes.</p>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="reassignment_reason">Reassignment reason <span class="field-badge optional">Optional</span></label>
                                        <textarea id="reassignment_reason" name="reassignment_reason" rows="3" placeholder="Example: Technician unavailable for the assigned slot.">{{ old('reassignment_reason', $installation->reassignment_reason) }}</textarea>
                                        @error('reassignment_reason')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @error('reassignment')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div>
                                    <button class="btn">Reassign Technician</button>
                                </div>
                            </form>
                        @elseif($isRejectedState)
                            <div class="admin-app-empty">
                                <span class="admin-app-inline-label">Review closed</span>
                                <p class="admin-app-helper">This application has been rejected and is no longer available for scheduling.</p>
                            </div>
                        @elseif($isCancelledState)
                            <div class="admin-app-empty">
                                <span class="admin-app-inline-label">Application cancelled</span>
                                <p class="admin-app-helper">This application was cancelled. The saved record remains available for review.</p>
                            </div>
                        @endif
                    </section>
                @endif

                @if($isCompletedState && $installation)
                    <section class="admin-app-card">
                        <div class="admin-app-card-head">
                            <h2>Installation Photo</h2>
                        </div>

                        <x-photo-gallery
                            :photos="$proofPhotos"
                            title="Installation Photos"
                            empty="No installation photo uploaded."
                            button-text="View photo/photos"
                            class="admin-app-photo"
                        />
                    </section>
                @endif

                <section class="admin-app-card">
                    <div class="admin-app-card-head">
                        <h2>Installation History</h2>
                        <button type="button" class="btn secondary" data-open-installation-history-modal>View History</button>
                    </div>

                    <p class="admin-app-helper">
                        {{ $installation && $installationLogs->isNotEmpty() ? $installationLogs->count() . ' history ' . \Illuminate\Support\Str::plural('entry', $installationLogs->count()) . ' recorded.' : 'No installation history recorded yet.' }}
                    </p>
                </section>
            </aside>
        </div>

        <div class="admin-app-footer">
            <a href="{{ route('admin.applications.index') }}" class="btn secondary">Back to Applications</a>
        </div>
    </div>

    <div class="admin-ticket-modal installation-history-modal" id="installationHistoryModal" aria-hidden="true">
        <div class="admin-ticket-modal__backdrop" data-close-installation-history-modal></div>
        <div class="admin-ticket-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="installationHistoryModalTitle">
            <button type="button" class="admin-ticket-modal__close" data-close-installation-history-modal aria-label="Close installation history">&times;</button>
            <div class="admin-ticket-modal__shell">
                <div class="admin-ticket-modal__intro">
                    <h2 id="installationHistoryModalTitle">Installation History</h2>
                    <p>Review scheduling, reassignment, reschedule, and completion notes for this installation.</p>
                </div>

                <div class="installation-history-modal__body">
                    @if($installation && $installationLogs->isNotEmpty())
                        <div class="admin-app-timeline">
                            @foreach($installationLogs as $log)
                                <div class="admin-app-timeline-item">
                                    <div class="admin-app-timeline-meta">
                                        {{ $log->created_at?->displayDateTime('F d, Y h:i A') ?? '-' }} - {{ $log->user?->name ?? 'System' }}
                                    </div>
                                    <div class="admin-app-value">{{ $log->notes }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="admin-app-empty">
                            <p class="admin-app-helper">No installation history recorded yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($hasLocation)
        <div class="admin-app-map-modal" id="applicationMapModal" aria-hidden="true">
            <div class="admin-app-map-modal__backdrop" data-close-map-modal></div>
            <div class="admin-app-map-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="applicationMapModalTitle">
                <div class="admin-app-map-modal__shell">
                    <div class="admin-app-map-modal__head">
                        <div>
                            <h3 id="applicationMapModalTitle">Pinned Service Location</h3>
                            <p class="admin-app-helper">Review the saved pin and confirm the exact install site when needed.</p>
                        </div>
                        <button type="button" class="admin-app-map-modal__close" data-close-map-modal aria-label="Close pinned location map">&times;</button>
                    </div>

                    <div id="adminApplicationMap" class="js-readonly-location-map admin-app-map admin-app-map--modal" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $fullAddress }}" data-empty-label="No pinned location available."></div>

                    <div class="admin-app-map-modal__meta">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Latitude</span>
                            <div class="admin-app-value">{{ number_format($locationLat, 6) }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Longitude</span>
                            <div class="admin-app-value">{{ number_format($locationLng, 6) }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Address</span>
                            <div class="admin-app-value">{{ $fullAddress }}</div>
                        </div>
                    </div>

                    <div>
                        @if($osmUrl)
                            <a href="{{ $osmUrl }}" target="_blank" rel="noopener noreferrer" class="btn secondary admin-app-map-link">Open in OpenStreetMap</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const initReadonlyMaps = window.initReadonlyLocationMaps || function () {
                if (typeof window.L === 'undefined') {
                    return;
                }

                document.querySelectorAll('.js-readonly-location-map').forEach(function (element) {
                    const lat = parseFloat(element.dataset.lat);
                    const lng = parseFloat(element.dataset.lng);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) {
                        if (element._readonlyLeafletMap) {
                            element._readonlyLeafletMap.remove();
                            element._readonlyLeafletMap = null;
                        }
                        element.classList.add('is-empty');
                        element.textContent = element.dataset.emptyLabel || 'No pinned location available.';
                        return;
                    }

                    if (element._readonlyLeafletMap) {
                        if (element.offsetWidth && element.offsetHeight) {
                            setTimeout(function () {
                                element._readonlyLeafletMap.invalidateSize();
                            }, 120);
                        }
                        return;
                    }

                    if (!element.offsetWidth || !element.offsetHeight) {
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

                    const popupNode = document.createElement('div');
                    popupNode.textContent = element.dataset.popup || 'Pinned service location';

                    L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(popupNode)
                        .openPopup();
                    element._readonlyLeafletMap = map;

                    setTimeout(function () {
                        map.invalidateSize();
                    }, 120);
                });
            };

            const decisionForm = document.getElementById('applicationDecisionForm');
            const rejectionReasonField = document.getElementById('rejection_reason');
            const rejectionReasonWrap = document.getElementById('rejectionReasonField');
            const statusButtons = document.querySelectorAll('[data-status-action]');
            const mapModal = document.getElementById('applicationMapModal');
            const mapOpeners = document.querySelectorAll('[data-open-map-modal]');
            const mapClosers = document.querySelectorAll('[data-close-map-modal]');
            const historyModal = document.getElementById('installationHistoryModal');
            const historyOpeners = document.querySelectorAll('[data-open-installation-history-modal]');
            const historyClosers = document.querySelectorAll('[data-close-installation-history-modal]');
            const technicianSelect = document.getElementById('technician_id');
            const installationDateInput = document.getElementById('installation_date');
            const installationTimeSelect = document.getElementById('installation_time');
            const installationTimeHint = document.getElementById('installationTimeHint');
            const schedulingConfig = {
                duration: {{ \App\Models\Installation::SLOT_DURATION_MINUTES }},
                slots: @json($availableTimeSlots),
                bookings: @json($activeScheduleMatrix),
            };

            const timeToMinutes = function (timeValue) {
                const [hours, minutes] = String(timeValue || '0:0').split(':').map(Number);
                return (hours * 60) + minutes;
            };

            const formatSlotLabel = function (timeValue) {
                const [hours, minutes] = String(timeValue).split(':').map(Number);
                const period = hours >= 12 ? 'PM' : 'AM';
                const displayHours = hours % 12 || 12;
                return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`;
            };

            const slotConflicts = function (slotTime, existingTime) {
                const newStart = timeToMinutes(slotTime);
                const newEnd = newStart + schedulingConfig.duration;
                const existingStart = timeToMinutes(existingTime);
                const existingEnd = existingStart + schedulingConfig.duration;

                return newStart < existingEnd && newEnd > existingStart;
            };

            const refreshInstallationSlots = function () {
                if (!installationTimeSelect) {
                    return;
                }

                const technicianId = technicianSelect ? technicianSelect.value : '';
                const installationDate = installationDateInput ? installationDateInput.value : '';
                const selectedValue = installationTimeSelect.value;
                const dayBookings = technicianId && installationDate
                    ? (schedulingConfig.bookings?.[technicianId]?.[installationDate] || [])
                    : [];

                let availableCount = 0;

                Array.from(installationTimeSelect.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        option.textContent = 'Select time slot';
                        return;
                    }

                    const slotValue = option.value;
                    const blocked = dayBookings.some(function (booking) {
                        return slotConflicts(slotValue, booking.time);
                    });

                    option.textContent = blocked
                        ? `${formatSlotLabel(slotValue)} (Unavailable)`
                        : formatSlotLabel(slotValue);
                    option.disabled = blocked;
                    option.hidden = blocked;

                    if (!blocked) {
                        availableCount += 1;
                    }
                });

                if (selectedValue) {
                    const currentOption = Array.from(installationTimeSelect.options).find(function (option) {
                        return option.value === selectedValue;
                    });

                    if (!currentOption || currentOption.disabled) {
                        installationTimeSelect.value = '';
                    }
                }

                if (!installationTimeHint) {
                    return;
                }

                if (!technicianId || !installationDate) {
                    installationTimeHint.textContent = 'Choose a technician and date to load available one-hour slots.';
                    return;
                }

                if (availableCount === 0) {
                    installationTimeHint.textContent = 'No available one-hour slots for this technician on the selected date.';
                    return;
                }

                installationTimeHint.textContent = `${availableCount} available one-hour slot${availableCount === 1 ? '' : 's'} for this technician on the selected date.`;
            };

            initReadonlyMaps();

            if (mapModal && mapOpeners.length) {
                const openMapModal = function () {
                    mapModal.classList.add('is-open');
                    mapModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('admin-app-map-open');
                    initReadonlyMaps();

                    const mapElement = document.getElementById('adminApplicationMap');
                    if (mapElement && mapElement._readonlyLeafletMap) {
                        setTimeout(function () {
                            mapElement._readonlyLeafletMap.invalidateSize();
                        }, 120);
                    }
                };

                const closeMapModal = function () {
                    mapModal.classList.remove('is-open');
                    mapModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('admin-app-map-open');
                };

                mapOpeners.forEach(function (button) {
                    button.addEventListener('click', openMapModal);
                });

                mapClosers.forEach(function (button) {
                    button.addEventListener('click', closeMapModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && mapModal.classList.contains('is-open')) {
                        closeMapModal();
                    }
                });
            }

            if (historyModal && historyOpeners.length) {
                const openHistoryModal = function () {
                    historyModal.classList.add('is-open');
                    historyModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('ticket-overlay-open');
                };

                const closeHistoryModal = function () {
                    historyModal.classList.remove('is-open');
                    historyModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('ticket-overlay-open');
                };

                historyOpeners.forEach(function (button) {
                    button.addEventListener('click', openHistoryModal);
                });

                historyClosers.forEach(function (button) {
                    button.addEventListener('click', closeHistoryModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && historyModal.classList.contains('is-open')) {
                        closeHistoryModal();
                    }
                });
            }

            if (technicianSelect && installationDateInput && installationTimeSelect) {
                technicianSelect.addEventListener('change', refreshInstallationSlots);
                installationDateInput.addEventListener('change', refreshInstallationSlots);
                refreshInstallationSlots();
            }

            if (decisionForm && rejectionReasonField && rejectionReasonWrap && statusButtons.length) {
                const toggleRejectionRequirement = function (status) {
                    const isRejected = status === 'rejected';
                    rejectionReasonField.required = isRejected;
                    rejectionReasonField.setAttribute('aria-required', isRejected ? 'true' : 'false');
                    rejectionReasonWrap.classList.toggle('is-hidden', !isRejected && rejectionReasonField.value.trim() === '');
                };

                statusButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        toggleRejectionRequirement(button.value);
                    });
                });

                rejectionReasonField.addEventListener('input', function () {
                    if (rejectionReasonField.required) {
                        rejectionReasonWrap.classList.remove('is-hidden');
                    }
                });

                toggleRejectionRequirement('{{ old('status', $application->status) }}');
            }
        });
    </script>
    @endpush
</x-app-layout>
